<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $seller = SellerProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $orders = ProductOrder::query()
            ->whereHas(
                'items',
                fn ($query) => $query->where(
                    'seller_profile_id',
                    $seller->id
                )
            )
            ->with([
                'items' => fn ($query) => $query->where(
                    'seller_profile_id',
                    $seller->id
                ),
                'customer:id,name,email',
            ])
            ->latest('id')
            ->paginate(
                min(
                    max(
                        (int) $request->input('per_page', 20),
                        1
                    ),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(
        Request $request,
        ProductOrder $productOrder
    ): JsonResponse {
        $seller = SellerProfile::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless(
            $productOrder->items()
                ->where('seller_profile_id', $seller->id)
                ->exists(),
            404
        );

        $productOrder->load([
            'items' => fn ($query) => $query->where(
                'seller_profile_id',
                $seller->id
            ),
        ]);

        return response()->json([
            'success' => true,
            'data' => $productOrder,
        ]);
    }
}
