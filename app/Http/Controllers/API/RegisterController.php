<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class RegisterController extends BaseController
{
    /**
     * Public account registration.
     *
     * Supported public roles:
     * - seller
     * - dealer
     * - commissioner
     *
     * Administrator accounts must never be created through
     * the public registration endpoint.
     */
    public function register(Request $request): JsonResponse
    {
        /*
         * Backward compatibility:
         * older clients may still send c_password.
         */
        if (
            ! $request->filled('password_confirmation')
            && $request->filled('c_password')
        ) {
            $request->merge([
                'password_confirmation' =>
                    $request->input('c_password'),
            ]);
        }

        /*
         * The current registration page is the seller page,
         * so default the role to seller when it is not supplied.
         */
        $request->merge([
            'role' => $request->input(
                'role',
                'seller'
            ),
        ]);

        $role = (string) $request->input('role');
        $sellerType = (string) $request->input(
            'seller_type',
            ''
        );

        $validator = Validator::make(
            $request->all(),
            [
                /*
                 * Account details
                 */
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email'),
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:30',
                    Rule::unique('users', 'phone'),
                ],

                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                ],

                'role' => [
                    'required',
                    'string',
                    Rule::in([
                        'seller',
                        'dealer',
                        'commissioner',
                    ]),
                ],

                /*
                 * Seller application details
                 */
                'seller_type' => [
                    'required_if:role,seller',
                    'nullable',
                    Rule::in([
                        'shop_owner',
                        'individual_seller',
                    ]),
                ],

                'shop_name' => [
                    'required_if:role,seller',
                    'nullable',
                    'string',
                    'max:255',
                ],

                'business_registration_number' => [
                    Rule::requiredIf(
                        $role === 'seller'
                        && $sellerType === 'shop_owner'
                    ),
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique(
                        'seller_profiles',
                        'registration_number'
                    ),
                ],

                'tax_identification_number' => [
                    'nullable',
                    'string',
                    'max:100',
                    Rule::unique(
                        'seller_profiles',
                        'tax_identification_number'
                    ),
                ],

                'city' => [
                    'required_if:role,seller',
                    'nullable',
                    'string',
                    'max:150',
                ],

                'address' => [
                    'required_if:role,seller',
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'product_categories' => [
                    'required_if:role,seller',
                    'nullable',
                    'string',
                    'max:2000',
                ],

                /*
                 * Legal confirmation
                 */
                'terms_accepted' => [
                    'required',
                    'accepted',
                ],

                'information_confirmed' => [
                    'required',
                    'accepted',
                ],
            ],
            [
                'password.confirmed' =>
                    'The password confirmation does not match.',

                'role.in' =>
                    'The selected registration role is not allowed.',

                'business_registration_number.required' =>
                    'The business registration number is required for a registered shop.',

                'terms_accepted.accepted' =>
                    'You must accept the RushPi terms and conditions.',

                'information_confirmed.accepted' =>
                    'You must confirm that your registration information is correct.',
            ]
        );

        if ($validator->fails()) {
            /*
             * Return "errors" because the Next.js form reads:
             * payload.errors
             */
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Validation Error.',
                    'errors' =>
                        $validator->errors(),
                ],
                422
            );
        }

        $validated = $validator->validated();

        try {
            $result = DB::transaction(
                function () use (
                    $validated
                ): array {
                    $role = (string) $validated['role'];

                    /*
                     * Create the login account.
                     *
                     * The account is active so the applicant can sign in,
                     * upload verification documents and complete onboarding.
                     * Seller selling privileges are controlled separately by
                     * the seller profile approval status.
                     */
                    $user = new User();

                    $user->name =
                        trim(
                            (string) $validated['name']
                        );

                    $user->email =
                        Str::lower(
                            trim(
                                (string) $validated['email']
                            )
                        );

                    $user->phone =
                        $this->nullableString(
                            $validated['phone'] ?? null
                        );

                    $user->password =
                        Hash::make(
                            (string) $validated['password']
                        );

                    /*
                     * Keep the role column synchronized for the current
                     * frontend and existing API responses.
                     */
                    $user->role = $role;

                    $user->status =
                        User::STATUS_ACTIVE;

                    $user->address =
                        $this->nullableString(
                            $validated['address'] ?? null
                        );

                    $user->save();

                    /*
                     * Also synchronize the Spatie role when the User model
                     * uses the HasRoles trait.
                     */
                    if (
                        method_exists(
                            $user,
                            'syncRoles'
                        )
                    ) {
                        $user->syncRoles([
                            $role,
                        ]);
                    }

                    $sellerProfile = null;

                    /*
                     * Create the seller business profile only when
                     * registering as a seller.
                     */
                    if ($role === 'seller') {
                        $shopName = trim(
                            (string) $validated['shop_name']
                        );

                        $sellerType = (string)
                            $validated['seller_type'];

                        $productCategories = trim(
                            (string)
                                $validated['product_categories']
                        );

                        $sellerProfile =
                            SellerProfile::query()->create([
                                'legal_business_name' =>
                                    $shopName,

                                'trading_name' =>
                                    $shopName,

                                'slug' =>
                                    $this->makeSellerSlug(
                                        $shopName
                                    ),

                                'registration_number' =>
                                    $this->nullableString(
                                        $validated[
                                            'business_registration_number'
                                        ] ?? null
                                    ),

                                'tax_identification_number' =>
                                    $this->nullableString(
                                        $validated[
                                            'tax_identification_number'
                                        ] ?? null
                                    ),

                                'business_email' =>
                                    $user->email,

                                'business_phone' =>
                                    $user->phone,

                                'country_code' =>
                                    'RW',

                                /*
                                 * Store the registration description using
                                 * the existing seller profile description.
                                 */
                                'description' =>
                                    sprintf(
                                        "Seller type: %s\nProducts planned for sale: %s",
                                        str_replace(
                                            '_',
                                            ' ',
                                            $sellerType
                                        ),
                                        $productCategories
                                    ),

                                /*
                                 * The seller must complete document
                                 * verification before selling.
                                 */
                                'status' =>
                                    'draft',
                            ]);

                        /*
                         * Make the registered user the owner of the
                         * seller business.
                         */
                        $sellerProfile
                            ->members()
                            ->attach(
                                $user->getKey(),
                                [
                                    'role' =>
                                        'owner',

                                    'status' =>
                                        'active',

                                    'joined_at' =>
                                        now(),
                                ]
                            );

                        /*
                         * Save the default seller business address.
                         */
                        $sellerProfile
                            ->addresses()
                            ->create([
                                'type' =>
                                    'business',

                                'contact_name' =>
                                    $user->name,

                                'contact_phone' =>
                                    $user->phone,

                                'country' =>
                                    'Rwanda',

                                'city' =>
                                    trim(
                                        (string)
                                            $validated['city']
                                    ),

                                'street_address' =>
                                    trim(
                                        (string)
                                            $validated['address']
                                    ),

                                'is_default' =>
                                    true,
                            ]);
                    }

                    return [
                        'user' =>
                            $user,

                        'seller_profile' =>
                            $sellerProfile,
                    ];
                }
            );

            /** @var User $user */
            $user = $result['user'];

            /** @var SellerProfile|null $sellerProfile */
            $sellerProfile =
                $result['seller_profile'];

            /*
             * Return a token so the applicant can continue with
             * profile documents and verification.
             */
            $token = $user
                ->createToken('RushPiToken')
                ->plainTextToken;

            $success = [
                'token' => $token,

                'user' =>
                    $this->userPayload($user),

                'seller_profile' =>
                    $sellerProfile
                        ? [
                            'id' =>
                                $sellerProfile->id,

                            'public_id' =>
                                $sellerProfile->public_id,

                            'legal_business_name' =>
                                $sellerProfile
                                    ->legal_business_name,

                            'trading_name' =>
                                $sellerProfile
                                    ->trading_name,

                            'status' =>
                                $sellerProfile->status,

                            'registration_number' =>
                                $sellerProfile
                                    ->registration_number,

                            'tax_identification_number' =>
                                $sellerProfile
                                    ->tax_identification_number,
                        ]
                        : null,
            ];

            $message =
                $user->role === 'seller'
                    ? 'Seller account created successfully. Complete your verification before publishing products.'
                    : ucfirst(
                        (string) $user->role
                    )
                    .' account created successfully.';

            return $this->sendResponse(
                $success,
                $message
            );
        } catch (Throwable $exception) {
            Log::error(
                'RushPi public registration failed.',
                [
                    'email' =>
                        $request->input('email'),

                    'role' =>
                        $request->input('role'),

                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Registration could not be completed.',

                    'errors' => [
                        'registration' => [
                            app()->isLocal()
                                ? $exception->getMessage()
                                : 'Please try again or contact RushPi support.',
                        ],
                    ],
                ],
                500
            );
        }
    }

    /**
     * Login API.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => [
                    'required',
                    'email',
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Validation Error.',
                    'errors' =>
                        $validator->errors(),
                ],
                422
            );
        }

        $credentials = [
            'email' =>
                Str::lower(
                    trim(
                        (string)
                            $request->input('email')
                    )
                ),

            'password' =>
                (string)
                    $request->input('password'),
        ];

        if (! Auth::attempt($credentials)) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unauthorised.',
                    'errors' => [
                        'email' => [
                            'Invalid email or password.',
                        ],
                    ],
                ],
                401
            );
        }

        /** @var User $user */
        $user = Auth::user();

        if (
            $user->status
            !== User::STATUS_ACTIVE
        ) {
            Auth::logout();

            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Account Disabled.',

                    'errors' => [
                        'account' => [
                            'Your account is not active. Please contact RushPi support.',
                        ],
                    ],
                ],
                403
            );
        }

        $success = [
            'token' =>
                $user
                    ->createToken('RushPiToken')
                    ->plainTextToken,

            'user' =>
                $this->userPayload($user),
        ];

        return $this->sendResponse(
            $success,
            'User logged in successfully.'
        );
    }

    /**
     * Get the authenticated user.
     */
    public function me(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Unauthenticated.',
                ],
                401
            );
        }

        return $this->sendResponse(
            [
                'user' =>
                    $this->userPayload($user),
            ],
            'User profile fetched successfully.'
        );
    }

    /**
<<<<<<< HEAD
     * Logout API.
=======
     * Logout API
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
     */
    public function logout(
        Request $request
    ): JsonResponse {
        $request
            ->user()
            ?->currentAccessToken()
            ?->delete();

        return $this->sendResponse(
            [],
            'User logged out successfully.'
        );
    }

    /**
     * Build the user information returned to the frontend.
     *
     * @return array<string, mixed>
     */
    private function userPayload(
        User $user
    ): array {
        return [
            'id' =>
                $user->id,

            'name' =>
                $user->name,

            'email' =>
                $user->email,

            'phone' =>
                $user->phone,

            'role' =>
                $this->resolveUserRole($user),

            'status' =>
                $user->status,

            'address' =>
                $user->address,
        ];
    }

    /**
     * Resolve the Spatie role first and fall back
     * to the existing users.role column.
     */
    private function resolveUserRole(
        User $user
    ): string {
        if (
            method_exists(
                $user,
                'getRoleNames'
            )
        ) {
            $spatieRole =
                $user
                    ->getRoleNames()
                    ->first();

            if (
                is_string($spatieRole)
                && $spatieRole !== ''
            ) {
                return $spatieRole;
            }
        }

        return (string) $user->role;
    }

    /**
     * Convert empty strings to null.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalized = trim(
            (string) $value
        );

        return $normalized !== ''
            ? $normalized
            : null;
    }

    /**
     * Generate a unique seller profile slug.
     */
    private function makeSellerSlug(
        string $shopName
    ): string {
        $baseSlug =
            Str::slug($shopName);

        if ($baseSlug === '') {
            $baseSlug = 'rushpi-seller';
        }

        do {
            $slug =
                $baseSlug
                .'-'
                .Str::lower(
                    Str::random(8)
                );
        } while (
            SellerProfile::query()
                ->where('slug', $slug)
                ->exists()
        );

        return $slug;
    }
}
