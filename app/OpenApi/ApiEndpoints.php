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
    description: 'Enter only the Sanctum token. Swagger automatically adds the Bearer prefix.'
)]
#[OA\Tag(
    name: 'System',
    description: 'Application health and infrastructure readiness endpoints.'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Customer registration, login, logout and authenticated-user endpoints.'
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
            description: 'Internal user ID.',
            type: 'integer',
            format: 'int64',
            example: 1
        ),
        new OA\Property(
            property: 'name',
            description: 'User full name.',
            type: 'string',
            maxLength: 255,
            example: 'Guillaume Karangwa'
        ),
        new OA\Property(
            property: 'email',
            description: 'User email address.',
            type: 'string',
            format: 'email',
            maxLength: 255,
            example: 'guillaume@example.com'
        ),
        new OA\Property(
            property: 'phone',
            description: 'User telephone number.',
            type: 'string',
            maxLength: 30,
            nullable: true,
            example: '+250788000000'
        ),
        new OA\Property(
            property: 'role',
            description: 'User system role.',
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
            description: 'Current account status.',
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
            description: 'User address.',
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
            description: 'Internal seller profile ID.',
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
            description: 'Registered legal business name.',
            type: 'string',
            example: 'RushPi Electronics Limited'
        ),
        new OA\Property(
            property: 'trading_name',
            description: 'Business trading name.',
            type: 'string',
            nullable: true,
            example: 'RushPi Electronics'
        ),
        new OA\Property(
            property: 'registration_number',
            description: 'Official business registration number.',
            type: 'string',
            nullable: true,
            example: 'RC123456789'
        ),
        new OA\Property(
            property: 'tax_identification_number',
            description: 'Business tax identification number.',
            type: 'string',
            nullable: true,
            example: 'TIN987654321'
        ),
        new OA\Property(
            property: 'business_email',
            description: 'Business email address.',
            type: 'string',
            format: 'email',
            nullable: true,
            example: 'seller@example.com'
        ),
        new OA\Property(
            property: 'business_phone',
            description: 'Business telephone number.',
            type: 'string',
            nullable: true,
            example: '+250788000000'
        ),
        new OA\Property(
            property: 'status',
            description: 'Current seller profile status.',
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
            description: 'Date and time when the seller was approved.',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-01T10:30:00.000000Z'
        ),
        new OA\Property(
            property: 'suspended_at',
            description: 'Date and time when the seller was suspended.',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'suspension_reason',
            description: 'Reason the seller was suspended.',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'addresses',
            description: 'Seller business addresses.',
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'members',
            description: 'Users belonging to the seller business.',
            type: 'array',
            items: new OA\Items(
                type: 'object'
            )
        ),
        new OA\Property(
            property: 'applications',
            description: 'Seller verification applications.',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerApplication'
            )
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerApplication',
    title: 'Seller verification application',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'id',
            description: 'Internal application ID.',
            type: 'integer',
            format: 'int64',
            example: 1
        ),
        new OA\Property(
            property: 'public_id',
            description: 'Public application identifier used in API routes.',
            type: 'string',
            example: '01JZ8V6QP4EJ9XH7M3B2NK8W5C'
        ),
        new OA\Property(
            property: 'version',
            description: 'Application version.',
            type: 'integer',
            minimum: 1,
            example: 1
        ),
        new OA\Property(
            property: 'status',
            description: 'Current verification application status.',
            type: 'string',
            enum: [
                'draft',
                'submitted',
                'under_review',
                'more_information_required',
                'approved',
                'rejected',
                'suspended',
            ],
            example: 'submitted'
        ),
        new OA\Property(
            property: 'information_request',
            description: 'Information requested from the seller.',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'rejection_reason',
            description: 'Reason the application was rejected.',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'submitted_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-01T08:00:00.000000Z'
        ),
        new OA\Property(
            property: 'review_started_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'decided_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'documents_count',
            description: 'Number of submitted verification documents.',
            type: 'integer',
            minimum: 0,
            example: 2
        ),
        new OA\Property(
            property: 'seller_profile',
            ref: '#/components/schemas/SellerProfile'
        ),
        new OA\Property(
            property: 'documents',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerDocument'
            )
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerDocument',
    title: 'Seller verification document',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'id',
            description: 'Internal document ID.',
            type: 'integer',
            format: 'int64',
            example: 1
        ),
        new OA\Property(
            property: 'public_id',
            description: 'Public document identifier used in API routes.',
            type: 'string',
            example: '01JZ8W7KQ9B5C4D8F2M6P1R3XT'
        ),
        new OA\Property(
            property: 'document_type',
            description: 'Verification document type.',
            type: 'string',
            example: 'business_registration_certificate'
        ),
        new OA\Property(
            property: 'original_name',
            description: 'Original uploaded filename.',
            type: 'string',
            example: 'business-registration.pdf'
        ),
        new OA\Property(
            property: 'mime_type',
            description: 'Uploaded file MIME type.',
            type: 'string',
            example: 'application/pdf'
        ),
        new OA\Property(
            property: 'status',
            description: 'Current document verification status.',
            type: 'string',
            enum: [
                'pending_scan',
                'clean',
                'infected',
                'approved',
                'rejected',
            ],
            example: 'clean'
        ),
        new OA\Property(
            property: 'expires_at',
            description: 'Document expiry date.',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'reviewed_at',
            description: 'Date and time the document was reviewed.',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'rejection_reason',
            description: 'Reason the document was rejected.',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            example: '2026-08-01T00:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SuccessResponse',
    title: 'Generic success response',
    type: 'object',
    required: [
        'success',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Operation completed successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'object',
            nullable: true
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
        'errors',
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
            property: 'errors',
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
    schema: 'ForbiddenResponse',
    title: 'Forbidden response',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Only administrators can manage seller verification.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'NotFoundResponse',
    title: 'Not found response',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The requested resource was not found.'
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
            example: 'You are not allowed to perform this action.'
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