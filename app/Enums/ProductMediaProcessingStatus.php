<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductMediaProcessingStatus: string
{
    /**
     * The media was uploaded but processing has not started.
     */
    case PENDING = 'pending';

    /**
     * The media-processing job is currently running.
     */
    case PROCESSING = 'processing';

    /**
     * All required optimized versions were generated successfully.
     */
    case COMPLETED = 'completed';

    /**
     * Media processing failed.
     */
    case FAILED = 'failed';

    /**
     * Return a human-readable status label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING =>
                'Pending',

            self::PROCESSING =>
                'Processing',

            self::COMPLETED =>
                'Completed',

            self::FAILED =>
                'Failed',
        };
    }

    /**
     * Determine whether processing has not started yet.
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Determine whether processing is currently running.
     */
    public function isProcessing(): bool
    {
        return $this === self::PROCESSING;
    }

    /**
     * Determine whether processing completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Determine whether processing failed.
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Determine whether media processing has finished.
     */
    public function isFinished(): bool
    {
        return in_array(
            $this,
            [
                self::COMPLETED,
                self::FAILED,
            ],
            true
        );
    }

    /**
     * Determine whether processing may be attempted again.
     */
    public function canRetry(): bool
    {
        return in_array(
            $this,
            [
                self::PENDING,
                self::FAILED,
            ],
            true
        );
    }

    /**
     * Return all enum values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (
                self $status
            ): string => $status->value,
            self::cases()
        );
    }

    /**
     * Return API-friendly status options.
     *
     * @return array<int, array{
     *     value: string,
     *     label: string
     * }>
     */
    public static function options(): array
    {
        return array_map(
            static fn (
                self $status
            ): array => [
                'value' =>
                    $status->value,

                'label' =>
                    $status->label(),
            ],
            self::cases()
        );
    }
}
