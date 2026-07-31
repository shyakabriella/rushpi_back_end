<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\AddressType;
use App\Enums\SellerApplicationStatus;
use App\Enums\SellerMemberRole;
use App\Enums\SellerMemberStatus;
use App\Enums\SellerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Seller\StoreSellerProfileRequest;
use App\Http\Requests\API\V1\Seller\UpdateSellerProfileRequest;
use App\Models\SellerApplication;
use App\Models\SellerMember;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use function in_array;

class SellerProfileController extends Controller
{
    /**
     * Return seller businesses belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $sellerProfiles = $request->user()
            ->sellerProfiles()
            ->with([
                'addresses',
                'applications' => function ($query): void {
                    $query->latest('version');
                },
            ])
            ->latest('seller_profiles.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Seller profiles retrieved successfully.',
            'data' => $sellerProfiles,
        ]);
    }

    /**
     * Create a seller business and assign the current user as owner.
     */
    public function store(
        StoreSellerProfileRequest $request
    ): JsonResponse {
        try {
            $sellerProfile = DB::transaction(
                function () use ($request): SellerProfile {
                    $validated = $request->validated();

                    $address = $validated['address'] ?? null;

                    unset($validated['address']);

                    $sellerProfile = SellerProfile::query()->create([
                        ...$validated,
                        'status' => SellerProfileStatus::DRAFT,
                    ]);

                    SellerMember::query()->create([
                        'seller_profile_id' => $sellerProfile->id,
                        'user_id' => $request->user()->id,
                        'role' => SellerMemberRole::OWNER,
                        'status' => SellerMemberStatus::ACTIVE,
                        'joined_at' => now(),
                    ]);

                    SellerApplication::query()->create([
                        'seller_profile_id' => $sellerProfile->id,
                        'version' => 1,
                        'status' => SellerApplicationStatus::DRAFT,
                    ]);

                    if (is_array($address)) {
                        $sellerProfile->addresses()->create([
                            ...$address,
                            'type' => AddressType::BUSINESS,
                            'contact_name' => $request->user()->name,
                            'contact_phone' => $request->user()->phone,
                            'is_default' => true,
                        ]);
                    }

                    return $sellerProfile;
                }
            );

            $sellerProfile->load([
                'members.user',
                'addresses',
                'applications',
            ]);

            return response()->json([
                'success' => true,
                'message' =>
                    'Seller business profile created successfully.',
                'data' => $sellerProfile,
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' =>
                    'The seller business profile could not be created.',
                'data' => null,
            ], 500);
        }
    }


    /**
     * Return one seller business.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        if (! $request->user()->belongsToSeller($sellerProfile)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'You are not allowed to view this seller profile.',
                'data' => null,
            ], 403);
        }

        $sellerProfile->load([
            'members.user',
            'addresses',
            'documents',
            'applications.reviews',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Seller profile retrieved successfully.',
            'data' => $sellerProfile,
        ]);
    }

    /**
     * Update a draft seller business.
     */
    public function update(
        UpdateSellerProfileRequest $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        if (! $request->user()->ownsSeller($sellerProfile)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Only the seller owner can update this profile.',
                'data' => null,
            ], 403);
        }

        if (
            ! in_array($sellerProfile->status, [
                SellerProfileStatus::DRAFT,
                SellerProfileStatus::PENDING_VERIFICATION,
            ], true)
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This seller profile cannot currently be updated.',
                'data' => null,
            ], 422);
        }

        $sellerProfile->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Seller profile updated successfully.',
            'data' => $sellerProfile->fresh([
                'members.user',
                'addresses',
                'applications',
            ]),
        ]);
    }
}