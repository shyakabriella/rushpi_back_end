<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Public;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\SellerProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariantPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    /**
     * List searchable products that are safe for the public catalog.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],
            'category' => [
                'nullable',
                'string',
                'max:255',
            ],
            'brand' => [
                'nullable',
                'string',
                'max:255',
            ],
            'condition' => [
                'nullable',
                Rule::enum(ProductCondition::class),
            ],
            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'gte:min_price',
            ],
            'in_stock' => [
                'nullable',
                'boolean',
            ],
            'sort' => [
                'nullable',
                Rule::in([
                    'newest',
                    'oldest',
                    'price_asc',
                    'price_desc',
                    'name_asc',
                    'name_desc',
                ]),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = $this->publicProductsQuery();

        $this->applySearchFilter(
            $query,
            $validated['q'] ?? null
        );

        $this->applyCategoryFilter(
            $query,
            $validated['category'] ?? null
        );

        $this->applyBrandFilter(
            $query,
            $validated['brand'] ?? null
        );

        $query->when(
            $validated['condition'] ?? null,
            static function (
                Builder $query,
                string $condition
            ): void {
                $query->where('condition', $condition);
            }
        );

        $this->applyPriceFilter(
            $query,
            isset($validated['min_price'])
                ? (float) $validated['min_price']
                : null,
            isset($validated['max_price'])
                ? (float) $validated['max_price']
                : null
        );

        if (array_key_exists('in_stock', $validated)) {
            $this->applyStockFilter(
                $query,
                $request->boolean('in_stock')
            );
        }

        $this->applySorting(
            $query,
            $validated['sort'] ?? 'newest'
        );

        $products = $query
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Public products retrieved successfully.',
            'data' => PublicProductResource::collection(
                $products->getCollection()
            )->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'path' => $products->path(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url(
                    $products->lastPage()
                ),
                'previous' => $products->previousPageUrl(),
                'next' => $products->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Show one approved public product by public ID or slug.
     */
    public function show(
        Request $request,
        string $product
    ): JsonResponse {
        $catalogProduct = $this->publicProductsQuery()
            ->where(
                static function (
                    Builder $query
                ) use ($product): void {
                    $query
                        ->where('public_id', $product)
                        ->orWhere('slug', $product);
                }
            )
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Public product retrieved successfully.',
            'data' => (new PublicProductResource(
                $catalogProduct
            ))->resolve($request),
        ]);
    }

    /**
     * List active categories that contain public products.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas(
                'products',
                function (Builder $query): void {
                    $this->applyPublicVisibility($query);
                }
            )
            ->with([
                'parent:id,public_id,name,slug',
            ])
            ->withCount([
                'products as public_products_count' =>
                    function (Builder $query): void {
                        $this->applyPublicVisibility($query);
                    },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (Category $category): array => [
                    'public_id' =>
                        (string) $category->public_id,

                    'name' =>
                        (string) $category->name,

                    'slug' =>
                        (string) $category->slug,

                    'description' =>
                        $category->description,

                    'image_path' =>
                        $category->image_path,

                    'sort_order' =>
                        (int) $category->sort_order,

                    'products_count' =>
                        (int) $category
                            ->public_products_count,

                    'parent' => $category->parent !== null
                        ? [
                            'public_id' =>
                                (string) $category
                                    ->parent
                                    ->public_id,

                            'name' =>
                                (string) $category
                                    ->parent
                                    ->name,

                            'slug' =>
                                (string) $category
                                    ->parent
                                    ->slug,
                        ]
                        : null,
                ]
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' =>
                'Public categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    /**
     * List active brands that contain public products.
     */
    public function brands(): JsonResponse
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->whereHas(
                'products',
                function (Builder $query): void {
                    $this->applyPublicVisibility($query);
                }
            )
            ->withCount([
                'products as public_products_count' =>
                    function (Builder $query): void {
                        $this->applyPublicVisibility($query);
                    },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (Brand $brand): array => [
                    'public_id' =>
                        (string) $brand->public_id,

                    'name' =>
                        (string) $brand->name,

                    'slug' =>
                        (string) $brand->slug,

                    'description' =>
                        $brand->description,

                    'logo_path' =>
                        $brand->logo_path,

                    'website_url' =>
                        $brand->website_url,

                    'sort_order' =>
                        (int) $brand->sort_order,

                    'products_count' =>
                        (int) $brand
                            ->public_products_count,
                ]
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' =>
                'Public brands retrieved successfully.',
            'data' => $brands,
        ]);
    }

    /**
     * Build the public product query with customer-safe relationships.
     *
     * @return Builder<Product>
     */
    private function publicProductsQuery(): Builder
    {
        $query = Product::query();

        $this->applyPublicVisibility($query);

        return $query->with([
            'category:id,public_id,parent_id,name,slug,is_active',

            'brand:id,public_id,name,slug,logo_path,is_active',

            'sellerProfile:id,public_id,legal_business_name,'
                .'trading_name,status',

            'media.variant:id,public_id',

            'activeVariants' =>
                static function (Builder $query): void {
                    $query
                        ->orderByDesc('is_default')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },

            'activeVariants.price',

            'activeVariants.inventoryStock',

            'activeVariants.media' =>
                static function (Builder $query): void {
                    $query
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
        ]);
    }

    /**
     * Apply all visibility rules required for a public product.
     *
     * @param Builder<Product> $query
     */
    private function applyPublicVisibility(
        Builder $query
    ): void {
        $query
            ->where(
                'status',
                ProductStatus::APPROVED->value
            )
            ->whereHas(
                'sellerProfile',
                static function (
                    Builder $sellerQuery
                ): void {
                    $sellerQuery->where(
                        'status',
                        SellerProfileStatus::APPROVED->value
                    );
                }
            )
            ->whereHas(
                'category',
                static function (
                    Builder $categoryQuery
                ): void {
                    $categoryQuery->where(
                        'is_active',
                        true
                    );
                }
            )
            ->where(
                static function (
                    Builder $brandQuery
                ): void {
                    $brandQuery
                        ->whereNull('brand_id')
                        ->orWhereHas(
                            'brand',
                            static function (
                                Builder $query
                            ): void {
                                $query->where(
                                    'is_active',
                                    true
                                );
                            }
                        );
                }
            )
            ->whereHas(
                'activeVariants',
                static function (
                    Builder $variantQuery
                ): void {
                    $variantQuery->whereHas(
                        'price',
                        static function (
                            Builder $priceQuery
                        ): void {
                            $priceQuery->where(
                                'selling_price',
                                '>',
                                0
                            );
                        }
                    );
                }
            );
    }

    /**
     * Search product, variant, category, brand and seller information.
     *
     * @param Builder<Product> $query
     */
    private function applySearchFilter(
        Builder $query,
        ?string $search
    ): void {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $escapedSearch = addcslashes(
            $search,
            '\\%_'
        );

        $like = "%{$escapedSearch}%";

        $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere(
                        'short_description',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'description',
                        'like',
                        $like
                    )
                    ->orWhereHas(
                        'activeVariants',
                        static function (
                            Builder $variantQuery
                        ) use ($like): void {
                            $variantQuery
                                ->where(
                                    'sku',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'name',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'category',
                        static function (
                            Builder $categoryQuery
                        ) use ($like): void {
                            $categoryQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'slug',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'brand',
                        static function (
                            Builder $brandQuery
                        ) use ($like): void {
                            $brandQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'slug',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'sellerProfile',
                        static function (
                            Builder $sellerQuery
                        ) use ($like): void {
                            $sellerQuery
                                ->where(
                                    'legal_business_name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'trading_name',
                                    'like',
                                    $like
                                );
                        }
                    );
            }
        );
    }

    /**
     * Filter by category public ID or slug.
     *
     * @param Builder<Product> $query
     */
    private function applyCategoryFilter(
        Builder $query,
        ?string $category
    ): void {
        if (
            $category === null
            || trim($category) === ''
        ) {
            return;
        }

        $category = trim($category);

        $query->whereHas(
            'category',
            static function (
                Builder $categoryQuery
            ) use ($category): void {
                $categoryQuery->where(
                    static function (
                        Builder $identifierQuery
                    ) use ($category): void {
                        $identifierQuery
                            ->where(
                                'public_id',
                                $category
                            )
                            ->orWhere(
                                'slug',
                                $category
                            );
                    }
                );
            }
        );
    }

    /**
     * Filter by brand public ID or slug.
     *
     * @param Builder<Product> $query
     */
    private function applyBrandFilter(
        Builder $query,
        ?string $brand
    ): void {
        if (
            $brand === null
            || trim($brand) === ''
        ) {
            return;
        }

        $brand = trim($brand);

        $query->whereHas(
            'brand',
            static function (
                Builder $brandQuery
            ) use ($brand): void {
                $brandQuery->where(
                    static function (
                        Builder $identifierQuery
                    ) use ($brand): void {
                        $identifierQuery
                            ->where(
                                'public_id',
                                $brand
                            )
                            ->orWhere(
                                'slug',
                                $brand
                            );
                    }
                );
            }
        );
    }

    /**
     * Filter products using active variant selling prices.
     *
     * @param Builder<Product> $query
     */
    private function applyPriceFilter(
        Builder $query,
        ?float $minimumPrice,
        ?float $maximumPrice
    ): void {
        if (
            $minimumPrice === null
            && $maximumPrice === null
        ) {
            return;
        }

        $query->whereHas(
            'activeVariants.price',
            static function (
                Builder $priceQuery
            ) use (
                $minimumPrice,
                $maximumPrice
            ): void {
                if ($minimumPrice !== null) {
                    $priceQuery->where(
                        'selling_price',
                        '>=',
                        $minimumPrice
                    );
                }

                if ($maximumPrice !== null) {
                    $priceQuery->where(
                        'selling_price',
                        '<=',
                        $maximumPrice
                    );
                }
            }
        );
    }

    /**
     * Filter products by active variant stock availability.
     *
     * @param Builder<Product> $query
     */
    private function applyStockFilter(
        Builder $query,
        bool $inStock
    ): void {
        $stockConstraint = static function (
            Builder $stockQuery
        ): void {
            $stockQuery->where(
                static function (
                    Builder $availableQuery
                ): void {
                    $availableQuery
                        ->whereColumn(
                            'inventory_stocks.quantity_on_hand',
                            '>',
                            'inventory_stocks.quantity_reserved'
                        )
                        ->orWhere(
                            'inventory_stocks.allow_backorder',
                            true
                        );
                }
            );
        };

        if ($inStock) {
            $query->whereHas(
                'activeVariants.inventoryStock',
                $stockConstraint
            );

            return;
        }

        $query->whereDoesntHave(
            'activeVariants.inventoryStock',
            $stockConstraint
        );
    }

    /**
     * Apply a safe public sorting option.
     *
     * @param Builder<Product> $query
     */
    private function applySorting(
        Builder $query,
        string $sort
    ): void {
        match ($sort) {
            'oldest' => $query
                ->orderBy('approved_at')
                ->orderBy('id'),

            'price_asc' => $query
                ->orderBy(
                    $this->minimumSellingPriceSubquery()
                )
                ->orderByDesc('approved_at')
                ->orderByDesc('id'),

            'price_desc' => $query
                ->orderByDesc(
                    $this->minimumSellingPriceSubquery()
                )
                ->orderByDesc('approved_at')
                ->orderByDesc('id'),

            'name_asc' => $query
                ->orderBy('name')
                ->orderByDesc('id'),

            'name_desc' => $query
                ->orderByDesc('name')
                ->orderByDesc('id'),

            default => $query
                ->orderByDesc('approved_at')
                ->orderByDesc('id'),
        };
    }

    /**
     * Build the correlated starting-price subquery.
     */
    private function minimumSellingPriceSubquery(): Builder
    {
        return ProductVariantPrice::query()
            ->selectRaw(
                'MIN(product_variant_prices.selling_price)'
            )
            ->join(
                'product_variants',
                'product_variants.id',
                '=',
                'product_variant_prices.product_variant_id'
            )
            ->whereColumn(
                'product_variants.product_id',
                'products.id'
            )
            ->where(
                'product_variants.is_active',
                true
            )
            ->whereNull(
                'product_variants.deleted_at'
            );
    }
}
