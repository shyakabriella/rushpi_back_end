<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\SubmitProductForReviewRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Http\Resources\SellerProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductController extends Controller
{
    /**
     * Display products belonging to one seller profile.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        if (! $this->canManageProducts(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        $perPage = min(
            max(
                (int) $request->input('per_page', 15),
                1
            ),
            100
        );

        $query = Product::query()
            ->where(
                'seller_profile_id',
                $sellerProfile->getKey()
            )
            ->with([
                'category:id,public_id,name,slug',

                'brand:id,public_id,name,slug,logo_path',
            ])
            ->withCount([
                'variants',
                'activeVariants',
                'media',
                'moderationReviews',
            ]);

        $search = trim(
            (string) $request->input('q')
        );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $productQuery
                ) use ($search): void {
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
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        );
                }
            );
        }

        $status = trim(
            (string) $request->input('status')
        );

        if ($status !== '') {
            $productStatus =
                ProductStatus::tryFrom($status);

            if ($productStatus === null) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'The selected product status is invalid.',

                    'data' => null,
                ], 422);
            }

            $query->where(
                'status',
                $productStatus->value
            );
        }

        $categoryPublicId = trim(
            (string) $request->input('category')
        );

        if ($categoryPublicId !== '') {
            $query->whereHas(
                'category',
                function (
                    Builder $categoryQuery
                ) use ($categoryPublicId): void {
                    $categoryQuery->where(
                        'public_id',
                        $categoryPublicId
                    );
                }
            );
        }

        $brandPublicId = trim(
            (string) $request->input('brand')
        );

        if ($brandPublicId !== '') {
            $query->whereHas(
                'brand',
                function (
                    Builder $brandQuery
                ) use ($brandPublicId): void {
                    $brandQuery->where(
                        'public_id',
                        $brandPublicId
                    );
                }
            );
        }

        $products = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return SellerProductResource::collection(
            $products
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Seller products retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Create a new seller product in draft status.
     */
    public function store(
        StoreProductRequest $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        try {
            $product = DB::transaction(
                function () use (
                    $request,
                    $sellerProfile
                ): Product {
                    $data = $request->validated();

                    $category = Category::query()
                        ->where(
                            'public_id',
                            $data['category_public_id']
                        )
                        ->where('is_active', true)
                        ->firstOrFail();

                    $brand = null;

                    if (! empty(
                        $data['brand_public_id']
                    )) {
                        $brand = Brand::query()
                            ->where(
                                'public_id',
                                $data['brand_public_id']
                            )
                            ->where('is_active', true)
                            ->firstOrFail();
                    }

                    unset(
                        $data['category_public_id'],
                        $data['brand_public_id']
                    );

                    if (
                        array_key_exists('slug', $data)
                        && blank($data['slug'])
                    ) {
                        unset($data['slug']);
                    }

                    $product = new Product($data);

                    $product->seller_profile_id =
                        $sellerProfile->getKey();

                    $product->category_id =
                        $category->getKey();

                    $product->brand_id =
                        $brand?->getKey();

                    $product->created_by =
                        $request->user()?->getKey();

                    $product->updated_by =
                        $request->user()?->getKey();

                    $product->status =
                        ProductStatus::DRAFT;

                    $product->save();

                    return $product;
                },
                5
            );

            $this->loadProductRelations($product);

            return response()->json([
                'success' => true,

                'message' =>
                    'Product draft created successfully.',

                'data' =>
                    new SellerProductResource($product),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to create the product.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Display one seller product.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageProducts(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->belongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        $this->loadProductRelations($product);

        return response()->json([
            'success' => true,

            'message' =>
                'Seller product retrieved successfully.',

            'data' =>
                new SellerProductResource($product),
        ]);
    }

    /**
     * Update a seller product.
     */
    public function update(
        UpdateProductRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->belongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product
                ): void {
                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $data = $request->validated();

                    if (
                        array_key_exists(
                            'category_public_id',
                            $data
                        )
                    ) {
                        $category = Category::query()
                            ->where(
                                'public_id',
                                $data['category_public_id']
                            )
                            ->where('is_active', true)
                            ->firstOrFail();

                        $lockedProduct->category_id =
                            $category->getKey();

                        unset(
                            $data['category_public_id']
                        );
                    }

                    if (
                        array_key_exists(
                            'brand_public_id',
                            $data
                        )
                    ) {
                        if (
                            $data['brand_public_id'] === null
                            || $data['brand_public_id'] === ''
                        ) {
                            $lockedProduct->brand_id = null;
                        } else {
                            $brand = Brand::query()
                                ->where(
                                    'public_id',
                                    $data['brand_public_id']
                                )
                                ->where('is_active', true)
                                ->firstOrFail();

                            $lockedProduct->brand_id =
                                $brand->getKey();
                        }

                        unset(
                            $data['brand_public_id']
                        );
                    }

                    if (
                        array_key_exists('slug', $data)
                        && blank($data['slug'])
                    ) {
                        unset($data['slug']);
                    }

                    $lockedProduct->fill($data);

                    $lockedProduct->updated_by =
                        $request->user()?->getKey();

                    /*
                     * Public catalog changes to approved or
                     * rejected products require a new review.
                     */
                    if (
                        $lockedProduct->isDirty()
                        && in_array(
                            $this->resolveProductStatus(
                                $lockedProduct
                            ),
                            [
                                ProductStatus::APPROVED,
                                ProductStatus::REJECTED,
                            ],
                            true
                        )
                    ) {
                        $this->returnProductToDraft(
                            $lockedProduct
                        );
                    }

                    $lockedProduct->save();
                },
                5
            );

            $product->refresh();

            $this->loadProductRelations($product);

            return response()->json([
                'success' => true,

                'message' =>
                    'Product updated successfully.',

                'data' =>
                    new SellerProductResource($product),
            ]);
        } catch (ModelNotFoundException) {
            return $this->productNotFoundResponse();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to update the product.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Submit a complete product for administrator review.
     */
    public function submitForReview(
        SubmitProductForReviewRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->belongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        try {
            $submittedProduct = DB::transaction(
                function () use (
                    $request,
                    $sellerProfile,
                    $product
                ): ?Product {
                    /*
                     * Lock the product so two submission requests
                     * cannot change its moderation status together.
                     */
                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $this->belongsToSeller(
                        $lockedProduct,
                        $sellerProfile
                    )) {
                        return null;
                    }

                    $status = $this->resolveProductStatus(
                        $lockedProduct
                    );

                    /*
                     * Recheck the status inside the transaction.
                     * The request validation may have happened
                     * shortly before another request changed it.
                     */
                    if (
                        ! in_array(
                            $status,
                            [
                                ProductStatus::DRAFT,
                                ProductStatus::REJECTED,
                            ],
                            true
                        )
                    ) {
                        return null;
                    }

                    /*
                     * Defence-in-depth readiness verification.
                     * The form request already provides detailed
                     * validation messages.
                     */
                    if (! $this->isReadyForSubmission(
                        $lockedProduct
                    )) {
                        return null;
                    }

                    $lockedProduct->status =
                        ProductStatus::PENDING_REVIEW;

                    $lockedProduct->submitted_at = now();

                    /*
                     * Clear previous moderation decisions.
                     */
                    $lockedProduct->approved_at = null;

                    $lockedProduct->approved_by = null;

                    $lockedProduct->rejected_at = null;

                    $lockedProduct->rejection_reason = null;

                    $lockedProduct->suspended_at = null;

                    $lockedProduct->suspension_reason = null;

                    $lockedProduct->archived_at = null;

                    $lockedProduct->updated_by =
                        $request->user()?->getKey();

                    $lockedProduct->save();

                    return $lockedProduct;
                },
                5
            );

            if (! $submittedProduct instanceof Product) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'The product could not be submitted because its status or catalog information changed. Review the product and try again.',

                    'data' => null,
                ], 409);
            }

            $this->loadProductRelations(
                $submittedProduct
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Product submitted for review successfully.',

                'data' =>
                    new SellerProductResource(
                        $submittedProduct
                    ),
            ]);
        } catch (ModelNotFoundException) {
            return $this->productNotFoundResponse();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to submit the product for review.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Archive a seller product.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageProducts(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->belongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        $status = $this->resolveProductStatus(
            $product
        );

        if (
            $status === ProductStatus::PENDING_REVIEW
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'A product under moderation cannot be archived.',

                'data' => null,
            ], 409);
        }

        if ($status === ProductStatus::ARCHIVED) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This product is already archived.',

                'data' => null,
            ], 409);
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product
                ): void {
                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (
                        $this->resolveProductStatus(
                            $lockedProduct
                        ) === ProductStatus::PENDING_REVIEW
                    ) {
                        return;
                    }

                    $lockedProduct->status =
                        ProductStatus::ARCHIVED;

                    $lockedProduct->archived_at = now();

                    $lockedProduct->updated_by =
                        $request->user()?->getKey();

                    $lockedProduct->save();
                },
                5
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Product archived successfully.',

                'data' => null,
            ]);
        } catch (ModelNotFoundException) {
            return $this->productNotFoundResponse();
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to archive the product.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Verify product completeness before moderation.
     */
    private function isReadyForSubmission(
        Product $product
    ): bool {
        $categoryIsActive = $product->category()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if (! $categoryIsActive) {
            return false;
        }

        if ($product->brand_id !== null) {
            $brandIsActive = $product->brand()
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if (! $brandIsActive) {
                return false;
            }
        }

        $activeVariants = $product->variants()
            ->where('is_active', true)
            ->with([
                'price',
                'inventoryStock',
            ])
            ->get();

        if ($activeVariants->isEmpty()) {
            return false;
        }

        $hasDefaultVariant = $activeVariants->contains(
            fn ($variant): bool =>
                (bool) $variant->is_default
        );

        if (! $hasDefaultVariant) {
            return false;
        }

        foreach ($activeVariants as $variant) {
            if (
                $variant->price === null
                || (float) $variant->price->selling_price
                    <= 0
            ) {
                return false;
            }

            if ($variant->inventoryStock === null) {
                return false;
            }
        }

        $hasMedia = $product->media()->exists();

        if (! $hasMedia) {
            return false;
        }

        return $product->media()
            ->where('is_primary', true)
            ->exists();
    }

    /**
     * Return a product to draft and clear old decisions.
     */
    private function returnProductToDraft(
        Product $product
    ): void {
        $product->status =
            ProductStatus::DRAFT;

        $product->submitted_at = null;

        $product->approved_at = null;

        $product->approved_by = null;

        $product->rejected_at = null;

        $product->rejection_reason = null;

        $product->suspended_at = null;

        $product->suspension_reason = null;

        $product->archived_at = null;
    }

    /**
     * Load relationships required by the seller resource.
     */
    private function loadProductRelations(
        Product $product
    ): void {
        $product->load([
            'category:id,public_id,name,slug',

            'brand:id,public_id,name,slug,logo_path',

            'sellerProfile:id,public_id,legal_business_name,trading_name,status',
        ]);

        $product->loadCount([
            'variants',
            'activeVariants',
            'media',
            'moderationReviews',
        ]);
    }

    /**
     * Determine whether the product belongs to the seller.
     */
    private function belongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    /**
     * Determine whether the current user may manage products.
     */
    private function canManageProducts(
        Request $request,
        SellerProfile $sellerProfile
    ): bool {
        $user = $request->user();

        if (
            $user === null
            || ! $sellerProfile->isApproved()
        ) {
            return false;
        }

        return $sellerProfile->members()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereIn('role', [
                'owner',
                'manager',
            ])
            ->exists();
    }

    /**
     * Resolve a product status safely.
     */
    private function resolveProductStatus(
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
     * Return a standard seller permission response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to manage products for this seller business.',

            'data' => null,
        ], 403);
    }

    /**
     * Avoid exposing products belonging to another seller.
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