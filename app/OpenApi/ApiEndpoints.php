<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class ApiEndpoints
{
    #[OA\Post(
        path: '/register',
        operationId: 'registerCustomer',
        summary: 'Register a customer account',
        description: 'Creates an active customer account and returns a Laravel Sanctum token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'name',
                    'email',
                    'password',
                    'c_password',
                ],
                properties: [
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        maxLength: 255,
                        example: 'Guillaume Karangwa'
                    ),
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        maxLength: 255,
                        example: 'guillaume@example.com'
                    ),
                    new OA\Property(
                        property: 'phone',
                        type: 'string',
                        maxLength: 30,
                        nullable: true,
                        example: '+250788000000'
                    ),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        format: 'password',
                        minLength: 6,
                        example: 'StrongPassword123!'
                    ),
                    new OA\Property(
                        property: 'c_password',
                        type: 'string',
                        format: 'password',
                        example: 'StrongPassword123!'
                    ),
                    new OA\Property(
                        property: 'address',
                        type: 'string',
                        maxLength: 1000,
                        nullable: true,
                        example: 'Kigali, Rwanda'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer registered successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/AuthSuccessResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function register(): void
    {
    }

    #[OA\Post(
        path: '/login',
        operationId: 'loginUser',
        summary: 'Log in to RushPi',
        description: 'Authenticates a user and returns a Laravel Sanctum token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'email',
                    'password',
                ],
                properties: [
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        example: 'guillaume@example.com'
                    ),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        format: 'password',
                        example: 'StrongPassword123!'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User logged in successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/AuthSuccessResponse'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials or inactive account.'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/me',
        operationId: 'getAuthenticatedUser',
        summary: 'Get the authenticated user',
        tags: ['Authentication'],
        security: [
            ['sanctum' => []],
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated user retrieved successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UserProfileResponse'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
        ]
    )]
    public function me(): void
    {
    }

    #[OA\Post(
        path: '/logout',
        operationId: 'logoutUser',
        summary: 'Log out the authenticated user',
        description: 'Deletes the Laravel Sanctum token used by the current request.',
        tags: ['Authentication'],
        security: [
            ['sanctum' => []],
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User logged out successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    example: [
                        'success' => true,
                        'data' => [],
                        'message' => 'User logged out successfully.',
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
        ]
    )]
    public function logout(): void
    {
    }

    #[OA\Get(
        path: '/seller/profiles',
        operationId: 'listSellerProfiles',
        summary: 'List the authenticated user’s seller profiles',
        tags: ['Seller Profiles'],
        security: [
            ['sanctum' => []],
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller profiles retrieved successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Seller profiles retrieved successfully.'
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                ref: '#/components/schemas/SellerProfile'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/UnauthenticatedResponse'
                )
            ),
        ]
    )]
    public function listSellerProfiles(): void
    {
    }

    #[OA\Post(
        path: '/seller/profiles',
        operationId: 'createSellerProfile',
        summary: 'Create a seller business profile',
        description: 'Creates a draft seller profile and assigns the authenticated user as its owner.',
        tags: ['Seller Profiles'],
        security: [
            ['sanctum' => []],
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'legal_business_name' => 'RushPi Electronics Limited',
                    'trading_name' => 'RushPi Electronics',
                    'registration_number' => 'RC123456789',
                    'tax_identification_number' => 'TIN987654321',
                    'business_email' => 'seller@example.com',
                    'business_phone' => '+250788000000',
                    'address' => [
                        'country' => 'Rwanda',
                        'city' => 'Kigali',
                        'address_line_1' => 'KG 7 Avenue',
                    ],
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Seller profile created successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Seller business profile created successfully.'
                        ),
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/SellerProfile'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Seller profile could not be created.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerErrorResponse'
                )
            ),
        ]
    )]
    public function createSellerProfile(): void
    {
    }

    #[OA\Get(
        path: '/seller/profiles/{sellerProfile}',
        operationId: 'showSellerProfile',
        summary: 'Get one seller profile',
        tags: ['Seller Profiles'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerProfile',
                description: 'Seller profile public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: '01JZ8T5M8P7BZW2K4X9D6QYH3A'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller profile retrieved successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'success',
                            type: 'boolean',
                            example: true
                        ),
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'Seller profile retrieved successfully.'
                        ),
                        new OA\Property(
                            property: 'data',
                            ref: '#/components/schemas/SellerProfile'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.'
            ),
            new OA\Response(
                response: 403,
                description: 'The user does not belong to this seller.'
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile not found.'
            ),
        ]
    )]
    public function showSellerProfile(): void
    {
    }

    #[OA\Put(
        path: '/seller/profiles/{sellerProfile}',
        operationId: 'updateSellerProfileWithPut',
        summary: 'Update a seller profile using PUT',
        tags: ['Seller Profiles'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerProfile',
                description: 'Seller profile public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: '01JZ8T5M8P7BZW2K4X9D6QYH3A'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'trading_name' => 'RushPi Technology',
                    'business_email' => 'contact@example.com',
                    'business_phone' => '+250788111111',
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller profile updated successfully.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.'
            ),
            new OA\Response(
                response: 403,
                description: 'Only the seller owner may update the profile.'
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile not found.'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or profile status prevents updating.'
            ),
        ]
    )]
    public function updateSellerProfileWithPut(): void
    {
    }

    #[OA\Patch(
        path: '/seller/profiles/{sellerProfile}',
        operationId: 'updateSellerProfileWithPatch',
        summary: 'Partially update a seller profile',
        tags: ['Seller Profiles'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerProfile',
                description: 'Seller profile public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: '01JZ8T5M8P7BZW2K4X9D6QYH3A'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                example: [
                    'business_phone' => '+250788222222',
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller profile updated successfully.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated.'
            ),
            new OA\Response(
                response: 403,
                description: 'Only the seller owner may update the profile.'
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile not found.'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or profile status prevents updating.'
            ),
        ]
    )]
    public function updateSellerProfileWithPatch(): void
    {
    }
}
