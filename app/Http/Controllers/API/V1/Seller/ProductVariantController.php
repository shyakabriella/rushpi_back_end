<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductVariantRequest;
use App\Http\Requests\Seller\UpdateProductVariantRequest;
use App\Http\Resources\SellerProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductVariantController extends Controller
{
    /**
     * Display variants belonging to one seller product.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageVariants($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $query = ProductVariant::query()
            ->where('product_id', $product->getKey())
            ->with([
                'product',
                'price',
                'inventoryStock',
                'media',
            ])
            ->withCount([
                'media',
                'stockMovements',
            ]);

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $query->search($search);
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        if ($request->has('is_default')) {
            $query->where(
                'is_default',
                $request->boolean('is_default')
            );
        }

        $variants = $query
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return SellerProductVariantResource::collection($variants)
            ->additional([
                'success' => true,
                'message' =>
                    'Product variants retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Create a product variant.
     */
    public function store(
        StoreProductVariantRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->productBelongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        try {
            $variant = DB::transaction(
                function () use ($request, $product): ProductVariant {
                    $data = $request->validated();

                    $hasExistingVariants = $product
                        ->variants()
                        ->exists();

                    /*
                     * The first variant must always be the default.
                     */
                    if (! $hasExistingVariants) {
                        $data['is_default'] = true;
                    }

                    /*
                     * When this variant becomes default,
                     * remove default status from all others.
                     */
                    if (($data['is_default'] ?? false) === true) {
                        $product->variants()->update([
                            'is_default' => false,
                        ]);
                    }

                    $variant = $product
                        ->variants()
                        ->create($data);

                    /*
                     * Every variant starts with an inventory record.
                     * Stock changes will later use InventoryService.
                     */
                    $variant->inventoryStock()->create([
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'reorder_level' => 0,
                        'allow_backorder' => false,
                    ]);

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request->user()?->getKey()
                    );

                    return $variant;
                }
            );

            $this->loadVariantRelations($variant);

            return response()->json([
                'success' => true,
                'message' =>
                    'Product variant created successfully.',
                'data' =>
                    new SellerProductVariantResource($variant),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to create the product variant.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display one product variant.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->canManageVariants($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct($variant, $product)) {
            return $this->variantNotFoundResponse();
        }

        $this->loadVariantRelations($variant);

        return response()->json([
            'success' => true,
            'message' =>
                'Product variant retrieved successfully.',
            'data' =>
                new SellerProductVariantResource($variant),
        ]);
    }

    /**
     * Update a product variant.
     */
    public function update(
        UpdateProductVariantRequest $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->productBelongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct($variant, $product)) {
            return $this->variantNotFoundResponse();
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product,
                    $variant
                ): void {
                    $data = $request->validated();

                    $makingDefault =
                        ($data['is_default'] ?? false) === true;

                    if ($makingDefault) {
                        $product->variants()
                            ->whereKeyNot($variant->getKey())
                            ->update([
                                'is_default' => false,
                            ]);
                    }

                    $variant->fill($data);
                    $variantChanged = $variant->isDirty();

                    $variant->save();

                    /*
                     * If the default variant was disabled or manually
                     * unset, select another active variant automatically.
                     */
                    if (
                        ! $variant->is_default
                        || ! $variant->is_active
                    ) {
                        $this->ensureProductHasDefaultVariant(
                            $product
                        );
                    }

                    if ($variantChanged) {
                        $this->resetProductModeration(
                            product: $product,
                            userId: $request->user()?->getKey()
                        );
                    }
                }
            );

            $variant->refresh();

            $this->loadVariantRelations($variant);

            return response()->json([
                'success' => true,
                'message' =>
                    'Product variant updated successfully.',
                'data' =>
                    new SellerProductVariantResource($variant),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to update the product variant.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Soft-delete a product variant.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->canManageVariants($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller($product, $sellerProfile)) {
            return $this->productNotFoundResponse();
        }

        if (! $this->variantBelongsToProduct($variant, $product)) {
            return $this->variantNotFoundResponse();
        }

        if (! $product->canBeEditedBySeller()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This product cannot currently be edited.',
                'data' => null,
            ], 409);
        }

        if ($variant->stockMovements()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'A variant with stock movement history cannot be deleted. Deactivate it instead.',
                'data' => null,
            ], 409);
        }

        $inventory = $variant->inventoryStock;

        if (
            $inventory !== null
            && (
                $inventory->quantity_on_hand > 0
                || $inventory->quantity_reserved > 0
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'A variant with available or reserved stock cannot be deleted.',
                'data' => null,
            ], 409);
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product,
                    $variant
                ): void {
                    $wasDefault = $variant->is_default;

                    $variant->delete();

                    if ($wasDefault) {
                        $this->ensureProductHasDefaultVariant(
                            $product
                        );
                    }

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request->user()?->getKey()
                    );
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Product variant deleted successfully.',
                'data' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to delete the product variant.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Load relationships required by the seller variant resource.
     */
    private function loadVariantRelations(
        ProductVariant $variant
    ): void {
        $variant->load([
            'product',
            'price',
            'inventoryStock',
            'media',
        ]);

        $variant->loadCount([
            'media',
            'stockMovements',
        ]);
    }

    /**
     * Ensure one active variant is marked as default.
     */
    private function ensureProductHasDefaultVariant(
        Product $product
    ): void {
        $hasDefaultVariant = $product
            ->variants()
            ->where('is_active', true)
            ->where('is_default', true)
            ->exists();

        if ($hasDefaultVariant) {
            return;
        }

        $nextDefaultVariant = $product
            ->variants()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($nextDefaultVariant !== null) {
            $nextDefaultVariant->update([
                'is_default' => true,
            ]);
        }
    }

    /**
     * Return an approved or rejected product to draft when
     * its catalog structure changes.
     */
    private function resetProductModeration(
        Product $product,
        ?int $userId
    ): void {
        if (
            ! in_array(
                $product->status,
                [
                    ProductStatus::APPROVED,
                    ProductStatus::REJECTED,
                ],
                true
            )
        ) {
            $product->updated_by = $userId;
            $product->save();

            return;
        }

        $product->status = ProductStatus::DRAFT;

        $product->submitted_at = null;
        $product->approved_at = null;
        $product->approved_by = null;
        $product->rejected_at = null;
        $product->rejection_reason = null;
        $product->suspended_at = null;
        $product->suspension_reason = null;
        $product->updated_by = $userId;

        $product->save();
    }

    /**
     * Determine whether the current user may manage variants.
     */
    private function canManageVariants(
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
     * Determine whether a product belongs to the seller.
     */
    private function productBelongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    /**
     * Determine whether a variant belongs to the product.
     */
    private function variantBelongsToProduct(
        ProductVariant $variant,
        Product $product
    ): bool {
        return (int) $variant->product_id
            === (int) $product->getKey();
    }

    /**
     * Return a standard forbidden response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'You are not allowed to manage variants for this seller business.',
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
}
