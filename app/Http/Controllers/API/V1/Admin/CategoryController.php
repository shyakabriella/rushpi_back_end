<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Public;

use App\Enums\ProductCondition;
use App\Enums\ProductMediaProcessingStatus;
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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

final class CatalogController extends Controller
{
    private const HOME_ROTATION_HOURS = 4;

    private const HOME_CANDIDATE_LIMIT = 1000;

    private const HOME_SHOWCASE_LIMIT = 3;

    private const HOME_PROMO_LIMIT = 5;

    private const HOME_NEW_ARRIVALS_LIMIT = 12;

    /**
     * Build the public RushPi homepage product allocation.
     *
     * Approval makes a product eligible for the public marketplace. Homepage
     * placement is then selected from public, in-stock products using a stable
     * seller-balanced rotation. This prevents one seller from taking every
     * premium homepage position simply by uploading many products.
     *
     * The same rotation slot remains stable for four hours, so the homepage
     * does not reshuffle on every request. When a new slot starts, seller order
     * and the starting product inside each seller rotate deterministically.
     */
    public function home(
        Request $request
    ): JsonResponse {
        $query =
            $this->publicProductsQuery();

        /*
         * Homepage placements should normally be immediately purchasable.
         */
        $this->applyStockFilter(
            $query,
            true
        );

        /*
         * Freshly approved products enter the candidate pool first.
         * The balancing step below prevents one seller from dominating the
         * final homepage sections.
         */
        $candidates = $query
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(
                self::HOME_CANDIDATE_LIMIT
            )
            ->get();

        $rotationSlot =
            $this->currentHomepageRotationSlot();

        $balancedPool =
            $this->buildSellerBalancedPool(
                $candidates,
                $rotationSlot
            );

        /*
         * IDs used by earlier sections are remembered so that, when enough
         * inventory exists, a product does not occupy several homepage slots.
         * If the catalog is still small the selector is allowed to reuse a
         * product in another section rather than leaving that section empty.
         *
         * @var array<string, true> $usedProductIds
         */
        $usedProductIds = [];

        $showcase =
            $this->takeHomepageProducts(
                pool: $balancedPool,
                usedProductIds: $usedProductIds,
                limit: self::HOME_SHOWCASE_LIMIT,
                maxPerSeller: 1,
                maxPerCategory: 1
            );

        $promo =
            $this->takeHomepageProducts(
                pool: $balancedPool,
                usedProductIds: $usedProductIds,
                limit: self::HOME_PROMO_LIMIT,
                maxPerSeller: 1,
                maxPerCategory: 2
            );

        $newArrivals =
            $this->takeHomepageProducts(
                pool: $balancedPool,
                usedProductIds: $usedProductIds,
                limit: self::HOME_NEW_ARRIVALS_LIMIT,
                maxPerSeller: 2,
                maxPerCategory: 3
            );

        return response()->json([
            'success' => true,

            'message' =>
                'RushPi homepage retrieved successfully.',

            'data' => [
                'showcase' =>
                    PublicProductResource::collection(
                        $showcase
                    )->resolve($request),

                'promo' =>
                    PublicProductResource::collection(
                        $promo
                    )->resolve($request),

                'new_arrivals' =>
                    PublicProductResource::collection(
                        $newArrivals
                    )->resolve($request),
            ],

            'meta' => [
                'candidate_products' =>
                    $candidates->count(),

                'candidate_sellers' =>
                    $candidates
                        ->pluck(
                            'seller_profile_id'
                        )
                        ->filter()
                        ->unique()
                        ->count(),

                'candidate_categories' =>
                    $candidates
                        ->pluck('category_id')
                        ->filter()
                        ->unique()
                        ->count(),

                'rotation_hours' =>
                    self::HOME_ROTATION_HOURS,

                'rotation_slot' =>
                    $rotationSlot,

                'section_limits' => [
                    'showcase' =>
                        self::HOME_SHOWCASE_LIMIT,

                    'promo' =>
                        self::HOME_PROMO_LIMIT,

                    'new_arrivals' =>
                        self::HOME_NEW_ARRIVALS_LIMIT,
                ],
            ],
        ]);
    }

    /**
     * Return the deterministic four-hour rotation slot for the current time.
     */
    private function currentHomepageRotationSlot():
        int
    {
        return (int) floor(
            now()->timestamp
            /
            (
                self::HOME_ROTATION_HOURS
                * 3600
            )
        );
    }

    /**
     * Produce a seller-balanced product pool using round-robin allocation.
     *
     * Example:
     * Seller A: A1, A2, A3
     * Seller B: B1, B2
     * Seller C: C1
     *
     * Result:
     * A1, B1, C1, A2, B2, A3
     *
     * The seller order and each seller's starting product rotate by slot.
     *
     * @param Collection<int, Product> $products
     * @return Collection<int, Product>
     */
    private function buildSellerBalancedPool(
        Collection $products,
        int $rotationSlot
    ): Collection {
        if ($products->isEmpty()) {
            return collect();
        }

        $sellerGroups =
            $products
                ->groupBy(
                    fn (
                        Product $product
                    ): string =>
                        $this->homepageSellerKey(
                            $product
                        )
                );

        /*
         * Stable pseudo-random seller ordering for this rotation slot.
         */
        $sellerKeys =
            $sellerGroups
                ->keys()
                ->sortBy(
                    fn (
                        mixed $sellerKey
                    ): int =>
                        $this->homepageStableScore(
                            $rotationSlot
                            .'|seller|'
                            .(string) $sellerKey
                        )
                )
                ->values();

        $rotatedGroups = collect();

        foreach ($sellerKeys as $sellerKey) {
            $sellerKey =
                (string) $sellerKey;

            /**
             * Products already arrive newest-approved first from the query.
             * Rotate the starting product so the same seller does not always
             * show the exact same item in its first eligible homepage slot.
             *
             * @var Collection<int, Product> $sellerProducts
             */
            $sellerProducts =
                $sellerGroups
                    ->get(
                        $sellerKey,
                        collect()
                    )
                    ->values();

            if ($sellerProducts->isEmpty()) {
                continue;
            }

            $offset = 0;

            if (
                $sellerProducts->count()
                > 1
            ) {
                $offset =
                    $this->homepageStableScore(
                        $rotationSlot
                        .'|products|'
                        .$sellerKey
                    )
                    % $sellerProducts->count();
            }

            $rotatedGroups->put(
                $sellerKey,
                $sellerProducts
                    ->slice($offset)
                    ->concat(
                        $sellerProducts
                            ->take($offset)
                    )
                    ->values()
            );
        }

        $balanced = collect();
        $position = 0;

        while (true) {
            $added = false;

            foreach (
                $sellerKeys
                as $sellerKey
            ) {
                $sellerProducts =
                    $rotatedGroups->get(
                        (string) $sellerKey
                    );

                if (
                    ! $sellerProducts
                    instanceof Collection
                ) {
                    continue;
                }

                $product =
                    $sellerProducts->get(
                        $position
                    );

                if (
                    ! $product
                    instanceof Product
                ) {
                    continue;
                }

                $balanced->push(
                    $product
                );

                $added = true;
            }

            if (! $added) {
                break;
            }

            $position++;
        }

        return $balanced->values();
    }

    /**
     * Select one homepage section while enforcing seller/category diversity.
     *
     * Selection passes are intentionally progressive:
     * 1. unused product + seller cap + category cap
     * 2. unused product + seller cap
     * 3. any unused product
     * 4. reuse across sections + seller cap + category cap
     * 5. reuse across sections + seller cap
     * 6. reuse across sections with no cap
     *
     * This keeps a large marketplace fair while still allowing a very small
     * catalog to populate every visual section.
     *
     * @param Collection<int, Product> $pool
     * @param array<string, true> $usedProductIds
     * @return Collection<int, Product>
     */
    private function takeHomepageProducts(
        Collection $pool,
        array &$usedProductIds,
        int $limit,
        int $maxPerSeller,
        int $maxPerCategory
    ): Collection {
        if (
            $limit <= 0
            || $pool->isEmpty()
        ) {
            return collect();
        }

        $selected = collect();

        /** @var array<string, true> $sectionProductIds */
        $sectionProductIds = [];

        /** @var array<string, int> $sellerCounts */
        $sellerCounts = [];

        /** @var array<string, int> $categoryCounts */
        $categoryCounts = [];

        $passes = [
            [
                'respect_global_used' => true,
                'respect_seller_cap' => true,
                'respect_category_cap' => true,
            ],
            [
                'respect_global_used' => true,
                'respect_seller_cap' => true,
                'respect_category_cap' => false,
            ],
            [
                'respect_global_used' => true,
                'respect_seller_cap' => false,
                'respect_category_cap' => false,
            ],
            [
                'respect_global_used' => false,
                'respect_seller_cap' => true,
                'respect_category_cap' => true,
            ],
            [
                'respect_global_used' => false,
                'respect_seller_cap' => true,
                'respect_category_cap' => false,
            ],
            [
                'respect_global_used' => false,
                'respect_seller_cap' => false,
                'respect_category_cap' => false,
            ],
        ];

        foreach ($passes as $pass) {
            foreach ($pool as $product) {
                if (
                    ! $product
                    instanceof Product
                ) {
                    continue;
                }

                $productKey =
                    (string) $product->getKey();

                if (
                    isset(
                        $sectionProductIds[
                            $productKey
                        ]
                    )
                ) {
                    continue;
                }

                if (
                    $pass[
                        'respect_global_used'
                    ]
                    && isset(
                        $usedProductIds[
                            $productKey
                        ]
                    )
                ) {
                    continue;
                }

                $sellerKey =
                    $this->homepageSellerKey(
                        $product
                    );

                $categoryKey =
                    $this->homepageCategoryKey(
                        $product
                    );

                if (
                    $pass[
                        'respect_seller_cap'
                    ]
                    && (
                        $sellerCounts[
                            $sellerKey
                        ]
                        ?? 0
                    ) >= $maxPerSeller
                ) {
                    continue;
                }

                if (
                    $pass[
                        'respect_category_cap'
                    ]
                    && (
                        $categoryCounts[
                            $categoryKey
                        ]
                        ?? 0
                    ) >= $maxPerCategory
                ) {
                    continue;
                }

                $selected->push(
                    $product
                );

                $sectionProductIds[
                    $productKey
                ] = true;

                $usedProductIds[
                    $productKey
                ] = true;

                $sellerCounts[
                    $sellerKey
                ] =
                    (
                        $sellerCounts[
                            $sellerKey
                        ]
                        ?? 0
                    ) + 1;

                $categoryCounts[
                    $categoryKey
                ] =
                    (
                        $categoryCounts[
                            $categoryKey
                        ]
                        ?? 0
                    ) + 1;

                if (
                    $selected->count()
                    >= $limit
                ) {
                    return $selected->values();
                }
            }
        }

        return $selected->values();
    }

    /**
     * Stable integer score used for deterministic rotation ordering.
     */
    private function homepageStableScore(
        string $value
    ): int {
        return (int) sprintf(
            '%u',
            crc32($value)
        );
    }

    /**
     * Return one stable seller grouping key.
     */
    private function homepageSellerKey(
        Product $product
    ): string {
        return (string) (
            $product
                ->sellerProfile
                ?->public_id
            ??
            'seller-'
            .$product
                ->seller_profile_id
        );
    }

    /**
     * Return one stable category grouping key.
     */
    private function homepageCategoryKey(
        Product $product
    ): string {
        return (string) (
            $product
                ->category
                ?->public_id
            ??
            'category-'
            .$product
                ->category_id
        );
    }

    /**
     * List searchable products that are safe for the public catalog.
     */
    public function index(
        Request $request
    ): JsonResponse {
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
                Rule::enum(
                    ProductCondition::class
                ),
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

        $query =
            $this->publicProductsQuery();

        $this->applySearchFilter(
            $query,
            $validated['q']
                ?? null
        );

        $this->applyCategoryFilter(
            $query,
            $validated['category']
                ?? null
        );

        $this->applyBrandFilter(
            $query,
            $validated['brand']
                ?? null
        );

        $query->when(
            $validated['condition']
                ?? null,
            static function (
                Builder $query,
                string $condition
            ): void {
                $query->where(
                    'condition',
                    $condition
                );
            }
        );

        $this->applyPriceFilter(
            $query,
            isset(
                $validated['min_price']
            )
                ? (float) $validated[
                    'min_price'
                ]
                : null,
            isset(
                $validated['max_price']
            )
                ? (float) $validated[
                    'max_price'
                ]
                : null
        );

        if (
            array_key_exists(
                'in_stock',
                $validated
            )
        ) {
            $this->applyStockFilter(
                $query,
                $request->boolean(
                    'in_stock'
                )
            );
        }

        $this->applySorting(
            $query,
            $validated['sort']
                ?? 'newest'
        );

        $products = $query
            ->paginate(
                $validated['per_page']
                    ?? 20
            )
            ->withQueryString();

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Public products retrieved successfully.',

            'data' =>
                PublicProductResource::collection(
                    $products->getCollection()
                )->resolve($request),

            'meta' => [
                'current_page' =>
                    $products->currentPage(),

                'from' =>
                    $products->firstItem(),

                'last_page' =>
                    $products->lastPage(),

                'path' =>
                    $products->path(),

                'per_page' =>
                    $products->perPage(),

                'to' =>
                    $products->lastItem(),

                'total' =>
                    $products->total(),
            ],

            'links' => [
                'first' =>
                    $products->url(1),

                'last' =>
                    $products->url(
                        $products->lastPage()
                    ),

                'previous' =>
                    $products
                        ->previousPageUrl(),

                'next' =>
                    $products
                        ->nextPageUrl(),
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
        $catalogProduct =
            $this
                ->publicProductsQuery()
                ->where(
                    static function (
                        Builder $query
                    ) use ($product): void {
                        $query
                            ->where(
                                'public_id',
                                $product
                            )
                            ->orWhere(
                                'slug',
                                $product
                            );
                    }
                )
                ->firstOrFail();

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Public product retrieved successfully.',

            'data' => (
                new PublicProductResource(
                    $catalogProduct
                )
            )->resolve($request),
        ]);
    }

    /**
     * List active categories that contain public products.
     */
    public function categories():
        JsonResponse
    {
        $categories = Category::query()
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'products',
                function (
                    Builder $query
                ): void {
                    $this
                        ->applyPublicVisibility(
                            $query
                        );
                }
            )
            ->with([
                'parent:id,public_id,name,slug',
            ])
            ->withCount([
                'products as public_products_count' =>
                    function (
                        Builder $query
                    ): void {
                        $this
                            ->applyPublicVisibility(
                                $query
                            );
                    },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (
                    Category $category
                ): array => [
                    'public_id' =>
                        (string) $category
                            ->public_id,

                    'name' =>
                        (string) $category
                            ->name,

                    'slug' =>
                        (string) $category
                            ->slug,

                    'description' =>
                        $category
                            ->description,

                    'image_path' =>
                        $category
                            ->image_path,

                    'sort_order' =>
                        (int) $category
                            ->sort_order,

                    'products_count' =>
                        (int) $category
                            ->public_products_count,

                    'parent' =>
                        $category->parent
                            !== null
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
            'success' =>
                true,

            'message' =>
                'Public categories retrieved successfully.',

            'data' =>
                $categories,
        ]);
    }

    /**
     * List active brands that contain public products.
     */
    public function brands():
        JsonResponse
    {
        $brands = Brand::query()
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'products',
                function (
                    Builder $query
                ): void {
                    $this
                        ->applyPublicVisibility(
                            $query
                        );
                }
            )
            ->withCount([
                'products as public_products_count' =>
                    function (
                        Builder $query
                    ): void {
                        $this
                            ->applyPublicVisibility(
                                $query
                            );
                    },
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (
                    Brand $brand
                ): array => [
                    'public_id' =>
                        (string) $brand
                            ->public_id,

                    'name' =>
                        (string) $brand
                            ->name,

                    'slug' =>
                        (string) $brand
                            ->slug,

                    'description' =>
                        $brand
                            ->description,

                    'logo_path' =>
                        $brand
                            ->logo_path,

                    'website_url' =>
                        $brand
                            ->website_url,

                    'sort_order' =>
                        (int) $brand
                            ->sort_order,

                    'products_count' =>
                        (int) $brand
                            ->public_products_count,
                ]
            )
            ->values();

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Public brands retrieved successfully.',

            'data' =>
                $brands,
        ]);
    }

    /**
     * Build the public product query with customer-safe relationships.
     *
     * @return Builder<Product>
     */
    private function publicProductsQuery():
        Builder
    {
        $query =
            Product::query();

        $this->applyPublicVisibility(
            $query
        );

        return $query->with([
            'category:id,public_id,parent_id,name,slug,is_active',

            'brand:id,public_id,name,slug,logo_path,is_active',

            'sellerProfile:id,public_id,legal_business_name,trading_name,status',

            /*
             * Only completed product-level media is loaded.
             */
            'media' =>
                static function (
                    Builder $mediaQuery
                ): void {
                    self::applyPublicMediaVisibility(
                        $mediaQuery
                    );
                },

            'media.variant:id,public_id',

            /*
             * The relation itself should constrain the policy to the active,
             * currently valid product return policy.
             */
            'activeReturnPolicy',

            'activeVariants' =>
                static function (
                    Builder $variantQuery
                ): void {
                    $variantQuery
                        ->orderByDesc(
                            'is_default'
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy('id');
                },

            'activeVariants.price',

            'activeVariants.inventoryStock',

            /*
             * Variant media also uses optimized completed renditions only.
             */
            'activeVariants.media' =>
                static function (
                    Builder $mediaQuery
                ): void {
                    self::applyPublicMediaVisibility(
                        $mediaQuery
                    );
                },
        ]);
    }

    /**
     * Apply every rule required for public product visibility.
     *
     * @param Builder<Product> $query
     */
    private function applyPublicVisibility(
        Builder $query
    ): void {
        $query
            /*
             * Only administrator-approved products are public.
             */
            ->where(
                'status',
                ProductStatus
                    ::APPROVED
                    ->value
            )

            /*
             * The seller must remain approved.
             */
            ->whereHas(
                'sellerProfile',
                static function (
                    Builder $sellerQuery
                ): void {
                    $sellerQuery->where(
                        'status',
                        SellerProfileStatus
                            ::APPROVED
                            ->value
                    );
                }
            )

            /*
             * The assigned category must be active.
             */
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

            /*
             * A missing brand is permitted. An assigned brand must be active.
             */
            ->where(
                static function (
                    Builder $brandQuery
                ): void {
                    $brandQuery
                        ->whereNull(
                            'brand_id'
                        )
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

            /*
             * At least one active variant must have a positive public price.
             */
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
            )

            /*
             * At least one successfully processed optimized image is required.
             */
            ->whereHas(
                'media',
                static function (
                    Builder $mediaQuery
                ): void {
                    self::applyPublicMediaVisibility(
                        $mediaQuery
                    );
                }
            )

            /*
             * Public products must have a currently active return policy.
             */
            ->whereHas(
                'activeReturnPolicy'
            );
    }

    /**
     * Restrict public media to successfully generated optimized renditions.
     */
    private static function applyPublicMediaVisibility(
        Builder $query
    ): void {
        $query
            ->where(
                'processing_status',
                ProductMediaProcessingStatus
                    ::COMPLETED
                    ->value
            )
            ->whereNotNull(
                'renditions'
            )
            ->orderByDesc(
                'is_primary'
            )
            ->orderBy(
                'sort_order'
            )
            ->orderBy('id');
    }

    /**
     * Search product, variant, seller, category and brand information.
     *
     * @param Builder<Product> $query
     */
    private function applySearchFilter(
        Builder $query,
        ?string $search
    ): void {
        $search = trim(
            (string) $search
        );

        if ($search === '') {
            return;
        }

        $escapedSearch =
            addcslashes(
                $search,
                '\\%_'
            );

        $like =
            "%{$escapedSearch}%";

        $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where(
                        'name',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'slug',
                        'like',
                        $like
                    )
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
     * Filter by category public identifier or slug.
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

        $category =
            trim($category);

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
     * Filter by brand public identifier or slug.
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

        $brand =
            trim($brand);

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
     * Filter products using active-variant selling prices.
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
                if (
                    $minimumPrice
                    !== null
                ) {
                    $priceQuery->where(
                        'selling_price',
                        '>=',
                        $minimumPrice
                    );
                }

                if (
                    $maximumPrice
                    !== null
                ) {
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
     * Filter products by active-variant stock availability.
     *
     * @param Builder<Product> $query
     */
    private function applyStockFilter(
        Builder $query,
        bool $inStock
    ): void {
        $stockConstraint =
            static function (
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
     * Apply a supported public sorting option.
     *
     * @param Builder<Product> $query
     */
    private function applySorting(
        Builder $query,
        string $sort
    ): void {
        match ($sort) {
            'oldest' =>
                $query
                    ->orderBy(
                        'approved_at'
                    )
                    ->orderBy('id'),

            'price_asc' =>
                $query
                    ->orderBy(
                        $this
                            ->minimumSellingPriceSubquery()
                    )
                    ->orderByDesc(
                        'approved_at'
                    )
                    ->orderByDesc('id'),

            'price_desc' =>
                $query
                    ->orderByDesc(
                        $this
                            ->minimumSellingPriceSubquery()
                    )
                    ->orderByDesc(
                        'approved_at'
                    )
                    ->orderByDesc('id'),

            'name_asc' =>
                $query
                    ->orderBy('name')
                    ->orderByDesc('id'),

            'name_desc' =>
                $query
                    ->orderByDesc('name')
                    ->orderByDesc('id'),

            default =>
                $query
                    ->orderByDesc(
                        'approved_at'
                    )
                    ->orderByDesc('id'),
        };
    }

    /**
     * Build the correlated starting-price query used for sorting.
     */
    private function minimumSellingPriceSubquery():
        Builder
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