<?php

declare(strict_types=1);

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Admin\BrandController;
use App\Http\Controllers\API\V1\Admin\CategoryController;
use App\Http\Controllers\API\V1\Admin\CategorySpecificationController;
use App\Http\Controllers\API\V1\Admin\ProductModerationController;
use App\Http\Controllers\API\V1\Admin\SellerVerificationController;
use App\Http\Controllers\API\V1\Admin\SpecificationDefinitionController;
use App\Http\Controllers\API\V1\Public\CatalogController;
use App\Http\Controllers\API\V1\Seller\InventoryController;
use App\Http\Controllers\API\V1\Seller\ProductController;
use App\Http\Controllers\API\V1\Seller\ProductMediaController;
use App\Http\Controllers\API\V1\Seller\ProductReturnPolicyController;
use App\Http\Controllers\API\V1\Seller\ProductVariantController;
use App\Http\Controllers\API\V1\Seller\ProductVariantPriceController;
use App\Http\Controllers\API\V1\Seller\SellerDocumentController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use App\Http\Controllers\API\V1\System\HealthController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

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
        )
            ->middleware('throttle:20,1')
            ->name('api.auth.register');

        Route::post(
            'login',
            'login'
        )
            ->middleware('throttle:20,1')
            ->name('api.auth.login');
    });

/*
|--------------------------------------------------------------------------
| Public marketplace catalog
|--------------------------------------------------------------------------
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
            ->middleware(
                RoleMiddleware::class
                . ':'
                . User::ROLE_SELLER
            )
            ->name('api.seller.')
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Seller profile
                |--------------------------------------------------------------------------
                |
                | Seller profiles can be created and completed before approval.
                |
                | POST profiles/{sellerProfile} is intentionally provided for
                | multipart/form-data updates containing logo / cover images.
                |
                */

                Route::get(
                    'profiles',
                    [SellerProfileController::class, 'index']
                )
                    ->name('profiles.index');

                Route::post(
                    'profiles',
                    [SellerProfileController::class, 'store']
                )
                    ->middleware('throttle:20,1')
                    ->name('profiles.store');

                /*
                 * Multipart seller profile update.
                 *
                 * Useful when the frontend sends FormData
                 * containing logo and cover_image.
                 */
                Route::post(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'update']
                )
                    ->middleware('throttle:30,1')
                    ->name('profiles.update.multipart');

                Route::get(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'show']
                )
                    ->name('profiles.show');

                Route::put(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'update']
                )
                    ->middleware('throttle:30,1')
                    ->name('profiles.update');

                Route::patch(
                    'profiles/{sellerProfile:public_id}',
                    [SellerProfileController::class, 'update']
                )
                    ->middleware('throttle:30,1')
                    ->name('profiles.patch');

                /*
                |--------------------------------------------------------------------------
                | Seller verification documents
                |--------------------------------------------------------------------------
                */

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents',
                    [SellerDocumentController::class, 'index']
                )
                    ->name(
                        'applications.documents.index'
                    );

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents',
                    [SellerDocumentController::class, 'store']
                )
                    ->middleware('throttle:10,1')
                    ->name(
                        'applications.documents.store'
                    );

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}/download',
                    [SellerDocumentController::class, 'download']
                )
                    ->name(
                        'applications.documents.download'
                    );

                Route::delete(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}',
                    [SellerDocumentController::class, 'destroy']
                )
                    ->middleware('throttle:20,1')
                    ->name(
                        'applications.documents.destroy'
                    );

                /*
                |--------------------------------------------------------------------------
                | Submit seller verification application
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/submit',
                    [SellerDocumentController::class, 'submit']
                )
                    ->middleware('throttle:5,1')
                    ->name(
                        'applications.submit'
                    );

                /*
                |--------------------------------------------------------------------------
                | Approved seller routes
                |--------------------------------------------------------------------------
                */

                Route::prefix(
                    'profiles/{sellerProfile:public_id}'
                )
                    ->middleware(
                        'seller.approved'
                    )
                    ->scopeBindings()
                    ->name('selling.')
                    ->group(function (): void {
                        /*
                        |--------------------------------------------------------------------------
                        | Products
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
                            ->middleware(
                                'throttle:10,1'
                            )
                            ->name(
                                'products.submit'
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | Product return policy
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}/return-policy',
                            [
                                ProductReturnPolicyController::class,
                                'show',
                            ]
                        )
                            ->name(
                                'products.return-policy.show'
                            );

                        Route::post(
                            'products/{product:public_id}/return-policy',
                            [
                                ProductReturnPolicyController::class,
                                'upsert',
                            ]
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'products.return-policy.store'
                            );

                        Route::put(
                            'products/{product:public_id}/return-policy',
                            [
                                ProductReturnPolicyController::class,
                                'upsert',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.return-policy.update'
                            );

                        Route::patch(
                            'products/{product:public_id}/return-policy',
                            [
                                ProductReturnPolicyController::class,
                                'upsert',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.return-policy.patch'
                            );

                        Route::delete(
                            'products/{product:public_id}/return-policy',
                            [
                                ProductReturnPolicyController::class,
                                'destroy',
                            ]
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'products.return-policy.destroy'
                            );

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
                            . '/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'show',
                            ]
                        )
                            ->name(
                                'products.variants.price.show'
                            );

                        Route::post(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'store',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.variants.price.store'
                            );

                        Route::put(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'update',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.variants.price.update'
                            );

                        Route::patch(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}/price',
                            [
                                ProductVariantPriceController::class,
                                'update',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.variants.price.patch'
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | Product variant inventory
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}/inventory',
                            [
                                InventoryController::class,
                                'show',
                            ]
                        )
                            ->name(
                                'products.variants.inventory.show'
                            );

                        Route::post(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/adjust',
                            [
                                InventoryController::class,
                                'adjust',
                            ]
                        )
                            ->middleware(
                                'throttle:60,1'
                            )
                            ->name(
                                'products.variants.inventory.adjust'
                            );

                        Route::put(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/settings',
                            [
                                InventoryController::class,
                                'updateSettings',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.variants.inventory.settings.update'
                            );

                        Route::patch(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/settings',
                            [
                                InventoryController::class,
                                'updateSettings',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.variants.inventory.settings.patch'
                            );

                        Route::get(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/movements',
                            [
                                InventoryController::class,
                                'movements',
                            ]
                        )
                            ->name(
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
                        )
                            ->name(
                                'products.media.index'
                            );

                        Route::post(
                            'products/{product:public_id}/media',
                            [
                                ProductMediaController::class,
                                'store',
                            ]
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'products.media.store'
                            );

                        /*
                         * Keep this fixed route before
                         * the dynamic {media} parameter.
                         */
                        Route::patch(
                            'products/{product:public_id}/media/reorder',
                            [
                                ProductMediaController::class,
                                'reorder',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.media.reorder'
                            );

                        Route::post(
                            'products/{product:public_id}'
                            . '/media/{media:public_id}/retry-processing',
                            [
                                ProductMediaController::class,
                                'retryProcessing',
                            ]
                        )
                            ->middleware(
                                'throttle:10,1'
                            )
                            ->name(
                                'products.media.retry-processing'
                            );

                        Route::patch(
                            'products/{product:public_id}'
                            . '/media/{media:public_id}/primary',
                            [
                                ProductMediaController::class,
                                'setPrimary',
                            ]
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'products.media.primary'
                            );

                        Route::delete(
                            'products/{product:public_id}'
                            . '/media/{media:public_id}',
                            [
                                ProductMediaController::class,
                                'destroy',
                            ]
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'products.media.destroy'
                            );
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | Administrator routes
        |--------------------------------------------------------------------------
        */

        Route::prefix('admin')
            ->middleware(
                RoleMiddleware::class
                . ':'
                . User::ROLE_ADMIN
            )
            ->name('api.admin.')
            ->group(function (): void {
                /*
                |--------------------------------------------------------------------------
                | Specification definitions
                |--------------------------------------------------------------------------
                */

                Route::prefix(
                    'specification-definitions'
                )
                    ->name(
                        'specification-definitions.'
                    )
                    ->controller(
                        SpecificationDefinitionController::class
                    )
                    ->group(function (): void {
                        Route::get(
                            '/',
                            'index'
                        )
                            ->name(
                                'index'
                            );

                        Route::post(
                            '/',
                            'store'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'store'
                            );

                        Route::patch(
                            '/{specificationDefinition:public_id}/activate',
                            'activate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'activate'
                            );

                        Route::patch(
                            '/{specificationDefinition:public_id}/deactivate',
                            'deactivate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'deactivate'
                            );

                        Route::get(
                            '/{specificationDefinition:public_id}',
                            'show'
                        )
                            ->name(
                                'show'
                            );

                        Route::put(
                            '/{specificationDefinition:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'update'
                            );

                        Route::patch(
                            '/{specificationDefinition:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'patch'
                            );

                        Route::delete(
                            '/{specificationDefinition:public_id}',
                            'destroy'
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'destroy'
                            );
                    });

                /*
                |--------------------------------------------------------------------------
                | Category specification assignments
                |--------------------------------------------------------------------------
                */

                Route::prefix(
                    'categories/{category:public_id}/specifications'
                )
                    ->name(
                        'categories.specifications.'
                    )
                    ->controller(
                        CategorySpecificationController::class
                    )
                    ->group(function (): void {
                        Route::get(
                            '/',
                            'index'
                        )
                            ->name(
                                'index'
                            );

                        Route::post(
                            '/',
                            'store'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'store'
                            );

                        Route::patch(
                            '/reorder',
                            'reorder'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'reorder'
                            );

                        Route::patch(
                            '/{categorySpecification:public_id}/activate',
                            'activate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'activate'
                            );

                        Route::patch(
                            '/{categorySpecification:public_id}/deactivate',
                            'deactivate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'deactivate'
                            );

                        Route::get(
                            '/{categorySpecification:public_id}',
                            'show'
                        )
                            ->name(
                                'show'
                            );

                        Route::put(
                            '/{categorySpecification:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'update'
                            );

                        Route::patch(
                            '/{categorySpecification:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'patch'
                            );

                        Route::delete(
                            '/{categorySpecification:public_id}',
                            'destroy'
                        )
                            ->middleware(
                                'throttle:20,1'
                            )
                            ->name(
                                'destroy'
                            );
                    });

                /*
                |--------------------------------------------------------------------------
                | Categories
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'categories',
                    CategoryController::class
                )->parameters([
                    'categories' =>
                        'category',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Brands
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'brands',
                    BrandController::class
                )->parameters([
                    'brands' =>
                        'brand',
                ]);

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
                )
                    ->name(
                        'products.index'
                    );

                Route::get(
                    'products/{product:public_id}',
                    [
                        ProductModerationController::class,
                        'show',
                    ]
                )
                    ->name(
                        'products.show'
                    );

                Route::post(
                    'products/{product:public_id}/moderate',
                    [
                        ProductModerationController::class,
                        'moderate',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'products.moderate'
                    );

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
                )
                    ->name(
                        'seller-applications.index'
                    );

                Route::get(
                    'seller-applications/{sellerApplication:public_id}',
                    [
                        SellerVerificationController::class,
                        'show',
                    ]
                )
                    ->name(
                        'seller-applications.show'
                    );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/start-review',
                    [
                        SellerVerificationController::class,
                        'startReview',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.start-review'
                    );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/request-information',
                    [
                        SellerVerificationController::class,
                        'requestInformation',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.request-information'
                    );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/approve',
                    [
                        SellerVerificationController::class,
                        'approve',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.approve'
                    );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/reject',
                    [
                        SellerVerificationController::class,
                        'reject',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.reject'
                    );

                /*
                |--------------------------------------------------------------------------
                | Seller verification documents
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}/approve',
                    [
                        SellerVerificationController::class,
                        'approveDocument',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.documents.approve'
                    );

                Route::post(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}/reject',
                    [
                        SellerVerificationController::class,
                        'rejectDocument',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'seller-applications.documents.reject'
                    );

                Route::get(
                    'seller-applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}/download',
                    [
                        SellerVerificationController::class,
                        'downloadDocument',
                    ]
                )
                    ->name(
                        'seller-applications.documents.download'
                    );

                /*
                |--------------------------------------------------------------------------
                | Seller suspension
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'seller-profiles/{sellerProfile:public_id}/suspend',
                    [
                        SellerVerificationController::class,
                        'suspend',
                    ]
                )
                    ->middleware(
                        'throttle:20,1'
                    )
                    ->name(
                        'seller-profiles.suspend'
                    );
            });
    });