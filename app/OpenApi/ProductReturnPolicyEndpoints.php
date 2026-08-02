<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Product Return Policies',
    description: 'Seller product return, refund and exchange policy management.'
)]

/*
|--------------------------------------------------------------------------
| Shared option schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductReturnPolicyOption',
    title: 'Return policy selectable option',
    type: 'object',
    required: [
        'value',
        'label',
    ],
    properties: [
        new OA\Property(
            property: 'value',
            type: 'string',
            example: 'customer'
        ),
        new OA\Property(
            property: 'label',
            type: 'string',
            example: 'Customer'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyOptions',
    title: 'Product return policy form options',
    type: 'object',
    required: [
        'shipping_payers',
        'accepted_conditions',
        'refund_methods',
    ],
    properties: [
        new OA\Property(
            property: 'shipping_payers',
            description: 'Supported return-shipping payer options.',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductReturnPolicyOption'
            )
        ),
        new OA\Property(
            property: 'accepted_conditions',
            description: 'Supported returned-product condition options.',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductReturnPolicyOption'
            )
        ),
        new OA\Property(
            property: 'refund_methods',
            description: 'Supported customer refund methods.',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductReturnPolicyOption'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Request schema
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'UpsertProductReturnPolicyRequest',
    title: 'Create or update product return policy',
    type: 'object',
    required: [
        'is_returnable',
        'allow_refund',
        'allow_exchange',
        'requires_original_packaging',
        'requires_proof_of_purchase',
        'return_shipping_payer',
        'is_active',
    ],
    properties: [
        new OA\Property(
            property: 'is_returnable',
            description: 'Whether customers may return this product.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'return_window_days',
            description: 'Number of days after fulfillment during which the customer may request a return. Required when the product is returnable.',
            type: 'integer',
            minimum: 1,
            maximum: 365,
            nullable: true,
            example: 7
        ),
        new OA\Property(
            property: 'allow_refund',
            description: 'Whether approved returns may receive a refund.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'allow_exchange',
            description: 'Whether approved returns may receive an exchange.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'requires_original_packaging',
            description: 'Whether the original product packaging is required.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'requires_proof_of_purchase',
            description: 'Whether the customer must provide proof of purchase.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'restocking_fee_percent',
            description: 'Percentage deducted as a restocking fee.',
            type: 'number',
            format: 'float',
            minimum: 0,
            maximum: 100,
            nullable: true,
            example: 0
        ),
        new OA\Property(
            property: 'return_shipping_payer',
            description: 'Party responsible for return-shipping expenses.',
            type: 'string',
            enum: [
                'customer',
                'seller',
                'shared',
                'conditional',
            ],
            example: 'customer'
        ),
        new OA\Property(
            property: 'accepted_conditions',
            description: 'Product conditions accepted for return. Use values returned in the options object.',
            type: 'array',
            nullable: true,
            maxItems: 20,
            uniqueItems: true,
            items: new OA\Items(
                type: 'string',
                example: 'unused'
            ),
            example: [
                'unused',
                'unopened',
                'defective',
                'damaged',
                'wrong_item',
                'not_as_described',
            ]
        ),
        new OA\Property(
            property: 'refund_methods',
            description: 'Refund methods available when allow_refund is true. Use values returned in the options object.',
            type: 'array',
            nullable: true,
            maxItems: 10,
            uniqueItems: true,
            items: new OA\Items(
                type: 'string',
                example: 'original_payment_method'
            ),
            example: [
                'original_payment_method',
                'mobile_money',
            ]
        ),
        new OA\Property(
            property: 'instructions',
            description: 'Customer-facing instructions for requesting and completing a return.',
            type: 'string',
            maxLength: 5000,
            nullable: true,
            example: 'Contact the seller through RushPi before shipping the product back.'
        ),
        new OA\Property(
            property: 'non_returnable_reason',
            description: 'Customer-facing explanation required when is_returnable is false.',
            type: 'string',
            maxLength: 2000,
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'is_active',
            description: 'Whether this return policy is currently active.',
            type: 'boolean',
            example: true
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Response detail schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductReturnPolicyProductSummary',
    title: 'Return-policy product summary',
    type: 'object',
    required: [
        'public_id',
        'name',
        'slug',
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
            example: 'HP EliteBook 840 G8'
        ),
        new OA\Property(
            property: 'slug',
            type: 'string',
            example: 'hp-elitebook-840-g8'
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
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyValueLabel',
    title: 'Return-policy selected value',
    type: 'object',
    required: [
        'value',
        'label',
    ],
    properties: [
        new OA\Property(
            property: 'value',
            type: 'string',
            example: 'unused'
        ),
        new OA\Property(
            property: 'label',
            type: 'string',
            example: 'Unused'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyAuditUser',
    title: 'Return-policy audit user',
    type: 'object',
    nullable: true,
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1USER123456789ABCDEFG'
        ),
        new OA\Property(
            property: 'name',
            type: 'string',
            example: 'Guillaume Karangwa'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyConfiguration',
    title: 'Return-policy configuration status',
    type: 'object',
    required: [
        'is_valid',
        'errors',
    ],
    properties: [
        new OA\Property(
            property: 'is_valid',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'errors',
            type: 'array',
            items: new OA\Items(
                type: 'string'
            ),
            example: []
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Main policy schema
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductReturnPolicy',
    title: 'Seller product return policy',
    type: 'object',
    required: [
        'public_id',
        'is_returnable',
        'resolutions',
        'requirements',
        'restocking_fee',
        'return_shipping',
        'accepted_conditions',
        'refund_methods',
        'is_active',
        'allows_returns',
        'allows_refunds',
        'allows_exchanges',
        'configuration',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1POLICY123456789ABCDE'
        ),
        new OA\Property(
            property: 'product',
            ref: '#/components/schemas/ProductReturnPolicyProductSummary',
            nullable: true
        ),
        new OA\Property(
            property: 'is_returnable',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'return_window_days',
            type: 'integer',
            minimum: 1,
            maximum: 365,
            nullable: true,
            example: 7
        ),
        new OA\Property(
            property: 'resolutions',
            type: 'object',
            required: [
                'allow_refund',
                'allow_exchange',
            ],
            properties: [
                new OA\Property(
                    property: 'allow_refund',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'allow_exchange',
                    type: 'boolean',
                    example: true
                ),
            ]
        ),
        new OA\Property(
            property: 'requirements',
            type: 'object',
            required: [
                'requires_original_packaging',
                'requires_proof_of_purchase',
            ],
            properties: [
                new OA\Property(
                    property: 'requires_original_packaging',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'requires_proof_of_purchase',
                    type: 'boolean',
                    example: true
                ),
            ]
        ),
        new OA\Property(
            property: 'restocking_fee',
            type: 'object',
            required: [
                'percent',
                'applies',
            ],
            properties: [
                new OA\Property(
                    property: 'percent',
                    type: 'number',
                    format: 'float',
                    minimum: 0,
                    maximum: 100,
                    example: 0
                ),
                new OA\Property(
                    property: 'applies',
                    type: 'boolean',
                    example: false
                ),
            ]
        ),
        new OA\Property(
            property: 'return_shipping',
            type: 'object',
            required: [
                'payer',
                'label',
            ],
            properties: [
                new OA\Property(
                    property: 'payer',
                    type: 'string',
                    enum: [
                        'customer',
                        'seller',
                        'shared',
                        'conditional',
                    ],
                    example: 'customer'
                ),
                new OA\Property(
                    property: 'label',
                    type: 'string',
                    example: 'Customer'
                ),
            ]
        ),
        new OA\Property(
            property: 'accepted_conditions',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductReturnPolicyValueLabel'
            )
        ),
        new OA\Property(
            property: 'refund_methods',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductReturnPolicyValueLabel'
            )
        ),
        new OA\Property(
            property: 'instructions',
            type: 'string',
            nullable: true,
            example: 'Contact the seller through RushPi before shipping the product back.'
        ),
        new OA\Property(
            property: 'non_returnable_reason',
            type: 'string',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'is_active',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'allows_returns',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'allows_refunds',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'allows_exchanges',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'configuration',
            ref: '#/components/schemas/ProductReturnPolicyConfiguration'
        ),
        new OA\Property(
            property: 'customer_policy',
            description: 'Customer-safe representation used by the public catalog.',
            type: 'object'
        ),
        new OA\Property(
            property: 'audit',
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'created_by',
                    ref: '#/components/schemas/ProductReturnPolicyAuditUser',
                    nullable: true
                ),
                new OA\Property(
                    property: 'updated_by',
                    ref: '#/components/schemas/ProductReturnPolicyAuditUser',
                    nullable: true
                ),
            ]
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T10:00:00.000000Z'
        ),
        new OA\Property(
            property: 'updated_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T10:00:00.000000Z'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Response envelope schemas
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductReturnPolicyResponse',
    title: 'Product return-policy response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
        'options',
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
            example: 'Product return policy retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/ProductReturnPolicy'
        ),
        new OA\Property(
            property: 'options',
            ref: '#/components/schemas/ProductReturnPolicyOptions'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyNotConfiguredResponse',
    title: 'Product return policy not configured response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
        'options',
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
            example: 'No return policy has been configured for this product.'
        ),
        new OA\Property(
            property: 'data',
            nullable: true,
            example: null
        ),
        new OA\Property(
            property: 'options',
            ref: '#/components/schemas/ProductReturnPolicyOptions'
        ),
    ]
)]

#[OA\Schema(
    schema: 'ProductReturnPolicyDeleteResponse',
    title: 'Product return policy deletion response',
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
            example: 'Product return policy deleted successfully.'
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
| GET product return policy
|--------------------------------------------------------------------------
*/

#[OA\Get(
    path: '/seller/profiles/{sellerProfile}/products/{product}/return-policy',
    operationId: 'sellerProductReturnPolicyShow',
    summary: 'Show product return policy',
    description: 'Returns the product return policy and supported form options. When no policy exists, data is null and the options object is still returned.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Return Policies',
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
            description: 'Return policy retrieved, or no policy has been configured.',
            content: new OA\JsonContent(
                oneOf: [
                    new OA\Schema(
                        ref: '#/components/schemas/ProductReturnPolicyResponse'
                    ),
                    new OA\Schema(
                        ref: '#/components/schemas/ProductReturnPolicyNotConfiguredResponse'
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
        new OA\Response(
            response: 403,
            description: 'Approved seller access is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile or product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| POST product return policy
|--------------------------------------------------------------------------
*/

#[OA\Post(
    path: '/seller/profiles/{sellerProfile}/products/{product}/return-policy',
    operationId: 'sellerProductReturnPolicyStore',
    summary: 'Create or update product return policy',
    description: 'Creates the policy when none exists. When a policy already exists, the same endpoint updates it. Changing an approved or rejected product policy returns the product to draft.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Return Policies',
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
            ref: '#/components/schemas/UpsertProductReturnPolicyRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Return policy created successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
            )
        ),
        new OA\Response(
            response: 200,
            description: 'Existing return policy updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
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
            description: 'Approved seller access is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile or product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'The policy cannot be changed while the product has its current status.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Return-policy validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| PUT product return policy
|--------------------------------------------------------------------------
*/

#[OA\Put(
    path: '/seller/profiles/{sellerProfile}/products/{product}/return-policy',
    operationId: 'sellerProductReturnPolicyUpdatePut',
    summary: 'Replace product return policy',
    description: 'Creates or replaces the complete return-policy configuration. All required policy fields must be supplied.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Return Policies',
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
            ref: '#/components/schemas/UpsertProductReturnPolicyRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Return policy updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
            )
        ),
        new OA\Response(
            response: 201,
            description: 'Return policy created because none previously existed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
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
            description: 'Approved seller access is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile or product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'The policy cannot be changed while the product has its current status.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Return-policy validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| PATCH product return policy
|--------------------------------------------------------------------------
*/

#[OA\Patch(
    path: '/seller/profiles/{sellerProfile}/products/{product}/return-policy',
    operationId: 'sellerProductReturnPolicyUpdatePatch',
    summary: 'Update product return policy',
    description: 'Uses the complete return-policy validation contract. Required fields must still be supplied even when PATCH is used.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Return Policies',
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
            ref: '#/components/schemas/UpsertProductReturnPolicyRequest'
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Return policy updated successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
            )
        ),
        new OA\Response(
            response: 201,
            description: 'Return policy created because none previously existed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyResponse'
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
            description: 'Approved seller access is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile or product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'The policy cannot be changed while the product has its current status.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
        new OA\Response(
            response: 422,
            description: 'Return-policy validation failed.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ValidationErrorResponse'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| DELETE product return policy
|--------------------------------------------------------------------------
*/

#[OA\Delete(
    path: '/seller/profiles/{sellerProfile}/products/{product}/return-policy',
    operationId: 'sellerProductReturnPolicyDestroy',
    summary: 'Delete product return policy',
    description: 'Deletes the product policy. Deleting the policy from an approved or rejected product returns that product to draft.',
    security: [
        [
            'sanctum' => [],
        ],
    ],
    tags: [
        'Product Return Policies',
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
            description: 'Return policy deleted successfully.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ProductReturnPolicyDeleteResponse'
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
            description: 'Approved seller access is required.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ForbiddenResponse'
            )
        ),
        new OA\Response(
            response: 404,
            description: 'Seller profile or product not found.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/NotFoundResponse'
            )
        ),
        new OA\Response(
            response: 409,
            description: 'The policy cannot be deleted while the product has its current status.',
            content: new OA\JsonContent(
                ref: '#/components/schemas/ConflictResponse'
            )
        ),
    ]
)]

final class ProductReturnPolicyEndpoints
{
    /*
     * OpenAPI-only attribute container.
     */
}
