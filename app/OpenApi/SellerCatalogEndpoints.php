<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Seller catalog tags
|--------------------------------------------------------------------------
*/

#[OA\Tag(
    name: 'Seller Products',
    description: 'Approved seller product creation, management and moderation submission.'
)]
#[OA\Tag(
    name: 'Product Variants',
    description: 'Seller product variant creation and management.'
)]
#[OA\Tag(
    name: 'Product Pricing',
    description: 'Seller-only product variant pricing management.'
)]
#[OA\Tag(
    name: 'Product Inventory',
    description: 'Stock management, inventory settings and immutable stock movement history.'
)]
#[OA\Tag(
    name: 'Product Media',
    description: 'Product image upload, ordering and primary-image management.'
)]

/*
|--------------------------------------------------------------------------
| Reusable path parameters
|--------------------------------------------------------------------------
*/

#[OA\Parameter(
    parameter: 'SellerProfilePublicId',
    name: 'sellerProfile',
    description: 'Public identifier of the approved seller profile.',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        example: '01K1SELLER123456789ABCDE'
    )
)]
#[OA\Parameter(
    parameter: 'ProductPublicId',
    name: 'product',
    description: 'Public identifier of the seller product.',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        example: '01K1PRODUCT123456789ABCD'
    )
)]
#[OA\Parameter(
    parameter: 'VariantPublicId',
    name: 'variant',
    description: 'Public identifier of the product variant.',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        example: '01K1VARIANT123456789ABCD'
    )
)]
#[OA\Parameter(
    parameter: 'ProductMediaPublicId',
    name: 'media',
    description: 'Public identifier of the product media record.',
    in: 'path',
    required: true,
    schema: new OA\Schema(
        type: 'string',
        example: '01K1MEDIA123456789ABCDE'
    )
)]

/*
|--------------------------------------------------------------------------
| Shared seller catalog response schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'SellerProductResponse',
    title: 'Seller Product Response',
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
            example: 'Seller product retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerProduct'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductCollectionResponse',
    title: 'Seller Product Collection Response',
    type: 'object',
    required: [
        'data',
        'success',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProduct'
            )
        ),
        new OA\Property(
            property: 'links',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Seller products retrieved successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductVariantResponse',
    title: 'Seller Product Variant Response',
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
            example: 'Product variant retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerProductVariant'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductVariantCollectionResponse',
    title: 'Seller Product Variant Collection Response',
    type: 'object',
    required: [
        'data',
        'success',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProductVariant'
            )
        ),
        new OA\Property(
            property: 'links',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Product variants retrieved successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerVariantPriceResponse',
    title: 'Seller Variant Price Response',
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
            example: 'Product variant pricing retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerProductVariantPrice'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerInventoryResponse',
    title: 'Seller Inventory Response',
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
            example: 'Product variant inventory retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerInventory'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerInventoryAdjustmentResponse',
    title: 'Seller Inventory Adjustment Response',
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
            example: 'Inventory stock adjusted successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'object',
            required: [
                'inventory',
                'movement',
            ],
            properties: [
                new OA\Property(
                    property: 'inventory',
                    ref: '#/components/schemas/SellerInventory'
                ),
                new OA\Property(
                    property: 'movement',
                    ref: '#/components/schemas/SellerStockMovement'
                ),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerStockMovementCollectionResponse',
    title: 'Seller Stock Movement Collection Response',
    type: 'object',
    required: [
        'data',
        'success',
        'message',
    ],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerStockMovement'
            )
        ),
        new OA\Property(
            property: 'links',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'success',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Stock movement history retrieved successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductMediaResponse',
    title: 'Seller Product Media Response',
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
            example: 'Product image uploaded successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/SellerProductMedia'
        ),
    ]
)]
#[OA\Schema(
    schema: 'SellerProductMediaCollectionResponse',
    title: 'Seller Product Media Collection Response',
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
            example: 'Product media retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProductMedia'
            )
        ),
    ]
)]
#[OA\Schema(
    schema: 'CatalogActionResponse',
    title: 'Catalog Action Response',
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
            nullable: true,
            example: null
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Seller product endpoints
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products',
    operationId: 'sellerProductsIndex',
    summary: 'List seller products',
    description: 'Returns paginated products belonging to the selected approved seller profile.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            name: 'q',
            description: 'Search by product name, slug, description, variant SKU or variant name.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 255
            )
        ),
        new OA\Parameter(
            name: 'status',
            description: 'Filter by product moderation status.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'draft',
                    'pending_review',
                    'approved',
                    'rejected',
                    'suspended',
                    'archived',
                ]
            )
        ),
        new OA\Parameter(
            name: 'category',
            description: 'Filter by category public identifier.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string'
            )
        ),
        new OA\Parameter(
            name: 'brand',
            description: 'Filter by brand public identifier.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string'
            )
        ),
        new OA\Parameter(
            name: 'per_page',
            description: 'Number of products returned per page.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'integer',
                minimum: 1,
                maximum: 100,
                default: 15
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Seller products retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductCollectionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Seller is not approved or the user cannot manage this seller.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Invalid product filter.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products',
    operationId: 'sellerProductsStore',
    summary: 'Create product draft',
    description: 'Creates a new draft product for an approved seller.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/StoreSellerProductRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Product draft created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Seller is not approved or permission was denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Product validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}',
    operationId: 'sellerProductsShow',
    summary: 'Show seller product',
    description: 'Returns one product belonging to the selected seller profile.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Seller product retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/seller/profiles/{sellerProfile}/products/{product}',
    operationId: 'sellerProductsUpdatePut',
    summary: 'Replace seller product information',
    description: 'Updates seller product information. Material changes may return an approved product to draft.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateSellerProductRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Product validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}',
    operationId: 'sellerProductsUpdatePatch',
    summary: 'Update selected seller product fields',
    description: 'Updates selected seller product fields.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateSellerProductRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Product validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Delete(
    path: '/seller/profiles/{sellerProfile}/products/{product}',
    operationId: 'sellerProductsArchive',
    summary: 'Archive seller product',
    description: 'Archives the seller product instead of permanently deleting it.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product archived successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/CatalogActionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Product cannot currently be archived.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/submit',
    operationId: 'sellerProductsSubmitForReview',
    summary: 'Submit product for moderation',
    description: 'Submits a complete draft or rejected product for administrator review.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Seller Products',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product submitted for review successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Product status or catalog information changed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Product is incomplete or not ready for moderation.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product variant endpoints
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants',
    operationId: 'sellerProductVariantsIndex',
    summary: 'List product variants',
    description: 'Returns variants belonging to one seller product.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            name: 'q',
            description: 'Search by SKU, barcode or variant name.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string'
            )
        ),
        new OA\Parameter(
            name: 'is_active',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'boolean'
            )
        ),
        new OA\Parameter(
            name: 'is_default',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'boolean'
            )
        ),
        new OA\Parameter(
            name: 'per_page',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'integer',
                minimum: 1,
                maximum: 100,
                default: 15
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product variants retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductVariantCollectionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants',
    operationId: 'sellerProductVariantsStore',
    summary: 'Create product variant',
    description: 'Creates a variant and initializes its inventory record.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/StoreProductVariantRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Product variant created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductVariantResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Variant validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}',
    operationId: 'sellerProductVariantsShow',
    summary: 'Show product variant',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product variant retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductVariantResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}',
    operationId: 'sellerProductVariantsUpdatePut',
    summary: 'Replace product variant information',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateProductVariantRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product variant updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductVariantResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Variant validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}',
    operationId: 'sellerProductVariantsUpdatePatch',
    summary: 'Update selected product variant fields',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateProductVariantRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product variant updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductVariantResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Variant validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Delete(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}',
    operationId: 'sellerProductVariantsDestroy',
    summary: 'Delete product variant',
    description: 'Soft-deletes a variant when it has no stock or stock movement history.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Variants',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product variant deleted successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/CatalogActionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Variant cannot be deleted because it has stock or history.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product variant pricing endpoints
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/price',
    operationId: 'sellerVariantPriceShow',
    summary: 'Show variant pricing',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Pricing',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Variant pricing retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerVariantPriceResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Variant pricing not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/price',
    operationId: 'sellerVariantPriceStore',
    summary: 'Create variant pricing',
    description: 'Creates the single price record allowed for a product variant.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Pricing',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/StoreProductVariantPriceRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Variant pricing created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerVariantPriceResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Variant pricing already exists.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Pricing validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/price',
    operationId: 'sellerVariantPriceUpdatePut',
    summary: 'Replace variant pricing',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Pricing',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateProductVariantPriceRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Variant pricing updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerVariantPriceResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Variant pricing not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Pricing validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/price',
    operationId: 'sellerVariantPriceUpdatePatch',
    summary: 'Update selected variant price fields',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Pricing',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateProductVariantPriceRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Variant pricing updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerVariantPriceResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Variant pricing not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Pricing validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product inventory endpoints
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory',
    operationId: 'sellerVariantInventoryShow',
    summary: 'Show variant inventory',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Inventory',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Variant inventory retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerInventoryResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Inventory not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory/adjust',
    operationId: 'sellerVariantInventoryAdjust',
    summary: 'Adjust variant stock',
    description: 'Adds or removes physical stock and creates an immutable stock movement record.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Inventory',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/AdjustInventoryRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Inventory adjusted successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerInventoryAdjustmentResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Inventory resource not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Stock adjustment conflicts with available or reserved stock.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Inventory adjustment validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory/settings',
    operationId: 'sellerVariantInventorySettingsPut',
    summary: 'Replace inventory settings',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Inventory',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateInventorySettingsRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Inventory settings updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerInventoryResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Inventory not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Inventory setting conflicts with reserved stock.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Inventory settings validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory/settings',
    operationId: 'sellerVariantInventorySettingsPatch',
    summary: 'Update selected inventory settings',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Inventory',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateInventorySettingsRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Inventory settings updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerInventoryResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Inventory not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Inventory setting conflicts with reserved stock.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Inventory settings validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory/movements',
    operationId: 'sellerVariantInventoryMovements',
    summary: 'List stock movement history',
    description: 'Returns paginated immutable stock movement records for one product variant.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Inventory',
    ],
    parameters: [
        new OA\Parameter(
            ref: '#/components/parameters/SellerProfilePublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/ProductPublicId'
        ),
        new OA\Parameter(
            ref: '#/components/parameters/VariantPublicId'
        ),
        new OA\Parameter(
            name: 'movement_type',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string'
            )
        ),
        new OA\Parameter(
            name: 'q',
            description: 'Search movement ID, reason or reference.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 150
            )
        ),
        new OA\Parameter(
            name: 'date_from',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                format: 'date'
            )
        ),
        new OA\Parameter(
            name: 'date_to',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                format: 'date'
            )
        ),
        new OA\Parameter(
            name: 'per_page',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'integer',
                minimum: 1,
                maximum: 100,
                default: 20
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Stock movement history retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerStockMovementCollectionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product or variant not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Movement filter validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product media endpoints
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media',
    operationId: 'sellerProductMediaIndex',
    summary: 'List product media',
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
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product media retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductMediaCollectionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media',
    operationId: 'sellerProductMediaStore',
    summary: 'Upload product image',
    description: 'Uploads a JPG, JPEG, PNG or WebP image with a maximum size of 5 MB.',
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
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                ref: '#/components/schemas/StoreProductMediaRequest'
            )
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Product image uploaded successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductMediaResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Image validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media/reorder',
    operationId: 'sellerProductMediaReorder',
    summary: 'Reorder product images',
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
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/ReorderProductMediaRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product media reordered successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductMediaCollectionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Product cannot currently be edited.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Media ordering validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media/{media}/primary',
    operationId: 'sellerProductMediaSetPrimary',
    summary: 'Set primary product image',
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
            response: 200,
            description: 'Primary product image updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/SellerProductMediaResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product image not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Product cannot currently be edited.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]
#[OA\Delete(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media/{media}',
    operationId: 'sellerProductMediaDestroy',
    summary: 'Delete product image',
    description: 'Soft-deletes the media record and removes the stored image.',
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
            response: 200,
            description: 'Product image deleted successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/CatalogActionResponse'
            )
        ),
        new OA\Response(
            response: 401,
            description: 'Unauthenticated.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/UnauthenticatedResponse'
            )
        ),
        new OA\Response(
            response: 403,
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Product image not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Product cannot currently be edited.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

final class SellerCatalogEndpoints
{
    /*
     * This class only contains OpenAPI endpoint attributes.
     */
}