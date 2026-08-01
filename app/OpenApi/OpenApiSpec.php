<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'RushPi Marketplace API',
    description: 'Backend API contract for the RushPi verified electronics marketplace.',
    contact: new OA\Contact(
        name: 'RushPi Backend Team'
    )
)]
#[OA\Server(
    url: '/api',
    description: 'RushPi production API'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Bearer token',
    description: 'Enter only the Sanctum token. Swagger adds the Bearer prefix automatically.'
)]
#[OA\Tag(
    name: 'System',
    description: 'Application health and infrastructure readiness endpoints.'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Registration, login, logout and authenticated-user endpoints.'
)]
#[OA\Tag(
    name: 'Seller Profiles',
    description: 'Seller business profile creation and management endpoints.'
)]
#[OA\Tag(
    name: 'Seller Verification Admin',
    description: 'Administrator endpoints for reviewing and deciding seller verification applications.'
)]
#[OA\Schema(
    schema: 'User',
    title: 'User',
    type: 'object',
    required: [
        'id',
        'name',
        'email',
        'role',
        'status',
    ],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            format: 'int64',
            example: 1
        ),
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
            property: 'role',
            type: 'string',
            enum: [
                'superadmin',
                'admin',
                'customer',
                'seller_owner',
                'seller_staff',
                'delivery_agent',
            ],
            example: 'customer'
        ),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: [
                'active',
                'inactive',
                'blocked',
            ],
            example: 'active'
        ),
        new OA\Property(
            property: 'address',
            type: 'string',
            maxLength: 1000,
            nullable: true,
            example: 'Kigali, Rwanda'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AuthData',
    title: 'Authentication data',
    type: 'object',
    required: [
        'token',
        'user',
    ],
    properties: [
        new OA\Property(
            property: 'token',
            description: 'Laravel Sanctum access token.',
            type: 'string',
            example: '1|long-sanctum-access-token'
        ),
        new OA\Property(
            property: 'user',
            ref: '#/components/schemas/User'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AuthSuccessResponse',
    title: 'Authentication success response',
    type: 'object',
    required: [
        'success',
        'data',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/AuthData'
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'User logged in successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'UserProfileResponse',
    title: 'Authenticated user response',
    type: 'object',
    required: [
        'success',
        'data',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'data',
            type: 'object',
            required: [
                'user',
            ],
            properties: [
                new OA\Property(
                    property: 'user',
                    ref: '#/components/schemas/User'
                ),
            ]
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'User profile fetched successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProfile',
    title: 'Seller profile',
    type: 'object',
    required: [
        'public_id',
        'status',
    ],
    properties: [
        new OA\Property(
            property: 'id',
            type: 'integer',
            format: 'int64',
            example: 1
        ),
        new OA\Property(
            property: 'public_id',
            description: 'Public seller profile identifier used in API routes.',
            type: 'string',
            example: '01JZ8T5M8P7BZW2K4X9D6QYH3A'
        ),
        new OA\Property(
            property: 'legal_business_name',
            type: 'string',
            example: 'RushPi Electronics Limited'
        ),
        new OA\Property(
            property: 'trading_name',
            type: 'string',
            nullable: true,
            example: 'RushPi Electronics'
        ),
        new OA\Property(
            property: 'registration_number',
            type: 'string',
            nullable: true,
            example: 'RC123456789'
        ),
        new OA\Property(
            property: 'tax_identification_number',
            type: 'string',
            nullable: true,
            example: 'TIN987654321'
        ),
        new OA\Property(
            property: 'business_email',
            type: 'string',
            format: 'email',
            nullable: true,
            example: 'seller@example.com'
        ),
        new OA\Property(
            property: 'business_phone',
            type: 'string',
            nullable: true,
            example: '+250788000000'
        ),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: [
                'draft',
                'pending_verification',
                'approved',
                'rejected',
                'suspended',
            ],
            example: 'draft'
        ),
        new OA\Property(
            property: 'approved_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'suspended_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'suspension_reason',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'addresses',
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'members',
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'applications',
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-01T00:00:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-01T00:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    title: 'Validation error response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
    ],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Validation Error.'
        ),
        new OA\Property(
            property: 'data',
            type: 'object',
            example: [
                'email' => [
                    'The email field is required.',
                ],
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedResponse',
    title: 'Unauthenticated response',
    type: 'object',
    required: [
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Unauthenticated.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerErrorResponse',
    title: 'Seller operation error response',
    type: 'object',
    required: [
        'success',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The seller business profile could not be created.'
        ),
        new OA\Property(
            property: 'data',
            nullable: true,
            example: null
        ),
    ]
)]
final class OpenApiSpec
{
}
