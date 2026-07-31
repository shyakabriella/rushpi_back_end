<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;
use Throwable;

final class HealthController extends Controller
{
    #[OA\Get(
        path: '/v1/system/health',
        operationId: 'getSystemHealth',
        summary: 'Check API health',
        description: 'Confirms that the RushPi Laravel application is running.',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application is running.',
                content: new OA\JsonContent(
                    example: [
                        'success' => true,
                        'message' => 'RushPi API is healthy.',
                        'data' => [
                            'status' => 'up',
                            'version' => 'v1',
                            'timestamp' => '2026-07-31T17:00:00+00:00',
                        ],
                    ]
                )
            ),
        ]
    )]
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'RushPi API is healthy.',
            'data' => [
                'status' => 'up',
                'version' => 'v1',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/v1/system/readiness',
        operationId: 'getSystemReadiness',
        summary: 'Check API readiness',
        description: 'Checks the Laravel application, MySQL database and Redis connection.',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All required services are available.',
                content: new OA\JsonContent(
                    example: [
                        'success' => true,
                        'message' => 'RushPi API is ready.',
                        'data' => [
                            'ready' => true,
                            'checks' => [
                                'application' => true,
                                'database' => true,
                                'redis' => true,
                            ],
                        ],
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'One or more required services are unavailable.',
                content: new OA\JsonContent(
                    example: [
                        'success' => false,
                        'message' => 'RushPi API is not ready.',
                        'data' => [
                            'ready' => false,
                            'checks' => [
                                'application' => true,
                                'database' => false,
                                'redis' => true,
                            ],
                        ],
                    ]
                )
            ),
        ]
    )]
    public function readiness(): JsonResponse
    {
        $checks = [
            'application' => true,
            'database' => false,
            'redis' => false,
        ];

        try {
            DB::select('SELECT 1');
            $checks['database'] = true;
        } catch (Throwable) {
            $checks['database'] = false;
        }

        try {
            $redisResult = Redis::connection()->ping();

            $checks['redis'] = $redisResult === true
                || strtoupper((string) $redisResult) === 'PONG'
                || (string) $redisResult === '1';
        } catch (Throwable) {
            $checks['redis'] = false;
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'success' => $ready,
            'message' => $ready
                ? 'RushPi API is ready.'
                : 'RushPi API is not ready.',
            'data' => [
                'ready' => $ready,
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $ready ? 200 : 503);
    }
}
