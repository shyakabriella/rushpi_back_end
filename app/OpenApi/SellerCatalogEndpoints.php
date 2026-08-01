<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

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
#[OA\Components(
    parameters: [
        new OA\Parameter(
            parameter: 'SellerProfilePublicId',
            name: 'sellerProfile',
            description: 'Public identifier of the approved seller profile.',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1SELLER123456789ABCDE'
            )
        ),
        new OA\Parameter(
            parameter: 'ProductPublicId',
            name: 'product',
            description: 'Public identifier of the seller product.',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1PRODUCT123456789ABCD'
            )
        ),
        new OA\Parameter(
            parameter: 'VariantPublicId',
            name: 'variant',
            description: 'Public identifier of the product variant.',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1VARIANT123456789ABCD'
            )
        ),
        new OA\Parameter(
            parameter: 'ProductMediaPublicId',
            name: 'media',
            description: 'Public identifier of the product media record.',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1MEDIA123456789ABCDE'
            )
        ),
    ],
    schemas: [
        new OA\Schema(
            schema: 'SellerProductResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'SellerProductCollectionResponse',
            type: 'object',
            required: ['data', 'success', 'message'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        ref: '#/components/schemas/SellerProduct'
                    )
                ),
                new OA\Property(property: 'links', type: 'object', nullable: true),
                new OA\Property(property: 'meta', type: 'object', nullable: true),
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Seller products retrieved successfully.'
                ),
            ]
        ),
        new OA\Schema(
            schema: 'SellerProductVariantResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'SellerProductVariantCollectionResponse',
            type: 'object',
            required: ['data', 'success', 'message'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        ref: '#/components/schemas/SellerProductVariant'
                    )
                ),
                new OA\Property(property: 'links', type: 'object', nullable: true),
                new OA\Property(property: 'meta', type: 'object', nullable: true),
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Product variants retrieved successfully.'
                ),
            ]
        ),
        new OA\Schema(
            schema: 'SellerVariantPriceResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'SellerInventoryResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'SellerInventoryAdjustmentResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Inventory stock adjusted successfully.'
                ),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    required: ['inventory', 'movement'],
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
        ),
        new OA\Schema(
            schema: 'SellerStockMovementCollectionResponse',
            type: 'object',
            required: ['data', 'success', 'message'],
            properties: [
                new OA\Property(
                    property: 'data',
                    type: 'array',
                    items: new OA\Items(
                        ref: '#/components/schemas/SellerStockMovement'
                    )
                ),
                new OA\Property(property: 'links', type: 'object', nullable: true),
                new OA\Property(property: 'meta', type: 'object', nullable: true),
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Stock movement history retrieved successfully.'
                ),
            ]
        ),
        new OA\Schema(
            schema: 'SellerProductMediaResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'SellerProductMediaCollectionResponse',
            type: 'object',
            required: ['success', 'message', 'data'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
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
        ),
        new OA\Schema(
            schema: 'CatalogActionResponse',
            type: 'object',
            required: ['success', 'message'],
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Operation completed successfully.'
                ),
                new OA\Property(property: 'data', nullable: true),
            ]
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Seller products
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products',
    operationId: 'sellerProductsIndex',
    summary: 'List seller products',
    description: 'Returns paginated products belonging to the selected approved seller profile.',
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(
            name: 'q',
            description: 'Search product name, slug, description, SKU or variant name.',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', maxLength: 255)
        ),
        new OA\Parameter(
            name: 'status',
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
            description: 'Category public identifier.',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'brand',
            description: 'Brand public identifier.',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
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
            description: 'Permission denied.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Invalid filters.',
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
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
            description: 'Permission denied.',
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Seller Products'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
            description: 'Product is incomplete.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product variants
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants',
    operationId: 'sellerProductVariantsIndex',
    summary: 'List product variants',
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(
            name: 'q',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'is_active',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'boolean')
        ),
        new OA\Parameter(
            name: 'is_default',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'boolean')
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
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Variants'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
            description: 'Variant has stock or movement history.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product pricing
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/price',
    operationId: 'sellerVariantPriceShow',
    summary: 'Show variant pricing',
    security: [['sanctum' => []]],
    tags: ['Product Pricing'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Pricing'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Pricing'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Pricing'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
| Product inventory
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/variants/{variant}/inventory',
    operationId: 'sellerVariantInventoryShow',
    summary: 'Show variant inventory',
    security: [['sanctum' => []]],
    tags: ['Product Inventory'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Inventory'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Inventory'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
            description: 'Settings conflict with reserved stock.',
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
    security: [['sanctum' => []]],
    tags: ['Product Inventory'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
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
            description: 'Settings conflict with reserved stock.',
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
    security: [['sanctum' => []]],
    tags: ['Product Inventory'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/VariantPublicId'),
        new OA\Parameter(
            name: 'movement_type',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'q',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', maxLength: 150)
        ),
        new OA\Parameter(
            name: 'date_from',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', format: 'date')
        ),
        new OA\Parameter(
            name: 'date_to',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', format: 'date')
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
| Product media
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/media',
    operationId: 'sellerProductMediaIndex',
    summary: 'List product media',
    security: [['sanctum' => []]],
    tags: ['Product Media'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Media'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Media'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Media'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductMediaPublicId'),
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
    security: [['sanctum' => []]],
    tags: ['Product Media'],
    parameters: [
        new OA\Parameter(ref: '#/components/parameters/SellerProfilePublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductPublicId'),
        new OA\Parameter(ref: '#/components/parameters/ProductMediaPublicId'),
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
     * OpenAPI-only attribute container.
     */
}