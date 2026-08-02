<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Enums\ProductModerationFlag;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Resources\SellerProductResource;
use App\Models\Product;
use App\Models\ProductModerationReview;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

final class ProductModerationController extends Controller
{
    /**
     * List products available to administrators for moderation.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $this->authorizeAdministrator(
            $request
        );

        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                Rule::enum(
                    ProductStatus::class
                ),
            ],

            'moderation_flag' => [
                'nullable',
                Rule::enum(
                    ProductModerationFlag::class
                ),
            ],

            'flagged' => [
                'nullable',
                'boolean',
            ],

            'prohibited' => [
                'nullable',
                'boolean',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'submitted_newest',
                    'submitted_oldest',
                    'name_asc',
                    'name_desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Product::query()
            ->with([
                'category:id,public_id,name,slug',
                'brand:id,public_id,name,slug',
                'sellerProfile:id,public_id,legal_business_name,trading_name,status',
            ]);

        $this->applySearch(
            $query,
            $validated['q']
                ?? null
        );

        if (
            isset(
                $validated['status']
            )
        ) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (
            isset(
                $validated[
                    'moderation_flag'
                ]
            )
        ) {
            $query->whereHas(
                'moderationReviews',
                static function (
                    Builder $reviewQuery
                ) use ($validated): void {
                    $reviewQuery->whereJsonContains(
                        'moderation_flags',
                        $validated[
                            'moderation_flag'
                        ]
                    );
                }
            );
        }

        if (
            array_key_exists(
                'flagged',
                $validated
            )
        ) {
            $this->applyFlaggedFilter(
                $query,
                $request->boolean(
                    'flagged'
                )
            );
        }

        if (
            array_key_exists(
                'prohibited',
                $validated
            )
        ) {
            $this->applyProhibitedFilter(
                $query,
                $request->boolean(
                    'prohibited'
                )
            );
        }

        $this->applySorting(
            $query,
            $validated['sort']
                ?? 'submitted_newest'
        );

        $products = $query
            ->paginate(
                $validated['per_page']
                    ?? 20
            )
            ->withQueryString();

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Products awaiting moderation retrieved successfully.',

            'data' =>
                SellerProductResource::collection(
                    $products->getCollection()
                )->resolve($request),

            'meta' => [
                'current_page' =>
                    $products->currentPage(),

                'from' =>
                    $products->firstItem(),

                'last_page' =>
                    $products->lastPage(),

                'path' =>
                    $products->path(),

                'per_page' =>
                    $products->perPage(),

                'to' =>
                    $products->lastItem(),

                'total' =>
                    $products->total(),
            ],

            'links' => [
                'first' =>
                    $products->url(1),

                'last' =>
                    $products->url(
                        $products->lastPage()
                    ),

                'previous' =>
                    $products
                        ->previousPageUrl(),

                'next' =>
                    $products
                        ->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show one product with its complete moderation history.
     */
    public function show(
        Request $request,
        Product $product
    ): JsonResponse {
        $this->authorizeAdministrator(
            $request
        );

        $this->loadModerationProduct(
            $product
        );

        $moderationHistory =
            ProductModerationReview::query()
                ->where(
                    'product_id',
                    $product->getKey()
                )
                ->with([
                    'moderator:id,public_id,name,email',
                ])
                ->latestFirst()
                ->get();

        $product->setRelation(
            'moderationReviews',
            $moderationHistory
        );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Product moderation details retrieved successfully.',

            'data' => (
                new SellerProductResource(
                    $product
                )
            )->resolve($request),

            'moderation_history' =>
                $moderationHistory
                    ->map(
                        fn (
                            ProductModerationReview $review
                        ): array =>
                            $this
                                ->moderationReviewData(
                                    $review
                                )
                    )
                    ->values()
                    ->all(),
        ]);
    }

    /**
     * Approve, reject, suspend or return a product to draft.
     */
    public function moderate(
        ModerateProductRequest $request,
        Product $product
    ): JsonResponse {
        $moderator =
            $this->authorizeAdministrator(
                $request
            );

        $moderationData =
            $request->moderationData();

        $result = DB::transaction(
            function () use (
                $request,
                $product,
                $moderator,
                $moderationData
            ): array {
                $lockedProduct =
                    Product::query()
                        ->whereKey(
                            $product->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $fromStatus =
                    $this->statusValue(
                        $lockedProduct->status
                    );

                $requestedAction =
                    $moderationData[
                        'action_value'
                    ];

                $appliedAction =
                    $this->resolveAppliedAction(
                        requestedAction:
                            $requestedAction,

                        currentStatus:
                            $fromStatus,

                        requiresRejection:
                            $moderationData[
                                'requires_rejection'
                            ]
                    );

                $toStatus =
                    $this->targetStatus(
                        currentStatus:
                            $fromStatus,

                        action:
                            $appliedAction
                    );

                $this->applyProductDecision(
                    product:
                        $lockedProduct,

                    action:
                        $appliedAction,

                    toStatus:
                        $toStatus,

                    moderator:
                        $moderator,

                    reason:
                        $moderationData[
                            'reason'
                        ]
                );

                $reviewPayload = [
                    'product_id' =>
                        $lockedProduct
                            ->getKey(),

                    'action' =>
                        $appliedAction,

                    'from_status' =>
                        $fromStatus,

                    'to_status' =>
                        $toStatus,

                    'reason' =>
                        $moderationData[
                            'reason'
                        ],

                    'notes' =>
                        $moderationData[
                            'notes'
                        ],

                    'moderation_flags' =>
                        $moderationData[
                            'moderation_flags'
                        ],

                    'is_prohibited_item' =>
                        $moderationData[
                            'is_prohibited_item'
                        ],

                    'flag_notes' =>
                        $moderationData[
                            'flag_notes'
                        ],

                    'flagged_at' =>
                        $moderationData[
                            'moderation_flags'
                        ] !== []
                            ? now()
                            : null,

                    'metadata' => [
                        'requested_action' =>
                            $requestedAction,

                        'applied_action' =>
                            $appliedAction,

                        'action_automatically_changed' =>
                            $requestedAction
                            !== $appliedAction,

                        'ip_address' =>
                            $request->ip(),

                        'user_agent' =>
                            $this
                                ->nullableLimitedText(
                                    $request
                                        ->userAgent(),
                                    1000
                                ),

                        'moderated_at' =>
                            now()->toISOString(),
                    ],
                ];

                $moderatorColumn =
                    $this
                        ->moderatorForeignKeyColumn();

                if (
                    $moderatorColumn
                    !== null
                ) {
                    $reviewPayload[
                        $moderatorColumn
                    ] = $moderator
                        ->getKey();
                }

                $review =
                    ProductModerationReview::query()
                        ->create(
                            $reviewPayload
                        );

                return [
                    'product' =>
                        $lockedProduct,

                    'review' =>
                        $review,

                    'requested_action' =>
                        $requestedAction,

                    'applied_action' =>
                        $appliedAction,
                ];
            },
            3
        );

        /** @var Product $moderatedProduct */
        $moderatedProduct =
            $result['product'];

        /** @var ProductModerationReview $review */
        $review =
            $result['review'];

        $this->loadModerationProduct(
            $moderatedProduct
        );

        $review->load([
            'moderator:id,public_id,name,email',
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                $this->moderationMessage(
                    $result[
                        'applied_action'
                    ],
                    $result[
                        'requested_action'
                    ]
                ),

            'data' => (
                new SellerProductResource(
                    $moderatedProduct
                )
            )->resolve($request),

            'moderation_review' =>
                $this
                    ->moderationReviewData(
                        $review
                    ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Product lifecycle transitions
    |--------------------------------------------------------------------------
    */

    /**
     * Automatically enforce rejection or suspension for prohibited items.
     */
    private function resolveAppliedAction(
        string $requestedAction,
        string $currentStatus,
        bool $requiresRejection
    ): string {
        if (!$requiresRejection) {
            return $requestedAction;
        }

        return match ($currentStatus) {
            'pending_review' =>
                'reject',

            'approved' =>
                'suspend',

            default =>
                $requestedAction,
        };
    }

    /**
     * Resolve and validate the resulting product status.
     */
    private function targetStatus(
        string $currentStatus,
        string $action
    ): string {
        $targetStatus = match ($action) {
            'approve' =>
                $currentStatus ===
                    'pending_review'
                    ? 'approved'
                    : null,

            'reject' =>
                $currentStatus ===
                    'pending_review'
                    ? 'rejected'
                    : null,

            'suspend' =>
                $currentStatus ===
                    'approved'
                    ? 'suspended'
                    : null,

            'return_to_draft' =>
                in_array(
                    $currentStatus,
                    [
                        'pending_review',
                        'approved',
                        'rejected',
                        'suspended',
                    ],
                    true
                )
                    ? 'draft'
                    : null,

            default =>
                null,
        };

        if ($targetStatus === null) {
            $this->transitionConflict(
                currentStatus:
                    $currentStatus,
                action:
                    $action
            );
        }

        return $targetStatus;
    }

    /**
     * Update the product lifecycle fields for one moderation decision.
     */
    private function applyProductDecision(
        Product $product,
        string $action,
        string $toStatus,
        User $moderator,
        ?string $reason
    ): void {
        $now = now();

        $values = [
            'status' =>
                $toStatus,
        ];

        if ($action === 'approve') {
            $values = array_merge(
                $values,
                [
                    'approved_at' =>
                        $now,

                    'approved_by' =>
                        $moderator
                            ->getKey(),

                    'rejected_at' =>
                        null,

                    'rejected_by' =>
                        null,

                    'rejection_reason' =>
                        null,

                    'suspended_at' =>
                        null,

                    'suspended_by' =>
                        null,

                    'suspension_reason' =>
                        null,

                    'archived_at' =>
                        null,
                ]
            );
        }

        if ($action === 'reject') {
            $values = array_merge(
                $values,
                [
                    'approved_at' =>
                        null,

                    'approved_by' =>
                        null,

                    'rejected_at' =>
                        $now,

                    'rejected_by' =>
                        $moderator
                            ->getKey(),

                    'rejection_reason' =>
                        $reason,

                    'suspended_at' =>
                        null,

                    'suspended_by' =>
                        null,

                    'suspension_reason' =>
                        null,

                    'archived_at' =>
                        null,
                ]
            );
        }

        if ($action === 'suspend') {
            $values = array_merge(
                $values,
                [
                    'suspended_at' =>
                        $now,

                    'suspended_by' =>
                        $moderator
                            ->getKey(),

                    'suspension_reason' =>
                        $reason,

                    'archived_at' =>
                        null,
                ]
            );
        }

        if (
            $action ===
            'return_to_draft'
        ) {
            $values = array_merge(
                $values,
                [
                    'submitted_at' =>
                        null,

                    'approved_at' =>
                        null,

                    'approved_by' =>
                        null,

                    'rejected_at' =>
                        null,

                    'rejected_by' =>
                        null,

                    'rejection_reason' =>
                        null,

                    'suspended_at' =>
                        null,

                    'suspended_by' =>
                        null,

                    'suspension_reason' =>
                        null,

                    'archived_at' =>
                        null,
                ]
            );
        }

        $this->setExistingProductAttributes(
            $product,
            $values
        );

        $product->save();
    }

    /**
     * Throw a standard invalid-transition response.
     */
    private function transitionConflict(
        string $currentStatus,
        string $action
    ): never {
        throw new HttpResponseException(
            response()->json([
                'success' =>
                    false,

                'message' =>
                    'The requested moderation transition is not allowed.',

                'errors' => [
                    'action' => [
                        sprintf(
                            'The action "%s" cannot be applied while the product status is "%s".',
                            $action,
                            $currentStatus
                        ),
                    ],
                ],
            ], 409)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure the authenticated user is an administrator.
     */
    private function authorizeAdministrator(
        Request $request
    ): User {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Authentication is required.'
        );

        $allowedRoles = [
            'admin',
            'superadmin',
            'super_admin',
        ];

        if (
            method_exists(
                $user,
                'hasAnyRole'
            )
        ) {
            try {
                if (
                    $user->hasAnyRole(
                        $allowedRoles
                    )
                ) {
                    return $user;
                }
            } catch (Throwable) {
                // Continue with other supported role checks.
            }
        }

        if (
            method_exists(
                $user,
                'hasRole'
            )
        ) {
            foreach (
                $allowedRoles
                as $role
            ) {
                try {
                    if (
                        $user->hasRole(
                            $role
                        )
                    ) {
                        return $user;
                    }
                } catch (Throwable) {
                    break;
                }
            }
        }

        $directRole =
            $this->statusValue(
                $user->getAttribute(
                    'role'
                )
            );

        if (
            in_array(
                $directRole,
                $allowedRoles,
                true
            )
        ) {
            return $user;
        }

        if (
            method_exists(
                $user,
                'roles'
            )
        ) {
            try {
                $hasRole =
                    $user->roles()
                        ->whereIn(
                            'name',
                            $allowedRoles
                        )
                        ->exists();

                if ($hasRole) {
                    return $user;
                }
            } catch (Throwable) {
                // Return the standard forbidden response below.
            }
        }

        abort(
            403,
            'Administrator access is required.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query filters
    |--------------------------------------------------------------------------
    */

    /**
     * Apply administrator product search.
     *
     * @param Builder<Product> $query
     */
    private function applySearch(
        Builder $query,
        ?string $search
    ): void {
        $search = trim(
            (string) $search
        );

        if ($search === '') {
            return;
        }

        $escapedSearch =
            addcslashes(
                $search,
                '\\%_'
            );

        $like =
            "%{$escapedSearch}%";

        $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where(
                        'name',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'slug',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'model_number',
                        'like',
                        $like
                    )
                    ->orWhereHas(
                        'category',
                        static function (
                            Builder $categoryQuery
                        ) use ($like): void {
                            $categoryQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'brand',
                        static function (
                            Builder $brandQuery
                        ) use ($like): void {
                            $brandQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'sellerProfile',
                        static function (
                            Builder $sellerQuery
                        ) use ($like): void {
                            $sellerQuery
                                ->where(
                                    'legal_business_name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'trading_name',
                                    'like',
                                    $like
                                );
                        }
                    );
            }
        );
    }

    /**
     * Filter products by whether moderation flags exist.
     *
     * @param Builder<Product> $query
     */
    private function applyFlaggedFilter(
        Builder $query,
        bool $flagged
    ): void {
        $constraint =
            static function (
                Builder $reviewQuery
            ): void {
                $reviewQuery
                    ->whereNotNull(
                        'moderation_flags'
                    )
                    ->whereNotNull(
                        'flagged_at'
                    );
            };

        if ($flagged) {
            $query->whereHas(
                'moderationReviews',
                $constraint
            );

            return;
        }

        $query->whereDoesntHave(
            'moderationReviews',
            $constraint
        );
    }

    /**
     * Filter products by prohibited-item classification.
     *
     * @param Builder<Product> $query
     */
    private function applyProhibitedFilter(
        Builder $query,
        bool $prohibited
    ): void {
        $constraint =
            static function (
                Builder $reviewQuery
            ): void {
                $reviewQuery->where(
                    'is_prohibited_item',
                    true
                );
            };

        if ($prohibited) {
            $query->whereHas(
                'moderationReviews',
                $constraint
            );

            return;
        }

        $query->whereDoesntHave(
            'moderationReviews',
            $constraint
        );
    }

    /**
     * Apply deterministic administrator sorting.
     *
     * @param Builder<Product> $query
     */
    private function applySorting(
        Builder $query,
        string $sort
    ): void {
        match ($sort) {
            'oldest' =>
                $query
                    ->orderBy(
                        'created_at'
                    )
                    ->orderBy('id'),

            'submitted_oldest' =>
                $query
                    ->orderByRaw(
                        'submitted_at IS NULL'
                    )
                    ->orderBy(
                        'submitted_at'
                    )
                    ->orderBy('id'),

            'name_asc' =>
                $query
                    ->orderBy('name')
                    ->orderBy('id'),

            'name_desc' =>
                $query
                    ->orderByDesc('name')
                    ->orderByDesc('id'),

            'newest' =>
                $query
                    ->orderByDesc(
                        'created_at'
                    )
                    ->orderByDesc('id'),

            default =>
                $query
                    ->orderByRaw(
                        'submitted_at IS NULL'
                    )
                    ->orderByDesc(
                        'submitted_at'
                    )
                    ->orderByDesc('id'),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Data loading and output
    |--------------------------------------------------------------------------
    */

    /**
     * Load relationships required for administrator moderation.
     */
    private function loadModerationProduct(
        Product $product
    ): void {
        $product->loadMissing([
            'category',
            'brand',
            'sellerProfile',
            'variants.price',
            'variants.inventoryStock',
            'media.variant',
            'returnPolicy',
            'activeReturnPolicy',
        ]);
    }

    /**
     * Transform a review into administrator audit data.
     *
     * @return array<string, mixed>
     */
    private function moderationReviewData(
        ProductModerationReview $review
    ): array {
        $data =
            $review->toAuditData();

        $moderator =
            $review->relationLoaded(
                'moderator'
            )
                ? $review->moderator
                : null;

        $data['moderator'] =
            $moderator instanceof User
                ? [
                    'public_id' =>
                        (string) $moderator
                            ->public_id,

                    'name' =>
                        $moderator->name,

                    'email' =>
                        $moderator->email,
                ]
                : null;

        return $data;
    }

    /**
     * Return a moderation success message.
     */
    private function moderationMessage(
        string $appliedAction,
        string $requestedAction
    ): string {
        if (
            $appliedAction !==
            $requestedAction
        ) {
            return match (
                $appliedAction
            ) {
                'reject' =>
                    'The product was automatically rejected because prohibited-item flags were selected.',

                'suspend' =>
                    'The product was automatically suspended because prohibited-item flags were selected.',

                default =>
                    'The product moderation decision was applied successfully.',
            };
        }

        return match (
            $appliedAction
        ) {
            'approve' =>
                'The product was approved successfully.',

            'reject' =>
                'The product was rejected successfully.',

            'suspend' =>
                'The product was suspended successfully.',

            'return_to_draft' =>
                'The product was returned to draft successfully.',

            default =>
                'The product moderation decision was applied successfully.',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Schema compatibility
    |--------------------------------------------------------------------------
    */

    /**
     * Set only product attributes that exist in the current schema.
     *
     * @param array<string, mixed> $values
     */
    private function setExistingProductAttributes(
        Product $product,
        array $values
    ): void {
        foreach (
            $values
            as $column => $value
        ) {
            if (
                $column === 'status'
                || Schema::hasColumn(
                    $product->getTable(),
                    $column
                )
            ) {
                $product->setAttribute(
                    $column,
                    $value
                );
            }
        }
    }

    /**
     * Resolve the moderator foreign-key column from the current schema.
     */
    private function moderatorForeignKeyColumn():
        ?string
    {
        $table =
            'product_moderation_reviews';

        $candidates = [
            'moderator_id',
            'moderated_by',
            'reviewer_id',
            'reviewed_by',
            'admin_user_id',
            'created_by',
        ];

        foreach (
            $candidates
            as $column
        ) {
            if (
                Schema::hasColumn(
                    $table,
                    $column
                )
            ) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Convert an enum or scalar into a normalized string.
     */
    private function statusValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return strtolower(
                trim(
                    (string) $value->value
                )
            );
        }

        return strtolower(
            trim(
                (string) $value
            )
        );
    }

    /**
     * Normalize optional text and limit its length.
     */
    private function nullableLimitedText(
        mixed $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        return Str::limit(
            $value,
            $maximumLength,
            ''
        );
    }
}