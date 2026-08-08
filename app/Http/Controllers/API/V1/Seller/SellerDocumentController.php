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
use Illuminate\Database\Eloquent\Builder;
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
     * Return the active seller verification document requirements.
     *
     * This endpoint is used by the seller verification workspace to populate
     * the document type selector before any document has been uploaded.
     */
    public function requirements(
        Request $request
    ): JsonResponse {
        /*
         * Keep the endpoint authenticated. Any authenticated user reaching the
         * seller verification workspace may read the requirement catalog.
         */
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
     *
     * The response also exposes the active verification requirement catalog
     * so the seller UI can render required, conditional and recommended
     * documents from the database instead of hard-coding document types.
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
         * All allowed document types are driven by the seeded
         * seller_document_requirements table.
         */
        $requirement = SellerDocumentRequirement::query()
            ->active()
            ->where('key', $documentType)
            ->first();

        if ($requirement === null) {
            throw ValidationException::withMessages([
                'document_type' => [
                    'The selected verification document type is not active or does not exist.',
                ],
            ]);
        }

        /*
         * For single-file requirements, prevent another current document of
         * the same type. Rejected or infected files do not block a replacement.
         */
        if (! $requirement->allow_multiple) {
            $existingCurrentDocument = $sellerApplication
                ->documents()
                ->where('document_type', $documentType)
                ->whereNotIn(
                    'status',
                    [
                        SellerDocumentStatus::REJECTED->value,
                        SellerDocumentStatus::INFECTED->value,
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

        /** @var UploadedFile $file */
        $file = $request->file('document');

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
         * Prevent the exact same file from being uploaded repeatedly
         * to the same seller business.
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
         * Files are stored privately in a quarantine directory.
         * The public storage disk must never be used here.
         */
        $directory = sprintf(
            'seller-documents/quarantine/%s/%s',
            $sellerProfile->public_id,
            $sellerApplication->public_id
        );

        $storageDisk = 'private';
        $storagePath = null;

        try {
            $storagePath = Storage::disk($storageDisk)
                ->putFileAs(
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
                    $document = SellerDocument::query()->create([
                        'seller_profile_id' =>
                            $sellerProfile->id,

                        'seller_application_id' =>
                            $sellerApplication->id,

                        'uploaded_by' =>
                            $request->user()->id,

                        'document_type' =>
                            $documentType,

                        /*
                         * The document must remain quarantined
                         * until the antivirus scanner marks it clean.
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

                        /*
                         * Only persist an expiry date for requirements that
                         * are configured to support one.
                         */
                        'expires_at' =>
                            $requirement->supports_expiry_date
                                ? (
                                    $validated['expires_at']
                                    ?? null
                                )
                                : null,

                        'scanned_at' => null,
                        'scan_result' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                    ]);

                    DocumentAccessLog::query()->create([
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
                                $requirement->requirement_level,
                        ],
                    ]);

                    return $document;
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Seller document uploaded successfully and placed in quarantine for security scanning.',
                'data' => $sellerDocument->fresh([
                    'uploadedBy:id,name,email',
                ]),
                'requirement' => $requirement,
            ], 201);
        } catch (Throwable $exception) {
            if (
                is_string($storagePath)
                && $storagePath !== ''
            ) {
                Storage::disk($storageDisk)
                    ->delete($storagePath);
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
     * Securely download a seller's own private document.
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

        DocumentAccessLog::query()->create([
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
     * Delete a document before application submission.
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

        $storageDisk = $sellerDocument->storage_disk;
        $storagePath = $sellerDocument->storage_path;

        DB::transaction(
            function () use (
                $sellerDocument
            ): void {
                $document = SellerDocument::query()
                    ->whereKey($sellerDocument->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $document->delete();
            }
        );

        /*
         * Delete the private file only after the database
         * transaction succeeds.
         */
        Storage::disk($storageDisk)
            ->delete($storagePath);

        return response()->json([
            'success' => true,
            'message' =>
                'Seller document deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * Submit the seller application for admin review.
     *
     * Required document types are read dynamically from
     * seller_document_requirements.
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
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $profile = SellerProfile::query()
                    ->whereKey($sellerProfile->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireEditableApplication(
                    $application
                );

                /*
                 * A document still in quarantine or waiting for
                 * a scan must not enter admin verification.
                 */
                $hasUnscannedDocuments = $application
                    ->documents()
                    ->whereIn(
                        'status',
                        [
                            SellerDocumentStatus::QUARANTINED->value,
                            SellerDocumentStatus::PENDING_SCAN->value,
                        ]
                    )
                    ->exists();

                if ($hasUnscannedDocuments) {
                    throw ValidationException::withMessages([
                        'documents' => [
                            'Some documents are still waiting for security scanning.',
                        ],
                    ]);
                }

                $hasInfectedDocuments = $application
                    ->documents()
                    ->where(
                        'status',
                        SellerDocumentStatus::INFECTED->value
                    )
                    ->exists();

                if ($hasInfectedDocuments) {
                    throw ValidationException::withMessages([
                        'documents' => [
                            'An infected document was detected. Remove it and upload a safe document.',
                        ],
                    ]);
                }

                /*
                 * Fail closed when the verification catalog has not been
                 * configured. This prevents an application from being
                 * submitted with no required documents due to missing seed data.
                 */
                $requiredRequirements =
                    SellerDocumentRequirement::query()
                        ->active()
                        ->required()
                        ->orderBy('sort_order')
                        ->get([
                            'key',
                            'name',
                        ]);

                if ($requiredRequirements->isEmpty()) {
                    throw ValidationException::withMessages([
                        'documents' => [
                            'Seller verification requirements are not configured. Please contact RushPi administration.',
                        ],
                    ]);
                }

                $requiredDocumentTypes =
                    $requiredRequirements
                        ->pluck('key')
                        ->values()
                        ->all();

                $availableDocumentTypes = $application
                    ->documents()
                    ->whereIn(
                        'status',
                        [
                            SellerDocumentStatus::CLEAN->value,
                            SellerDocumentStatus::APPROVED->value,
                        ]
                    )
                    ->where(
                        function (
                            Builder $query
                        ): void {
                            $query
                                ->whereNull('expires_at')
                                ->orWhereDate(
                                    'expires_at',
                                    '>=',
                                    now()->toDateString()
                                );
                        }
                    )
                    ->pluck('document_type')
                    ->map(
                        fn (mixed $type): string =>
                            $this->documentTypeValue($type)
                    )
                    ->unique()
                    ->values()
                    ->all();

                $missingDocumentTypes = array_values(
                    array_diff(
                        $requiredDocumentTypes,
                        $availableDocumentTypes
                    )
                );

                if ($missingDocumentTypes !== []) {
                    $missingDocumentNames =
                        $requiredRequirements
                            ->whereIn(
                                'key',
                                $missingDocumentTypes
                            )
                            ->pluck('name')
                            ->values()
                            ->all();

                    throw ValidationException::withMessages([
                        'documents' => [
                            'The following required documents are missing, expired, or not clean: '
                            . implode(
                                ', ',
                                $missingDocumentNames
                            ),
                        ],
                    ]);
                }

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
            'data' => $application->fresh([
                'sellerProfile',
                'documents',
            ]),
        ]);
    }

    /**
     * Ensure the authenticated user belongs to the seller.
     */
    private function ensureSellerMember(
        Request $request,
        SellerProfile $sellerProfile
    ): void {
        abort_unless(
            $request->user() !== null
            && $request->user()
                ->belongsToSeller($sellerProfile),
            403,
            'You are not allowed to access this seller business.'
        );
    }

    /**
     * Ensure the authenticated user owns the seller.
     */
    private function ensureSellerOwner(
        Request $request,
        SellerProfile $sellerProfile
    ): void {
        abort_unless(
            $request->user() !== null
            && $request->user()
                ->ownsSeller($sellerProfile),
            403,
            'Only the seller owner can perform this action.'
        );
    }

    /**
     * Ensure an application belongs to the selected profile.
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
     * Ensure a document belongs to the selected application.
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
     * Allow changes only while the application is editable.
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
     * Return a plain string regardless of whether the SellerDocument model
     * currently casts document_type to a backed enum or leaves it as a string.
     */
    private function documentTypeValue(
        mixed $documentType
    ): string {
        if ($documentType instanceof BackedEnum) {
            return (string) $documentType->value;
        }

        return (string) $documentType;
    }
}