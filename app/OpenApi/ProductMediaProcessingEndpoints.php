<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Product-media processing schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductMediaVariantSummary',
    title: 'Product media variant summary',
    type: 'object',
    nullable: true,
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1VARIANT123456789ABCD'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            nullable: true,
            example: 'Black / 256 GB'
        ),
        new OA\Property(
            property: 'sku',
            type: 'string',
            nullable: true,
            example: 'PHONE-BLK-256'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaProcessingState',
    title: 'Product media processing state',
    type: 'object',
    required: [
        'status',
        'label',
        'attempts',
        'is_pending',
        'is_processing',
        'is_completed',
        'is_failed',
        'is_finished',
        'can_retry',
        'is_ready_for_public_use',
    ],
    properties: [
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: [
                'pending',
                'processing',
                'completed',
                'failed',
            ],
            example: 'pending'
        ),
        new OA\Property(
            property: 'label',
            type: 'string',
            example: 'Pending'
        ),
        new OA\Property(
            property: 'attempts',
            type: 'integer',
            minimum: 0,
            example: 1
        ),
        new OA\Property(
            property: 'error',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'is_pending',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_processing',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'is_completed',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'is_failed',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'is_finished',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'can_retry',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_ready_for_public_use',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'started_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'last_attempt_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T14:00:00.000000Z'
        ),
        new OA\Property(
            property: 'processed_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'failed_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: null
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaOriginalFile',
    title: 'Original product image information',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'disk',
            description: 'Seller-visible storage disk identifier.',
            type: 'string',
            example: 'public'
        ),
        new OA\Property(
            property: 'path',
            description: 'Seller-visible original image storage path.',
            type: 'string',
            nullable: true,
            example: 'product-media/01K1PRODUCT/originals/01K1MEDIA.jpg'
        ),
        new OA\Property(
            property: 'url',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/01K1PRODUCT/originals/01K1MEDIA.jpg'
        ),
        new OA\Property(
            property: 'width',
            type: 'integer',
            minimum: 1,
            nullable: true,
            example: 1600
        ),
        new OA\Property(
            property: 'height',
            type: 'integer',
            minimum: 1,
            nullable: true,
            example: 1200
        ),
        new OA\Property(
            property: 'checksum_sha256',
            type: 'string',
            minLength: 64,
            maxLength: 64,
            nullable: true,
            example: '4e86f3d06a8dd8f52da81df9f74a7549b9ad0e9337bdd08dca0382a3f9d8b712'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaRendition',
    title: 'Generated product image rendition',
    type: 'object',
    nullable: true,
    required: [
        'name',
        'disk',
        'path',
        'url',
        'width',
        'height',
        'size_bytes',
        'mime_type',
    ],
    properties: [
        new OA\Property(
            property: 'name',
            type: 'string',
            enum: [
                'thumbnail',
                'card',
                'detail',
                'original_optimized',
            ],
            example: 'card'
        ),
        new OA\Property(
            property: 'disk',
            type: 'string',
            example: 'public'
        ),
        new OA\Property(
            property: 'path',
            type: 'string',
            example: 'product-media/1/01K1MEDIA/checksum/card.webp'
        ),
        new OA\Property(
            property: 'url',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/1/01K1MEDIA/checksum/card.webp'
        ),
        new OA\Property(
            property: 'width',
            type: 'integer',
            minimum: 1,
            nullable: true,
            example: 600
        ),
        new OA\Property(
            property: 'height',
            type: 'integer',
            minimum: 1,
            nullable: true,
            example: 600
        ),
        new OA\Property(
            property: 'size_bytes',
            type: 'integer',
            format: 'int64',
            minimum: 0,
            nullable: true,
            example: 48125
        ),
        new OA\Property(
            property: 'mime_type',
            type: 'string',
            enum: [
                'image/webp',
            ],
            nullable: true,
            example: 'image/webp'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaRenditionSet',
    title: 'Product media rendition set',
    type: 'object',
    required: [
        'thumbnail',
        'card',
        'detail',
        'original_optimized',
    ],
    properties: [
        new OA\Property(
            property: 'thumbnail',
            ref: '#/components/schemas/ProductMediaRendition'
        ),
        new OA\Property(
            property: 'card',
            ref: '#/components/schemas/ProductMediaRendition'
        ),
        new OA\Property(
            property: 'detail',
            ref: '#/components/schemas/ProductMediaRendition'
        ),
        new OA\Property(
            property: 'original_optimized',
            ref: '#/components/schemas/ProductMediaRendition'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaUrlSet',
    title: 'Product media URL set',
    type: 'object',
    required: [
        'thumbnail',
        'card',
        'detail',
        'original_optimized',
        'original',
    ],
    properties: [
        new OA\Property(
            property: 'thumbnail',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/1/01K1MEDIA/checksum/thumbnail.webp'
        ),
        new OA\Property(
            property: 'card',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/1/01K1MEDIA/checksum/card.webp'
        ),
        new OA\Property(
            property: 'detail',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/1/01K1MEDIA/checksum/detail.webp'
        ),
        new OA\Property(
            property: 'original_optimized',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/1/01K1MEDIA/checksum/original_optimized.webp'
        ),
        new OA\Property(
            property: 'original',
            description: 'Original URL is visible only in seller-facing responses.',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://api.example.com/storage/product-media/01K1PRODUCT/originals/01K1MEDIA.jpg'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ProductMediaCapabilities',
    title: 'Product media capabilities',
    type: 'object',
    required: [
        'supports_processing',
        'has_optimized_rendition',
        'can_be_primary',
        'can_retry_processing',
    ],
    properties: [
        new OA\Property(
            property: 'supports_processing',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'has_optimized_rendition',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'can_be_primary',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'can_retry_processing',
            type: 'boolean',
            example: true
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProcessedProductMedia',
    title: 'Seller processed product media',
    type: 'object',
    required: [
        'public_id',
        'media_type',
        'is_primary',
        'sort_order',
        'processing',
        'original',
        'renditions',
        'urls',
        'capabilities',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1MEDIA123456789ABCDE'
        ),
        new OA\Property(
            property: 'media_type',
            type: 'string',
            enum: [
                'image',
            ],
            example: 'image'
        ),
        new OA\Property(
            property: 'variant',
            ref: '#/components/schemas/ProductMediaVariantSummary'
        ),
        new OA\Property(
            property: 'original_name',
            type: 'string',
            nullable: true,
            example: 'smartphone-front.jpg'
        ),
        new OA\Property(
            property: 'mime_type',
            type: 'string',
            nullable: true,
            example: 'image/jpeg'
        ),
        new OA\Property(
            property: 'size_bytes',
            type: 'integer',
            format: 'int64',
            nullable: true,
            example: 925300
        ),
        new OA\Property(
            property: 'alt_text',
            type: 'string',
            nullable: true,
            example: 'Front view of a black smartphone'
        ),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'is_primary',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 0
        ),
        new OA\Property(
            property: 'processing',
            ref: '#/components/schemas/ProductMediaProcessingState'
        ),
        new OA\Property(
            property: 'original',
            ref: '#/components/schemas/ProductMediaOriginalFile'
        ),
        new OA\Property(
            property: 'renditions',
            ref: '#/components/schemas/ProductMediaRenditionSet'
        ),
        new OA\Property(
            property: 'urls',
            ref: '#/components/schemas/ProductMediaUrlSet'
        ),
        new OA\Property(
            property: 'preferred_url',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'capabilities',
            ref: '#/components/schemas/ProductMediaCapabilities'
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T13:50:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T14:00:00.000000Z'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductMediaProcessingRetryResponse',
    title: 'Seller product media processing retry response',
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
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Product image processing has been queued for retry.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerProcessedProductMedia'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product-media processing endpoint
|--------------------------------------------------------------------------
*/

#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media/{media}/retry-processing',
    operationId: 'sellerProductMediaRetryProcessing',
    summary: 'Retry failed product image processing',
    description: 'Returns failed, pending, incomplete or stale product media to the pending state and queues optimized WebP rendition generation. Successfully completed media and media currently being processed cannot be queued again.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Media',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductMediaPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 202,
            description: 'Product image processing retry queued successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductMediaProcessingRetryResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Authentication is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'The authenticated user cannot manage the selected seller profile.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile, product or product media record not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'The media is already completed or is currently being processed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'The original file is missing, inaccessible or unsupported.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
final class ProductMediaProcessingEndpoints
{
    /*
     * OpenAPI-only attribute container.
     */
}
