<?php

declare(strict_types=1);

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use App\Http\Controllers\API\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Version 1 system routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/system')->group(function (): void {
    Route::get(
        'health',
        [HealthController::class, 'health']
    )->name('api.v1.system.health');

    Route::get(
        'readiness',
        [HealthController::class, 'readiness']
    )->name('api.v1.system.readiness');
});

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/

Route::controller(RegisterController::class)->group(function (): void {
    Route::post('register', 'register')
        ->name('api.auth.register');

    Route::post('login', 'login')
        ->name('api.auth.login');
});

/*
|--------------------------------------------------------------------------
| Public category routes
|--------------------------------------------------------------------------
|
| Temporarily disabled because this controller does not currently exist:
| App\Http\Controllers\API\Admin\CategoryController
|
| Restore these routes after CategoryController is implemented.
|
*/

// Route::get(
//     'categories',
//     [CategoryController::class, 'publicIndex']
// );

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get(
        'me',
        [RegisterController::class, 'me']
    )->name('api.auth.me');

    Route::post(
        'logout',
        [RegisterController::class, 'logout']
    )->name('api.auth.logout');

    /*
    |--------------------------------------------------------------------------
    | Seller profile routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('seller')->group(function (): void {
        Route::get(
            'profiles',
            [SellerProfileController::class, 'index']
        )->name('api.seller.profiles.index');

        Route::post(
            'profiles',
            [SellerProfileController::class, 'store']
        )->name('api.seller.profiles.store');

        Route::get(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'show']
        )->name('api.seller.profiles.show');

        Route::put(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'update']
        )->name('api.seller.profiles.update');

        Route::patch(
            'profiles/{sellerProfile}',
            [SellerProfileController::class, 'update']
        )->name('api.seller.profiles.patch');
    });
});

/*
|--------------------------------------------------------------------------
| Administrator routes
|--------------------------------------------------------------------------
|
| Category administration is temporarily disabled until the missing
| CategoryController is implemented.
|
*/

// Route::middleware('auth:sanctum')
//     ->prefix('admin')
//     ->group(function (): void {
//         Route::apiResource(
//             'categories',
//             CategoryController::class
//         );
//     });
