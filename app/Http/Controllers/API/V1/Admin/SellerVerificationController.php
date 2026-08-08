<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Enums\SellerApplicationStatus;
use App\Enums\SellerDocumentStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\VerificationDecision;
use App\Http\Controllers\Controller;
use App\Models\DocumentAccessLog;
use App\Models\SellerApplication;
use App\Models\SellerDocument;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Throwable;

class SellerVerificationController extends Controller
{
    private const MINIMUM_APPROVED_DOCUMENTS = 2;

    /**
     * List seller verification applications.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'status' => [
                'nullable',
                Rule::enum(SellerApplicationStatus::class),
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $applications = SellerApplication::query()
            ->with([
                'sellerProfile:id,public_id,legal_business_name,trading_name,registration_number,tax_identification_number,business_email,business_phone,status',
                'currentReviewer:id,name,email',
            ])
            ->withCount('documents')
            ->when(
                $validated['status'] ?? null,
                function (
                    Builder $query,
                    string $status
                ): void {
                    $query->where('status', $status);
                }
            )
            ->when(
                $validated['search'] ?? null,
                function (
                    Builder $query,
                    string $search
                ): void {
                    $query->whereHas(
                        'sellerProfile',
                        function (
                            Builder $sellerQuery
                        ) use ($search): void {
                            $sellerQuery
                                ->where(
                                    'legal_business_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'trading_name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'registration_number',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'tax_identification_number',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->latest('submitted_at')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' =>
                'Seller verification applications retrieved successfully.',
            'data' => $applications,
        ]);
    }

    /**
     * Show one complete seller application.
     */
    public function show(
        Request $request,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureAdmin($request);

        /*
         * IMPORTANT:
         * Do not type-hint these eager-load closures as Builder.
         * Laravel may pass a HasMany relation instance here.
         */
        $sellerApplication->load([
            'sellerProfile.addresses',
            'sellerProfile.members.user:id,name,email,phone,status',

            'documents' => function ($query): void {
                $query->latest();
            },

            'documents.uploadedBy:id,name,email',
            'documents.reviewedBy:id,name,email',

            'reviews' => function ($query): void {
                $query->latest();
            },

            'reviews.reviewer:id,name,email',
            'currentReviewer:id,name,email',
            'decidedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Seller verification application retrieved successfully.',
            'data' => $sellerApplication,
        ]);
    }

    /**
     * Move an application from submitted to under review.
     */
    public function startReview(
        Request $request,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureAdmin($request);

        $application = DB::transaction(
            function () use (
                $request,
                $sellerApplication
            ): SellerApplication {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::SUBMITTED,
                    ]
                );

                $application->update([
                    'status' =>
                        SellerApplicationStatus::UNDER_REVIEW,
                    'current_reviewer_id' =>
                        $request->user()->id,
                    'review_started_at' => now(),
                    'information_request' => null,
                    'rejection_reason' => null,
                ]);

                $application->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' =>
                        VerificationDecision::REVIEW_STARTED,
                    'reason' =>
                        'Seller verification review started.',
                    'internal_notes' => null,
                    'metadata' => [
                        'previous_status' =>
                            SellerApplicationStatus::SUBMITTED
                                ->value,
                        'new_status' =>
                            SellerApplicationStatus::UNDER_REVIEW
                                ->value,
                    ],
                ]);

                return $application;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller verification review started successfully.',
            'data' => $application->fresh([
                'sellerProfile',
                'documents',
                'reviews.reviewer',
                'currentReviewer',
            ]),
        ]);
    }


    /**
     * Run a malware/security scan for a quarantined seller document.
     *
     * Status flow:
     * QUARANTINED / SCAN_FAILED
     *      -> PENDING_SCAN
     *      -> CLEAN | INFECTED | SCAN_FAILED
     */
    public function scanDocument(
        Request $request,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): JsonResponse {
        $this->ensureAdmin($request);

        $this->ensureDocumentBelongsToApplication(
            $sellerApplication,
            $sellerDocument
        );

        $this->requireApplicationStatus(
            $sellerApplication,
            [
                SellerApplicationStatus::UNDER_REVIEW,
            ]
        );

        $this->ensureAssignedReviewer(
            $request,
            $sellerApplication
        );

        if (
            ! in_array(
                $sellerDocument->status,
                [
                    SellerDocumentStatus::QUARANTINED,
                    SellerDocumentStatus::SCAN_FAILED,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'document' => [
                    sprintf(
                        'This document cannot be scanned while its status is %s.',
                        $sellerDocument->status->value
                    ),
                ],
            ]);
        }

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
                    'The document file was not found in private storage.',
                'data' => null,
            ], 404);
        }

        /*
         * The private disk is currently a local filesystem disk.
         * Storage::path() gives clamscan an absolute server path.
         */
        try {
            $absolutePath = $disk->path(
                $sellerDocument->storage_path
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'This storage disk does not support local security scanning.',
                'data' => null,
            ], 500);
        }

        $sellerDocument->update([
            'status' =>
                SellerDocumentStatus::PENDING_SCAN,
            'scanned_at' =>
                null,
            'scan_result' =>
                'Security scan started.',
        ]);

        try {
            /*
             * Exit codes used by ClamAV clamscan:
             * 0 = no virus found
             * 1 = virus found
             * 2 = scan error
             */
            $process = new Process([
                'clamscan',
                '--no-summary',
                $absolutePath,
            ]);

            $process->setTimeout(120);
            $process->run();

            $exitCode =
                $process->getExitCode();

            $output = trim(
                $process->getOutput()
                .PHP_EOL
                .$process->getErrorOutput()
            );

            /*
             * Avoid storing an unexpectedly large process output.
             */
            $scanResult = mb_substr(
                $output !== ''
                    ? $output
                    : 'ClamAV returned no scan output.',
                0,
                8000
            );

            if ($exitCode === 0) {
                $status =
                    SellerDocumentStatus::CLEAN;

                $message =
                    'Document scanned successfully. No malware was detected.';
            } elseif ($exitCode === 1) {
                $status =
                    SellerDocumentStatus::INFECTED;

                $message =
                    'Security scan completed and malware was detected.';
            } else {
                $status =
                    SellerDocumentStatus::SCAN_FAILED;

                $message =
                    'The document security scan could not be completed.';
            }

            $sellerDocument->update([
                'status' =>
                    $status,
                'scanned_at' =>
                    now(),
                'scan_result' =>
                    $scanResult,
            ]);

            DocumentAccessLog::query()->create([
                'seller_document_id' =>
                    $sellerDocument->id,
                'user_id' =>
                    $request->user()->id,
                'action' =>
                    'document_security_scanned',
                'ip_address' =>
                    $request->ip(),
                'user_agent' =>
                    $request->userAgent(),
                'metadata' => [
                    'scan_engine' =>
                        'clamav',
                    'exit_code' =>
                        $exitCode,
                    'result_status' =>
                        $status->value,
                ],
            ]);

            return response()->json([
                'success' =>
                    true,
                'message' =>
                    $message,
                'data' =>
                    $sellerDocument->fresh([
                        'uploadedBy:id,name,email',
                        'reviewedBy:id,name,email',
                    ]),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            $sellerDocument->update([
                'status' =>
                    SellerDocumentStatus::SCAN_FAILED,
                'scanned_at' =>
                    now(),
                'scan_result' =>
                    mb_substr(
                        $exception->getMessage(),
                        0,
                        8000
                    ),
            ]);

            return response()->json([
                'success' => false,
                'message' =>
                    'Security scanner is unavailable or failed to execute. Verify that ClamAV is installed in the application container.',
                'data' =>
                    $sellerDocument->fresh(),
            ], 503);
        }
    }

    /**
     * Approve one clean seller document.
     */
    public function approveDocument(
        Request $request,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $document = DB::transaction(
            function () use (
                $request,
                $sellerApplication,
                $sellerDocument,
                $validated
            ): SellerDocument {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $document = SellerDocument::query()
                    ->whereKey($sellerDocument->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::UNDER_REVIEW,
                    ]
                );

                $this->ensureAssignedReviewer(
                    $request,
                    $application
                );

                $this->ensureDocumentBelongsToApplication(
                    $application,
                    $document
                );

                if (
                    $document->status
                    !== SellerDocumentStatus::CLEAN
                ) {
                    throw ValidationException::withMessages([
                        'document' => [
                            'Only a clean, successfully scanned document can be approved.',
                        ],
                    ]);
                }

                if ($document->isExpired()) {
                    throw ValidationException::withMessages([
                        'document' => [
                            'An expired document cannot be approved.',
                        ],
                    ]);
                }

                $document->update([
                    'status' =>
                        SellerDocumentStatus::APPROVED,
                    'reviewed_by' =>
                        $request->user()->id,
                    'reviewed_at' =>
                        now(),
                    'rejection_reason' =>
                        null,
                ]);

                DocumentAccessLog::query()->create([
                    'seller_document_id' =>
                        $document->id,
                    'user_id' =>
                        $request->user()->id,
                    'action' =>
                        'document_approved',
                    'ip_address' =>
                        $request->ip(),
                    'user_agent' =>
                        $request->userAgent(),
                    'metadata' => [
                        'internal_notes' =>
                            $validated['internal_notes']
                            ?? null,
                    ],
                ]);

                return $document;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller document approved successfully.',
            'data' => $document->fresh([
                'uploadedBy:id,name,email',
                'reviewedBy:id,name,email',
            ]),
        ]);
    }

    /**
     * Reject one seller document.
     */
    public function rejectDocument(
        Request $request,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $reason = $this->firstNonEmptyString(
            $validated['reason'] ?? null,
            $validated['rejection_reason'] ?? null
        );

        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => [
                    'A rejection reason is required.',
                ],
            ]);
        }

        $document = DB::transaction(
            function () use (
                $request,
                $sellerApplication,
                $sellerDocument,
                $validated,
                $reason
            ): SellerDocument {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $document = SellerDocument::query()
                    ->whereKey($sellerDocument->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::UNDER_REVIEW,
                    ]
                );

                $this->ensureAssignedReviewer(
                    $request,
                    $application
                );

                $this->ensureDocumentBelongsToApplication(
                    $application,
                    $document
                );

                if (
                    ! in_array(
                        $document->status,
                        [
                            SellerDocumentStatus::CLEAN,
                            SellerDocumentStatus::APPROVED,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'document' => [
                            'This document cannot currently be rejected.',
                        ],
                    ]);
                }

                $document->update([
                    'status' =>
                        SellerDocumentStatus::REJECTED,
                    'reviewed_by' =>
                        $request->user()->id,
                    'reviewed_at' =>
                        now(),
                    'rejection_reason' =>
                        $reason,
                ]);

                DocumentAccessLog::query()->create([
                    'seller_document_id' =>
                        $document->id,
                    'user_id' =>
                        $request->user()->id,
                    'action' =>
                        'document_rejected',
                    'ip_address' =>
                        $request->ip(),
                    'user_agent' =>
                        $request->userAgent(),
                    'metadata' => [
                        'reason' =>
                            $reason,
                        'internal_notes' =>
                            $validated['internal_notes']
                            ?? null,
                    ],
                ]);

                return $document;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller document rejected successfully.',
            'data' => $document->fresh([
                'uploadedBy:id,name,email',
                'reviewedBy:id,name,email',
            ]),
        ]);
    }

    /**
     * Ask the seller to correct or provide more information.
     */
    public function requestInformation(
        Request $request,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'message' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'information_request' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $message = $this->firstNonEmptyString(
            $validated['message'] ?? null,
            $validated['information_request'] ?? null
        );

        if ($message === null) {
            throw ValidationException::withMessages([
                'message' => [
                    'An information request message is required.',
                ],
            ]);
        }

        $application = DB::transaction(
            function () use (
                $request,
                $sellerApplication,
                $validated,
                $message
            ): SellerApplication {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::UNDER_REVIEW,
                    ]
                );

                $this->ensureAssignedReviewer(
                    $request,
                    $application
                );

                $previousStatus =
                    $application->status->value;

                $application->update([
                    'status' =>
                        SellerApplicationStatus::
                            MORE_INFORMATION_REQUIRED,
                    'information_request' =>
                        $message,
                    'rejection_reason' =>
                        null,
                    'current_reviewer_id' =>
                        null,
                ]);

                $application->sellerProfile()->update([
                    'status' =>
                        SellerProfileStatus::
                            PENDING_VERIFICATION,
                ]);

                $application->reviews()->create([
                    'reviewer_id' =>
                        $request->user()->id,
                    'decision' =>
                        VerificationDecision::
                            INFORMATION_REQUESTED,
                    'reason' =>
                        $message,
                    'internal_notes' =>
                        $validated['internal_notes']
                        ?? null,
                    'metadata' => [
                        'previous_status' =>
                            $previousStatus,
                        'new_status' =>
                            SellerApplicationStatus::
                                MORE_INFORMATION_REQUIRED
                                ->value,
                    ],
                ]);

                return $application;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Additional information requested successfully.',
            'data' => $application->fresh([
                'sellerProfile',
                'documents',
                'reviews.reviewer',
            ]),
        ]);
    }

    /**
     * Approve the seller application.
     *
     * The seller must have at least two approved, unexpired
     * verification documents.
     */
    public function approve(
        Request $request,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $application = DB::transaction(
            function () use (
                $request,
                $sellerApplication,
                $validated
            ): SellerApplication {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::UNDER_REVIEW,
                    ]
                );

                $this->ensureAssignedReviewer(
                    $request,
                    $application
                );

                $this->ensureMinimumApprovedDocuments(
                    $application
                );

                $previousStatus =
                    $application->status->value;

                $application->update([
                    'status' =>
                        SellerApplicationStatus::APPROVED,
                    'information_request' =>
                        null,
                    'rejection_reason' =>
                        null,
                    'decided_at' =>
                        now(),
                    'decided_by' =>
                        $request->user()->id,
                    'current_reviewer_id' =>
                        null,
                ]);

                $application->sellerProfile()->update([
                    'status' =>
                        SellerProfileStatus::APPROVED,
                    'approved_at' =>
                        now(),
                    'approved_by' =>
                        $request->user()->id,
                    'suspended_at' =>
                        null,
                    'suspended_by' =>
                        null,
                    'suspension_reason' =>
                        null,
                ]);

                $application->reviews()->create([
                    'reviewer_id' =>
                        $request->user()->id,
                    'decision' =>
                        VerificationDecision::APPROVED,
                    'reason' =>
                        'Seller verification application approved.',
                    'internal_notes' =>
                        $validated['internal_notes']
                        ?? null,
                    'metadata' => [
                        'previous_status' =>
                            $previousStatus,
                        'new_status' =>
                            SellerApplicationStatus::APPROVED
                                ->value,
                    ],
                ]);

                return $application;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller application approved successfully.',
            'data' => $application->fresh([
                'sellerProfile',
                'documents',
                'reviews.reviewer',
                'decidedBy',
            ]),
        ]);
    }

    /**
     * Reject the seller application.
     */
    public function reject(
        Request $request,
        SellerApplication $sellerApplication
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'rejection_reason' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $reason = $this->firstNonEmptyString(
            $validated['reason'] ?? null,
            $validated['rejection_reason'] ?? null
        );

        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => [
                    'A rejection reason is required.',
                ],
            ]);
        }

        $application = DB::transaction(
            function () use (
                $request,
                $sellerApplication,
                $validated,
                $reason
            ): SellerApplication {
                $application = SellerApplication::query()
                    ->whereKey($sellerApplication->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->requireApplicationStatus(
                    $application,
                    [
                        SellerApplicationStatus::UNDER_REVIEW,
                    ]
                );

                $this->ensureAssignedReviewer(
                    $request,
                    $application
                );

                $previousStatus =
                    $application->status->value;

                $application->update([
                    'status' =>
                        SellerApplicationStatus::REJECTED,
                    'information_request' =>
                        null,
                    'rejection_reason' =>
                        $reason,
                    'decided_at' =>
                        now(),
                    'decided_by' =>
                        $request->user()->id,
                    'current_reviewer_id' =>
                        null,
                ]);

                $application->sellerProfile()->update([
                    'status' =>
                        SellerProfileStatus::REJECTED,
                    'approved_at' =>
                        null,
                    'approved_by' =>
                        null,
                ]);

                $application->reviews()->create([
                    'reviewer_id' =>
                        $request->user()->id,
                    'decision' =>
                        VerificationDecision::REJECTED,
                    'reason' =>
                        $reason,
                    'internal_notes' =>
                        $validated['internal_notes']
                        ?? null,
                    'metadata' => [
                        'previous_status' =>
                            $previousStatus,
                        'new_status' =>
                            SellerApplicationStatus::REJECTED
                                ->value,
                    ],
                ]);

                return $application;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller application rejected successfully.',
            'data' => $application->fresh([
                'sellerProfile',
                'documents',
                'reviews.reviewer',
                'decidedBy',
            ]),
        ]);
    }

    /**
     * Suspend an approved seller business.
     */
    public function suspend(
        Request $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'suspension_reason' => [
                'nullable',
                'string',
                'min:10',
                'max:3000',
            ],
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $reason = $this->firstNonEmptyString(
            $validated['reason'] ?? null,
            $validated['suspension_reason'] ?? null
        );

        if ($reason === null) {
            throw ValidationException::withMessages([
                'reason' => [
                    'A suspension reason is required.',
                ],
            ]);
        }

        $profile = DB::transaction(
            function () use (
                $request,
                $sellerProfile,
                $validated,
                $reason
            ): SellerProfile {
                $profile = SellerProfile::query()
                    ->whereKey($sellerProfile->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $profile->status
                    !== SellerProfileStatus::APPROVED
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only an approved seller can be suspended.',
                        ],
                    ]);
                }

                $application = $profile
                    ->applications()
                    ->where(
                        'status',
                        SellerApplicationStatus::APPROVED
                            ->value
                    )
                    ->latest('version')
                    ->first();

                if ($application === null) {
                    throw ValidationException::withMessages([
                        'application' => [
                            'No approved application was found for this seller.',
                        ],
                    ]);
                }

                $profile->update([
                    'status' =>
                        SellerProfileStatus::SUSPENDED,
                    'suspended_at' =>
                        now(),
                    'suspended_by' =>
                        $request->user()->id,
                    'suspension_reason' =>
                        $reason,
                ]);

                $application->update([
                    'status' =>
                        SellerApplicationStatus::SUSPENDED,
                ]);

                $application->reviews()->create([
                    'reviewer_id' =>
                        $request->user()->id,
                    'decision' =>
                        VerificationDecision::SUSPENDED,
                    'reason' =>
                        $reason,
                    'internal_notes' =>
                        $validated['internal_notes']
                        ?? null,
                    'metadata' => [
                        'previous_seller_status' =>
                            SellerProfileStatus::APPROVED
                                ->value,
                        'new_seller_status' =>
                            SellerProfileStatus::SUSPENDED
                                ->value,
                    ],
                ]);

                return $profile;
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Seller suspended successfully.',
            'data' => $profile->fresh([
                'applications.reviews',
                'members.user',
                'addresses',
            ]),
        ]);
    }

    /**
     * Securely download a private seller document.
     */
    public function downloadDocument(
        Request $request,
        SellerApplication $sellerApplication,
        SellerDocument $sellerDocument
    ): StreamedResponse|JsonResponse {
        $this->ensureAdmin($request);

        $this->ensureDocumentBelongsToApplication(
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
                    'The requested document file was not found.',
                'data' => null,
            ], 404);
        }

        DocumentAccessLog::query()->create([
            'seller_document_id' =>
                $sellerDocument->id,
            'user_id' =>
                $request->user()->id,
            'action' =>
                'document_downloaded',
            'ip_address' =>
                $request->ip(),
            'user_agent' =>
                $request->userAgent(),
            'metadata' => [
                'seller_application_id' =>
                    $sellerApplication->id,
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
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /**
     * Ensure the authenticated user is an administrator.
     */
    private function ensureAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user() !== null
            && $request->user()->isAdmin(),
            403,
            'Only administrators can manage seller verification.'
        );
    }

    /**
     * Ensure an application is in one of the allowed states.
     *
     * @param array<int, SellerApplicationStatus> $statuses
     */
    private function requireApplicationStatus(
        SellerApplication $application,
        array $statuses
    ): void {
        if (
            ! in_array(
                $application->status,
                $statuses,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    sprintf(
                        'This action is not allowed while the application status is %s.',
                        $application->status->value
                    ),
                ],
            ]);
        }
    }

    /**
     * Ensure the administrator owns the active review.
     */
    private function ensureAssignedReviewer(
        Request $request,
        SellerApplication $application
    ): void {
        abort_if(
            $application->current_reviewer_id
                !== $request->user()->id,
            409,
            'This application is assigned to another reviewer.'
        );
    }

    /**
     * Ensure the document belongs to the selected application.
     */
    private function ensureDocumentBelongsToApplication(
        SellerApplication $application,
        SellerDocument $document
    ): void {
        abort_unless(
            $document->seller_application_id
                === $application->id,
            404,
            'The selected document does not belong to this application.'
        );
    }

    /**
     * Require at least two approved and unexpired documents.
     */
    private function ensureMinimumApprovedDocuments(
        SellerApplication $application
    ): void {
        $approvedDocumentsCount = $application
            ->documents()
            ->where(
                'status',
                SellerDocumentStatus::APPROVED->value
            )
            ->where(
                function (Builder $query): void {
                    $query
                        ->whereNull('expires_at')
                        ->orWhereDate(
                            'expires_at',
                            '>=',
                            now()->toDateString()
                        );
                }
            )
            ->count();

        if (
            $approvedDocumentsCount
            < self::MINIMUM_APPROVED_DOCUMENTS
        ) {
            throw ValidationException::withMessages([
                'documents' => [
                    sprintf(
                        'At least %d approved and unexpired verification documents are required before approving the seller. Currently approved: %d.',
                        self::MINIMUM_APPROVED_DOCUMENTS,
                        $approvedDocumentsCount
                    ),
                ],
            ]);
        }
    }

    /**
     * Return the first non-empty string.
     */
    private function firstNonEmptyString(
        ?string ...$values
    ): ?string {
        foreach ($values as $value) {
            if (
                is_string($value)
                && trim($value) !== ''
            ) {
                return trim($value);
            }
        }

        return null;
    }
}