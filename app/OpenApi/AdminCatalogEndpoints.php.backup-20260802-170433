<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Catalog Administration',
    description: 'Administrator category and brand management.'
)]
#[OA\Tag(
    name: 'Product Moderation',
    description: 'Administrator product review, approval, rejection and suspension.'
)]

#[OA\Schema(
    schema: 'AdminCategoryResponse',
    type: 'object',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Category retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/CatalogCategory'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminCategoryCollectionResponse',
    type: 'object',
    required: ['data', 'success', 'message'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/CatalogCategory'
            )
        ),
        new OA\Property(property: 'links', type: 'object', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Categories retrieved successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminBrandResponse',
    type: 'object',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Brand retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/CatalogBrand'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminBrandCollectionResponse',
    type: 'object',
    required: ['data', 'success', 'message'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/CatalogBrand'
            )
        ),
        new OA\Property(property: 'links', type: 'object', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Brands retrieved successfully.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminProductResponse',
    type: 'object',
    required: ['success', 'message', 'data'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Product moderation details retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/AdminProduct'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminProductCollectionResponse',
    type: 'object',
    required: ['data', 'success', 'message'],
    properties: [
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/AdminProduct'
            )
        ),
        new OA\Property(property: 'links', type: 'object', nullable: true),
        new OA\Property(property: 'meta', type: 'object', nullable: true),
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Administrator products retrieved successfully.'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Category administration
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/admin/categories',
    operationId: 'adminCategoriesIndex',
    summary: 'List categories',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'q',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', maxLength: 255)
        ),
        new OA\Parameter(
            name: 'parent_public_id',
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
            description: 'Categories retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminCategoryCollectionResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Invalid category filters.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/admin/categories',
    operationId: 'adminCategoriesStore',
    summary: 'Create category',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/StoreCategoryRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Category created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminCategoryResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Category validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/admin/categories/{category}',
    operationId: 'adminCategoriesShow',
    summary: 'Show category',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'category',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1CATEGORY123456789ABC'
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Category retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminCategoryResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Category not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/admin/categories/{category}',
    operationId: 'adminCategoriesUpdatePut',
    summary: 'Replace category information',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'category',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateCategoryRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Category updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminCategoryResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Category not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Category hierarchy conflict.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Category validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/admin/categories/{category}',
    operationId: 'adminCategoriesUpdatePatch',
    summary: 'Update selected category fields',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'category',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateCategoryRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Category updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminCategoryResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Category not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Category hierarchy conflict.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Category validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Delete(
    path: '/admin/categories/{category}',
    operationId: 'adminCategoriesDestroy',
    summary: 'Delete category',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'category',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Category deleted successfully.',
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Category not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Category contains children or products.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Brand administration
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/admin/brands',
    operationId: 'adminBrandsIndex',
    summary: 'List brands',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'q',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', maxLength: 255)
        ),
        new OA\Parameter(
            name: 'is_active',
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
            description: 'Brands retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminBrandCollectionResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Invalid brand filters.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Post(
    path: '/admin/brands',
    operationId: 'adminBrandsStore',
    summary: 'Create brand',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/StoreBrandRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Brand created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminBrandResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Brand validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/admin/brands/{brand}',
    operationId: 'adminBrandsShow',
    summary: 'Show brand',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'brand',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1BRAND123456789ABCDE'
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Brand retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminBrandResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Brand not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]
#[OA\Put(
    path: '/admin/brands/{brand}',
    operationId: 'adminBrandsUpdatePut',
    summary: 'Replace brand information',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'brand',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateBrandRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Brand updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminBrandResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Brand not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Brand validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Patch(
    path: '/admin/brands/{brand}',
    operationId: 'adminBrandsUpdatePatch',
    summary: 'Update selected brand fields',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'brand',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/UpdateBrandRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Brand updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminBrandResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Brand not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Brand validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Delete(
    path: '/admin/brands/{brand}',
    operationId: 'adminBrandsDestroy',
    summary: 'Delete brand',
    security: [['sanctum' => []]],
    tags: ['Catalog Administration'],
    parameters: [
        new OA\Parameter(
            name: 'brand',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string')
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Brand deleted successfully.',
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Brand not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'Brand is assigned to products.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product moderation
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/admin/products',
    operationId: 'adminProductsIndex',
    summary: 'List products for moderation',
    description: 'When status is omitted, pending-review products are returned.',
    security: [['sanctum' => []]],
    tags: ['Product Moderation'],
    parameters: [
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'all',
                    'draft',
                    'pending_review',
                    'approved',
                    'rejected',
                    'suspended',
                    'archived',
                ],
                default: 'pending_review'
            )
        ),
        new OA\Parameter(
            name: 'condition',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'new',
                    'refurbished',
                    'used_like_new',
                    'used_good',
                    'used_fair',
                ]
            )
        ),
        new OA\Parameter(
            name: 'seller_public_id',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'category_public_id',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'brand_public_id',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string')
        ),
        new OA\Parameter(
            name: 'q',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', maxLength: 255)
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
            description: 'Administrator products retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminProductCollectionResponse'
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
            description: 'Administrator permission required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Invalid moderation filters.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]
#[OA\Get(
    path: '/admin/products/{product}',
    operationId: 'adminProductsShow',
    summary: 'Show complete product moderation details',
    security: [['sanctum' => []]],
    tags: ['Product Moderation'],
    parameters: [
        new OA\Parameter(
            name: 'product',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1PRODUCT123456789ABCD'
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product moderation details retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminProductResponse'
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
            description: 'Administrator permission required.',
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
    path: '/admin/products/{product}/moderate',
    operationId: 'adminProductsModerate',
    summary: 'Moderate product',
    description: 'Approves or rejects a pending product, or suspends an approved product.',
    security: [['sanctum' => []]],
    tags: ['Product Moderation'],
    parameters: [
        new OA\Parameter(
            name: 'product',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: '01K1PRODUCT123456789ABCD'
            )
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: '#/components/schemas/ModerateProductRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Product moderation completed successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/AdminProductResponse'
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
            description: 'Administrator permission required.',
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
            description: 'Invalid moderation transition or incomplete product.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Moderation validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

final class AdminCatalogEndpoints
{
    /*
     * OpenAPI-only attribute container.
     */
}