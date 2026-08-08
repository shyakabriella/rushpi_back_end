<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\SellerApplicationStatus;
use App\Enums\SellerDocumentStatus;
use App\Enums\SellerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Seller\UploadSellerDocumentRequest;
use App\Models\DocumentAccessLog;
use App\Models\SellerApplication;
use App\Models\SellerDocument;
use App\Models\SellerDocumentRequirement;
use App\Models\SellerProfile;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SellerDocumentController extends Controller
{
    /**
     * Return active seller verification document requirements.
     */
    public function requirements(
        Request $request
    ): JsonResponse {
        abort_unless(
            $request->user() !== null,
            401,
            'Unauthenticated.'
        );

        $requirements = SellerDocumentRequirement::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'key',
                'name',
                'requirement_level',
                'condition',
                'description',
                'allow_multiple',
                'supports_expiry_date',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Seller document requirements retrieved successfully.',
            'data' => $requirements,
        ]);
    }

    /**
     * List documents belonging to one seller application.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureSellerMember(
            $request,
            $sellerProfile
        );

        $this->ensureApplicationBelongsToProfile(
            $sellerProfile,
            $sellerApplication
        );

        $documents = $sellerApplication
            ->documents()
            ->with([
                'uploadedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->latest()
            ->get();

        $requirements = SellerDocumentRequirement::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'key',
                'name',
                'requirement_level',
                'condition',
                'description',
                'allow_multiple',
                'supports_expiry_date',
                'is_active',
                'sort_order',
            ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Seller documents retrieved successfully.',
            'data' => $documents,
            'requirements' => $requirements,
        ]);
    }

    /**
     * Upload a private seller verification document.
     */
    public function store(
        UploadSellerDocumentRequest $request,
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureSellerOwner(
            $request,
            $sellerProfile
        );

        $this->ensureApplicationBelongsToProfile(
            $sellerProfile,
            $sellerApplication
        );

        $this->requireEditableApplication(
            $sellerApplication
        );

        if (
            ! in_array(
                $sellerProfile->status,
                [
                    SellerProfileStatus::DRAFT,
                    SellerProfileStatus::PENDING_VERIFICATION,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'seller_profile' => [
                    'Documents cannot be uploaded for this seller profile.',
                ],
            ]);
        }

        $validated = $request->validated();

        $documentType = (string) $validated['document_type'];

        /*
         * Document types come dynamically from
         * seller_document_requirements.
         */
        $requirement = SellerDocumentRequirement::query()
            ->active()
            ->where(
                'key',
                $documentType
            )
            ->first();

        if ($requirement === null) {
            throw ValidationException::withMessages([
                'document_type' => [
                    'The selected verification document type is not active or does not exist.',
                ],
            ]);
        }

        /*
         * Single-file requirements may have only one current file.
         *
         * Rejected, infected, scan failed, expired or deleted
         * documents do not block replacement.
         */
        if (! $requirement->allow_multiple) {
            $existingCurrentDocument = $sellerApplication
                ->documents()
                ->where(
                    'document_type',
                    $documentType
                )
                ->whereNotIn(
                    'status',
                    [
                        SellerDocumentStatus::REJECTED->value,
                        SellerDocumentStatus::INFECTED->value,
                        SellerDocumentStatus::SCAN_FAILED->value,
                        SellerDocumentStatus::EXPIRED->value,
                        SellerDocumentStatus::DELETED->value,
                    ]
                )
                ->exists();

            if ($existingCurrentDocument) {
                throw ValidationException::withMessages([
                    'document_type' => [
                        sprintf(
                            '%s has already been uploaded for this application. Delete or replace the existing document when allowed.',
                            $requirement->name
                        ),
                    ],
                ]);
            }
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('document');

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'document' => [
                    'Select a verification document to upload.',
                ],
            ]);
        }

        $temporaryPath = $file->getRealPath();

        if ($temporaryPath === false) {
            throw ValidationException::withMessages([
                'document' => [
                    'The uploaded document could not be processed.',
                ],
            ]);
        }

        $checksum = hash_file(
            'sha256',
            $temporaryPath
        );

        if ($checksum === false) {
            throw ValidationException::withMessages([
                'document' => [
                    'The document checksum could not be generated.',
                ],
            ]);
        }

        /*
         * Prevent the exact same file from being uploaded
         * repeatedly to the same seller profile.
         */
        $duplicateExists = SellerDocument::query()
            ->where(
                'seller_profile_id',
                $sellerProfile->id
            )
            ->where(
                'checksum_sha256',
                $checksum
            )
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'document' => [
                    'This exact document has already been uploaded.',
                ],
            ]);
        }

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $extension = preg_replace(
            '/[^a-z0-9]/',
            '',
            $extension
        ) ?: 'bin';

        $storedFilename = sprintf(
            '%s.%s',
            Str::uuid()->toString(),
            $extension
        );

        /*
         * Documents are private and start in quarantine.
         */
        $directory = sprintf(
            'seller-documents/quarantine/%s/%s',
            $sellerProfile->public_id,
            $sellerApplication->public_id
        );

        $storageDisk = 'private';

        $storagePath = null;

        try {
            $storagePath = Storage::disk(
                $storageDisk
            )->putFileAs(
                $directory,
                $file,
                $storedFilename
            );

            if (
                ! is_string($storagePath)
                || $storagePath === ''
            ) {
                throw new \RuntimeException(
                    'The private document could not be stored.'
                );
            }

            $sellerDocument = DB::transaction(
                function () use (
                    $request,
                    $validated,
                    $file,
                    $checksum,
                    $storageDisk,
                    $storagePath,
                    $sellerProfile,
                    $sellerApplication,
                    $requirement,
                    $documentType
                ): SellerDocument {
                    $document = SellerDocument::query()
                        ->create([
                            'seller_profile_id' =>
                                $sellerProfile->id,

                            'seller_application_id' =>
                                $sellerApplication->id,

                            'uploaded_by' =>
                                $request->user()->id,

                            'document_type' =>
                                $documentType,

                            /*
                             * Security scan must process the file
                             * after upload.
                             */
                            'status' =>
                                SellerDocumentStatus::QUARANTINED,

                            'original_name' =>
                                basename(
                                    $file->getClientOriginalName()
                                ),

                            'storage_disk' =>
                                $storageDisk,

                            'storage_path' =>
                                $storagePath,

                            'mime_type' =>
                                $file->getMimeType()
                                ?? $file->getClientMimeType(),

                            'size_bytes' =>
                                $file->getSize(),

                            'checksum_sha256' =>
                                $checksum,

                            'issued_at' =>
                                $validated['issued_at']
                                ?? null,

                            'expires_at' =>
                                $requirement->supports_expiry_date
                                    ? (
                                        $validated['expires_at']
                                        ?? null
                                    )
                                    : null,

                            'scanned_at' =>
                                null,

                            'scan_result' =>
                                null,

                            'reviewed_by' =>
                                null,

                            'reviewed_at' =>
                                null,

                            'rejection_reason' =>
                                null,
                        ]);

                    DocumentAccessLog::query()
                        ->create([
                            'seller_document_id' =>
                                $document->id,

                            'user_id' =>
                                $request->user()->id,

                            'action' =>
                                'document_uploaded',

                            'ip_address' =>
                                $request->ip(),

                            'user_agent' =>
                                $request->userAgent(),

                            'metadata' => [
                                'seller_profile_id' =>
                                    $sellerProfile->id,

                                'seller_application_id' =>
                                    $sellerApplication->id,

                                'document_type' =>
                                    $this->documentTypeValue(
                                        $document->document_type
                                    ),

                                'requirement_name' =>
                                    $requirement->name,

                                'requirement_level' =>
                                    $requirement
                                        ->requirement_level,
                            ],
                        ]);

                    return $document;
                }
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Seller document uploaded successfully and placed in quarantine for security scanning.',

                'data' =>
                    $sellerDocument->fresh([
                        'uploadedBy:id,name,email',
                    ]),

                'requirement' =>
                    $requirement,
            ], 201);
        } catch (Throwable $exception) {
            /*
             * Remove physical file if database operation failed.
             */
            if (
                is_string($storagePath)
                && $storagePath !== ''
            ) {
                Storage::disk(
                    $storageDisk
                )->delete(
                    $storagePath
                );
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'The seller document could not be uploaded.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Securely download a seller's private document.
     */
    public function download(
        Request $request,
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): StreamedResponse|JsonResponse {
        $this->ensureSellerMember(
            $request,
            $sellerProfile
        );

        $this->ensureApplicationBelongsToProfile(
            $sellerProfile,
            $sellerApplication
        );

        $this->ensureDocumentBelongsToApplication(
            $sellerProfile,
            $sellerApplication,
            $sellerDocument
        );

        $disk = Storage::disk(
            $sellerDocument->storage_disk
        );

        if (
            ! $disk->exists(
                $sellerDocument->storage_path
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The requested seller document was not found.',
                'data' => null,
            ], 404);
        }

        DocumentAccessLog::query()
            ->create([
                'seller_document_id' =>
                    $sellerDocument->id,

                'user_id' =>
                    $request->user()->id,

                'action' =>
                    'document_downloaded_by_seller',

                'ip_address' =>
                    $request->ip(),

                'user_agent' =>
                    $request->userAgent(),

                'metadata' => [
                    'seller_profile_id' =>
                        $sellerProfile->id,

                    'seller_application_id' =>
                        $sellerApplication->id,

                    'document_type' =>
                        $this->documentTypeValue(
                            $sellerDocument->document_type
                        ),
                ],
            ]);

        return $disk->download(
            $sellerDocument->storage_path,
            $sellerDocument->original_name,
            [
                'Content-Type' =>
                    $sellerDocument->mime_type,

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate',

                'Pragma' =>
                    'no-cache',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Security-Policy' =>
                    "default-src 'none'; sandbox",
            ]
        );
    }

    /**
     * Delete a document while the application is editable.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): JsonResponse {
        $this->ensureSellerOwner(
            $request,
            $sellerProfile
        );

        $this->ensureApplicationBelongsToProfile(
            $sellerProfile,
            $sellerApplication
        );

        $this->ensureDocumentBelongsToApplication(
            $sellerProfile,
            $sellerApplication,
            $sellerDocument
        );

        $this->requireEditableApplication(
            $sellerApplication
        );

        if (
            $sellerDocument->status
            === SellerDocumentStatus::APPROVED
        ) {
            throw ValidationException::withMessages([
                'document' => [
                    'An approved document cannot be deleted.',
                ],
            ]);
        }

        $storageDisk =
            $sellerDocument->storage_disk;

        $storagePath =
            $sellerDocument->storage_path;

        DB::transaction(
            function () use (
                $sellerDocument
            ): void {
                $document = SellerDocument::query()
                    ->whereKey(
                        $sellerDocument->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $document->delete();
            }
        );

        /*
         * Delete the physical private file only after
         * the database transaction succeeds.
         */
        Storage::disk(
            $storageDisk
        )->delete(
            $storagePath
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller document deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * Submit seller application for administrator review.
     *
     * Seller only needs a minimum of 2 usable documents.
     *
     * QUARANTINED and PENDING_SCAN documents are allowed
     * to count toward submission.
     *
     * INFECTED, SCAN_FAILED, REJECTED, EXPIRED and DELETED
     * documents do not count.
     */
    public function submit(
        Request $request,
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureSellerOwner(
            $request,
            $sellerProfile
        );

        $this->ensureApplicationBelongsToProfile(
            $sellerProfile,
            $sellerApplication
        );

        $application = DB::transaction(
            function () use (
                $sellerProfile,
                $sellerApplication
            ): SellerApplication {
                $application =
                    SellerApplication::query()
                        ->whereKey(
                            $sellerApplication->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $profile =
                    SellerProfile::query()
                        ->whereKey(
                            $sellerProfile->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->requireEditableApplication(
                    $application
                );

                /*
                 * Never allow submission if a known infected
                 * document is attached.
                 */
                $hasInfectedDocuments =
                    $application
                        ->documents()
                        ->where(
                            'status',
                            SellerDocumentStatus::INFECTED->value
                        )
                        ->exists();

                if ($hasInfectedDocuments) {
                    throw ValidationException::withMessages([
                        'documents' => [
                            'An infected document was detected. Remove it before submitting the application.',
                        ],
                    ]);
                }

                /*
                 * Seller must upload at least two usable
                 * verification documents.
                 */
                $minimumDocumentsRequired = 2;

                $eligibleDocumentsCount =
                    $application
                        ->documents()

                        /*
                         * These statuses cannot count toward
                         * minimum document requirements.
                         */
                        ->whereNotIn(
                            'status',
                            [
                                SellerDocumentStatus::INFECTED->value,
                                SellerDocumentStatus::SCAN_FAILED->value,
                                SellerDocumentStatus::REJECTED->value,
                                SellerDocumentStatus::EXPIRED->value,
                                SellerDocumentStatus::DELETED->value,
                            ]
                        )

                        /*
                         * Do not count a document if its actual
                         * expiry date has already passed even if
                         * its status has not yet been changed to
                         * EXPIRED.
                         */
                        ->where(
                            function ($query): void {
                                $query
                                    ->whereNull(
                                        'expires_at'
                                    )
                                    ->orWhereDate(
                                        'expires_at',
                                        '>=',
                                        now()->toDateString()
                                    );
                            }
                        )
                        ->count();

                if (
                    $eligibleDocumentsCount
                    < $minimumDocumentsRequired
                ) {
                    throw ValidationException::withMessages([
                        'documents' => [
                            sprintf(
                                'Upload at least %d valid verification documents before submitting the application. You currently have %d.',
                                $minimumDocumentsRequired,
                                $eligibleDocumentsCount
                            ),
                        ],
                    ]);
                }

                /*
                 * Submit application.
                 */
                $application->update([
                    'status' =>
                        SellerApplicationStatus::SUBMITTED,

                    'submitted_at' =>
                        now(),

                    'information_request' =>
                        null,

                    'rejection_reason' =>
                        null,

                    'current_reviewer_id' =>
                        null,

                    'review_started_at' =>
                        null,

                    'decided_at' =>
                        null,

                    'decided_by' =>
                        null,
                ]);

                /*
                 * Seller now waits for administrator
                 * verification.
                 */
                $profile->update([
                    'status' =>
                        SellerProfileStatus::PENDING_VERIFICATION,
                ]);

                return $application;
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Seller application submitted successfully and is waiting for administrator review.',

            'data' =>
                $application->fresh([
                    'sellerProfile',
                    'documents',
                ]),
        ]);
    }

    /**
     * Ensure authenticated user belongs to seller profile.
     */
    private function ensureSellerMember(
        Request $request,
        SellerProfile $sellerProfile
    ): void {
        abort_unless(
            $request->user() !== null
            && $request->user()
                ->belongsToSeller(
                    $sellerProfile
                ),
            403,
            'You are not allowed to access this seller business.'
        );
    }

    /**
     * Ensure authenticated user owns seller profile.
     */
    private function ensureSellerOwner(
        Request $request,
        SellerProfile $sellerProfile
    ): void {
        abort_unless(
            $request->user() !== null
            && $request->user()
                ->ownsSeller(
                    $sellerProfile
                ),
            403,
            'Only the seller owner can perform this action.'
        );
    }

    /**
     * Ensure application belongs to seller profile.
     */
    private function ensureApplicationBelongsToProfile(
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication
    ): void {
        abort_unless(
            $sellerApplication->seller_profile_id
                === $sellerProfile->id,
            404,
            'The selected application does not belong to this seller profile.'
        );
    }

    /**
     * Ensure document belongs to application and profile.
     */
    private function ensureDocumentBelongsToApplication(
        SellerProfile $sellerProfile,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): void {
        abort_unless(
            $sellerDocument->seller_profile_id
                === $sellerProfile->id
            && $sellerDocument->seller_application_id
                === $sellerApplication->id,
            404,
            'The selected document does not belong to this seller application.'
        );
    }

    /**
     * Allow document/application changes only while editable.
     */
    private function requireEditableApplication(
        SellerApplication $sellerApplication
    ): void {
        if (
            ! in_array(
                $sellerApplication->status,
                [
                    SellerApplicationStatus::DRAFT,
                    SellerApplicationStatus::MORE_INFORMATION_REQUIRED,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'application' => [
                    sprintf(
                        'This application cannot be changed while its status is %s.',
                        $sellerApplication->status->value
                    ),
                ],
            ]);
        }
    }

    /**
     * Return a plain document type string.
     *
     * SellerDocument now stores document_type as a plain
     * string driven by seller_document_requirements.key.
     *
     * BackedEnum support is retained for compatibility with
     * any older loaded records/code.
     */
    private function documentTypeValue(
        mixed $documentType
    ): string {
        if (
            $documentType instanceof BackedEnum
        ) {
            return (string)
                $documentType->value;
        }

        return (string)
            $documentType;
    }
}