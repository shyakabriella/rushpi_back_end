<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| Product moderation values
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductModerationActionValue',
    title: 'Product moderation action',
    description: 'Administrator action applied to a product listing.',
    type: 'string',
    enum: [
        'approve',
        'reject',
        'suspend',
        'return_to_draft',
    ],
    example: 'reject'
)]
#[OA\Schema(
    schema: 'ProductModerationFlagValue',
    title: 'Product moderation flag',
    description: 'Structured marketplace policy or catalog-quality flag.',
    type: 'string',
    enum: [
        'prohibited_item',
        'counterfeit_goods',
        'suspected_stolen_goods',
        'restricted_weapon',
        'explosive_or_hazardous_item',
        'restricted_medication',
        'illegal_drugs',
        'age_restricted_content',
        'wildlife_or_environmental_violation',
        'extremist_or_hate_content',
        'misleading_information',
        'misleading_media',
        'incorrect_category',
        'incomplete_information',
        'invalid_specifications',
        'suspicious_pricing',
        'duplicate_listing',
        'unauthorized_seller',
        'intellectual_property_violation',
        'marketplace_policy_violation',
        'requires_manual_review',
    ],
    example: 'counterfeit_goods'
)]
#[OA\Schema(
    schema: 'ProductModerationStatusValue',
    title: 'Product moderation status',
    type: 'string',
    enum: [
        'draft',
        'pending_review',
        'approved',
        'rejected',
        'suspended',
        'archived',
    ],
    example: 'rejected'
)]

/*
|--------------------------------------------------------------------------
| Moderate-product request
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ModerateProductRequest',
    title: 'Moderate product request',
    description: 'Administrator decision for an electronics marketplace product. Approval cannot contain flags. Rejection, suspension and return-to-draft actions require a reason. Selected flags require flag notes. Prohibited flags require rejection or suspension.',
    type: 'object',
    required: [
        'action',
        'moderation_flags',
    ],
    properties: [
        new OA\Property(
            property: 'action',
            ref: '#/components/schemas/ProductModerationActionValue'
        ),
        new OA\Property(
            property: 'reason',
            description: 'Required for rejection, suspension and return-to-draft decisions.',
            type: 'string',
            minLength: 10,
            maxLength: 5000,
            nullable: true,
            example: 'The product appears to be counterfeit and cannot be listed.'
        ),
        new OA\Property(
            property: 'notes',
            description: 'Private administrator notes. These notes must never be exposed through the public catalog.',
            type: 'string',
            maxLength: 10000,
            nullable: true,
            example: 'The serial number could not be verified against manufacturer records.'
        ),
        new OA\Property(
            property: 'moderation_flags',
            description: 'Structured moderation flags. Send an empty array when approving a clean product.',
            type: 'array',
            maxItems: 25,
            uniqueItems: true,
            items: new OA\Items(
                ref: '#/components/schemas/ProductModerationFlagValue'
            ),
            example: [
                'counterfeit_goods',
                'misleading_information',
            ]
        ),
        new OA\Property(
            property: 'flag_notes',
            description: 'Required whenever one or more moderation flags are selected.',
            type: 'string',
            minLength: 10,
            maxLength: 10000,
            nullable: true,
            example: 'The branding and serial number do not match the manufacturer information.'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Moderation flag details
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductModerationFlagDetail',
    title: 'Product moderation flag detail',
    type: 'object',
    required: [
        'value',
        'label',
        'is_prohibited',
        'requires_rejection',
        'is_correctable',
    ],
    properties: [
        new OA\Property(
            property: 'value',
            ref: '#/components/schemas/ProductModerationFlagValue'
        ),
        new OA\Property(
            property: 'label',
            type: 'string',
            example: 'Counterfeit goods'
        ),
        new OA\Property(
            property: 'is_prohibited',
            description: 'Indicates that the product belongs to a prohibited or high-risk marketplace category.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'requires_rejection',
            description: 'Indicates that the selected flag requires rejection or suspension.',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'is_correctable',
            description: 'Indicates that the seller may normally correct the issue and resubmit.',
            type: 'boolean',
            example: false
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Moderator summary
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductModerationModerator',
    title: 'Product moderator',
    type: 'object',
    nullable: true,
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1ADMIN123456789ABCDE'
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

/*
|--------------------------------------------------------------------------
| Moderation review audit record
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductModerationReviewAudit',
    title: 'Product moderation review audit',
    description: 'Immutable history record representing one product moderation decision.',
    type: 'object',
    required: [
        'public_id',
        'action',
        'from_status',
        'to_status',
        'moderation_flags',
        'is_prohibited_item',
        'requires_rejection',
        'has_correctable_flags',
    ],
    properties: [
        new OA\Property(
            property: 'public_id',
            type: 'string',
            example: '01K1REVIEW123456789ABCDE'
        ),
        new OA\Property(
            property: 'action',
            ref: '#/components/schemas/ProductModerationActionValue'
        ),
        new OA\Property(
            property: 'from_status',
            ref: '#/components/schemas/ProductModerationStatusValue'
        ),
        new OA\Property(
            property: 'to_status',
            ref: '#/components/schemas/ProductModerationStatusValue'
        ),
        new OA\Property(
            property: 'reason',
            type: 'string',
            nullable: true,
            example: 'The product appears to be counterfeit and cannot be listed.'
        ),
        new OA\Property(
            property: 'notes',
            description: 'Private administrator moderation notes.',
            type: 'string',
            nullable: true,
            example: 'The seller may contact marketplace support to appeal the decision.'
        ),
        new OA\Property(
            property: 'moderation_flags',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductModerationFlagDetail'
            )
        ),
        new OA\Property(
            property: 'is_prohibited_item',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'requires_rejection',
            type: 'boolean',
            example: true
        ),
        new OA\Property(
            property: 'has_correctable_flags',
            type: 'boolean',
            example: false
        ),
        new OA\Property(
            property: 'flag_notes',
            type: 'string',
            nullable: true,
            example: 'The device serial number and packaging do not match the manufacturer records.'
        ),
        new OA\Property(
            property: 'flagged_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T15:00:00.000000Z'
        ),
        new OA\Property(
            property: 'moderator',
            ref: '#/components/schemas/ProductModerationModerator'
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            format: 'date-time',
            nullable: true,
            example: '2026-08-02T15:00:00.000000Z'
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Moderation API responses
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'AdminProductModerationDecisionResponse',
    title: 'Administrator product moderation decision response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
        'moderation_review',
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
            example: 'The product was rejected successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/AdminProduct'
        ),
        new OA\Property(
            property: 'moderation_review',
            ref: '#/components/schemas/ProductModerationReviewAudit'
        ),
    ]
)]
#[OA\Schema(
    schema: 'AdminProductModerationDetailsResponse',
    title: 'Administrator product moderation details response',
    type: 'object',
    required: [
        'success',
        'message',
        'data',
        'moderation_history',
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
            example: 'Product moderation details retrieved successfully.'
        ),
        new OA\Property(
            property: 'data',
            ref: '#/components/schemas/AdminProduct'
        ),
        new OA\Property(
            property: 'moderation_history',
            type: 'array',
            items: new OA\Items(
                ref: '#/components/schemas/ProductModerationReviewAudit'
            )
        ),
    ]
)]

/*
|--------------------------------------------------------------------------
| Moderation filter examples
|--------------------------------------------------------------------------
*/

#[OA\Schema(
    schema: 'ProductModerationFilterOptions',
    title: 'Product moderation filter options',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'status',
            ref: '#/components/schemas/ProductModerationStatusValue'
        ),
        new OA\Property(
            property: 'moderation_flag',
            ref: '#/components/schemas/ProductModerationFlagValue'
        ),
        new OA\Property(
            property: 'flagged',
            type: 'boolean',
            nullable: true,
            example: true
        ),
        new OA\Property(
            property: 'prohibited',
            type: 'boolean',
            nullable: true,
            example: true
        ),
        new OA\Property(
            property: 'sort',
            type: 'string',
            enum: [
                'newest',
                'oldest',
                'submitted_newest',
                'submitted_oldest',
                'name_asc',
                'name_desc',
            ],
            example: 'submitted_newest'
        ),
        new OA\Property(
            property: 'per_page',
            type: 'integer',
            minimum: 1,
            maximum: 100,
            default: 20,
            example: 20
        ),
    ]
)]

final class ProductModerationSchemas
{
    /*
     * OpenAPI-only schema container.
     */
}
