<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Public catalog tag
|--------------------------------------------------------------------------
*/

#[OA\Tag(
    name: 'Public Catalog',
    description: 'Public product search, product details, categories and brands. Authentication is not required.'
)]

/*
|--------------------------------------------------------------------------
| Public media schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicCatalogMedia',
    title: 'Public Catalog Media',
    type: 'object',
    required: [
        'public_id',
        'media_type',
        'url',
        'is_primary',
        'sort_order',
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
            example: 'image'
        ),
        new OA\Property(
            property: 'url',
            type: 'string',
            format: 'uri',
            example: 'https://api.rushpi.com/storage/products/example.webp'
        ),
        new OA\Property(
            property: 'alt_text',
            type: 'string',
            nullable: true,
            example: 'Samsung Galaxy smartphone front view'
        ),
        new OA\Property(
            property: 'is_primary',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 0
        ),
        new OA\Property(
            property: 'variant_public_id',
            type: 'string',
            nullable: true,
            example: '01K1VARIANT123456789ABCD'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public pricing schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicVariantDiscount',
    title: 'Public Variant Discount',
    type: 'object',
    required: [
        'is_discounted',
    ],
    properties: [
        new OA\Property(
            property: 'is_discounted',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'amount',
            type: 'string',
            nullable: true,
            example: '100000.00'
        ),
        new OA\Property(
            property: 'percentage',
            type: 'number',
            format: 'float',
            nullable: true,
            example: 6.45
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicVariantPrice',
    title: 'Public Variant Price',
    description: 'Customer-safe pricing. Cost price and profit are never returned.',
    type: 'object',
    required: [
        'currency',
        'selling_price',
        'formatted_selling_price',
        'discount',
    ],
    properties: [
        new OA\Property(
            property: 'currency',
            type: 'string',
            example: 'RWF'
        ),
        new OA\Property(
            property: 'selling_price',
            type: 'string',
            example: '1450000.00'
        ),
        new OA\Property(
            property: 'compare_at_price',
            type: 'string',
            nullable: true,
            example: '1550000.00'
        ),
        new OA\Property(
            property: 'formatted_selling_price',
            type: 'string',
            example: 'RWF 1,450,000.00'
        ),
        new OA\Property(
            property: 'formatted_compare_at_price',
            type: 'string',
            nullable: true,
            example: 'RWF 1,550,000.00'
        ),
        new OA\Property(
            property: 'discount',
            ref: '#/components/schemas/PublicVariantDiscount'
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicProductPricingSummary',
    title: 'Public Product Pricing Summary',
    type: 'object',
    required: [
        'has_price',
        'has_price_range',
        'has_discount',
    ],
    properties: [
        new OA\Property(
            property: 'has_price',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'currency',
            type: 'string',
            nullable: true,
            example: 'RWF'
        ),
        new OA\Property(
            property: 'minimum_price',
            type: 'string',
            nullable: true,
            example: '1400000.00'
        ),
        new OA\Property(
            property: 'maximum_price',
            type: 'string',
            nullable: true,
            example: '1650000.00'
        ),
        new OA\Property(
            property: 'formatted_minimum_price',
            type: 'string',
            nullable: true,
            example: 'RWF 1,400,000.00'
        ),
        new OA\Property(
            property: 'formatted_maximum_price',
            type: 'string',
            nullable: true,
            example: 'RWF 1,650,000.00'
        ),
        new OA\Property(
            property: 'has_price_range',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'has_discount',
            type: 'boolean',
            example: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public inventory schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicVariantInventory',
    title: 'Public Variant Inventory',
    description: 'Customer-safe stock availability. Reserved stock is never returned.',
    type: 'object',
    required: [
        'available_quantity',
        'allow_backorder',
        'is_available',
        'stock_status',
    ],
    properties: [
        new OA\Property(
            property: 'available_quantity',
            type: 'integer',
            example: 22
        ),
        new OA\Property(
            property: 'allow_backorder',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'is_available',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'stock_status',
            type: 'string',
            enum: [
                'in_stock',
                'low_stock',
                'out_of_stock',
            ],
            example: 'in_stock'
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicProductAvailability',
    title: 'Public Product Availability',
    type: 'object',
    required: [
        'is_available',
        'stock_status',
        'available_variants_count',
        'total_variants_count',
        'total_available_quantity',
    ],
    properties: [
        new OA\Property(
            property: 'is_available',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'stock_status',
            type: 'string',
            enum: [
                'in_stock',
                'low_stock',
                'out_of_stock',
            ],
            example: 'in_stock'
        ),
        new OA\Property(
            property: 'available_variants_count',
            type: 'integer',
            example: 2
        ),
        new OA\Property(
            property: 'total_variants_count',
            type: 'integer',
            example: 3
        ),
        new OA\Property(
            property: 'total_available_quantity',
            type: 'integer',
            example: 35
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public product variant
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicCatalogProductVariant',
    title: 'Public Catalog Product Variant',
    type: 'object',
    required: [
        'public_id',
        'sku',
        'name',
        'attributes',
        'dimensions',
        'is_default',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1VARIANT123456789ABCD'
        ),
        new OA\Property(
            property: 'sku',
            type: 'string',
            example: 'S26-ULTRA-BLK-256'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Black Titanium / 256 GB'
        ),
        new OA\Property(
            property: 'attributes',
            type: 'object',
            example: [
                'color' => 'Black Titanium',
                'storage' => '256 GB',
                'ram' => '12 GB',
            ]
        ),
        new OA\Property(
            property: 'dimensions',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'weight_grams',
                    type: 'integer',
                    nullable: true,
                    example: 232
                ),
                new OA\Property(
                    property: 'length_cm',
                    type: 'number',
                    format: 'float',
                    nullable: true,
                    example: 16.3
                ),
                new OA\Property(
                    property: 'width_cm',
                    type: 'number',
                    format: 'float',
                    nullable: true,
                    example: 7.8
                ),
                new OA\Property(
                    property: 'height_cm',
                    type: 'number',
                    format: 'float',
                    nullable: true,
                    example: 0.9
                ),
            ]
        ),
        new OA\Property(
            property: 'is_default',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'price',
            ref: '#/components/schemas/PublicVariantPrice',
            nullable: true
        ),
        new OA\Property(
            property: 'inventory',
            ref: '#/components/schemas/PublicVariantInventory',
            nullable: true
        ),
        new OA\Property(
            property: 'media',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicCatalogMedia'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public category, brand and seller summaries
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicCatalogCategory',
    title: 'Public Catalog Category',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1CATEGORY123456789ABC'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Smartphones'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'smartphones'
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicCatalogBrand',
    title: 'Public Catalog Brand',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1BRAND123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Samsung'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'samsung'
        ),
        new OA\Property(
            property: 'logo_path',
            type: 'string',
            nullable: true,
            example: 'brands/samsung.webp'
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicCatalogSeller',
    title: 'Public Catalog Seller',
    description: 'Public seller business summary without private contact or verification information.',
    type: 'object',
    required: [
        'public_id',
        'legal_business_name',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1SELLER123456789ABCDE'
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
    ]
)]

/*
|--------------------------------------------------------------------------
| Public product
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicCatalogProduct',
    title: 'Public Catalog Product',
    description: 'Customer-safe approved product information.',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'condition',
        'specifications',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1PRODUCT123456789ABCD'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Samsung Galaxy S26 Ultra'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'samsung-galaxy-s26-ultra'
        ),
        new OA\Property(
            property: 'short_description',
            type: 'string',
            nullable: true,
            example: 'Flagship smartphone with advanced camera technology.'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Complete public product description.'
        ),
        new OA\Property(
            property: 'condition',
            type: 'object',
            required: [
                'value',
                'label',
            ],
            properties: [
                new OA\Property(
                    property: 'value',
                    type: 'string',
                    enum: [
                        'new',
                        'refurbished',
                        'used_like_new',
                        'used_good',
                        'used_fair',
                    ],
                    example: 'new'
                ),
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    example: 'New'
                ),
            ]
        ),
        new OA\Property(
            property: 'warranty_months',
            type: 'integer',
            nullable: true,
            example: 12
        ),
        new OA\Property(
            property: 'specifications',
            type: 'object',
            example: [
                'display' => '6.9 inch AMOLED',
                'storage' => '256 GB',
                'ram' => '12 GB',
            ]
        ),
        new OA\Property(
            property: 'category',
            ref: '#/components/schemas/PublicCatalogCategory',
            nullable: true
        ),
        new OA\Property(
            property: 'brand',
            ref: '#/components/schemas/PublicCatalogBrand',
            nullable: true
        ),
        new OA\Property(
            property: 'seller',
            ref: '#/components/schemas/PublicCatalogSeller',
            nullable: true
        ),
        new OA\Property(
            property: 'primary_image',
            ref: '#/components/schemas/PublicCatalogMedia',
            nullable: true
        ),
        new OA\Property(
            property: 'media',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicCatalogMedia'
            )
        ),
        new OA\Property(
            property: 'pricing',
            ref: '#/components/schemas/PublicProductPricingSummary'
        ),
        new OA\Property(
            property: 'availability',
            ref: '#/components/schemas/PublicProductAvailability'
        ),
        new OA\Property(
            property: 'variants',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicCatalogProductVariant'
            )
        ),
        new OA\Property(
            property: 'variants_count',
            type: 'integer',
            example: 3
        ),
        new OA\Property(
            property: 'published_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public category and brand list items
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicCategoryListItem',
    title: 'Public Category List Item',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'sort_order',
        'products_count',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1CATEGORY123456789ABC'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Smartphones'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'smartphones'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'image_path',
            type: 'string',
            nullable: true,
            example: 'categories/smartphones.webp'
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 1
        ),
        new OA\Property(
            property: 'products_count',
            type: 'integer',
            example: 25
        ),
        new OA\Property(
            property: 'parent',
            ref: '#/components/schemas/PublicCatalogCategory',
            nullable: true
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicBrandListItem',
    title: 'Public Brand List Item',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'sort_order',
        'products_count',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1BRAND123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Samsung'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'samsung'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'logo_path',
            type: 'string',
            nullable: true,
            example: 'brands/samsung.webp'
        ),
        new OA\Property(
            property: 'website_url',
            type: 'string',
            format: 'uri',
            nullable: true,
            example: 'https://www.samsung.com'
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 1
        ),
        new OA\Property(
            property: 'products_count',
            type: 'integer',
            example: 18
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public response schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'PublicProductResponse',
    title: 'Public Product Response',
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
            example: 'Public product retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/PublicCatalogProduct'
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicProductCollectionResponse',
    title: 'Public Product Collection Response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
        'meta',
        'links',
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
            example: 'Public products retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicCatalogProduct'
            )
        ),
        new OA\Property(
            property: 'meta',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'current_page',
                    type: 'integer',
                    example: 1
                ),
                new OA\Property(
                    property: 'from',
                    type: 'integer',
                    nullable: true,
                    example: 1
                ),
                new OA\Property(
                    property: 'last_page',
                    type: 'integer',
                    example: 3
                ),
                new OA\Property(
                    property: 'path',
                    type: 'string',
                    example: 'https://api.rushpi.com/api/catalog/products'
                ),
                new OA\Property(
                    property: 'per_page',
                    type: 'integer',
                    example: 20
                ),
                new OA\Property(
                    property: 'to',
                    type: 'integer',
                    nullable: true,
                    example: 20
                ),
                new OA\Property(
                    property: 'total',
                    type: 'integer',
                    example: 54
                ),
            ]
        ),
        new OA\Property(
            property: 'links',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'first',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'last',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'previous',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'next',
                    type: 'string',
                    nullable: true
                ),
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicCategoryCollectionResponse',
    title: 'Public Category Collection Response',
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
            example: 'Public categories retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicCategoryListItem'
            )
        ),
    ]
)]

#[OA\Schema(
    schema: 'PublicBrandCollectionResponse',
    title: 'Public Brand Collection Response',
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
            example: 'Public brands retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/PublicBrandListItem'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public product search
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/catalog/products',
    operationId: 'publicCatalogProductsIndex',
    summary: 'Search public products',
    description: 'Returns approved products belonging to approved sellers. Cost prices, reserved stock and private seller information are excluded.',
    tags: [
        'Public Catalog',
    ],
    parameters: [
        new OA\Parameter(
            name: 'q',
            description: 'Search product name, description, SKU, category, brand or seller business name.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 150
            )
        ),
        new OA\Parameter(
            name: 'category',
            description: 'Category public identifier or slug.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 255
            )
        ),
        new OA\Parameter(
            name: 'brand',
            description: 'Brand public identifier or slug.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                maxLength: 255
            )
        ),
        new OA\Parameter(
            name: 'condition',
            description: 'Product condition.',
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
            name: 'min_price',
            description: 'Minimum variant selling price.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'number',
                format: 'double',
                minimum: 0
            )
        ),
        new OA\Parameter(
            name: 'max_price',
            description: 'Maximum variant selling price.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'number',
                format: 'double',
                minimum: 0
            )
        ),
        new OA\Parameter(
            name: 'in_stock',
            description: 'Use true for products with available stock or enabled backorders.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'boolean'
            )
        ),
        new OA\Parameter(
            name: 'sort',
            description: 'Public product ordering.',
            in: 'query',
            required: false,
            schema: new OA\Schema(
                type: 'string',
                enum: [
                    'newest',
                    'oldest',
                    'price_asc',
                    'price_desc',
                    'name_asc',
                    'name_desc',
                ],
                default: 'newest'
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
                default: 20
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Public products retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/PublicProductCollectionResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Public catalog filter validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
        new OA\Response(
            response: 429,
            description: 'Too many public catalog requests.'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public product details
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/catalog/products/{product}',
    operationId: 'publicCatalogProductsShow',
    summary: 'Show public product',
    description: 'Returns one approved public product using its public identifier or slug.',
    tags: [
        'Public Catalog',
    ],
    parameters: [
        new OA\Parameter(
            name: 'product',
            description: 'Product public identifier or slug.',
            in: 'path',
            required: true,
            schema: new OA\Schema(
                type: 'string',
                example: 'samsung-galaxy-s26-ultra'
            )
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Public product retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/PublicProductResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Approved public product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 429,
            description: 'Too many public catalog requests.'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public categories
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/catalog/categories',
    operationId: 'publicCatalogCategoriesIndex',
    summary: 'List public categories',
    description: 'Returns active categories containing at least one approved public product.',
    tags: [
        'Public Catalog',
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Public categories retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/PublicCategoryCollectionResponse'
            )
        ),
        new OA\Response(
            response: 429,
            description: 'Too many public catalog requests.'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Public brands
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/catalog/brands',
    operationId: 'publicCatalogBrandsIndex',
    summary: 'List public brands',
    description: 'Returns active brands containing at least one approved public product.',
    tags: [
        'Public Catalog',
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Public brands retrieved successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/PublicBrandCollectionResponse'
            )
        ),
        new OA\Response(
            response: 429,
            description: 'Too many public catalog requests.'
        ),
    ]
)]

final class PublicCatalogEndpoints
{
    /*
     * OpenAPI-only attribute container.
     */
}