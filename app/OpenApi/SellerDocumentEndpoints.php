<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

final class SellerDocumentEndpoints
{
    #[OA\Get(
        path: '/seller/profiles/{sellerProfile}/applications/{sellerApplication}/documents',
        operationId: 'sellerListVerificationDocuments',
        summary: 'List seller verification documents',
        description: 'Returns documents belonging to the selected seller application. The authenticated user must belong to the seller business.',
        tags: ['Seller Documents'],
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
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-application-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller documents retrieved successfully.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerDocumentCollectionResponse'
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
                description: 'The authenticated user does not belong to the seller business.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile or application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
        ]
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/seller/profiles/{sellerProfile}/applications/{sellerApplication}/documents',
        operationId: 'sellerUploadVerificationDocument',
        summary: 'Upload a private seller verification document',
        description: 'Uploads a private verification document to quarantine. Only the seller owner can upload documents. The application must be editable.',
        tags: ['Seller Documents'],
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
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-application-public-id'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [
                        'document_type',
                        'document',
                    ],
                    properties: [
                        new OA\Property(
                            property: 'document_type',
                            description: 'Type of verification document.',
                            type: 'string',
                            enum: [
                                'business_registration_certificate',
                                'tax_certificate',
                                'authorized_representative_id',
                                'trading_license',
                                'payout_account_proof',
                                'proof_of_address',
                                'store_photo',
                                'other',
                            ],
                            example: 'business_registration_certificate'
                        ),
                        new OA\Property(
                            property: 'document',
                            description: 'PDF, JPG, JPEG or PNG document. Maximum size is 10 MB.',
                            type: 'string',
                            format: 'binary'
                        ),
                        new OA\Property(
                            property: 'issued_at',
                            description: 'Date the document was issued.',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2026-01-15'
                        ),
                        new OA\Property(
                            property: 'expires_at',
                            description: 'Document expiry date. It must not be earlier than today or the issue date.',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2027-01-15'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Document uploaded and placed in quarantine successfully.',
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
                description: 'Only the seller owner can upload documents.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile or application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed, duplicate document detected or application cannot be edited.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many upload attempts.'
            ),
            new OA\Response(
                response: 500,
                description: 'The document could not be stored.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SellerErrorResponse'
                )
            ),
        ]
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/seller/profiles/{sellerProfile}/applications/{sellerApplication}/documents/{sellerDocument}/download',
        operationId: 'sellerDownloadVerificationDocument',
        summary: 'Download a private seller verification document',
        description: 'Securely downloads a private verification document and records a document access log.',
        tags: ['Seller Documents'],
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
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-application-public-id'
            ),
            new OA\Parameter(
                name: 'sellerDocument',
                description: 'Seller document public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-document-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Private seller verification document.',
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
                description: 'The authenticated user does not belong to the seller business.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Profile, application, document or stored file not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
        ]
    )]
    public function download(): void
    {
    }

    #[OA\Delete(
        path: '/seller/profiles/{sellerProfile}/applications/{sellerApplication}/documents/{sellerDocument}',
        operationId: 'sellerDeleteVerificationDocument',
        summary: 'Delete a seller verification document',
        description: 'Deletes a document before application submission. Approved documents cannot be deleted.',
        tags: ['Seller Documents'],
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
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-application-public-id'
            ),
            new OA\Parameter(
                name: 'sellerDocument',
                description: 'Seller document public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-document-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller document deleted successfully.',
                content: new OA\JsonContent(
                    type: 'object',
                    example: [
                        'success' => true,
                        'message' => 'Seller document deleted successfully.',
                        'data' => null,
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
                description: 'Only the seller owner can delete documents.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Profile, application or document not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Application cannot be edited or the document is already approved.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
        ]
    )]
    public function destroy(): void
    {
    }

    #[OA\Post(
        path: '/seller/profiles/{sellerProfile}/applications/{sellerApplication}/submit',
        operationId: 'sellerSubmitVerificationApplication',
        summary: 'Submit a seller verification application',
        description: 'Submits a completed seller application for administrator review. Required documents must be clean, valid and not expired.',
        tags: ['Seller Documents'],
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
            new OA\Parameter(
                name: 'sellerApplication',
                description: 'Seller application public_id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string'
                ),
                example: 'seller-application-public-id'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller application submitted successfully.',
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
                description: 'Only the seller owner can submit the application.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ForbiddenResponse'
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Seller profile or application not found.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NotFoundResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Required documents are missing, infected, expired or still waiting for scanning.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationErrorResponse'
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many submission attempts.'
            ),
        ]
    )]
    public function submit(): void
    {
    }
}