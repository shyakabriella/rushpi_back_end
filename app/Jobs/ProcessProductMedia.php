<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProductMedia;
use App\Services\Catalog\ProductMediaProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessProductMedia implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of processing attempts.
     */
    public int $tries = 4;

    /**
     * Maximum number of unhandled exceptions.
     */
    public int $maxExceptions = 4;

    /**
     * Maximum execution time for one attempt.
     */
    public int $timeout = 180;

    /**
     * Fail the job when the timeout is reached.
     */
    public bool $failOnTimeout = true;

    /**
     * A processing state is considered stale after this period.
     */
    private const STALE_PROCESSING_MINUTES = 15;

    /**
     * Create a queued product-media processing job.
     */
    public function __construct(
        public readonly int|string $mediaId
    ) {
        /*
         * The job should only become available after the database transaction
         * that created the media record has committed.
         */
        $this->afterCommit();
    }

    /**
     * Retry delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [
            10,
            30,
            90,
            180,
        ];
    }

    /**
     * Execute the queued job.
     */
    public function handle(
        ProductMediaProcessor $processor
    ): void {
        $media =
            $this->claimMediaForProcessing();

        /*
         * The record may have been deleted, completed by another job or may
         * currently be processed by another worker.
         */
        if (!$media instanceof ProductMedia) {
            return;
        }

        $sourceDisk =
            $media->originalDisk();

        $sourcePath =
            $media->originalPath();

        if ($sourcePath === null) {
            $this->recordAttemptFailure(
                error:
                    new \RuntimeException(
                        'The original product image path is missing.'
                    ),

                sourceDisk:
                    $sourceDisk,

                sourcePath:
                    null
            );

            return;
        }

        $result = null;

        try {
            $result =
                $processor->process(
                    $media
                );

            try {
                $saved =
                    $this->persistProcessingResult(
                        sourceDisk:
                            $sourceDisk,

                        sourcePath:
                            $sourcePath,

                        result:
                            $result
                    );
            } catch (Throwable $exception) {
                /*
                 * Processing succeeded but the database update failed. Remove
                 * the generated files so they do not become orphaned.
                 */
                $this->deleteRenditions(
                    $result['renditions']
                    ?? []
                );

                throw $exception;
            }

            /*
             * The media record may have been deleted or its original upload
             * may have changed while this job was running.
             */
            if (!$saved) {
                $this->deleteRenditions(
                    $result['renditions']
                    ?? []
                );

                return;
            }

            Log::info(
                'Product media processing completed.',
                [
                    'product_media_id' =>
                        $this->mediaId,

                    'original_width' =>
                        $result[
                            'original_width'
                        ],

                    'original_height' =>
                        $result[
                            'original_height'
                        ],

                    'renditions' =>
                        array_keys(
                            $result['renditions']
                            ?? []
                        ),
                ]
            );
        } catch (Throwable $exception) {
            $this->recordAttemptFailure(
                error:
                    $exception,

                sourceDisk:
                    $sourceDisk,

                sourcePath:
                    $sourcePath
            );

            Log::warning(
                'Product media processing attempt failed.',
                [
                    'product_media_id' =>
                        $this->mediaId,

                    'attempt' =>
                        $this->attempts(),

                    'message' =>
                        $exception->getMessage(),
                ]
            );

            /*
             * Rethrowing allows the queue worker to retry according to the
             * configured tries and backoff values.
             */
            throw $exception;
        }
    }

    /**
     * Claim the media record for processing.
     *
     * A database row lock prevents two workers from changing the processing
     * state simultaneously.
     */
    private function claimMediaForProcessing():
        ?ProductMedia
    {
        return DB::transaction(
            function (): ?ProductMedia {
                $media =
                    ProductMedia::query()
                        ->whereKey(
                            $this->mediaId
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$media instanceof ProductMedia) {
                    return null;
                }

                /*
                 * A duplicate queued job should do nothing when the media has
                 * already been processed successfully.
                 */
                if (
                    $media->isCompleted()
                    && $media
                        ->hasOptimizedRendition()
                ) {
                    return null;
                }

                /*
                 * Skip this job when another worker recently claimed the same
                 * media record.
                 */
                if (
                    $media->isProcessing()
                    && $media
                        ->processing_started_at
                        !== null
                    && $media
                        ->processing_started_at
                        ->greaterThan(
                            now()->subMinutes(
                                self::
                                    STALE_PROCESSING_MINUTES
                            )
                        )
                ) {
                    return null;
                }

                if (
                    !$media
                        ->supportsImageProcessing()
                ) {
                    $media->markFailed(
                        'This media file is not a supported processable image.'
                    );

                    return null;
                }

                /*
                 * Pending, failed, incomplete or stale records may be claimed.
                 */
                $media->markProcessing();

                return $media->fresh();
            },
            3
        );
    }

    /**
     * Persist successful processing metadata.
     *
     * @param array{
     *     renditions: array<string, array<string, mixed>>,
     *     original_width: int,
     *     original_height: int,
     *     checksum_sha256: string,
     *     detected_mime_type: string
     * } $result
     */
    private function persistProcessingResult(
        string $sourceDisk,
        string $sourcePath,
        array $result
    ): bool {
        $previousRenditions = [];

        $saved = DB::transaction(
            function () use (
                $sourceDisk,
                $sourcePath,
                $result,
                &$previousRenditions
            ): bool {
                $media =
                    ProductMedia::query()
                        ->whereKey(
                            $this->mediaId
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$media instanceof ProductMedia) {
                    return false;
                }

                /*
                 * Do not allow an old queue job to overwrite metadata for a
                 * newly uploaded replacement file.
                 */
                if (
                    $media->originalDisk()
                        !== $sourceDisk
                    || $media->originalPath()
                        !== $sourcePath
                ) {
                    if ($media->isProcessing()) {
                        $media->markPending();
                    }

                    return false;
                }

                $previousRenditions =
                    is_array(
                        $media->renditions
                    )
                        ? $media->renditions
                        : [];

                $media->mime_type =
                    $result[
                        'detected_mime_type'
                    ];

                $media->markCompleted(
                    renditions:
                        $result['renditions'],

                    originalWidth:
                        $result[
                            'original_width'
                        ],

                    originalHeight:
                        $result[
                            'original_height'
                        ],

                    checksumSha256:
                        $result[
                            'checksum_sha256'
                        ]
                );

                return true;
            },
            3
        );

        if ($saved) {
            $this->deleteReplacedRenditions(
                previous:
                    $previousRenditions,

                current:
                    $result['renditions']
            );
        }

        return $saved;
    }

    /**
     * Record one failed processing attempt.
     */
    private function recordAttemptFailure(
        Throwable $error,
        string $sourceDisk,
        ?string $sourcePath
    ): void {
        try {
            DB::transaction(
                function () use (
                    $error,
                    $sourceDisk,
                    $sourcePath
                ): void {
                    $media =
                        ProductMedia::query()
                            ->whereKey(
                                $this->mediaId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        !$media instanceof
                        ProductMedia
                    ) {
                        return;
                    }

                    /*
                     * Do not mark a replacement upload as failed because of an
                     * exception caused by the old file.
                     */
                    if (
                        $media->originalDisk()
                            !== $sourceDisk
                        || $media->originalPath()
                            !== $sourcePath
                    ) {
                        return;
                    }

                    $media->markFailed(
                        $error
                    );
                },
                3
            );
        } catch (Throwable $recordingError) {
            Log::error(
                'Unable to record product media processing failure.',
                [
                    'product_media_id' =>
                        $this->mediaId,

                    'processing_error' =>
                        $error->getMessage(),

                    'recording_error' =>
                        $recordingError
                            ->getMessage(),
                ]
            );
        }
    }

    /**
     * Handle the job after all retries have been exhausted.
     */
    public function failed(
        ?Throwable $exception
    ): void {
        try {
            DB::transaction(
                function () use (
                    $exception
                ): void {
                    $media =
                        ProductMedia::query()
                            ->whereKey(
                                $this->mediaId
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        !$media instanceof
                        ProductMedia
                    ) {
                        return;
                    }

                    /*
                     * Most failures were already recorded inside handle().
                     * This protects against worker termination, timeout or
                     * another queue-level failure.
                     */
                    if ($media->isProcessing()) {
                        $media->markFailed(
                            $exception
                            ?? 'Product media processing failed after all retries.'
                        );
                    }
                },
                3
            );
        } catch (Throwable $recordingError) {
            Log::error(
                'Unable to record final product media job failure.',
                [
                    'product_media_id' =>
                        $this->mediaId,

                    'message' =>
                        $recordingError
                            ->getMessage(),
                ]
            );
        }

        Log::error(
            'Product media processing failed permanently.',
            [
                'product_media_id' =>
                    $this->mediaId,

                'message' =>
                    $exception?->getMessage()
                    ?? 'Unknown queue failure.',
            ]
        );
    }

    /**
     * Delete renditions replaced by a successful processing attempt.
     *
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $current
     */
    private function deleteReplacedRenditions(
        array $previous,
        array $current
    ): void {
        $currentFiles =
            $this->renditionFileKeys(
                $current
            );

        $filesToDelete = [];

        foreach ($previous as $rendition) {
            if (!is_array($rendition)) {
                continue;
            }

            $disk = trim(
                (string) (
                    $rendition['disk']
                    ?? 'public'
                )
            );

            $path = trim(
                (string) (
                    $rendition['path']
                    ?? ''
                )
            );

            if ($path === '') {
                continue;
            }

            $key =
                $disk.'|'.$path;

            if (
                !in_array(
                    $key,
                    $currentFiles,
                    true
                )
            ) {
                $filesToDelete[] = [
                    'disk' => $disk,
                    'path' => $path,
                ];
            }
        }

        $this->deleteStoredFiles(
            $filesToDelete
        );
    }

    /**
     * Delete generated rendition files.
     *
     * @param array<string, mixed> $renditions
     */
    private function deleteRenditions(
        array $renditions
    ): void {
        $files = [];

        foreach ($renditions as $rendition) {
            if (!is_array($rendition)) {
                continue;
            }

            $path = trim(
                (string) (
                    $rendition['path']
                    ?? ''
                )
            );

            if ($path === '') {
                continue;
            }

            $files[] = [
                'disk' =>
                    trim(
                        (string) (
                            $rendition['disk']
                            ?? 'public'
                        )
                    ),

                'path' =>
                    $path,
            ];
        }

        $this->deleteStoredFiles(
            $files
        );
    }

    /**
     * Build disk-and-path identifiers for rendition metadata.
     *
     * @param array<string, mixed> $renditions
     *
     * @return array<int, string>
     */
    private function renditionFileKeys(
        array $renditions
    ): array {
        $keys = [];

        foreach ($renditions as $rendition) {
            if (!is_array($rendition)) {
                continue;
            }

            $path = trim(
                (string) (
                    $rendition['path']
                    ?? ''
                )
            );

            if ($path === '') {
                continue;
            }

            $disk = trim(
                (string) (
                    $rendition['disk']
                    ?? 'public'
                )
            );

            $keys[] =
                $disk.'|'.$path;
        }

        return array_values(
            array_unique($keys)
        );
    }

    /**
     * Delete files grouped by storage disk.
     *
     * @param array<int, array{
     *     disk: string,
     *     path: string
     * }> $files
     */
    private function deleteStoredFiles(
        array $files
    ): void {
        $groupedFiles = [];

        foreach ($files as $file) {
            $disk = trim(
                $file['disk']
                ?? ''
            );

            $path = trim(
                $file['path']
                ?? ''
            );

            if (
                $disk === ''
                || $path === ''
            ) {
                continue;
            }

            $groupedFiles[$disk][] =
                $path;
        }

        foreach (
            $groupedFiles
            as $disk => $paths
        ) {
            try {
                Storage::disk(
                    $disk
                )->delete(
                    array_values(
                        array_unique(
                            $paths
                        )
                    )
                );
            } catch (Throwable $exception) {
                Log::warning(
                    'Unable to delete old product media renditions.',
                    [
                        'product_media_id' =>
                            $this->mediaId,

                        'disk' =>
                            $disk,

                        'paths' =>
                            $paths,

                        'message' =>
                            $exception
                                ->getMessage(),
                    ]
                );
            }
        }
    }

    /**
     * Return job tags for queue dashboards such as Laravel Horizon.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'product-media',
            'product-media:'
                .$this->mediaId,
        ];
    }
}
