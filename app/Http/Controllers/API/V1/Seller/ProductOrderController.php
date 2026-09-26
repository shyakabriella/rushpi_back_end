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
    /**
     * Statuses where the seller may see the complete
     * customer email address and phone number.
     *
     * @var array<int, string>
     */
    private const CONTACT_VISIBLE_STATUSES = [
        'confirmed',
        'processing',
        'shipped',
        'delivered',
    ];

    public function index(Request $request): JsonResponse
    {
        $seller = $this->currentSeller($request);

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
                        (int) $request->input(
                            'per_page',
                            20
                        ),
                        1
                    ),
                    100
                )
            );

        $orders->getCollection()->transform(
            fn (ProductOrder $order) =>
                $this->protectCustomerContact($order)
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
        $seller = $this->currentSeller($request);

        abort_unless(
            $productOrder
                ->items()
                ->where(
                    'seller_profile_id',
                    $seller->id
                )
                ->exists(),
            404,
            'Order not found.'
        );

        $productOrder->load([
            'items' => fn ($query) => $query->where(
                'seller_profile_id',
                $seller->id
            ),
            'customer:id,name,email',
        ]);

        $this->protectCustomerContact(
            $productOrder
        );

        return response()->json([
            'success' => true,
            'data' => $productOrder,
        ]);
    }

    private function currentSeller(
        Request $request
    ): SellerProfile {
        $seller = $request
            ->user()
            ->sellerProfiles()
            ->first();

        abort_if(
            $seller === null,
            403,
            'Your account is not connected to a seller profile.'
        );

        return $seller;
    }

    private function protectCustomerContact(
        ProductOrder $order
    ): ProductOrder {
        $status = strtolower(
            (string) (
                $order->getRawOriginal('status')
                ?? $order->status
                ?? ''
            )
        );

        $contactVisible = in_array(
            $status,
            self::CONTACT_VISIBLE_STATUSES,
            true
        );

        $order->setAttribute(
            'contact_revealed',
            $contactVisible
        );

        if ($contactVisible) {
            return $order;
        }

        $order->setAttribute(
            'email',
            $this->maskEmail($order->email)
        );

        $order->setAttribute(
            'phone',
            $this->maskPhone($order->phone)
        );

        if ($order->relationLoaded('customer')) {
            $customer = $order->customer;

            if ($customer !== null) {
                $customer->setAttribute(
                    'email',
                    $this->maskEmail(
                        $customer->email
                    )
                );
            }
        }

        return $order;
    }

    private function maskEmail(
        ?string $email
    ): ?string {
        if (
            $email === null ||
            ! str_contains($email, '@')
        ) {
            return $email === null
                ? null
                : '********';
        }

        [$username, $domain] = explode(
            '@',
            $email,
            2
        );

        $firstCharacter = $username !== ''
            ? mb_substr($username, 0, 1)
            : '';

        return $firstCharacter
            .'******@'
            .$domain;
    }

    private function maskPhone(
        ?string $phone
    ): ?string {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $visibleDigits = mb_substr($phone, -3);

        return str_repeat(
            '*',
            max(mb_strlen($phone) - 3, 5)
        ).$visibleDigits;
    }
}
