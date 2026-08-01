<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class AdminSellerVerificationEndpoints
{
    #[OA\Get(
        path: '/admin/seller-applications',
        operationId: 'adminListSellerApplications',
        summary: 'List seller verification applications',
        description: 'Returns paginated seller applications. Only administrators may access this endpoint.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'status',
                description: 'Optional seller application status.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
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
                    nullable: true
                )
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Search by legal business name, trading name, registration number or tax identification number.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    maxLength: 100,
                    nullable: true
                ),
                example: 'RushPi Electronics'
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Number of records returned per page.',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    maximum: 100,
                    default: 20
                ),
                example: 20
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Applications retrieved successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    example: [
                        'success' => true,
                        'message' => 'Seller verification applications retrieved successfully.',
                        'data' => [
                            'current_page' => 1,
                            'data' => [
                                [
                                    'public_id' => 'application-public-id',
                                    'version' => 1,
                                    'status' => 'submitted',
                                    'documents_count' => 2,
                                    'seller_profile' => [
                                        'public_id' => 'seller-public-id',
                                        'legal_business_name' => 'RushPi Electronics Ltd',
                                        'trading_name' => 'RushPi Electronics',
                                    ],
                                ],
                            ],
                            'per_page' => 20,
                            'total' => 1,
                        ],
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
                description: 'Only administrators may access seller verification.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid query parameters.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function index(): void
    {
    }

    #[OA\Get(
        path: '/admin/seller-applications/{sellerApplication}',
        operationId: 'adminShowSellerApplication',
        summary: 'Show one seller verification application',
        description: 'Returns a complete application with seller profile, addresses, members, documents and review history.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application retrieved successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerApplicationResponse'
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
                description: 'Seller application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
        ]
    )]
    public function show(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/start-review',
        operationId: 'adminStartSellerApplicationReview',
        summary: 'Start reviewing a seller application',
        description: 'Moves a submitted application to under review and assigns the authenticated administrator as reviewer.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Review started successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerApplicationResponse'
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
                description: 'Seller application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'The application is not in a status that permits review.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function startReview(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/request-information',
        operationId: 'adminRequestSellerInformation',
        summary: 'Request additional information from a seller',
        description: 'Returns the application to the seller for corrections or additional information.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'message',
                ],
                properties: [
                    new OA\Property(
                        property: 'message',
                        type: 'string',
                        minLength: 10,
                        maxLength: 3000,
                        example: 'Please upload a clearer business registration certificate.'
                    ),
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'The current scan is not readable.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Additional information requested successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerApplicationResponse'
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
                description: 'Seller application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 409,
                description: 'The application is assigned to another reviewer.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ConflictResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or the application status does not permit this action.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function requestInformation(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/approve',
        operationId: 'adminApproveSellerApplication',
        summary: 'Approve a seller application',
        description: 'Approves the application after all required documents have been approved and are not expired.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'All required documents were verified.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller application approved successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerApplicationResponse'
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
                description: 'Seller application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 409,
                description: 'The application is assigned to another reviewer.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ConflictResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Application status is invalid or required documents have not been approved.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function approve(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/reject',
        operationId: 'adminRejectSellerApplication',
        summary: 'Reject a seller application',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'reason',
                ],
                properties: [
                    new OA\Property(
                        property: 'reason',
                        type: 'string',
                        minLength: 10,
                        maxLength: 3000,
                        example: 'The submitted registration information could not be verified.'
                    ),
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'Verification was attempted using the official registry.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller application rejected successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerApplicationResponse'
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
                description: 'Seller application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 409,
                description: 'The application is assigned to another reviewer.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ConflictResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or the application status does not permit rejection.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function reject(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/documents/{sellerDocument}/approve',
        operationId: 'adminApproveSellerDocument',
        summary: 'Approve a seller verification document',
        description: 'Only clean, successfully scanned and unexpired documents may be approved.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
            new OA\Parameter(
                name: 'sellerDocument',
                description: 'Seller document public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'document-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'Document verified successfully.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller document approved successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerDocumentResponse'
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
                description: 'Application or document not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 409,
                description: 'The application is assigned to another reviewer.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ConflictResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Document is not clean, is expired or cannot currently be approved.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function approveDocument(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-applications/{sellerApplication}/documents/{sellerDocument}/reject',
        operationId: 'adminRejectSellerDocument',
        summary: 'Reject a seller verification document',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
            new OA\Parameter(
                name: 'sellerDocument',
                description: 'Seller document public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'document-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'reason',
                ],
                properties: [
                    new OA\Property(
                        property: 'reason',
                        type: 'string',
                        minLength: 5,
                        maxLength: 2000,
                        example: 'The uploaded document is not readable.'
                    ),
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'Seller should upload a higher-resolution scan.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller document rejected successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerDocumentResponse'
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
                description: 'Application or document not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 409,
                description: 'The application is assigned to another reviewer.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ConflictResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or the document cannot currently be rejected.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function rejectDocument(): void
    {
    }

    #[OA\Get(
        path: '/admin/seller-applications/{sellerApplication}/documents/{sellerDocument}/download',
        operationId: 'adminDownloadSellerDocument',
        summary: 'Download a private seller document',
        description: 'Downloads the private document and records an access-log entry.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'application-public-id'
            ),
            new OA\Parameter(
                name: 'sellerDocument',
                description: 'Seller document public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'document-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Private seller document.',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(
                        type: 'string',
                        format: 'binary'
                    )
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
                description: 'Application, document or stored file not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
        ]
    )]
    public function downloadDocument(): void
    {
    }

    #[OA\Post(
        path: '/admin/seller-profiles/{sellerProfile}/suspend',
        operationId: 'adminSuspendSellerProfile',
        summary: 'Suspend an approved seller',
        description: 'Suspends an approved seller profile and its approved verification application.',
        tags: ['Seller Verification Admin'],
        security: [
            ['sanctum' => []],
        ],
        parameters: [
            new OA\Parameter(
                name: 'sellerProfile',
                description: 'Seller profile public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-profile-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'reason',
                ],
                properties: [
                    new OA\Property(
                        property: 'reason',
                        type: 'string',
                        minLength: 10,
                        maxLength: 3000,
                        example: 'The seller account was suspended following a compliance investigation.'
                    ),
                    new OA\Property(
                        property: 'internal_notes',
                        type: 'string',
                        maxLength: 2000,
                        nullable: true,
                        example: 'Suspension approved by the compliance administrator.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller suspended successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerProfileResponse'
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
                description: 'Seller profile or approved application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed or the seller is not currently approved.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function suspend(): void
    {
    }
}