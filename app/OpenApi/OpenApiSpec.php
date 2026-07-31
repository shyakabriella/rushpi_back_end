<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '0.1.0',
    title: 'RushPi Marketplace API',
    description: 'Backend API contract for the RushPi verified electronics marketplace.',
    contact: new OA\Contact(
        name: 'RushPi Backend Team'
    )
)]
#[OA\Server(
    url: '/api/v1',
    description: 'RushPi API Version 1'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Bearer token',
    description: 'Enter the authentication token without adding the word Bearer.'
)]
#[OA\Tag(
    name: 'System',
    description: 'Application health and infrastructure-readiness endpoints.'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Registration, login, verification, logout and current-user endpoints.'
)]
final class OpenApiSpec
{
}
