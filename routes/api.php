<?php

declare(strict_types=1);

use App\Http\Controllers\API\Admin\CategoryController;
use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/

Route::controller(RegisterController::class)->group(function (): void {
    Route::post('register', 'register');
    Route::post('login', 'login');
});

/*
|--------------------------------------------------------------------------
| Public category routes
|--------------------------------------------------------------------------
*/

Route::get(
    'categories',
    [CategoryController::class, 'publicIndex']
);

/*
|--------------------------------------------------------------------------
| Authenticated user routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get(
        'me',
        [RegisterController::class, 'me']
    );

    Route::post(
        'logout',
        [RegisterController::class, 'logout']
    );

    /*
    |--------------------------------------------------------------------------
    | Seller profile routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('seller')->group(function (): void {
        Route::get(
            'profiles',
            [SellerProfileController::class, 'index']
        );

        Route::post(
            'profiles',
            [SellerProfileController::class, 'store']
        );

        Route::get(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'show']
        );

        Route::put(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'update']
        );

        Route::patch(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'update']
        );
    });
});

/*
|--------------------------------------------------------------------------
| Administrator routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('admin')
    ->group(function (): void {
        Route::apiResource(
            'categories',
            CategoryController::class
        );
    });