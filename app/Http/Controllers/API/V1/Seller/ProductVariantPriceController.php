<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductVariantPriceRequest;
use App\Http\Requests\Seller\UpdateProductVariantPriceRequest;
use App\Http\Resources\SellerProductVariantPriceResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductVariantPriceController extends Controller
{
    /**
     * Display the current price for a product variant.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->canManagePricing(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct(
            $variant,
            $product
        )) {
            return $this->variantNotFoundResponse();
        }

        $price = ProductVariantPrice::query()
            ->where(
                'product_variant_id',
                $variant->getKey()
            )
            ->first();

        if ($price === null) {
            return $this->priceNotFoundResponse();
        }

        $this->loadPriceRelations($price);

        return response()->json([
            'success' => true,

            'message' =>
                'Product variant pricing retrieved successfully.',

            'data' =>
                new SellerProductVariantPriceResource($price),
        ]);
    }

    /**
     * Create pricing for a product variant.
     */
    public function store(
        StoreProductVariantPriceRequest $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct(
            $variant,
            $product
        )) {
            return $this->variantNotFoundResponse();
        }

        try {
            $price = DB::transaction(
                function () use (
                    $request,
                    $product,
                    $variant
                ): ?ProductVariantPrice {
                    /*
                     * Lock the variant during price creation to prevent
                     * two requests from creating duplicate price rows.
                     */
                    $lockedVariant = ProductVariant::query()
                        ->whereKey($variant->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $priceAlreadyExists =
                        ProductVariantPrice::query()
                            ->where(
                                'product_variant_id',
                                $lockedVariant->getKey()
                            )
                            ->exists();

                    if ($priceAlreadyExists) {
                        return null;
                    }

                    $data = $request->validated();

                    $data['created_by'] =
                        $request->user()?->getKey();

                    $data['updated_by'] =
                        $request->user()?->getKey();

                    $price = $lockedVariant
                        ->price()
                        ->create($data);

                    $lockedProduct = Product::query()
                        ->whereKey($product->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->resetProductModeration(
                        product: $lockedProduct,
                        userId: $request->user()?->getKey()
                    );

                    return $price;
                }
            );

            if ($price === null) {
                return response()->json([
                    'success' => false,

                    'message' =>
                        'This product variant already has pricing. Use the update endpoint instead.',

                    'data' => null,
                ], 409);
            }

            $this->loadPriceRelations($price);

            return response()->json([
                'success' => true,

                'message' =>
                    'Product variant pricing created successfully.',

                'data' =>
                    new SellerProductVariantPriceResource($price),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to create product variant pricing.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Update pricing for a product variant.
     */
    public function update(
        UpdateProductVariantPriceRequest $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct(
            $variant,
            $product
        )) {
            return $this->variantNotFoundResponse();
        }

        try {
            $result = DB::transaction(
                function () use (
                    $request,
                    $product,
                    $variant
                ): array {
                    $price = ProductVariantPrice::query()
                        ->where(
                            'product_variant_id',
                            $variant->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($price === null) {
                        return [
                            'price' => null,
                            'changed' => false,
                        ];
                    }

                    $price->fill($request->validated());

                    $priceChanged = $price->isDirty([
                        'currency',
                        'selling_price',
                        'compare_at_price',
                        'cost_price',
                    ]);

                    if ($priceChanged) {
                        $price->updated_by =
                            $request->user()?->getKey();

                        $price->save();

                        $lockedProduct = Product::query()
                            ->whereKey($product->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        $this->resetProductModeration(
                            product: $lockedProduct,
                            userId: $request
                                ->user()
                                ?->getKey()
                        );
                    }

                    return [
                        'price' => $price,
                        'changed' => $priceChanged,
                    ];
                }
            );

            $price = $result['price'];

            if (! $price instanceof ProductVariantPrice) {
                return $this->priceNotFoundResponse();
            }

            $price->refresh();

            $this->loadPriceRelations($price);

            return response()->json([
                'success' => true,

                'message' => $result['changed']
                    ? 'Product variant pricing updated successfully.'
                    : 'No pricing changes were detected.',

                'data' =>
                    new SellerProductVariantPriceResource($price),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to update product variant pricing.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Load relationships required by the seller price resource.
     */
    private function loadPriceRelations(
        ProductVariantPrice $price
    ): void {
        $price->load([
            'variant:id,public_id,sku,name,is_default,is_active',

            'createdBy:id,public_id,name,email',

            'updatedBy:id,public_id,name,email',
        ]);
    }

    /**
     * Return an approved or rejected product to draft after
     * the seller changes its public pricing information.
     */
    private function resetProductModeration(
        Product $product,
        ?int $userId
    ): void {
        if (
            in_array(
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

        $product->updated_by = $userId;

        $product->save();
    }

    /**
     * Determine whether the authenticated user may manage
     * pricing for the selected seller profile.
     */
    private function canManagePricing(
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
     * Determine whether the product belongs to the seller.
     */
    private function productBelongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    /**
     * Determine whether the variant belongs to the product.
     */
    private function variantBelongsToProduct(
        ProductVariant $variant,
        Product $product
    ): bool {
        return (int) $variant->product_id
            === (int) $product->getKey();
    }

    /**
     * Return a seller pricing permission response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to manage pricing for this seller business.',

            'data' => null,
        ], 403);
    }

    /**
     * Return a safe product-not-found response.
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

    /**
     * Return a safe variant-not-found response.
     */
    private function variantNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'The requested product variant was not found.',

            'data' => null,
        ], 404);
    }

    /**
     * Return a price-not-found response.
     */
    private function priceNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'Pricing has not been created for this product variant.',

            'data' => null,
        ], 404);
    }
}
