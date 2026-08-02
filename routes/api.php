<?php

declare(strict_types=1);

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Admin\BrandController;
use App\Http\Controllers\API\V1\Admin\CategoryController;
use App\Http\Controllers\API\V1\Admin\ProductModerationController;
use App\Http\Controllers\API\V1\Admin\SellerVerificationController;
use App\Http\Controllers\API\V1\Admin\SpecificationDefinitionController;
use App\Http\Controllers\API\V1\Public\CatalogController;
use App\Http\Controllers\API\V1\Seller\InventoryController;
use App\Http\Controllers\API\V1\Seller\ProductController;
use App\Http\Controllers\API\V1\Seller\ProductMediaController;
use App\Http\Controllers\API\V1\Seller\ProductVariantController;
use App\Http\Controllers\API\V1\Seller\ProductVariantPriceController;
use App\Http\Controllers\API\V1\Seller\SellerDocumentController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use App\Http\Controllers\API\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System routes
|--------------------------------------------------------------------------
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
| Public customer catalog
|--------------------------------------------------------------------------
|
| These endpoints require no login.
|
| Only approved products belonging to approved sellers are returned.
| Private seller information, cost prices and moderation notes are excluded.
|
*/

Route::prefix('catalog')
    ->name('api.catalog.')
    ->middleware('throttle:120,1')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Public categories
        |--------------------------------------------------------------------------
        */

        Route::get(
            'categories',
            [CatalogController::class, 'categories']
        )->name('categories.index');

        /*
        |--------------------------------------------------------------------------
        | Public brands
        |--------------------------------------------------------------------------
        */

        Route::get(
            'brands',
            [CatalogController::class, 'brands']
        )->name('brands.index');

        /*
        |--------------------------------------------------------------------------
        | Public products
        |--------------------------------------------------------------------------
        */

        Route::get(
            'products',
            [CatalogController::class, 'index']
        )->name('products.index');

        Route::get(
            'products/{product}',
            [CatalogController::class, 'show']
        )
            ->where(
                'product',
                '[A-Za-z0-9\-]+'
            )
            ->name('products.show');
    });

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
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
                | Seller verification documents
                |--------------------------------------------------------------------------
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
                | Submit seller verification application
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
                */

                Route::prefix(
                    'profiles/{sellerProfile:public_id}'
                )
                    ->middleware('seller.approved')
                    ->scopeBindings()
                    ->name('selling.')
                    ->group(function (): void {
                        /*
                        |--------------------------------------------------------------------------
                        | Seller products
                        |--------------------------------------------------------------------------
                        */

                        Route::apiResource(
                            'products',
                            ProductController::class
                        )->parameters([
                            'products' => 'product',
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Submit product for moderation
                        |--------------------------------------------------------------------------
                        */

                        Route::post(
                            'products/{product:public_id}/submit',
                            [
                                ProductController::class,
                                'submitForReview',
                            ]
                        )
                            ->middleware('throttle:10,1')
                            ->name('products.submit');

                        /*
                        |--------------------------------------------------------------------------
                        | Product variants
                        |--------------------------------------------------------------------------
                        */

                        Route::apiResource(
                            'products.variants',
                            ProductVariantController::class
                        )->parameters([
                            'products' => 'product',
                            'variants' => 'variant',
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Product variant pricing
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'show',
                            ]
                        )->name(
                            'products.variants.price.show'
                        );

                        Route::post(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'store',
                            ]
                        )
                            ->middleware('throttle:30,1')
                            ->name(
                                'products.variants.price.store'
                            );

                        Route::put(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'update',
                            ]
                        )->name(
                            'products.variants.price.update'
                        );

                        Route::patch(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'update',
                            ]
                        )->name(
                            'products.variants.price.patch'
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Product variant inventory
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}/inventory',
                            [
                                InventoryController::class,
                                'show',
                            ]
                        )->name(
                            'products.variants.inventory.show'
                        );

                        Route::post(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}'
                            .'/inventory/adjust',
                            [
                                InventoryController::class,
                                'adjust',
                            ]
                        )
                            ->middleware('throttle:60,1')
                            ->name(
                                'products.variants.inventory.adjust'
                            );

                        Route::put(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}'
                            .'/inventory/settings',
                            [
                                InventoryController::class,
                                'updateSettings',
                            ]
                        )->name(
                            'products.variants.inventory.settings.update'
                        );

                        Route::patch(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}'
                            .'/inventory/settings',
                            [
                                InventoryController::class,
                                'updateSettings',
                            ]
                        )->name(
                            'products.variants.inventory.settings.patch'
                        );

                        Route::get(
                            'products/{product:public_id}'
                            .'/variants/{variant:public_id}'
                            .'/inventory/movements',
                            [
                                InventoryController::class,
                                'movements',
                            ]
                        )->name(
                            'products.variants.inventory.movements'
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Product media
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}/media',
                            [
                                ProductMediaController::class,
                                'index',
                            ]
                        )->name('products.media.index');

                        Route::post(
                            'products/{product:public_id}/media',
                            [
                                ProductMediaController::class,
                                'store',
                            ]
                        )
                            ->middleware('throttle:20,1')
                            ->name('products.media.store');

                        /*
                         * Keep this fixed path before routes containing
                         * the dynamic {media} route parameter.
                         */

                        Route::patch(
                            'products/{product:public_id}/media/reorder',
                            [
                                ProductMediaController::class,
                                'reorder',
                            ]
                        )->name('products.media.reorder');

                        Route::patch(
                            'products/{product:public_id}'
                            .'/media/{media:public_id}/primary',
                            [
                                ProductMediaController::class,
                                'setPrimary',
                            ]
                        )->name('products.media.primary');

                        Route::delete(
                            'products/{product:public_id}'
                            .'/media/{media:public_id}',
                            [
                                ProductMediaController::class,
                                'destroy',
                            ]
                        )->name('products.media.destroy');
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | Administrator routes
        |--------------------------------------------------------------------------
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
                | Specification definition administration
                |--------------------------------------------------------------------------
                */

                Route::prefix('specification-definitions')
                    ->name('specification-definitions.')
                    ->controller(
                        SpecificationDefinitionController::class
                    )
                    ->group(function (): void {
                        Route::get(
                            '/',
                            'index'
                        )->name('index');

                        Route::post(
                            '/',
                            'store'
                        )
                            ->middleware('throttle:30,1')
                            ->name('store');

                        /*
                         * Keep fixed action routes before the dynamic
                         * specification-definition routes.
                         */

                        Route::patch(
                            '/{specificationDefinition:public_id}/activate',
                            'activate'
                        )
                            ->middleware('throttle:30,1')
                            ->name('activate');

                        Route::patch(
                            '/{specificationDefinition:public_id}/deactivate',
                            'deactivate'
                        )
                            ->middleware('throttle:30,1')
                            ->name('deactivate');

                        Route::get(
                            '/{specificationDefinition:public_id}',
                            'show'
                        )->name('show');

                        Route::put(
                            '/{specificationDefinition:public_id}',
                            'update'
                        )->name('update');

                        Route::patch(
                            '/{specificationDefinition:public_id}',
                            'update'
                        )->name('patch');

                        Route::delete(
                            '/{specificationDefinition:public_id}',
                            'destroy'
                        )->name('destroy');
                    });

                /*
                |--------------------------------------------------------------------------
                | Product moderation
                |--------------------------------------------------------------------------
                */

                Route::get(
                    'products',
                    [
                        ProductModerationController::class,
                        'index',
                    ]
                )->name('products.index');

                Route::get(
                    'products/{product:public_id}',
                    [
                        ProductModerationController::class,
                        'show',
                    ]
                )->name('products.show');

                Route::post(
                    'products/{product:public_id}/moderate',
                    [
                        ProductModerationController::class,
                        'moderate',
                    ]
                )
                    ->middleware('throttle:30,1')
                    ->name('products.moderate');

                /*
                |--------------------------------------------------------------------------
                | Seller verification applications
                |--------------------------------------------------------------------------
                */

                Route::get(
                    'seller-applications',
                    [
                        SellerVerificationController::class,
                        'index',
                    ]
                )->name('seller-applications.index');

                Route::get(
                    'seller-applications/{sellerApplication:public_id}',
                    [
                        SellerVerificationController::class,
                        'show',
                    ]
                )->name('seller-applications.show');

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    .'/start-review',
                    [
                        SellerVerificationController::class,
                        'startReview',
                    ]
                )->name(
                    'seller-applications.start-review'
                );

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