<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Shared catalog summaries
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'CatalogUserSummary',
    title: 'Catalog User Summary',
    description: 'Basic information about a user connected to a catalog action.',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1QWERTY123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'RushPi Administrator'
        ),
        new OA\Property(
            property: 'email',
            type: 'string',
            format: 'email',
            example: 'admin@rushpi.com'
        ),
    ]
)]

#[OA\Schema(
    schema: 'CatalogSellerSummary',
    title: 'Catalog Seller Summary',
    description: 'Seller business information connected to a product.',
    type: 'object',
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
        new OA\Property(
            property: 'status',
            type: 'string',
            example: 'approved'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Category schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'CatalogCategorySummary',
    title: 'Category Summary',
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
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]

#[OA\Schema(
    schema: 'CatalogCategory',
    title: 'Catalog Category',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'is_active',
        'sort_order',
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
            nullable: true,
            example: 'Mobile phones and smartphone accessories.'
        ),
        new OA\Property(
            property: 'image_path',
            type: 'string',
            nullable: true,
            example: 'categories/smartphones.webp'
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 1
        ),
        new OA\Property(
            property: 'parent',
            ref: '#/components/schemas/CatalogCategorySummary',
            nullable: true
        ),
        new OA\Property(
            property: 'children',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/CatalogCategorySummary'
            )
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

#[OA\Schema(
    schema: 'StoreCategoryRequest',
    title: 'Create Category Request',
    type: 'object',
    required: [
        'name',
    ],
    properties: [
        new OA\Property(
            property: 'parent_public_id',
            type: 'string',
            nullable: true,
            example: '01K1PARENT123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255,
            example: 'Smartphones'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true,
            maxLength: 255,
            example: 'smartphones'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Mobile phones and smartphone accessories.'
        ),
        new OA\Property(
            property: 'image_path',
            type: 'string',
            nullable: true,
            example: 'categories/smartphones.webp'
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 1
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateCategoryRequest',
    title: 'Update Category Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'parent_public_id',
            type: 'string',
            nullable: true,
            example: '01K1PARENT123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255,
            example: 'Mobile Smartphones'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true,
            maxLength: 255,
            example: 'mobile-smartphones'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Updated category description.'
        ),
        new OA\Property(
            property: 'image_path',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 2
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Brand schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'CatalogBrandSummary',
    title: 'Brand Summary',
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
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]

#[OA\Schema(
    schema: 'CatalogBrand',
    title: 'Catalog Brand',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'is_active',
        'sort_order',
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
            nullable: true,
            example: 'Samsung electronic products.'
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
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 1
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

#[OA\Schema(
    schema: 'StoreBrandRequest',
    title: 'Create Brand Request',
    type: 'object',
    required: [
        'name',
    ],
    properties: [
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255,
            example: 'Samsung'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true,
            maxLength: 255,
            example: 'samsung'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Samsung electronic products.'
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
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 1
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateBrandRequest',
    title: 'Update Brand Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255,
            example: 'Samsung Electronics'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true,
            example: 'samsung-electronics'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'logo_path',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'website_url',
            type: 'string',
            format: 'uri',
            nullable: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 2
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product request schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'StoreSellerProductRequest',
    title: 'Create Seller Product Request',
    type: 'object',
    required: [
        'category_public_id',
        'name',
        'condition',
    ],
    properties: [
        new OA\Property(
            property: 'category_public_id',
            type: 'string',
            example: '01K1CATEGORY123456789ABC'
        ),
        new OA\Property(
            property: 'brand_public_id',
            type: 'string',
            nullable: true,
            example: '01K1BRAND123456789ABCDE'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255,
            example: 'Samsung Galaxy S26 Ultra'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true,
            example: 'samsung-galaxy-s26-ultra'
        ),
        new OA\Property(
            property: 'short_description',
            type: 'string',
            nullable: true,
            maxLength: 500,
            example: 'Flagship smartphone with advanced camera technology.'
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true,
            example: 'Full product description.'
        ),
        new OA\Property(
            property: 'condition',
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
            property: 'warranty_months',
            type: 'integer',
            nullable: true,
            minimum: 0,
            maximum: 120,
            example: 12
        ),
        new OA\Property(
            property: 'specifications',
            type: 'object',
            nullable: true,
            example: [
                'display' => '6.9 inch AMOLED',
                'storage' => '256 GB',
                'ram' => '12 GB',
                'battery' => '5000 mAh',
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateSellerProductRequest',
    title: 'Update Seller Product Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'category_public_id',
            type: 'string'
        ),
        new OA\Property(
            property: 'brand_public_id',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            minLength: 2,
            maxLength: 255
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'short_description',
            type: 'string',
            nullable: true,
            maxLength: 500
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'condition',
            type: 'string',
            enum: [
                'new',
                'refurbished',
                'used_like_new',
                'used_good',
                'used_fair',
            ]
        ),
        new OA\Property(
            property: 'warranty_months',
            type: 'integer',
            nullable: true,
            minimum: 0,
            maximum: 120
        ),
        new OA\Property(
            property: 'specifications',
            type: 'object',
            nullable: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Variant schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'StoreProductVariantRequest',
    title: 'Create Product Variant Request',
    type: 'object',
    required: [
        'sku',
        'name',
    ],
    properties: [
        new OA\Property(
            property: 'sku',
            type: 'string',
            example: 'S26-ULTRA-BLK-256'
        ),
        new OA\Property(
            property: 'barcode',
            type: 'string',
            nullable: true,
            example: '8806099999999'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Black Titanium / 256 GB'
        ),
        new OA\Property(
            property: 'attributes',
            type: 'object',
            nullable: true,
            example: [
                'color' => 'Black Titanium',
                'storage' => '256 GB',
                'ram' => '12 GB',
            ]
        ),
        new OA\Property(
            property: 'weight_grams',
            type: 'integer',
            nullable: true,
            minimum: 0,
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
        new OA\Property(
            property: 'is_default',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 0
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateProductVariantRequest',
    title: 'Update Product Variant Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'sku',
            type: 'string',
            example: 'S26-ULTRA-BLK-512'
        ),
        new OA\Property(
            property: 'barcode',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Black Titanium / 512 GB'
        ),
        new OA\Property(
            property: 'attributes',
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'weight_grams',
            type: 'integer',
            nullable: true,
            minimum: 0
        ),
        new OA\Property(
            property: 'length_cm',
            type: 'number',
            format: 'float',
            nullable: true
        ),
        new OA\Property(
            property: 'width_cm',
            type: 'number',
            format: 'float',
            nullable: true
        ),
        new OA\Property(
            property: 'height_cm',
            type: 'number',
            format: 'float',
            nullable: true
        ),
        new OA\Property(
            property: 'is_default',
            type: 'boolean'
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean'
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductVariantSummary',
    title: 'Product Variant Summary',
    type: 'object',
    required: [
        'public_id',
        'sku',
        'name',
        'is_default',
        'is_active',
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
            property: 'barcode',
            type: 'string',
            nullable: true,
            example: '8806099999999'
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
            ]
        ),
        new OA\Property(
            property: 'is_default',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Pricing schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'StoreProductVariantPriceRequest',
    title: 'Create Product Variant Price Request',
    type: 'object',
    required: [
        'currency',
        'selling_price',
    ],
    properties: [
        new OA\Property(
            property: 'currency',
            type: 'string',
            minLength: 3,
            maxLength: 3,
            example: 'RWF'
        ),
        new OA\Property(
            property: 'selling_price',
            type: 'number',
            format: 'double',
            minimum: 0.01,
            example: 1450000
        ),
        new OA\Property(
            property: 'compare_at_price',
            type: 'number',
            format: 'double',
            nullable: true,
            example: 1550000
        ),
        new OA\Property(
            property: 'cost_price',
            type: 'number',
            format: 'double',
            nullable: true,
            minimum: 0,
            example: 1200000
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateProductVariantPriceRequest',
    title: 'Update Product Variant Price Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'currency',
            type: 'string',
            minLength: 3,
            maxLength: 3,
            example: 'RWF'
        ),
        new OA\Property(
            property: 'selling_price',
            type: 'number',
            format: 'double',
            minimum: 0.01,
            example: 1400000
        ),
        new OA\Property(
            property: 'compare_at_price',
            type: 'number',
            format: 'double',
            nullable: true,
            example: 1550000
        ),
        new OA\Property(
            property: 'cost_price',
            type: 'number',
            format: 'double',
            nullable: true,
            minimum: 0,
            example: 1200000
        ),
    ]
)]

#[OA\Schema(
    schema: 'SellerProductVariantPrice',
    title: 'Seller Product Variant Price',
    description: 'Seller-only price information. Cost price must not be exposed publicly.',
    type: 'object',
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
            property: 'cost_price',
            type: 'string',
            nullable: true,
            example: '1200000.00'
        ),
        new OA\Property(
            property: 'formatted',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'selling_price',
                    type: 'string',
                    example: 'RWF 1,450,000.00'
                ),
                new OA\Property(
                    property: 'compare_at_price',
                    type: 'string',
                    nullable: true,
                    example: 'RWF 1,550,000.00'
                ),
                new OA\Property(
                    property: 'cost_price',
                    type: 'string',
                    nullable: true,
                    example: 'RWF 1,200,000.00'
                ),
            ]
        ),
        new OA\Property(
            property: 'discount',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'is_discounted',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'percentage',
                    type: 'number',
                    format: 'float',
                    nullable: true,
                    example: 6.45
                ),
                new OA\Property(
                    property: 'amount',
                    type: 'string',
                    nullable: true,
                    example: '100000.00'
                ),
                new OA\Property(
                    property: 'formatted_amount',
                    type: 'string',
                    nullable: true,
                    example: 'RWF 100,000.00'
                ),
            ]
        ),
        new OA\Property(
            property: 'profit',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'amount',
                    type: 'string',
                    nullable: true,
                    example: '250000.00'
                ),
                new OA\Property(
                    property: 'formatted_amount',
                    type: 'string',
                    nullable: true,
                    example: 'RWF 250,000.00'
                ),
                new OA\Property(
                    property: 'margin_percentage',
                    type: 'number',
                    format: 'float',
                    nullable: true,
                    example: 17.24
                ),
            ]
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Inventory schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'AdjustInventoryRequest',
    title: 'Adjust Inventory Request',
    type: 'object',
    required: [
        'quantity',
        'movement_type',
        'reason',
    ],
    properties: [
        new OA\Property(
            property: 'quantity',
            type: 'integer',
            description: 'Positive values add stock. Negative values remove stock.',
            example: 25
        ),
        new OA\Property(
            property: 'movement_type',
            type: 'string',
            description: 'A valid value from StockMovementType.',
            example: 'adjustment'
        ),
        new OA\Property(
            property: 'reason',
            type: 'string',
            minLength: 3,
            maxLength: 1000,
            example: 'Initial warehouse stock received.'
        ),
        new OA\Property(
            property: 'reference_type',
            type: 'string',
            nullable: true,
            example: 'purchase_order'
        ),
        new OA\Property(
            property: 'reference_id',
            type: 'string',
            nullable: true,
            example: 'PO-2026-00045'
        ),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            example: [
                'warehouse' => 'Kigali Main Warehouse',
                'supplier' => 'RushPi Supplier',
            ]
        ),
    ]
)]

#[OA\Schema(
    schema: 'UpdateInventorySettingsRequest',
    title: 'Update Inventory Settings Request',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'reorder_level',
            type: 'integer',
            minimum: 0,
            example: 5
        ),
        new OA\Property(
            property: 'allow_backorder',
            type: 'boolean',
            example: false
        ),
    ]
)]

#[OA\Schema(
    schema: 'SellerInventory',
    title: 'Seller Inventory',
    type: 'object',
    required: [
        'quantity_on_hand',
        'quantity_reserved',
        'available_quantity',
        'reorder_level',
        'allow_backorder',
        'stock_status',
    ],
    properties: [
        new OA\Property(
            property: 'quantity_on_hand',
            type: 'integer',
            example: 25
        ),
        new OA\Property(
            property: 'quantity_reserved',
            type: 'integer',
            example: 3
        ),
        new OA\Property(
            property: 'available_quantity',
            type: 'integer',
            example: 22
        ),
        new OA\Property(
            property: 'reorder_level',
            type: 'integer',
            example: 5
        ),
        new OA\Property(
            property: 'allow_backorder',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'stock_status',
            type: 'string',
            example: 'in_stock'
        ),
        new OA\Property(
            property: 'is_in_stock',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_low_stock',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'is_out_of_stock',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'variant',
            ref: '#/components/schemas/ProductVariantSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'stock_movements_count',
            type: 'integer',
            nullable: true,
            example: 4
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

#[OA\Schema(
    schema: 'SellerStockMovement',
    title: 'Seller Stock Movement',
    description: 'Immutable stock movement audit record.',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1MOVEMENT123456789ABC'
        ),
        new OA\Property(
            property: 'movement_type',
            type: 'string',
            example: 'adjustment'
        ),
        new OA\Property(
            property: 'movement_type_label',
            type: 'string',
            nullable: true,
            example: 'Adjustment'
        ),
        new OA\Property(
            property: 'quantity',
            type: 'integer',
            example: 25
        ),
        new OA\Property(
            property: 'absolute_quantity',
            type: 'integer',
            example: 25
        ),
        new OA\Property(
            property: 'direction',
            type: 'string',
            enum: [
                'increase',
                'decrease',
                'unchanged',
            ],
            example: 'increase'
        ),
        new OA\Property(
            property: 'stock',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'quantity_on_hand_before',
                    type: 'integer',
                    example: 0
                ),
                new OA\Property(
                    property: 'quantity_on_hand_after',
                    type: 'integer',
                    example: 25
                ),
                new OA\Property(
                    property: 'quantity_on_hand_change',
                    type: 'integer',
                    example: 25
                ),
                new OA\Property(
                    property: 'quantity_reserved_before',
                    type: 'integer',
                    example: 0
                ),
                new OA\Property(
                    property: 'quantity_reserved_after',
                    type: 'integer',
                    example: 0
                ),
                new OA\Property(
                    property: 'available_before',
                    type: 'integer',
                    example: 0
                ),
                new OA\Property(
                    property: 'available_after',
                    type: 'integer',
                    example: 25
                ),
            ]
        ),
        new OA\Property(
            property: 'reason',
            type: 'string',
            example: 'Initial warehouse stock received.'
        ),
        new OA\Property(
            property: 'reference',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'type',
                    type: 'string',
                    nullable: true,
                    example: 'purchase_order'
                ),
                new OA\Property(
                    property: 'id',
                    type: 'string',
                    nullable: true,
                    example: 'PO-2026-00045'
                ),
            ]
        ),
        new OA\Property(
            property: 'metadata',
            type: 'object'
        ),
        new OA\Property(
            property: 'variant',
            ref: '#/components/schemas/ProductVariantSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'seller',
            ref: '#/components/schemas/CatalogSellerSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'performed_by',
            ref: '#/components/schemas/CatalogUserSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Product media schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'StoreProductMediaRequest',
    title: 'Upload Product Image Request',
    type: 'object',
    required: [
        'image',
    ],
    properties: [
        new OA\Property(
            property: 'image',
            type: 'string',
            format: 'binary',
            description: 'JPG, JPEG, PNG or WebP image with a maximum size of 5 MB.'
        ),
        new OA\Property(
            property: 'product_variant_public_id',
            type: 'string',
            nullable: true,
            example: '01K1VARIANT123456789ABCD'
        ),
        new OA\Property(
            property: 'alt_text',
            type: 'string',
            nullable: true,
            maxLength: 255,
            example: 'Samsung Galaxy S26 Ultra front view'
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            minimum: 0,
            example: 0
        ),
        new OA\Property(
            property: 'is_primary',
            type: 'boolean',
            example: true
        ),
    ]
)]

#[OA\Schema(
    schema: 'ReorderProductMediaRequest',
    title: 'Reorder Product Media Request',
    type: 'object',
    required: [
        'media',
    ],
    properties: [
        new OA\Property(
            property: 'media',
            type: 'array',
            minItems: 1,
            maxItems: 10,
            items: new OA\Items(
                type: 'object',
                required: [
                    'public_id',
                    'sort_order',
                ],
                properties: [
                    new OA\Property(
                        property: 'public_id',
                        type: 'string',
                        example: '01K1MEDIA123456789ABCDE'
                    ),
                    new OA\Property(
                        property: 'sort_order',
                        type: 'integer',
                        minimum: 0,
                        example: 1
                    ),
                ]
            )
        ),
    ]
)]

#[OA\Schema(
    schema: 'SellerProductMedia',
    title: 'Seller Product Media',
    type: 'object',
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
            property: 'original_name',
            type: 'string',
            example: 'galaxy-s26-front.webp'
        ),
        new OA\Property(
            property: 'mime_type',
            type: 'string',
            example: 'image/webp'
        ),
        new OA\Property(
            property: 'extension',
            type: 'string',
            nullable: true,
            example: 'webp'
        ),
        new OA\Property(
            property: 'size_bytes',
            type: 'integer',
            nullable: true,
            example: 824500
        ),
        new OA\Property(
            property: 'formatted_size',
            type: 'string',
            nullable: true,
            example: '805.18 KB'
        ),
        new OA\Property(
            property: 'alt_text',
            type: 'string',
            nullable: true,
            example: 'Samsung Galaxy S26 Ultra front view'
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 0
        ),
        new OA\Property(
            property: 'is_primary',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'url',
            type: 'string',
            format: 'uri',
            example: 'https://api.rushpi.com/storage/products/example.webp'
        ),
        new OA\Property(
            property: 'file_exists',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'variant',
            ref: '#/components/schemas/ProductVariantSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'uploaded_by',
            ref: '#/components/schemas/CatalogUserSummary',
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
| Complete seller product schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'SellerProductVariant',
    title: 'Seller Product Variant',
    type: 'object',
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
            property: 'barcode',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Black Titanium / 256 GB'
        ),
        new OA\Property(
            property: 'attributes',
            type: 'object'
        ),
        new OA\Property(
            property: 'dimensions',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'weight_grams',
                    type: 'integer',
                    nullable: true
                ),
                new OA\Property(
                    property: 'length_cm',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'width_cm',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'height_cm',
                    type: 'string',
                    nullable: true
                ),
            ]
        ),
        new OA\Property(
            property: 'is_default',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'sort_order',
            type: 'integer',
            example: 0
        ),
        new OA\Property(
            property: 'price',
            ref: '#/components/schemas/SellerProductVariantPrice',
            nullable: true
        ),
        new OA\Property(
            property: 'inventory',
            ref: '#/components/schemas/SellerInventory',
            nullable: true
        ),
        new OA\Property(
            property: 'media',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProductMedia'
            )
        ),
        new OA\Property(
            property: 'stock_movements_count',
            type: 'integer',
            nullable: true,
            example: 4
        ),
        new OA\Property(
            property: 'media_count',
            type: 'integer',
            nullable: true,
            example: 2
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

#[OA\Schema(
    schema: 'SellerProduct',
    title: 'Seller Product',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
        'condition',
        'status',
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
            nullable: true
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'condition',
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
            property: 'condition_label',
            type: 'string',
            nullable: true,
            example: 'New'
        ),
        new OA\Property(
            property: 'warranty_months',
            type: 'integer',
            nullable: true,
            example: 12
        ),
        new OA\Property(
            property: 'specifications',
            type: 'object'
        ),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: [
                'draft',
                'pending_review',
                'approved',
                'rejected',
                'suspended',
                'archived',
            ],
            example: 'draft'
        ),
        new OA\Property(
            property: 'status_label',
            type: 'string',
            nullable: true,
            example: 'Draft'
        ),
        new OA\Property(
            property: 'is_publicly_visible',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'can_be_edited',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'category',
            ref: '#/components/schemas/CatalogCategorySummary',
            nullable: true
        ),
        new OA\Property(
            property: 'brand',
            ref: '#/components/schemas/CatalogBrandSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'seller',
            ref: '#/components/schemas/CatalogSellerSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'variants_count',
            type: 'integer',
            nullable: true,
            example: 3
        ),
        new OA\Property(
            property: 'active_variants_count',
            type: 'integer',
            nullable: true,
            example: 3
        ),
        new OA\Property(
            property: 'media_count',
            type: 'integer',
            nullable: true,
            example: 5
        ),
        new OA\Property(
            property: 'moderation_reviews_count',
            type: 'integer',
            nullable: true,
            example: 1
        ),
        new OA\Property(
            property: 'moderation',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'rejection_reason',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'suspension_reason',
                    type: 'string',
                    nullable: true
                ),
                new OA\Property(
                    property: 'submitted_at',
                    type: 'string',
                    format: 'date-time',
                    nullable: true
                ),
                new OA\Property(
                    property: 'approved_at',
                    type: 'string',
                    format: 'date-time',
                    nullable: true
                ),
                new OA\Property(
                    property: 'rejected_at',
                    type: 'string',
                    format: 'date-time',
                    nullable: true
                ),
                new OA\Property(
                    property: 'suspended_at',
                    type: 'string',
                    format: 'date-time',
                    nullable: true
                ),
                new OA\Property(
                    property: 'archived_at',
                    type: 'string',
                    format: 'date-time',
                    nullable: true
                ),
            ]
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
| Product moderation schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ModerateProductRequest',
    title: 'Moderate Product Request',
    type: 'object',
    required: [
        'action',
    ],
    properties: [
        new OA\Property(
            property: 'action',
            type: 'string',
            enum: [
                'approve',
                'reject',
                'suspend',
            ],
            example: 'approve'
        ),
        new OA\Property(
            property: 'reason',
            type: 'string',
            nullable: true,
            maxLength: 2000,
            example: 'The product image does not clearly show the item.'
        ),
        new OA\Property(
            property: 'internal_notes',
            type: 'string',
            nullable: true,
            maxLength: 5000,
            example: 'Seller should upload a clearer primary image.'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductModerationReview',
    title: 'Product Moderation Review',
    description: 'Immutable administrator product-moderation decision.',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1REVIEW123456789ABCDE'
        ),
        new OA\Property(
            property: 'action',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'value',
                    type: 'string',
                    example: 'approve'
                ),
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    nullable: true,
                    example: 'Approve'
                ),
            ]
        ),
        new OA\Property(
            property: 'reason',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'internal_notes',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'snapshot',
            type: 'object'
        ),
        new OA\Property(
            property: 'reviewed_by',
            ref: '#/components/schemas/CatalogUserSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
    ]
)]

#[OA\Schema(
    schema: 'AdminProduct',
    title: 'Administrator Product',
    description: 'Complete product information available to administrators.',
    type: 'object',
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
            nullable: true
        ),
        new OA\Property(
            property: 'description',
            type: 'string',
            nullable: true
        ),
        new OA\Property(
            property: 'condition',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'value',
                    type: 'string',
                    example: 'new'
                ),
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    nullable: true,
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
            type: 'object'
        ),
        new OA\Property(
            property: 'status',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'value',
                    type: 'string',
                    example: 'pending_review'
                ),
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    nullable: true,
                    example: 'Pending Review'
                ),
            ]
        ),
        new OA\Property(
            property: 'seller',
            ref: '#/components/schemas/CatalogSellerSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'category',
            ref: '#/components/schemas/CatalogCategorySummary',
            nullable: true
        ),
        new OA\Property(
            property: 'brand',
            ref: '#/components/schemas/CatalogBrandSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'variants',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProductVariant'
            )
        ),
        new OA\Property(
            property: 'media',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/SellerProductMedia'
            )
        ),
        new OA\Property(
            property: 'moderation_reviews',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductModerationReview'
            )
        ),
        new OA\Property(
            property: 'created_by',
            ref: '#/components/schemas/CatalogUserSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'updated_by',
            ref: '#/components/schemas/CatalogUserSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'approved_by',
            ref: '#/components/schemas/CatalogUserSummary',
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
        new OA\Property(
            property: 'deleted_at',
            type: 'string',
            format: 'date-time',
            nullable: true
        ),
    ]
)]

final class CatalogSchemas
{
    /*
     * This class exists only as a container for OpenAPI
     * component schema attributes.
     */
}
