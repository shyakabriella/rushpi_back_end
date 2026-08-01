<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Enums\ProductCondition;
use App\Enums\ProductModerationAction;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModerateProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Models\Product;
use App\Models\ProductModerationReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProductModerationController extends Controller
{
    /**
     * Display products available to administrators.
     *
     * When no status is supplied, the endpoint returns products
     * currently waiting for moderation.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $statusValues = array_map(
            static fn (ProductStatus $status): string =>
                $status->value,
            ProductStatus::cases()
        );

        $validated = $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    ...$statusValues,
                ]),
            ],

            'condition' => [
                'nullable',
                Rule::enum(ProductCondition::class),
            ],

            'seller_public_id' => [
                'nullable',
                'string',
                Rule::exists(
                    'seller_profiles',
                    'public_id'
                ),
            ],

            'category_public_id' => [
                'nullable',
                'string',
                Rule::exists(
                    'categories',
                    'public_id'
                )->whereNull('deleted_at'),
            ],

            'brand_public_id' => [
                'nullable',
                'string',
                Rule::exists(
                    'brands',
                    'public_id'
                )->whereNull('deleted_at'),
            ],

            'q' => [
                'nullable',
                'string',
                'max:255',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ], [
            'status.in' =>
                'The selected product status is invalid.',

            'condition.enum' =>
                'The selected product condition is invalid.',

            'seller_public_id.exists' =>
                'The selected seller business was not found.',

            'category_public_id.exists' =>
                'The selected product category was not found.',

            'brand_public_id.exists' =>
                'The selected product brand was not found.',

            'q.max' =>
                'The product search may not exceed 255 characters.',

            'per_page.integer' =>
                'The page size must be a whole number.',

            'per_page.min' =>
                'The page size must be at least 1.',

            'per_page.max' =>
                'The page size may not exceed 100.',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $selectedStatus = $validated['status']
            ?? ProductStatus::PENDING_REVIEW->value;

        $query = Product::query()
            ->with([
                'sellerProfile',

                'category',

                'brand',

                'createdBy',

                'updatedBy',

                'approvedBy',
            ])
            ->withCount([
                'variants',
                'activeVariants',
                'media',
                'moderationReviews',
            ]);

        if ($selectedStatus !== 'all') {
            $query->where(
                'status',
                $selectedStatus
            );
        }

        if (! empty($validated['condition'])) {
            $query->where(
                'condition',
                $validated['condition']
            );
        }

        if (! empty(
            $validated['seller_public_id']
        )) {
            $query->whereHas(
                'sellerProfile',
                function (Builder $sellerQuery) use (
                    $validated
                ): void {
                    $sellerQuery->where(
                        'public_id',
                        $validated['seller_public_id']
                    );
                }
            );
        }

        if (! empty(
            $validated['category_public_id']
        )) {
            $query->whereHas(
                'category',
                function (Builder $categoryQuery) use (
                    $validated
                ): void {
                    $categoryQuery->where(
                        'public_id',
                        $validated['category_public_id']
                    );
                }
            );
        }

        if (! empty(
            $validated['brand_public_id']
        )) {
            $query->whereHas(
                'brand',
                function (Builder $brandQuery) use (
                    $validated
                ): void {
                    $brandQuery->where(
                        'public_id',
                        $validated['brand_public_id']
                    );
                }
            );
        }

        $search = trim(
            (string) ($validated['q'] ?? '')
        );

        if ($search !== '') {
            $query->where(
                function (Builder $productQuery) use (
                    $search
                ): void {
                    $productQuery
                        ->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'slug',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'short_description',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhereHas(
                            'variants',
                            function (
                                Builder $variantQuery
                            ) use ($search): void {
                                $variantQuery
                                    ->where(
                                        'sku',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'barcode',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        )
                        ->orWhereHas(
                            'sellerProfile',
                            function (
                                Builder $sellerQuery
                            ) use ($search): void {
                                $sellerQuery
                                    ->where(
                                        'legal_business_name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'trading_name',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        );
                }
            );
        }

        $products = $query
            ->orderByRaw(
                'submitted_at IS NULL'
            )
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return AdminProductResource::collection(
            $products
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Administrator products retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Display complete product details for moderation.
     */
    public function show(
        Request $request,
        Product $product
    ): JsonResponse {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $this->loadCompleteProduct($product);

        return response()->json([
            'success' => true,

            'message' =>
                'Product moderation details retrieved successfully.',

            'data' =>
                new AdminProductResource($product),
        ]);
    }

    /**
     * Approve, reject, or suspend a product.
     */
    public function moderate(
        ModerateProductRequest $request,
        Product $product
    ): JsonResponse {
        $data = $request->validated();

        $moderationAction =
            ProductModerationAction::tryFrom(
                $data['action']
            );

        if ($moderationAction === null) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The selected product moderation action is invalid.',

                'data' => null,
            ], 422);
        }

        $actionKind = $this->actionKind(
            $moderationAction
        );

        if ($actionKind === null) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The selected moderation action is not supported.',

                'data' => null,
            ], 422);
        }

        try {
            $result = DB::transaction(
                function () use (
                    $request,
                    $product,
                    $data,
                    $moderationAction,
                    $actionKind
                ): array {
                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $currentStatus =
                        $this->productStatus(
                            $lockedProduct
                        );

                    if ($currentStatus === null) {
                        return [
                            'product' => null,
                            'review' => null,
                            'error' =>
                                'The product has an invalid moderation status.',
                        ];
                    }

                    $transitionError =
                        $this->transitionError(
                            actionKind: $actionKind,
                            status: $currentStatus
                        );

                    if ($transitionError !== null) {
                        return [
                            'product' => null,
                            'review' => null,
                            'error' => $transitionError,
                        ];
                    }

                    $this->loadSnapshotRelations(
                        $lockedProduct
                    );

                    if ($actionKind === 'approve') {
                        $readinessError =
                            $this->approvalReadinessError(
                                $lockedProduct
                            );

                        if ($readinessError !== null) {
                            return [
                                'product' => null,
                                'review' => null,
                                'error' => $readinessError,
                            ];
                        }
                    }

                    $beforeSnapshot =
                        $this->moderationSnapshot(
                            $lockedProduct
                        );

                    $this->applyDecision(
                        product: $lockedProduct,
                        actionKind: $actionKind,
                        reason: $data['reason'] ?? null,
                        administratorId: $request
                            ->user()
                            ?->getKey()
                    );

                    $lockedProduct->updated_by =
                        $request->user()?->getKey();

                    $lockedProduct->save();

                    $afterSnapshot =
                        $this->moderationSnapshot(
                            $lockedProduct
                        );

                    $review = $lockedProduct
                        ->moderationReviews()
                        ->create([
                            'reviewed_by' =>
                                $request
                                    ->user()
                                    ?->getKey(),

                            'action' =>
                                $moderationAction,

                            'reason' =>
                                $data['reason'] ?? null,

                            'internal_notes' =>
                                $data['internal_notes']
                                ?? null,

                            'snapshot' => [
                                'before' =>
                                    $beforeSnapshot,

                                'after' =>
                                    $afterSnapshot,
                            ],
                        ]);

                    return [
                        'product' => $lockedProduct,
                        'review' => $review,
                        'error' => null,
                    ];
                },
                5
            );

            if (is_string($result['error'])) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        $result['error'],

                    'data' => null,
                ], 409);
            }

            $moderatedProduct = $result['product'];

            if (! $moderatedProduct instanceof Product) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'The product moderation decision could not be completed.',

                    'data' => null,
                ], 409);
            }

            $moderatedProduct->refresh();

            $this->loadCompleteProduct(
                $moderatedProduct
            );

            return response()->json([
                'success' => true,

                'message' =>
                    $this->successMessage($actionKind),

                'data' =>
                    new AdminProductResource(
                        $moderatedProduct
                    ),
            ]);
        } catch (ModelNotFoundException) {
            return $this->productNotFoundResponse();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to complete the product moderation decision.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Apply a moderation decision to the product.
     */
    private function applyDecision(
        Product $product,
        string $actionKind,
        ?string $reason,
        ?int $administratorId
    ): void {
        if ($actionKind === 'approve') {
            $product->status =
                ProductStatus::APPROVED;

            $product->approved_at = now();

            $product->approved_by =
                $administratorId;

            $product->rejected_at = null;

            $product->rejection_reason = null;

            $product->suspended_at = null;

            $product->suspension_reason = null;

            $product->archived_at = null;

            return;
        }

        if ($actionKind === 'reject') {
            $product->status =
                ProductStatus::REJECTED;

            $product->rejected_at = now();

            $product->rejection_reason =
                $reason;

            $product->approved_at = null;

            $product->approved_by = null;

            $product->suspended_at = null;

            $product->suspension_reason = null;

            $product->archived_at = null;

            return;
        }

        $product->status =
            ProductStatus::SUSPENDED;

        $product->suspended_at = now();

        $product->suspension_reason =
            $reason;

        /*
         * approved_at and approved_by are preserved so the audit
         * record still shows who originally approved the product.
         */
    }

    /**
     * Return a status-transition error when an action is no
     * longer valid inside the locked transaction.
     */
    private function transitionError(
        string $actionKind,
        ProductStatus $status
    ): ?string {
        if (
            in_array(
                $actionKind,
                ['approve', 'reject'],
                true
            )
            && $status !== ProductStatus::PENDING_REVIEW
        ) {
            return sprintf(
                'Only products with pending review status may be %s.',
                $actionKind === 'approve'
                    ? 'approved'
                    : 'rejected'
            );
        }

        if (
            $actionKind === 'suspend'
            && $status !== ProductStatus::APPROVED
        ) {
            return 'Only an approved product may be suspended.';
        }

        return null;
    }

    /**
     * Confirm that a product remains complete at approval time.
     */
    private function approvalReadinessError(
        Product $product
    ): ?string {
        $sellerProfile = $product->sellerProfile;

        if (
            $sellerProfile === null
            || ! $sellerProfile->isApproved()
        ) {
            return 'The seller business is no longer approved.';
        }

        if (
            $product->category === null
            || ! (bool) $product->category->is_active
            || $product->category->trashed()
        ) {
            return 'The product category is inactive or unavailable.';
        }

        if (
            $product->brand_id !== null
            && (
                $product->brand === null
                || ! (bool) $product->brand->is_active
                || $product->brand->trashed()
            )
        ) {
            return 'The product brand is inactive or unavailable.';
        }

        $activeVariants = $product->variants
            ->where('is_active', true);

        if ($activeVariants->isEmpty()) {
            return 'The product requires at least one active variant.';
        }

        if (
            ! $activeVariants->contains(
                static fn ($variant): bool =>
                    (bool) $variant->is_default
            )
        ) {
            return 'One active product variant must be marked as default.';
        }

        foreach ($activeVariants as $variant) {
            if (
                $variant->price === null
                || (float) $variant->price->selling_price
                    <= 0
            ) {
                return sprintf(
                    'Variant "%s" does not have valid pricing.',
                    $variant->sku
                );
            }

            if ($variant->inventoryStock === null) {
                return sprintf(
                    'Inventory is missing for variant "%s".',
                    $variant->sku
                );
            }
        }

        if ($product->media->isEmpty()) {
            return 'The product requires at least one image.';
        }

        if (
            ! $product->media->contains(
                static fn ($media): bool =>
                    (bool) $media->is_primary
            )
        ) {
            return 'One product image must be marked as primary.';
        }

        return null;
    }

    /**
     * Build the immutable moderation snapshot.
     *
     * @return array<string, mixed>
     */
    private function moderationSnapshot(
        Product $product
    ): array {
        return [
            'product' => [
                'public_id' =>
                    $product->public_id,

                'name' =>
                    $product->name,

                'slug' =>
                    $product->slug,

                'status' =>
                    $this->productStatus($product)?->value,

                'condition' =>
                    $product->condition?->value
                    ?? $product->condition,

                'category_public_id' =>
                    $product->category?->public_id,

                'brand_public_id' =>
                    $product->brand?->public_id,

                'submitted_at' =>
                    $product->submitted_at
                        ?->toISOString(),

                'approved_at' =>
                    $product->approved_at
                        ?->toISOString(),

                'rejected_at' =>
                    $product->rejected_at
                        ?->toISOString(),

                'suspended_at' =>
                    $product->suspended_at
                        ?->toISOString(),
            ],

            'seller' => [
                'public_id' =>
                    $product->sellerProfile?->public_id,

                'legal_business_name' =>
                    $product->sellerProfile
                        ?->legal_business_name,

                'trading_name' =>
                    $product->sellerProfile
                        ?->trading_name,
            ],

            'variants' =>
                $product->variants
                    ->map(
                        static function ($variant): array {
                            return [
                                'public_id' =>
                                    $variant->public_id,

                                'sku' =>
                                    $variant->sku,

                                'name' =>
                                    $variant->name,

                                'is_default' =>
                                    (bool) $variant->is_default,

                                'is_active' =>
                                    (bool) $variant->is_active,

                                'price' =>
                                    $variant->price !== null
                                        ? [
                                            'currency' =>
                                                $variant
                                                    ->price
                                                    ->currency,

                                            'selling_price' =>
                                                $variant
                                                    ->price
                                                    ->selling_price,

                                            'compare_at_price' =>
                                                $variant
                                                    ->price
                                                    ->compare_at_price,
                                        ]
                                        : null,

                                'inventory' =>
                                    $variant
                                        ->inventoryStock !== null
                                            ? [
                                                'quantity_on_hand' =>
                                                    (int) $variant
                                                        ->inventoryStock
                                                        ->quantity_on_hand,

                                                'quantity_reserved' =>
                                                    (int) $variant
                                                        ->inventoryStock
                                                        ->quantity_reserved,

                                                'allow_backorder' =>
                                                    (bool) $variant
                                                        ->inventoryStock
                                                        ->allow_backorder,
                                            ]
                                            : null,
                            ];
                        }
                    )
                    ->values()
                    ->all(),

            'media' => [
                'count' =>
                    $product->media->count(),

                'primary_public_id' =>
                    $product->media
                        ->firstWhere(
                            'is_primary',
                            true
                        )
                        ?->public_id,
            ],
        ];
    }

    /**
     * Load relationships needed to build an audit snapshot.
     */
    private function loadSnapshotRelations(
        Product $product
    ): void {
        $product->load([
            'sellerProfile',

            'category',

            'brand',

            'variants' => function (
                $variantQuery
            ): void {
                $variantQuery
                    ->with([
                        'price',
                        'inventoryStock',
                    ])
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'media' => function (
                $mediaQuery
            ): void {
                $mediaQuery
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ]);
    }

    /**
     * Load complete relationships for the administrator resource.
     */
    private function loadCompleteProduct(
        Product $product
    ): void {
        $product->load([
            'sellerProfile',

            'category',

            'brand',

            'createdBy',

            'updatedBy',

            'approvedBy',

            'variants' => function (
                $variantQuery
            ): void {
                $variantQuery
                    ->with([
                        'price',

                        'inventoryStock',

                        'media.variant',
                    ])
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'media' => function (
                $mediaQuery
            ): void {
                $mediaQuery
                    ->with('variant')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'moderationReviews' => function (
                $reviewQuery
            ): void {
                $reviewQuery
                    ->with('reviewedBy')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            },
        ]);

        $product->loadCount([
            'variants',
            'activeVariants',
            'media',
            'moderationReviews',
        ]);
    }

    /**
     * Resolve a moderation action into a controller operation.
     */
    private function actionKind(
        ProductModerationAction $action
    ): ?string {
        $identity = strtolower(
            $action->name.' '.$action->value
        );

        if (str_contains($identity, 'approv')) {
            return 'approve';
        }

        if (str_contains($identity, 'reject')) {
            return 'reject';
        }

        if (str_contains($identity, 'suspend')) {
            return 'suspend';
        }

        return null;
    }

    /**
     * Resolve the product status safely.
     */
    private function productStatus(
        Product $product
    ): ?ProductStatus {
        if ($product->status instanceof ProductStatus) {
            return $product->status;
        }

        if (is_string($product->status)) {
            return ProductStatus::tryFrom(
                $product->status
            );
        }

        return null;
    }

    /**
     * Return the moderation result message.
     */
    private function successMessage(
        string $actionKind
    ): string {
        return match ($actionKind) {
            'approve' =>
                'Product approved successfully.',

            'reject' =>
                'Product rejected successfully.',

            'suspend' =>
                'Product suspended successfully.',

            default =>
                'Product moderation completed successfully.',
        };
    }

    /**
     * Determine whether the authenticated user is an administrator.
     */
    private function isAdministrator(
        Request $request
    ): bool {
        $user = $request->user();

        return $user !== null
            && $user->hasAnyRole([
                'admin',
                'superadmin',
            ]);
    }

    /**
     * Standard administrator permission response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to moderate products.',

            'data' => null,
        ], 403);
    }

    /**
     * Safe product-not-found response.
     */
    private function productNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'The requested product was not found.',

            'data' => null,
        ], 404);
    }
}
