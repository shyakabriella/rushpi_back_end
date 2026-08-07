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
use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

class SellerProfileController extends Controller
{
    /**
     * Return all seller businesses that belong
     * to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $sellerProfiles = $request
            ->user()
            ->sellerProfiles()
            ->with([
                'members.user',

                'addresses',

                'applications' => function ($query): void {
                    $query->latest('version');
                },
            ])
            ->latest('seller_profiles.created_at')
            ->get();

        return response()->json([
            'success' => true,

            'message' =>
                'Seller profiles retrieved successfully.',

            'data' => $sellerProfiles
                ->map(
                    fn (SellerProfile $sellerProfile): array =>
                        $this->profilePayload($sellerProfile)
                )
                ->values(),
        ]);
    }

    /**
     * Create a new seller business.
     *
     * The authenticated user automatically becomes
     * the owner of the new seller profile.
     */
    public function store(
        StoreSellerProfileRequest $request
    ): JsonResponse {
        try {
            $sellerProfile = DB::transaction(
                function () use ($request): SellerProfile {
                    $validated = $request->validated();

                    /*
                     * Convert the frontend field names
                     * into seller_profiles database fields.
                     */
                    $profileData =
                        $this->profileData(
                            $validated
                        );

                    /*
                     * Address information is stored inside
                     * the polymorphic addresses table.
                     */
                    $addressData =
                        $this->addressData(
                            $validated
                        );

                    /*
                     * New sellers always start in draft.
                     *
                     * verification_status, seller_status,
                     * ratings, order totals and response
                     * statistics are NEVER accepted from
                     * the seller.
                     */
                    $sellerProfile =
                        new SellerProfile();

                    $sellerProfile->forceFill([
                        ...$profileData,

                        'status' =>
                            SellerProfileStatus::DRAFT,
                    ]);

                    $sellerProfile->save();

                    /*
                     * Save branding images.
                     */
                    $this->storeBrandingImages(
                        $request,
                        $sellerProfile
                    );

                    /*
                     * Add authenticated user as owner.
                     */
                    SellerMember::query()->create([
                        'seller_profile_id' =>
                            $sellerProfile->id,

                        'user_id' =>
                            $request->user()->id,

                        'role' =>
                            SellerMemberRole::OWNER,

                        'status' =>
                            SellerMemberStatus::ACTIVE,

                        'joined_at' =>
                            now(),
                    ]);

                    /*
                     * Initial seller application.
                     */
                    SellerApplication::query()->create([
                        'seller_profile_id' =>
                            $sellerProfile->id,

                        'version' =>
                            1,

                        'status' =>
                            SellerApplicationStatus::DRAFT,
                    ]);

                    /*
                     * Create default business address.
                     */
                    if ($addressData !== null) {
                        $sellerProfile
                            ->addresses()
                            ->create([
                                ...$addressData,

                                'type' =>
                                    AddressType::BUSINESS,

                                'contact_name' =>
                                    $request
                                        ->user()
                                        ->name,

                                'contact_phone' =>
                                    $profileData[
                                        'business_phone'
                                    ]
                                    ?? $request
                                        ->user()
                                        ->phone,

                                'is_default' =>
                                    true,
                            ]);
                    }

                    return $sellerProfile;
                }
            );

            $this->loadSellerRelations(
                $sellerProfile
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Seller business profile created successfully.',

                'data' =>
                    $this->profilePayload(
                        $sellerProfile
                    ),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'The seller business profile could not be created.',

                'data' =>
                    null,
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
        if (
            ! $request
                ->user()
                ->belongsToSeller(
                    $sellerProfile
                )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'You are not allowed to view this seller profile.',

                'data' =>
                    null,
            ], 403);
        }

        $sellerProfile->load([
            'members.user',
            'addresses',
            'documents',

            'applications' =>
                function ($query): void {
                    $query->latest(
                        'version'
                    );
                },

            'applications.reviews',
        ]);

        return response()->json([
            'success' => true,

            'message' =>
                'Seller profile retrieved successfully.',

            'data' =>
                $this->profilePayload(
                    $sellerProfile
                ),
        ]);
    }

    /**
     * Update seller profile.
     *
     * Supports:
     *
     * PATCH /seller/profiles/{sellerProfile}
     *
     * and multipart:
     *
     * POST /seller/profiles/{sellerProfile}
     * _method=PATCH
     *
     * The second form is useful when logo or
     * cover_image is being uploaded.
     */
    public function update(
        UpdateSellerProfileRequest $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        /*
         * Only the seller owner may edit the
         * business profile.
         */
        if (
            ! $request
                ->user()
                ->ownsSeller(
                    $sellerProfile
                )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'Only the seller owner can update this profile.',

                'data' =>
                    null,
            ], 403);
        }

        /*
         * Sellers may edit only while they are
         * completing or verifying the store.
         */
        if (
            ! $this->isEditableStatus(
                $sellerProfile
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This seller profile cannot currently be updated.',

                'data' =>
                    null,
            ], 422);
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $sellerProfile
                ): void {
                    $validated =
                        $request->validated();

                    /*
                     * Map profile-builder names to
                     * seller_profiles database columns.
                     */
                    $profileData =
                        $this->profileData(
                            $validated
                        );

                    /*
                     * Extract location separately.
                     */
                    $addressData =
                        $this->addressData(
                            $validated
                        );

                    /*
                     * Only explicitly permitted profile
                     * fields are updated.
                     *
                     * System-controlled fields such as:
                     *
                     * verification_status
                     * seller_status
                     * average_rating
                     * total_reviews
                     * total_orders
                     * completed_orders
                     * response_rate
                     * response_time
                     *
                     * are intentionally ignored.
                     */
                    if ($profileData !== []) {
                        $sellerProfile
                            ->forceFill(
                                $profileData
                            );

                        $sellerProfile
                            ->save();
                    }

                    /*
                     * Replace logo / cover when supplied.
                     */
                    $this->storeBrandingImages(
                        $request,
                        $sellerProfile
                    );

                    /*
                     * Update business location.
                     */
                    if ($addressData !== null) {
                        $businessAddress =
                            $sellerProfile
                                ->addresses()
                                ->where(
                                    'type',
                                    AddressType::BUSINESS
                                        ->value
                                )
                                ->where(
                                    'is_default',
                                    true
                                )
                                ->first();

                        $payload = [
                            ...$addressData,

                            'type' =>
                                AddressType::BUSINESS,

                            'contact_name' =>
                                $request
                                    ->user()
                                    ->name,

                            'contact_phone' =>
                                $profileData[
                                    'business_phone'
                                ]
                                ?? $sellerProfile
                                    ->business_phone
                                ?? $request
                                    ->user()
                                    ->phone,

                            'is_default' =>
                                true,
                        ];

                        if ($businessAddress) {
                            $businessAddress
                                ->update(
                                    $payload
                                );
                        } else {
                            $sellerProfile
                                ->addresses()
                                ->create(
                                    $payload
                                );
                        }
                    }
                }
            );

            $sellerProfile->refresh();

            $this->loadSellerRelations(
                $sellerProfile
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Seller profile updated successfully.',

                'data' =>
                    $this->profilePayload(
                        $sellerProfile
                    ),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'The seller profile could not be updated.',

                'data' =>
                    null,
            ], 500);
        }
    }

    /**
     * Convert frontend seller-profile fields into
     * seller_profiles database fields.
     *
     * FRONTEND                 DATABASE
     *
     * business_name         -> legal_business_name
     * store_name            -> trading_name
     * phone                 -> business_phone
     * email                 -> business_email
     * tin_number            -> tax_identification_number
     *
     * The controller also accepts the old names so
     * existing API clients remain compatible.
     */
    private function profileData(
        array $validated
    ): array {
        $data = [];

        /*
         * BUSINESS NAME
         */
        if (
            array_key_exists(
                'business_name',
                $validated
            )
        ) {
            $data['legal_business_name'] =
                $this->nullableString(
                    $validated[
                        'business_name'
                    ]
                );
        } elseif (
            array_key_exists(
                'legal_business_name',
                $validated
            )
        ) {
            $data['legal_business_name'] =
                $this->nullableString(
                    $validated[
                        'legal_business_name'
                    ]
                );
        }

        /*
         * STORE NAME
         */
        if (
            array_key_exists(
                'store_name',
                $validated
            )
        ) {
            $data['trading_name'] =
                $this->nullableString(
                    $validated[
                        'store_name'
                    ]
                );
        } elseif (
            array_key_exists(
                'trading_name',
                $validated
            )
        ) {
            $data['trading_name'] =
                $this->nullableString(
                    $validated[
                        'trading_name'
                    ]
                );
        }

        /*
         * DESCRIPTION
         */
        if (
            array_key_exists(
                'description',
                $validated
            )
        ) {
            $data['description'] =
                $this->nullableString(
                    $validated[
                        'description'
                    ]
                );
        }

        /*
         * PHONE
         */
        if (
            array_key_exists(
                'phone',
                $validated
            )
        ) {
            $data['business_phone'] =
                $this->nullableString(
                    $validated[
                        'phone'
                    ]
                );
        } elseif (
            array_key_exists(
                'business_phone',
                $validated
            )
        ) {
            $data['business_phone'] =
                $this->nullableString(
                    $validated[
                        'business_phone'
                    ]
                );
        }

        /*
         * WHATSAPP
         */
        if (
            array_key_exists(
                'whatsapp',
                $validated
            )
        ) {
            $data['whatsapp'] =
                $this->nullableString(
                    $validated[
                        'whatsapp'
                    ]
                );
        }

        /*
         * EMAIL
         */
        if (
            array_key_exists(
                'email',
                $validated
            )
        ) {
            $data['business_email'] =
                $this->nullableString(
                    $validated[
                        'email'
                    ]
                );
        } elseif (
            array_key_exists(
                'business_email',
                $validated
            )
        ) {
            $data['business_email'] =
                $this->nullableString(
                    $validated[
                        'business_email'
                    ]
                );
        }

        /*
         * BUSINESS TYPE
         */
        if (
            array_key_exists(
                'business_type',
                $validated
            )
        ) {
            $data['business_type'] =
                $this->nullableString(
                    $validated[
                        'business_type'
                    ]
                );
        }

        /*
         * REGISTRATION NUMBER
         */
        if (
            array_key_exists(
                'registration_number',
                $validated
            )
        ) {
            $data['registration_number'] =
                $this->nullableString(
                    $validated[
                        'registration_number'
                    ]
                );
        }

        /*
         * TIN
         */
        if (
            array_key_exists(
                'tin_number',
                $validated
            )
        ) {
            $data[
                'tax_identification_number'
            ] =
                $this->nullableString(
                    $validated[
                        'tin_number'
                    ]
                );
        } elseif (
            array_key_exists(
                'tax_identification_number',
                $validated
            )
        ) {
            $data[
                'tax_identification_number'
            ] =
                $this->nullableString(
                    $validated[
                        'tax_identification_number'
                    ]
                );
        }

        /*
         * RETURN POLICY
         */
        if (
            array_key_exists(
                'return_policy',
                $validated
            )
        ) {
            $data['return_policy'] =
                $this->nullableString(
                    $validated[
                        'return_policy'
                    ]
                );
        }

        /*
         * WARRANTY POLICY
         */
        if (
            array_key_exists(
                'warranty_policy',
                $validated
            )
        ) {
            $data['warranty_policy'] =
                $this->nullableString(
                    $validated[
                        'warranty_policy'
                    ]
                );
        }

        return $data;
    }

    /**
     * Extract the seller business address.
     *
     * Supports both:
     *
     * address[country]
     * address[province]
     * address[district]
     * address[sector]
     * address[address_line]
     *
     * and:
     *
     * country
     * province
     * district
     * sector
     * address
     */
    private function addressData(
        array $validated
    ): ?array {
        $nestedAddress =
            $validated['address']
            ?? null;

        $hasNestedAddress =
            is_array(
                $nestedAddress
            );

        $hasFlatAddress =
            array_key_exists(
                'country',
                $validated
            )
            || array_key_exists(
                'province',
                $validated
            )
            || array_key_exists(
                'district',
                $validated
            )
            || array_key_exists(
                'sector',
                $validated
            )
            || (
                array_key_exists(
                    'address',
                    $validated
                )
                &&
                ! is_array(
                    $validated[
                        'address'
                    ]
                )
            );

        if (
            ! $hasNestedAddress
            &&
            ! $hasFlatAddress
        ) {
            return null;
        }

        $data = [];

        /*
         * COUNTRY
         */
        if (
            $hasNestedAddress
            &&
            array_key_exists(
                'country',
                $nestedAddress
            )
        ) {
            $data['country'] =
                $this->nullableString(
                    $nestedAddress[
                        'country'
                    ]
                );
        } elseif (
            array_key_exists(
                'country',
                $validated
            )
        ) {
            $data['country'] =
                $this->nullableString(
                    $validated[
                        'country'
                    ]
                );
        }

        /*
         * PROVINCE
         */
        if (
            $hasNestedAddress
            &&
            array_key_exists(
                'province',
                $nestedAddress
            )
        ) {
            $data['province'] =
                $this->nullableString(
                    $nestedAddress[
                        'province'
                    ]
                );
        } elseif (
            array_key_exists(
                'province',
                $validated
            )
        ) {
            $data['province'] =
                $this->nullableString(
                    $validated[
                        'province'
                    ]
                );
        }

        /*
         * DISTRICT
         */
        if (
            $hasNestedAddress
            &&
            array_key_exists(
                'district',
                $nestedAddress
            )
        ) {
            $data['district'] =
                $this->nullableString(
                    $nestedAddress[
                        'district'
                    ]
                );
        } elseif (
            array_key_exists(
                'district',
                $validated
            )
        ) {
            $data['district'] =
                $this->nullableString(
                    $validated[
                        'district'
                    ]
                );
        }

        /*
         * SECTOR
         */
        if (
            $hasNestedAddress
            &&
            array_key_exists(
                'sector',
                $nestedAddress
            )
        ) {
            $data['sector'] =
                $this->nullableString(
                    $nestedAddress[
                        'sector'
                    ]
                );
        } elseif (
            array_key_exists(
                'sector',
                $validated
            )
        ) {
            $data['sector'] =
                $this->nullableString(
                    $validated[
                        'sector'
                    ]
                );
        }

        /*
         * ADDRESS LINE
         */
        if (
            $hasNestedAddress
            &&
            array_key_exists(
                'address_line',
                $nestedAddress
            )
        ) {
            $data['address_line'] =
                $this->nullableString(
                    $nestedAddress[
                        'address_line'
                    ]
                );
        } elseif (
            array_key_exists(
                'address',
                $validated
            )
            &&
            ! is_array(
                $validated[
                    'address'
                ]
            )
        ) {
            $data['address_line'] =
                $this->nullableString(
                    $validated[
                        'address'
                    ]
                );
        }

        /*
         * Optional address fields.
         */
        foreach (
            [
                'cell',
                'village',
                'postal_code',
            ] as $field
        ) {
            if (
                $hasNestedAddress
                &&
                array_key_exists(
                    $field,
                    $nestedAddress
                )
            ) {
                $data[$field] =
                    $this->nullableString(
                        $nestedAddress[
                            $field
                        ]
                    );
            }
        }

        return $data !== []
            ? $data
            : null;
    }

    /**
     * Store or replace logo and cover image.
     */
    private function storeBrandingImages(
        Request $request,
        SellerProfile $sellerProfile
    ): void {
        if (
            $request->hasFile(
                'logo'
            )
        ) {
            $this->replaceImage(
                $sellerProfile,
                'logo',
                $request->file(
                    'logo'
                )
            );
        }

        if (
            $request->hasFile(
                'cover_image'
            )
        ) {
            $this->replaceImage(
                $sellerProfile,
                'cover_image',
                $request->file(
                    'cover_image'
                )
            );
        }
    }

    /**
     * Replace one stored image.
     */
    private function replaceImage(
        SellerProfile $sellerProfile,
        string $column,
        ?UploadedFile $file
    ): void {
        if (! $file) {
            return;
        }

        $oldPath =
            $sellerProfile
                ->getAttribute(
                    $column
                );

        $directory = sprintf(
            'seller-profiles/%d/branding',
            $sellerProfile->id
        );

        $newPath =
            $file->store(
                $directory,
                'public'
            );

        /*
         * Only delete local storage paths.
         * Never try deleting an external URL.
         */
        if (
            is_string(
                $oldPath
            )
            &&
            $oldPath !== ''
            &&
            ! str_starts_with(
                $oldPath,
                'http://'
            )
            &&
            ! str_starts_with(
                $oldPath,
                'https://'
            )
        ) {
            $cleanOldPath =
                preg_replace(
                    '#^/?storage/#',
                    '',
                    $oldPath
                );

            if (
                is_string(
                    $cleanOldPath
                )
                &&
                Storage::disk(
                    'public'
                )->exists(
                    $cleanOldPath
                )
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $cleanOldPath
                );
            }
        }

        $sellerProfile
            ->forceFill([
                $column =>
                    $newPath,
            ]);

        $sellerProfile->save();
    }

    /**
     * Build the exact API response required by
     * the seller profile builder.
     *
     * This gives the frontend:
     *
     * business_name
     * store_name
     * logo
     * cover_image
     * description
     * phone
     * whatsapp
     * email
     * business_type
     * registration_number
     * tin_number
     * country
     * province
     * district
     * sector
     * address
     * verification_status
     * seller_status
     * average_rating
     * total_reviews
     * total_orders
     * completed_orders
     * response_rate
     * response_time
     * return_policy
     * warranty_policy
     * created_at
     * updated_at
     */
    private function profilePayload(
        SellerProfile $sellerProfile
    ): array {
        $defaultAddress =
            $sellerProfile
                ->addresses
                ->firstWhere(
                    'is_default',
                    true
                )
            ?? $sellerProfile
                ->addresses
                ->first();

        $latestApplication =
            $sellerProfile
                ->applications
                ->first();

        $sellerStatus =
            $this->enumValue(
                $sellerProfile
                    ->getAttribute(
                        'seller_status'
                    )
            )
            ?? $this->enumValue(
                $sellerProfile
                    ->getAttribute(
                        'status'
                    )
            )
            ?? 'draft';

        $verificationStatus =
            $this->enumValue(
                $sellerProfile
                    ->getAttribute(
                        'verification_status'
                    )
            );

        if (
            $verificationStatus === null
        ) {
            $verificationStatus =
                $this->enumValue(
                    $latestApplication
                        ?->status
                );
        }

        if (
            $verificationStatus === null
        ) {
            $verificationStatus =
                $sellerStatus ===
                    'pending_verification'
                    ? 'pending_verification'
                    : 'draft';
        }

        $logo =
            $sellerProfile
                ->getAttribute(
                    'logo'
                );

        $coverImage =
            $sellerProfile
                ->getAttribute(
                    'cover_image'
                );

        return [
            'id' =>
                $sellerProfile->id,

            'public_id' =>
                $sellerProfile
                    ->getAttribute(
                        'public_id'
                    ),

            /*
             * STORE
             */
            'business_name' =>
                $sellerProfile
                    ->getAttribute(
                        'legal_business_name'
                    ),

            'store_name' =>
                $sellerProfile
                    ->getAttribute(
                        'trading_name'
                    ),

            'logo' =>
                $logo,

            'logo_url' =>
                $this->publicImageUrl(
                    $logo
                ),

            'cover_image' =>
                $coverImage,

            'cover_image_url' =>
                $this->publicImageUrl(
                    $coverImage
                ),

            'description' =>
                $sellerProfile
                    ->getAttribute(
                        'description'
                    ),

            /*
             * CONTACT
             */
            'phone' =>
                $sellerProfile
                    ->getAttribute(
                        'business_phone'
                    ),

            'whatsapp' =>
                $sellerProfile
                    ->getAttribute(
                        'whatsapp'
                    ),

            'email' =>
                $sellerProfile
                    ->getAttribute(
                        'business_email'
                    ),

            /*
             * BUSINESS
             */
            'business_type' =>
                $sellerProfile
                    ->getAttribute(
                        'business_type'
                    ),

            'registration_number' =>
                $sellerProfile
                    ->getAttribute(
                        'registration_number'
                    ),

            'tin_number' =>
                $sellerProfile
                    ->getAttribute(
                        'tax_identification_number'
                    ),

            /*
             * LOCATION
             */
            'country' =>
                $defaultAddress
                    ?->country,

            'province' =>
                $defaultAddress
                    ?->province,

            'district' =>
                $defaultAddress
                    ?->district,

            'sector' =>
                $defaultAddress
                    ?->sector,

            'address' =>
                $defaultAddress
                    ?->address_line,

            /*
             * SYSTEM STATUS
             */
            'verification_status' =>
                $verificationStatus,

            'seller_status' =>
                $sellerStatus,

            /*
             * PERFORMANCE
             *
             * Seller cannot write these values.
             */
            'average_rating' =>
                (float) (
                    $sellerProfile
                        ->getAttribute(
                            'average_rating'
                        )
                    ?? 0
                ),

            'total_reviews' =>
                (int) (
                    $sellerProfile
                        ->getAttribute(
                            'total_reviews'
                        )
                    ?? 0
                ),

            'total_orders' =>
                (int) (
                    $sellerProfile
                        ->getAttribute(
                            'total_orders'
                        )
                    ?? 0
                ),

            'completed_orders' =>
                (int) (
                    $sellerProfile
                        ->getAttribute(
                            'completed_orders'
                        )
                    ?? 0
                ),

            'response_rate' =>
                (float) (
                    $sellerProfile
                        ->getAttribute(
                            'response_rate'
                        )
                    ?? 0
                ),

            'response_time' =>
                $sellerProfile
                    ->getAttribute(
                        'response_time'
                    ),

            /*
             * POLICIES
             */
            'return_policy' =>
                $sellerProfile
                    ->getAttribute(
                        'return_policy'
                    ),

            'warranty_policy' =>
                $sellerProfile
                    ->getAttribute(
                        'warranty_policy'
                    ),

            /*
             * TIMESTAMPS
             */
            'created_at' =>
                $sellerProfile
                    ->created_at
                    ?->toISOString(),

            'updated_at' =>
                $sellerProfile
                    ->updated_at
                    ?->toISOString(),

            /*
             * OLD FIELDS KEPT TEMPORARILY
             * FOR BACKWARD COMPATIBILITY.
             */
            'legal_business_name' =>
                $sellerProfile
                    ->getAttribute(
                        'legal_business_name'
                    ),

            'trading_name' =>
                $sellerProfile
                    ->getAttribute(
                        'trading_name'
                    ),

            'business_phone' =>
                $sellerProfile
                    ->getAttribute(
                        'business_phone'
                    ),

            'business_email' =>
                $sellerProfile
                    ->getAttribute(
                        'business_email'
                    ),

            'tax_identification_number' =>
                $sellerProfile
                    ->getAttribute(
                        'tax_identification_number'
                    ),

            'status' =>
                $sellerStatus,

            /*
             * Relations can still be useful elsewhere
             * in the seller dashboard.
             */
            'addresses' =>
                $sellerProfile
                    ->addresses,

            'members' =>
                $sellerProfile
                    ->relationLoaded(
                        'members'
                    )
                    ? $sellerProfile
                        ->members
                    : [],

            'applications' =>
                $sellerProfile
                    ->applications,
        ];
    }

    /**
     * Reload the relations needed by the
     * seller profile frontend.
     */
    private function loadSellerRelations(
        SellerProfile $sellerProfile
    ): void {
        $sellerProfile->load([
            'members.user',

            'addresses',

            'applications' =>
                function ($query): void {
                    $query->latest(
                        'version'
                    );
                },
        ]);
    }

    /**
     * Seller profile is editable only during
     * initial setup / verification.
     */
    private function isEditableStatus(
        SellerProfile $sellerProfile
    ): bool {
        $status =
            $sellerProfile
                ->getAttribute(
                    'status'
                );

        if (
            $status instanceof
            SellerProfileStatus
        ) {
            return in_array(
                $status,
                [
                    SellerProfileStatus::DRAFT,
                    SellerProfileStatus::PENDING_VERIFICATION,
                ],
                true
            );
        }

        $value =
            $this->enumValue(
                $status
            );

        return in_array(
            $value,
            [
                $this->enumValue(
                    SellerProfileStatus::DRAFT
                ),

                $this->enumValue(
                    SellerProfileStatus::PENDING_VERIFICATION
                ),
            ],
            true
        );
    }

    /**
     * Convert backed/unit enums into their
     * JSON-friendly value.
     */
    private function enumValue(
        mixed $value
    ): string|int|null {
        if (
            $value instanceof
            BackedEnum
        ) {
            return $value->value;
        }

        if (
            $value instanceof
            UnitEnum
        ) {
            return $value->name;
        }

        if (
            is_string($value)
            ||
            is_int($value)
        ) {
            return $value;
        }

        return null;
    }

    /**
     * Create public URL for seller branding.
     */
    private function publicImageUrl(
        mixed $path
    ): ?string {
        if (
            ! is_string(
                $path
            )
            ||
            trim($path) === ''
        ) {
            return null;
        }

        if (
            str_starts_with(
                $path,
                'https://'
            )
            ||
            str_starts_with(
                $path,
                'http://'
            )
        ) {
            return $path;
        }

        $cleanPath =
            preg_replace(
                '#^/?storage/#',
                '',
                $path
            );

        if (
            ! is_string(
                $cleanPath
            )
        ) {
            return null;
        }

        return Storage::disk(
            'public'
        )->url(
            $cleanPath
        );
    }

    /**
     * Convert empty strings into null.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (
            ! is_scalar(
                $value
            )
        ) {
            return null;
        }

        $value =
            trim(
                (string) $value
            );

        return $value === ''
            ? null
            : $value;
    }
}