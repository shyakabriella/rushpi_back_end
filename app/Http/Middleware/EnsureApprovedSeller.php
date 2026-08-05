<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SellerProfileStatus;
use App\Models\SellerProfile;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedSeller
{
    /**
     * Allow selling functions only for approved seller profiles.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        $sellerProfile = $request->route(
            'sellerProfile'
        );

        if (! $sellerProfile instanceof SellerProfile) {
            return new JsonResponse([
                'success' => false,
                'message' =>
                    'A seller profile is required for this action.',
                'data' => null,
            ], 422);
        }

        if (! $user->belongsToSeller($sellerProfile)) {
            return new JsonResponse([
                'success' => false,
                'message' =>
                    'You do not belong to this seller business.',
                'data' => null,
            ], 403);
        }

        if (
            $sellerProfile->status
            !== SellerProfileStatus::APPROVED
        ) {
            return new JsonResponse([
                'success' => false,
                'message' =>
                    'Your seller business must be approved before you can use selling functions.',
                'data' => [
                    'seller_status' =>
                        $sellerProfile->status->value,
                ],
            ], 403);
        }

        return $next($request);
    }
}