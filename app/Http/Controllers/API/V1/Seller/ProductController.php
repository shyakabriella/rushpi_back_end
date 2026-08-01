<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Http\Resources\SellerProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;
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
        if (! $this->canManageProducts($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
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

        $search = trim((string) $request->input('q'));

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
            $productStatus = ProductStatus::tryFrom($status);

            if ($productStatus === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected product status is invalid.',
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

        return SellerProductResource::collection($products)
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

                    if (! empty($data['brand_public_id'])) {
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
                }
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
        if (! $this->canManageProducts($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->belongsToSeller($product, $sellerProfile)) {
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
        if (! $this->belongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product
                ): void {
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

                        $product->category_id =
                            $category->getKey();

                        unset($data['category_public_id']);
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
                            $product->brand_id = null;
                        } else {
                            $brand = Brand::query()
                                ->where(
                                    'public_id',
                                    $data['brand_public_id']
                                )
                                ->where('is_active', true)
                                ->firstOrFail();

                            $product->brand_id =
                                $brand->getKey();
                        }

                        unset($data['brand_public_id']);
                    }

                    if (
                        array_key_exists('slug', $data)
                        && blank($data['slug'])
                    ) {
                        unset($data['slug']);
                    }

                    $product->fill($data);

                    $product->updated_by =
                        $request->user()?->getKey();

                    /*
                     * Material edits to approved or rejected
                     * products require a new moderation cycle.
                     */
                    if (
                        $product->isDirty()
                        && in_array(
                            $product->status,
                            [
                                ProductStatus::APPROVED,
                                ProductStatus::REJECTED,
                            ],
                            true
                        )
                    ) {
                        $product->status =
                            ProductStatus::DRAFT;

                        $product->submitted_at = null;
                        $product->approved_at = null;
                        $product->approved_by = null;
                        $product->rejected_at = null;
                        $product->rejection_reason = null;
                    }

                    $product->save();
                }
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
     * Archive a seller product.
     *
     * Products are archived instead of permanently deleted.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageProducts($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->belongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        if (
            $product->status
            === ProductStatus::PENDING_REVIEW
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'A product under moderation cannot be archived.',
                'data' => null,
            ], 409);
        }

        if (
            $product->status
            === ProductStatus::ARCHIVED
        ) {
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
                    $product->status =
                        ProductStatus::ARCHIVED;

                    $product->archived_at = now();

                    $product->updated_by =
                        $request->user()?->getKey();

                    $product->save();
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Product archived successfully.',
                'data' => null,
            ]);
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
     * Determine whether the product belongs
     * to the selected seller profile.
     */
    private function belongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    /**
     * Determine whether the current user may
     * manage products for this seller profile.
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
