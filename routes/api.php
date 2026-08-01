<?php

declare(strict_types=1);

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Admin\BrandController;
use App\Http\Controllers\API\V1\Admin\CategoryController;
use App\Http\Controllers\API\V1\Admin\SellerVerificationController;
use App\Http\Controllers\API\V1\Seller\SellerDocumentController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use App\Http\Controllers\API\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Version 1 system routes
|--------------------------------------------------------------------------
|
| These routes are public because monitoring services may need to check
| whether the RushPi API and its dependencies are working correctly.
|
*/

Route::prefix('v1/system')
    ->name('api.v1.system.')
    ->group(function (): void {
        Route::get(
            'health',
            [HealthController::class, 'health']
        )->name('health');

        Route::get(
            'readiness',
            [HealthController::class, 'readiness']
        )->name('readiness');
    });

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/

Route::controller(RegisterController::class)
    ->group(function (): void {
        Route::post(
            'register',
            'register'
        )->name('api.auth.register');

        Route::post(
            'login',
            'login'
        )->name('api.auth.login');
    });

/*
|--------------------------------------------------------------------------
| Public catalog routes
|--------------------------------------------------------------------------
|
| Public category, brand and approved product routes will be added here
| after their public controllers have been implemented.
|
*/

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
|
| Every route inside this group requires a valid Laravel Sanctum token.
|
*/

Route::middleware('auth:sanctum')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Authenticated account routes
        |--------------------------------------------------------------------------
        */

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
        | Seller routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('seller')
            ->name('api.seller.')
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Seller profile onboarding
                |--------------------------------------------------------------------------
                |
                | These routes must not use seller.approved because a new seller
                | needs them before their business is approved.
                |
                */

                Route::get(
                    'profiles',
                    [SellerProfileController::class, 'index']
                )->name('profiles.index');

                Route::post(
                    'profiles',
                    [SellerProfileController::class, 'store']
                )->name('profiles.store');

                Route::get(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'show']
                )->name('profiles.show');

                Route::put(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'update']
                )->name('profiles.update');

                Route::patch(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'update']
                )->name('profiles.patch');

                /*
                |--------------------------------------------------------------------------
                | Seller application documents
                |--------------------------------------------------------------------------
                |
                | Documents are stored privately. Access must always be checked
                | inside SellerDocumentController.
                |
                */

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    .'/applications/{sellerApplication:public_id}'
                    .'/documents',
                    [SellerDocumentController::class, 'index']
                )->name('applications.documents.index');

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    .'/applications/{sellerApplication:public_id}'
                    .'/documents',
                    [SellerDocumentController::class, 'store']
                )
                    ->middleware('throttle:10,1')
                    ->name('applications.documents.store');

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    .'/applications/{sellerApplication:public_id}'
                    .'/documents/{sellerDocument:public_id}/download',
                    [SellerDocumentController::class, 'download']
                )->name('applications.documents.download');

                Route::delete(
                    'profiles/{sellerProfile:public_id}'
                    .'/applications/{sellerApplication:public_id}'
                    .'/documents/{sellerDocument:public_id}',
                    [SellerDocumentController::class, 'destroy']
                )->name('applications.documents.destroy');

                /*
                |--------------------------------------------------------------------------
                | Submit seller application
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    .'/applications/{sellerApplication:public_id}'
                    .'/submit',
                    [SellerDocumentController::class, 'submit']
                )
                    ->middleware('throttle:5,1')
                    ->name('applications.submit');

                /*
                |--------------------------------------------------------------------------
                | Approved seller selling routes
                |--------------------------------------------------------------------------
                |
                | Product, variant, media, price and inventory routes will be
                | added here as their controllers are implemented.
                |
                */

                Route::prefix(
                    'profiles/{sellerProfile:public_id}'
                )
                    ->middleware('seller.approved')
                    ->name('selling.')
                    ->group(function (): void {
                        /*
                         * Upcoming Day 3 routes:
                         *
                         * Route::apiResource(
                         *     'products',
                         *     SellerProductController::class
                         * );
                         */
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | Administrator routes
        |--------------------------------------------------------------------------
        |
        | Form requests and controllers verify that the authenticated user has
        | the admin or superadmin role.
        |
        */

        Route::prefix('admin')
            ->name('api.admin.')
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Catalog administration
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'categories',
                    CategoryController::class
                );

                Route::apiResource(
                    'brands',
                    BrandController::class
                );

                /*
                |--------------------------------------------------------------------------
                | Seller verification applications
                |--------------------------------------------------------------------------
                */

                Route::get(
                    'seller-applications',
                    [SellerVerificationController::class, 'index']
                )->name('seller-applications.index');

                Route::get(
                    'seller-applications/{sellerApplication:public_id}',
                    [SellerVerificationController::class, 'show']
                )->name('seller-applications.show');

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/start-review',
                    [
                        SellerVerificationController::class,
                        'startReview',
                    ]
                )->name('seller-applications.start-review');

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/request-information',
                    [
                        SellerVerificationController::class,
                        'requestInformation',
                    ]
                )->name(
                    'seller-applications.request-information'
                );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/approve',
                    [
                        SellerVerificationController::class,
                        'approve',
                    ]
                )->name('seller-applications.approve');

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/reject',
                    [
                        SellerVerificationController::class,
                        'reject',
                    ]
                )->name('seller-applications.reject');

                /*
                |--------------------------------------------------------------------------
                | Seller verification documents
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/documents/{sellerDocument:public_id}/approve',
                    [
                        SellerVerificationController::class,
                        'approveDocument',
                    ]
                )->name(
                    'seller-applications.documents.approve'
                );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/documents/{sellerDocument:public_id}/reject',
                    [
                        SellerVerificationController::class,
                        'rejectDocument',
                    ]
                )->name(
                    'seller-applications.documents.reject'
                );

                Route::get(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/documents/{sellerDocument:public_id}/download',
                    [
                        SellerVerificationController::class,
                        'downloadDocument',
                    ]
                )->name(
                    'seller-applications.documents.download'
                );

                /*
                |--------------------------------------------------------------------------
                | Seller suspension
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'seller-profiles/{sellerProfile:public_id}'
                    .'/suspend',
                    [
                        SellerVerificationController::class,
                        'suspend',
                    ]
                )->name('seller-profiles.suspend');
            });
    });