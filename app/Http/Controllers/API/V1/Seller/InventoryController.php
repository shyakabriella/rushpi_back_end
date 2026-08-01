<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\AdjustInventoryRequest;
use App\Http\Requests\Seller\UpdateInventorySettingsRequest;
use App\Http\Resources\SellerInventoryResource;
use App\Http\Resources\SellerStockMovementResource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {
    }

    /**
     * Display current inventory for one product variant.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->canManageInventory(
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

        $inventory = InventoryStock::query()
            ->where(
                'product_variant_id',
                $variant->getKey()
            )
            ->first();

        if ($inventory === null) {
            return $this->inventoryNotFoundResponse();
        }

        $this->loadInventoryRelations($inventory);

        return response()->json([
            'success' => true,

            'message' =>
                'Product variant inventory retrieved successfully.',

            'data' =>
                new SellerInventoryResource($inventory),
        ]);
    }

    /**
     * Adjust physical stock for one product variant.
     */
    public function adjust(
        AdjustInventoryRequest $request,
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

        $data = $request->validated();

        $authenticatedUser = $request->user();

        $performedBy = $authenticatedUser instanceof User
            ? $authenticatedUser
            : null;

        try {
            $result = $this->inventoryService->adjustStock(
                variant: $variant,
                sellerProfile: $sellerProfile,
                performedBy: $performedBy,
                movementType: $data['movement_type'],
                quantity: (int) $data['quantity'],
                reason: $data['reason'],
                referenceType: $data['reference_type'] ?? null,
                referenceId: $data['reference_id'] ?? null,
                metadata: $data['metadata'] ?? []
            );

            $inventory = $result['inventory'];
            $movement = $result['movement'];

            $this->loadInventoryRelations($inventory);
            $this->loadMovementRelations($movement);

            return response()->json([
                'success' => true,

                'message' =>
                    'Inventory stock adjusted successfully.',

                'data' => [
                    'inventory' =>
                        new SellerInventoryResource(
                            $inventory
                        ),

                    'movement' =>
                        new SellerStockMovementResource(
                            $movement
                        ),
                ],
            ], 201);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,

                'message' => $exception->getMessage(),

                'data' => null,
            ], 422);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,

                'message' => $exception->getMessage(),

                'data' => null,
            ], 409);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The requested inventory resource was not found.',

                'data' => null,
            ], 404);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to adjust product inventory.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Update reorder level and backorder settings.
     */
    public function updateSettings(
        UpdateInventorySettingsRequest $request,
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

        $data = $request->validated();

        $reorderLevel = array_key_exists(
            'reorder_level',
            $data
        )
            ? (int) $data['reorder_level']
            : null;

        $allowBackorder = array_key_exists(
            'allow_backorder',
            $data
        )
            ? (bool) $data['allow_backorder']
            : null;

        try {
            $inventory = $this->inventoryService
                ->updateSettings(
                    variant: $variant,
                    sellerProfile: $sellerProfile,
                    reorderLevel: $reorderLevel,
                    allowBackorder: $allowBackorder
                );

            $this->loadInventoryRelations($inventory);

            return response()->json([
                'success' => true,

                'message' =>
                    'Inventory settings updated successfully.',

                'data' =>
                    new SellerInventoryResource($inventory),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,

                'message' => $exception->getMessage(),

                'data' => null,
            ], 422);
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,

                'message' => $exception->getMessage(),

                'data' => null,
            ], 409);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The requested inventory resource was not found.',

                'data' => null,
            ], 404);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to update inventory settings.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Display immutable stock movement history.
     */
    public function movements(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant
    ): JsonResponse {
        if (! $this->canManageInventory(
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

        $validated = $request->validate([
            'movement_type' => [
                'nullable',
                Rule::enum(StockMovementType::class),
            ],

            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ], [
            'movement_type.enum' =>
                'The selected stock movement type is invalid.',

            'q.max' =>
                'The stock movement search may not exceed 150 characters.',

            'date_from.date' =>
                'The starting date is invalid.',

            'date_to.date' =>
                'The ending date is invalid.',

            'date_to.after_or_equal' =>
                'The ending date must be equal to or after the starting date.',

            'per_page.integer' =>
                'The page size must be a whole number.',

            'per_page.min' =>
                'The page size must be at least 1.',

            'per_page.max' =>
                'The page size may not exceed 100.',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = StockMovement::query()
            ->where(
                'product_variant_id',
                $variant->getKey()
            )
            ->where(
                'seller_profile_id',
                $sellerProfile->getKey()
            )
            ->with([
                'variant' => function ($variantQuery): void {
                    $variantQuery
                        ->select([
                            'id',
                            'product_id',
                            'public_id',
                            'sku',
                            'barcode',
                            'name',
                            'attributes',
                            'is_default',
                            'is_active',
                        ])
                        ->with([
                            'product:id,public_id,name,slug,status',
                        ]);
                },

                'sellerProfile:id,public_id,legal_business_name,trading_name',

                'performedBy:id,public_id,name,email',
            ]);

        if (! empty($validated['movement_type'])) {
            $query->where(
                'movement_type',
                $validated['movement_type']
            );
        }

        $search = trim(
            (string) ($validated['q'] ?? '')
        );

        if ($search !== '') {
            $query->where(
                function (Builder $movementQuery) use (
                    $search
                ): void {
                    $movementQuery
                        ->where(
                            'public_id',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'reason',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'reference_type',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'reference_id',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate(
                'created_at',
                '<=',
                $validated['date_to']
            );
        }

        $movements = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return SellerStockMovementResource::collection(
            $movements
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Stock movement history retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Load relationships and movement count for inventory.
     */
    private function loadInventoryRelations(
        InventoryStock $inventory
    ): void {
        $inventory->load([
            'variant' => function ($variantQuery): void {
                $variantQuery
                    ->select([
                        'id',
                        'product_id',
                        'public_id',
                        'sku',
                        'barcode',
                        'name',
                        'attributes',
                        'is_default',
                        'is_active',
                    ])
                    ->with([
                        'product:id,public_id,name,slug,status',
                    ]);
            },
        ]);

        $movementCount = StockMovement::query()
            ->where(
                'product_variant_id',
                $inventory->product_variant_id
            )
            ->count();

        $inventory->setAttribute(
            'stock_movements_count',
            $movementCount
        );
    }

    /**
     * Load relationships required by the movement resource.
     */
    private function loadMovementRelations(
        StockMovement $movement
    ): void {
        $movement->load([
            'variant' => function ($variantQuery): void {
                $variantQuery
                    ->select([
                        'id',
                        'product_id',
                        'public_id',
                        'sku',
                        'barcode',
                        'name',
                        'attributes',
                        'is_default',
                        'is_active',
                    ])
                    ->with([
                        'product:id,public_id,name,slug,status',
                    ]);
            },

            'sellerProfile:id,public_id,legal_business_name,trading_name',

            'performedBy:id,public_id,name,email',
        ]);
    }

    /**
     * Determine whether the authenticated user may
     * manage inventory for the seller profile.
     */
    private function canManageInventory(
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
     * Standard inventory permission response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to manage inventory for this seller business.',

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

    /**
     * Safe variant-not-found response.
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
     * Inventory-not-found response.
     */
    private function inventoryNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'Inventory has not been initialized for this product variant.',

            'data' => null,
        ], 404);
    }
}
