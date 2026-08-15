<?php

declare(strict_types=1);

use App\Http\Controllers\API\RegisterController;
use App\Http\Controllers\API\V1\Admin\BrandController;
use App\Http\Controllers\API\V1\Admin\CategoryController;
use App\Http\Controllers\API\V1\Admin\CategorySpecificationController;
use App\Http\Controllers\API\V1\Admin\CommissionRuleController;
use App\Http\Controllers\API\V1\Admin\DepartmentController;
use App\Http\Controllers\API\V1\Admin\ProductModerationController;
use App\Http\Controllers\API\V1\Admin\SellerVerificationController;
use App\Http\Controllers\API\V1\Admin\ServiceController;
use App\Http\Controllers\API\V1\Admin\SpecificationDefinitionController;
use App\Http\Controllers\API\V1\Customer\ServiceOrderController;
use App\Http\Controllers\API\V1\Public\CatalogController;
use App\Http\Controllers\API\V1\Public\ServiceController as PublicServiceController;
use App\Http\Controllers\API\V1\Seller\InventoryController;
use App\Http\Controllers\API\V1\Seller\ProductController;
use App\Http\Controllers\API\V1\Seller\ProductMediaController;
use App\Http\Controllers\API\V1\Seller\ProductReturnPolicyController;
use App\Http\Controllers\API\V1\Seller\ProductVariantController;
use App\Http\Controllers\API\V1\Seller\ProductVariantPriceController;
use App\Http\Controllers\API\V1\Seller\SellerDocumentController;
use App\Http\Controllers\API\V1\Seller\SellerProfileController;
use App\Http\Controllers\API\V1\Seller\StockMovementController;
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
        | Homepage
        |--------------------------------------------------------------------------
        */

        Route::get(
            'home',
            [CatalogController::class, 'home']
        )->name('home');

        /*
        |--------------------------------------------------------------------------
        | Categories
        |--------------------------------------------------------------------------
        */

        Route::get(
            'categories',
            [CatalogController::class, 'categories']
        )->name('categories.index');

        /*
        |--------------------------------------------------------------------------
        | Brands
        |--------------------------------------------------------------------------
        */

        Route::get(
            'brands',
            [CatalogController::class, 'brands']
        )->name('brands.index');

        /*
        |--------------------------------------------------------------------------
        | Public paint services
        |--------------------------------------------------------------------------
        |
        | These routes are intentionally public.
        |
        | Mobile customers can:
        |
        | GET  /api/catalog/services
        | GET  /api/catalog/services/{service}
        | POST /api/catalog/services/{service}/quote
        |
        */

        Route::get(
            'services',
            [
                PublicServiceController::class,
                'index',
            ]
        )->name('services.index');

        Route::get(
            'services/{service:public_id}',
            [
                PublicServiceController::class,
                'show',
            ]
        )->name('services.show');

        Route::post(
            'services/{service:public_id}/quote',
            [
                PublicServiceController::class,
                'quote',
            ]
        )
            ->middleware('throttle:60,1')
            ->name('services.quote');

        /*
        |--------------------------------------------------------------------------
        | Products
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
        | Account
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
        | Mobile customer orders
        |--------------------------------------------------------------------------
        |
        | Primary NTEZINET mobile routes:
        |
        | GET  /api/orders
        | POST /api/orders
        | GET  /api/orders/{serviceOrder}
        | POST /api/orders/{serviceOrder}/cancel
        |
        | These routes use the same ServiceOrderController as the
        | original /api/customer/service-orders endpoints.
        |
        */

        Route::prefix('orders')
            ->middleware(
                RoleMiddleware::class
                . ':customer'
            )
            ->name('api.orders.')
            ->group(function (): void {

                /*
                 * Customer order history.
                 *
                 * GET /api/orders
                 */
                Route::get(
                    '/',
                    [
                        ServiceOrderController::class,
                        'index',
                    ]
                )
                    ->name('index');

                /*
                 * Create a new paint order.
                 *
                 * POST /api/orders
                 */
                Route::post(
                    '/',
                    [
                        ServiceOrderController::class,
                        'store',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name('store');

                /*
                 * Cancel an order.
                 *
                 * POST /api/orders/{public_id}/cancel
                 */
                Route::post(
                    '{serviceOrder:public_id}/cancel',
                    [
                        ServiceOrderController::class,
                        'cancel',
                    ]
                )
                    ->middleware(
                        'throttle:20,1'
                    )
                    ->name('cancel');

                /*
                 * View a single order.
                 *
                 * GET /api/orders/{public_id}
                 */
                Route::get(
                    '{serviceOrder:public_id}',
                    [
                        ServiceOrderController::class,
                        'show',
                    ]
                )
                    ->name('show');
            });

        /*
        |--------------------------------------------------------------------------
        | Customer paint / service orders
        |--------------------------------------------------------------------------
        |
        | These endpoints are used by the NTEZINET customer mobile app.
        |
        | GET  /api/customer/service-orders
        | POST /api/customer/service-orders
        |
        | GET
        | /api/customer/service-orders/{serviceOrder}
        |
        | POST
        | /api/customer/service-orders/{serviceOrder}/cancel
        |
        */

        Route::prefix('customer')
            ->middleware(
                RoleMiddleware::class
                . ':customer'
            )
            ->name('api.customer.')
            ->group(function (): void {

                /*
                 * Customer order history.
                 */
                Route::get(
                    'service-orders',
                    [
                        ServiceOrderController::class,
                        'index',
                    ]
                )
                    ->name(
                        'service-orders.index'
                    );

                /*
                 * Create paint order.
                 *
                 * Example modes:
                 *
                 * volume -> 250 ml
                 * weight -> 500 g
                 * amount -> 1000 RWF
                 */
                Route::post(
                    'service-orders',
                    [
                        ServiceOrderController::class,
                        'store',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'service-orders.store'
                    );

                /*
                 * Cancel first, before the generic
                 * service-order route for clarity.
                 */
                Route::post(
                    'service-orders/{serviceOrder:public_id}/cancel',
                    [
                        ServiceOrderController::class,
                        'cancel',
                    ]
                )
                    ->middleware(
                        'throttle:20,1'
                    )
                    ->name(
                        'service-orders.cancel'
                    );

                /*
                 * View one order.
                 */
                Route::get(
                    'service-orders/{serviceOrder:public_id}',
                    [
                        ServiceOrderController::class,
                        'show',
                    ]
                )
                    ->name(
                        'service-orders.show'
                    );
            });

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
                */

                Route::get(
                    'profiles',
                    [
                        SellerProfileController::class,
                        'index',
                    ]
                )
                    ->name(
                        'profiles.index'
                    );

                Route::post(
                    'profiles',
                    [
                        SellerProfileController::class,
                        'store',
                    ]
                )
                    ->middleware(
                        'throttle:20,1'
                    )
                    ->name(
                        'profiles.store'
                    );

                /*
                 * Multipart update for logo / cover.
                 */
                Route::post(
                    'profiles/{sellerProfile:public_id}',
                    [
                        SellerProfileController::class,
                        'update',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'profiles.update.multipart'
                    );

                Route::get(
                    'profiles/{sellerProfile:public_id}',
                    [
                        SellerProfileController::class,
                        'show',
                    ]
                )
                    ->name(
                        'profiles.show'
                    );

                Route::put(
                    'profiles/{sellerProfile:public_id}',
                    [
                        SellerProfileController::class,
                        'update',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'profiles.update'
                    );

                Route::patch(
                    'profiles/{sellerProfile:public_id}',
                    [
                        SellerProfileController::class,
                        'update',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'profiles.patch'
                    );

                /*
                |--------------------------------------------------------------------------
                | Seller verification documents
                |--------------------------------------------------------------------------
                */

                Route::get(
                    'document-requirements',
                    [
                        SellerDocumentController::class,
                        'requirements',
                    ]
                )
                    ->name(
                        'document-requirements.index'
                    );

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents',
                    [
                        SellerDocumentController::class,
                        'index',
                    ]
                )
                    ->name(
                        'applications.documents.index'
                    );

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents',
                    [
                        SellerDocumentController::class,
                        'store',
                    ]
                )
                    ->middleware(
                        'throttle:10,1'
                    )
                    ->name(
                        'applications.documents.store'
                    );

                Route::get(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}/download',
                    [
                        SellerDocumentController::class,
                        'download',
                    ]
                )
                    ->name(
                        'applications.documents.download'
                    );

                Route::delete(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/documents/{sellerDocument:public_id}',
                    [
                        SellerDocumentController::class,
                        'destroy',
                    ]
                )
                    ->middleware(
                        'throttle:20,1'
                    )
                    ->name(
                        'applications.documents.destroy'
                    );

                /*
                |--------------------------------------------------------------------------
                | Submit seller application
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'profiles/{sellerProfile:public_id}'
                    . '/applications/{sellerApplication:public_id}'
                    . '/submit',
                    [
                        SellerDocumentController::class,
                        'submit',
                    ]
                )
                    ->middleware(
                        'throttle:5,1'
                    )
                    ->name(
                        'applications.submit'
                    );

                /*
                |--------------------------------------------------------------------------
                | Approved seller marketplace
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
                        | Product form options
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/form-options',
                            [
                                ProductController::class,
                                'formOptions',
                            ]
                        )
                            ->middleware(
                                'throttle:60,1'
                            )
                            ->name(
                                'products.form-options'
                            );

                        /*
                        |--------------------------------------------------------------------------
                        | Products
                        |--------------------------------------------------------------------------
                        */

                        Route::apiResource(
                            'products',
                            ProductController::class
                        )->parameters([
                            'products' =>
                                'product',
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Submit product
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
                        | Return policy
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
                            'products' =>
                                'product',

                            'variants' =>
                                'variant',
                        ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Variant price
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
                        | Inventory
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

                        /*
                        |--------------------------------------------------------------------------
                        | Stock movements
                        |--------------------------------------------------------------------------
                        */

                        Route::get(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/movements',
                            [
                                StockMovementController::class,
                                'index',
                            ]
                        )
                            ->name(
                                'products.variants.inventory.movements'
                            );

                        Route::get(
                            'products/{product:public_id}'
                            . '/variants/{variant:public_id}'
                            . '/inventory/movements/{movement:public_id}',
                            [
                                StockMovementController::class,
                                'show',
                            ]
                        )
                            ->name(
                                'products.variants.inventory.movements.show'
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
                | Departments
                |--------------------------------------------------------------------------
                */

                Route::apiResource(
                    'departments',
                    DepartmentController::class
                )->parameters([
                    'departments' =>
                        'department',
                ]);

                Route::put(
                    'departments/{department:public_id}/categories',
                    [
                        DepartmentController::class,
                        'syncCategories',
                    ]
                )
                    ->middleware(
                        'throttle:30,1'
                    )
                    ->name(
                        'departments.categories.sync'
                    );

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
                        )->name('index');

                        Route::post(
                            '/',
                            'store'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name('store');

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
                        )->name('show');

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
                | Category specifications
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
                        )->name('index');

                        Route::post(
                            '/',
                            'store'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name('store');

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
                        )->name('show');

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
                | Paint / service management
                |--------------------------------------------------------------------------
                */

                Route::post(
                    'services/{service:public_id}/quote',
                    [
                        ServiceController::class,
                        'quote',
                    ]
                )
                    ->middleware(
                        'throttle:60,1'
                    )
                    ->name(
                        'services.quote'
                    );

                Route::apiResource(
                    'services',
                    ServiceController::class
                )->parameters([
                    'services' =>
                        'service',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Commission rules
                |--------------------------------------------------------------------------
                */

                Route::prefix(
                    'commission-rules'
                )
                    ->name(
                        'commission-rules.'
                    )
                    ->controller(
                        CommissionRuleController::class
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
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name('store');

                        Route::patch(
                            '/{commissionRule:public_id}/activate',
                            'activate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'activate'
                            );

                        Route::patch(
                            '/{commissionRule:public_id}/deactivate',
                            'deactivate'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'deactivate'
                            );

                        Route::get(
                            '/{commissionRule:public_id}',
                            'show'
                        )->name('show');

                        Route::put(
                            '/{commissionRule:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'update'
                            );

                        Route::patch(
                            '/{commissionRule:public_id}',
                            'update'
                        )
                            ->middleware(
                                'throttle:30,1'
                            )
                            ->name(
                                'patch'
                            );

                        Route::delete(
                            '/{commissionRule:public_id}',
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
                | Seller applications
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
                    . '/documents/{sellerDocument:public_id}/scan',
                    [
                        SellerVerificationController::class,
                        'scanDocument',
                    ]
                )
                    ->middleware(
                        'throttle:10,1'
                    )
                    ->name(
                        'seller-applications.documents.scan'
                    );

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