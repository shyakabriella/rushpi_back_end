<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureApprovedSeller;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(
        function (Middleware $middleware): void {
            /*
            |--------------------------------------------------------------------------
            | Custom middleware aliases
            |--------------------------------------------------------------------------
            |
            | The seller.approved middleware protects selling functions.
            | It allows access only when:
            |
            | 1. The user is authenticated.
            | 2. The user belongs to the selected seller profile.
            | 3. The seller profile has been approved.
            |
            */

            $middleware->alias([
                'seller.approved' =>
                    EnsureApprovedSeller::class,
            ]);
        }
    )
    ->withExceptions(
        function (Exceptions $exceptions): void {
            //
        }
    )
    ->create();