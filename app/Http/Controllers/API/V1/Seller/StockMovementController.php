<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerStockMovementResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    /**
     * List immutable stock movement history for one product variant.
     */
    public function index(
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
        ]);

        $perPage = (int) (
            $validated['per_page']
            ?? 20
        );

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
                'variant' => function (
                    Builder $variantQuery
                ): void {
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

                /*
                 * RushPi users currently do not have a public_id column.
                 */
                'performedBy:id,name,email',
            ]);

        if (! empty(
            $validated['movement_type']
        )) {
            $query->where(
                'movement_type',
                $validated['movement_type']
            );
        }

        $search = trim(
            (string) (
                $validated['q']
                ?? ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $movementQuery
                ) use (
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

        if (! empty(
            $validated['date_from']
        )) {
            $query->whereDate(
                'created_at',
                '>=',
                $validated['date_from']
            );
        }

        if (! empty(
            $validated['date_to']
        )) {
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
     * Show one immutable stock movement.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductVariant $variant,
        StockMovement $movement
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

        if (
            (int) $movement->product_variant_id
                !== (int) $variant->getKey()
            || (int) $movement->seller_profile_id
                !== (int) $sellerProfile->getKey()
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'The requested stock movement was not found.',

                'data' => null,
            ], 404);
        }

        $movement->load([
            'variant' => function (
                Builder $variantQuery
            ): void {
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

            'performedBy:id,name,email',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Stock movement retrieved successfully.',

            'data' =>
                new SellerStockMovementResource(
                    $movement
                ),
        ]);
    }

    /**
     * Determine whether the authenticated user may
     * view inventory movements for the seller profile.
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
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'status',
                'active'
            )
            ->whereIn(
                'role',
                [
                    'owner',
                    'manager',
                ]
            )
            ->exists();
    }

    private function productBelongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    private function variantBelongsToProduct(
        ProductVariant $variant,
        Product $product
    ): bool {
        return (int) $variant->product_id
            === (int) $product->getKey();
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to view stock movements for this seller business.',

            'data' => null,
        ], 403);
    }

    private function productNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'The requested product was not found.',

            'data' => null,
        ], 404);
    }

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