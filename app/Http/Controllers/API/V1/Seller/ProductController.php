<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductRequest;
use App\Http\Requests\Seller\UpdateProductRequest;
use App\Http\Resources\SellerProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\Catalog\ProductSpecificationValidator;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductController extends Controller
{
    /**
     * List products belonging to an approved seller.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'nullable',
                Rule::enum(ProductStatus::class),
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

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = $sellerProfile
            ->products()
            ->with([
                'category:id,public_id,parent_id,name,slug,is_active',

                'brand:id,public_id,name,slug,logo_path,is_active',

                'variants' => static function (
                    Builder $variantQuery
                ): void {
                    $variantQuery
                        ->orderByDesc('is_default')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },

                'variants.price',

                'variants.inventoryStock',

                'media' => static function (
                    Builder $mediaQuery
                ): void {
                    $mediaQuery
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->withCount([
                'variants',
                'media',
            ]);

        if (
            isset($validated['q'])
            && trim((string) $validated['q']) !== ''
        ) {
            $search = addcslashes(
                trim((string) $validated['q']),
                '\\%_'
            );

            $like = "%{$search}%";

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
                            'variants',
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
                        );
                }
            );
        }

        if (isset($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (
            isset($validated['category'])
            && trim((string) $validated['category']) !== ''
        ) {
            $categoryIdentifier = trim(
                (string) $validated['category']
            );

            $query->whereHas(
                'category',
                static function (
                    Builder $categoryQuery
                ) use ($categoryIdentifier): void {
                    $categoryQuery->where(
                        static function (
                            Builder $identifierQuery
                        ) use ($categoryIdentifier): void {
                            $identifierQuery
                                ->where(
                                    'public_id',
                                    $categoryIdentifier
                                )
                                ->orWhere(
                                    'slug',
                                    $categoryIdentifier
                                );
                        }
                    );
                }
            );
        }

        if (
            isset($validated['brand'])
            && trim((string) $validated['brand']) !== ''
        ) {
            $brandIdentifier = trim(
                (string) $validated['brand']
            );

            $query->whereHas(
                'brand',
                static function (
                    Builder $brandQuery
                ) use ($brandIdentifier): void {
                    $brandQuery->where(
                        static function (
                            Builder $identifierQuery
                        ) use ($brandIdentifier): void {
                            $identifierQuery
                                ->where(
                                    'public_id',
                                    $brandIdentifier
                                )
                                ->orWhere(
                                    'slug',
                                    $brandIdentifier
                                );
                        }
                    );
                }
            );
        }

        $products = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(
                (int) ($validated['per_page'] ?? 15)
            )
            ->withQueryString();

        return response()->json([
            'success' => true,

            'message' =>
                'Seller products retrieved successfully.',

            'data' =>
                SellerProductResource::collection(
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
                    $products->previousPageUrl(),

                'next' =>
                    $products->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Create a seller product draft.
     */
    public function store(
        StoreProductRequest $request,
        SellerProfile $sellerProfile
    ): JsonResponse {
        $category = $request->category();

        if (!$category instanceof Category) {
            throw ValidationException::withMessages([
                'category_public_id' => [
                    'The selected category could not be resolved.',
                ],
            ]);
        }

        $brand = $request->brand();

        $product = DB::transaction(
            function () use (
                $request,
                $sellerProfile,
                $category,
                $brand
            ): Product {
                $data = $request->validated();

                unset(
                    $data['category_public_id'],
                    $data['brand_public_id']
                );

                $data['category_id'] =
                    $category->getKey();

                $data['brand_id'] =
                    $brand?->getKey();

                $data['status'] =
                    ProductStatus::DRAFT->value;

                $data['specifications'] =
                    $request->normalizedSpecifications();

                if (
                    !isset($data['slug'])
                    || trim((string) $data['slug']) === ''
                ) {
                    $data['slug'] =
                        $this->generateUniqueSlug(
                            (string) $data['name']
                        );
                }

                return $sellerProfile
                    ->products()
                    ->create($data);
            }
        );

        $this->loadProductRelations($product);

        return response()->json([
            'success' => true,

            'message' =>
                'Product draft created successfully.',

            'data' => (
                new SellerProductResource($product)
            )->resolve($request),
        ], 201);
    }

    /**
     * Show one seller product.
     */
    public function show(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $this->loadProductRelations($product);

        return response()->json([
            'success' => true,

            'message' =>
                'Seller product retrieved successfully.',

            'data' => (
                new SellerProductResource($product)
            )->resolve($request),
        ]);
    }

    /**
     * Update seller product information.
     */
    public function update(
        UpdateProductRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $currentStatus = $this->productStatus(
            $product
        );

        if (
            in_array(
                $currentStatus,
                [
                    ProductStatus::PENDING_REVIEW,
                    ProductStatus::SUSPENDED,
                    ProductStatus::ARCHIVED,
                ],
                true
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This product cannot be edited while its status is '
                    .$currentStatus->value.'.',

                'errors' => [
                    'status' => [
                        'Only draft, rejected or approved products can be edited.',
                    ],
                ],
            ], 409);
        }

        $returnedToDraft = false;

        $updatedProduct = DB::transaction(
            function () use (
                $request,
                $sellerProfile,
                $product,
                $currentStatus,
                &$returnedToDraft
            ): Product {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductBelongsToSeller(
                    $sellerProfile,
                    $lockedProduct
                );

                $data = $request->validated();

                if (
                    array_key_exists(
                        'category_public_id',
                        $data
                    )
                ) {
                    $category = $request->category();

                    if (!$category instanceof Category) {
                        throw ValidationException::withMessages([
                            'category_public_id' => [
                                'The selected category could not be resolved.',
                            ],
                        ]);
                    }

                    $data['category_id'] =
                        $category->getKey();

                    unset(
                        $data['category_public_id']
                    );
                }

                if (
                    array_key_exists(
                        'brand_public_id',
                        $data
                    )
                ) {
                    $brand = $request
                        ->submittedBrand();

                    $data['brand_id'] =
                        $brand?->getKey();

                    unset(
                        $data['brand_public_id']
                    );
                }

                if (
                    array_key_exists('slug', $data)
                    && (
                        $data['slug'] === null
                        || trim(
                            (string) $data['slug']
                        ) === ''
                    )
                ) {
                    $finalName = (string) (
                        $data['name']
                        ?? $lockedProduct->name
                    );

                    $data['slug'] =
                        $this->generateUniqueSlug(
                            $finalName,
                            $lockedProduct
                        );
                }

                $lockedProduct->fill($data);

                $sensitiveChange =
                    $lockedProduct->isDirty(
                        $this->moderatedProductFields()
                    );

                if (
                    $sensitiveChange
                    && in_array(
                        $currentStatus,
                        [
                            ProductStatus::APPROVED,
                            ProductStatus::REJECTED,
                        ],
                        true
                    )
                ) {
                    $this->returnProductToDraft(
                        $lockedProduct
                    );

                    $returnedToDraft = true;
                }

                $lockedProduct->save();

                return $lockedProduct;
            }
        );

        $this->loadProductRelations(
            $updatedProduct
        );

        $message = $returnedToDraft
            ? 'Product updated successfully and returned to draft for moderation.'
            : 'Product updated successfully.';

        return response()->json([
            'success' => true,

            'message' => $message,

            'data' => (
                new SellerProductResource(
                    $updatedProduct
                )
            )->resolve($request),
        ]);
    }

    /**
     * Archive a seller product.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $status = $this->productStatus($product);

        if (
            $status === ProductStatus::PENDING_REVIEW
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'A product under moderation cannot be archived.',

                'errors' => [
                    'status' => [
                        'Wait for the moderation decision before archiving this product.',
                    ],
                ],
            ], 409);
        }

        if ($status === ProductStatus::ARCHIVED) {
            return response()->json([
                'success' => true,

                'message' =>
                    'Product is already archived.',

                'data' => null,
            ]);
        }

        DB::transaction(
            function () use (
                $sellerProfile,
                $product
            ): void {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductBelongsToSeller(
                    $sellerProfile,
                    $lockedProduct
                );

                $this->setExistingAttributes(
                    $lockedProduct,
                    [
                        'status' =>
                            ProductStatus::ARCHIVED->value,

                        'archived_at' =>
                            now(),
                    ]
                );

                $lockedProduct->save();
            }
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Product archived successfully.',

            'data' => null,
        ]);
    }

    /**
     * Submit a complete product for administrator moderation.
     */
    public function submitForReview(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductSpecificationValidator $specificationValidator
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $submittedProduct = DB::transaction(
            function () use (
                $sellerProfile,
                $product,
                $specificationValidator
            ): Product {
                $lockedProduct = Product::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductBelongsToSeller(
                    $sellerProfile,
                    $lockedProduct
                );

                $status = $this->productStatus(
                    $lockedProduct
                );

                if (
                    !in_array(
                        $status,
                        [
                            ProductStatus::DRAFT,
                            ProductStatus::REJECTED,
                        ],
                        true
                    )
                ) {
                    abort(
                        409,
                        'Only draft or rejected products can be submitted for moderation.'
                    );
                }

                $category = $lockedProduct
                    ->category()
                    ->first();

                if (!$category instanceof Category) {
                    throw ValidationException::withMessages([
                        'category' => [
                            'The product must belong to a valid category.',
                        ],
                    ]);
                }

                /*
                 * Enforce required category specifications and normalize the
                 * final values before checking the remaining catalog data.
                 */

                $normalizedSpecifications =
                    $specificationValidator
                        ->validateForPublication(
                            category: $category,

                            specifications:
                                $lockedProduct
                                    ->specifications
                                ?? [],

                            attribute:
                                'specifications'
                        );

                $lockedProduct->setAttribute(
                    'specifications',
                    $normalizedSpecifications
                );

                $readinessErrors =
                    $this->publicationReadinessErrors(
                        $sellerProfile,
                        $lockedProduct,
                        $category
                    );

                if ($readinessErrors !== []) {
                    throw ValidationException::withMessages(
                        $readinessErrors
                    );
                }

                $this->setExistingAttributes(
                    $lockedProduct,
                    [
                        'status' =>
                            ProductStatus::PENDING_REVIEW
                                ->value,

                        'submitted_at' =>
                            now(),

                        'approved_at' =>
                            null,

                        'approved_by' =>
                            null,

                        'rejected_at' =>
                            null,

                        'rejected_by' =>
                            null,

                        'rejection_reason' =>
                            null,

                        'suspended_at' =>
                            null,

                        'suspended_by' =>
                            null,

                        'suspension_reason' =>
                            null,
                    ]
                );

                $lockedProduct->save();

                return $lockedProduct;
            }
        );

        $this->loadProductRelations(
            $submittedProduct
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Product submitted for review successfully.',

            'data' => (
                new SellerProductResource(
                    $submittedProduct
                )
            )->resolve($request),
        ]);
    }

    /**
     * Validate product information needed before moderation.
     *
     * @return array<string, array<int, string>>
     */
    private function publicationReadinessErrors(
        SellerProfile $sellerProfile,
        Product $product,
        Category $category
    ): array {
        $errors = [];

        if (!$this->sellerIsApproved($sellerProfile)) {
            $errors['seller'][] =
                'The seller business must be approved before submitting products.';
        }

        if (!$category->is_active) {
            $errors['category'][] =
                'The selected product category is inactive.';
        }

        if (
            trim((string) $product->name) === ''
        ) {
            $errors['name'][] =
                'The product name is required.';
        }

        if (
            trim(
                (string) $product->short_description
            ) === ''
            && trim(
                (string) $product->description
            ) === ''
        ) {
            $errors['description'][] =
                'Provide a short description or full product description.';
        }

        if ($product->brand_id !== null) {
            $brand = $product
                ->brand()
                ->first();

            if (
                !$brand instanceof Brand
                || !$brand->is_active
            ) {
                $errors['brand'][] =
                    'The selected product brand is inactive or unavailable.';
            }
        }

        $activeVariantsQuery = $product
            ->variants()
            ->where(
                'is_active',
                true
            );

        if (
            !(clone $activeVariantsQuery)->exists()
        ) {
            $errors['variants'][] =
                'Create at least one active product variant.';
        }

        if (
            !(clone $activeVariantsQuery)
                ->whereHas(
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
                )
                ->exists()
        ) {
            $errors['pricing'][] =
                'At least one active variant must have a selling price greater than zero.';
        }

        if (
            !(clone $activeVariantsQuery)
                ->whereHas(
                    'inventoryStock'
                )
                ->exists()
        ) {
            $errors['inventory'][] =
                'Configure inventory for at least one active product variant.';
        }

        if (
            !$product
                ->media()
                ->exists()
        ) {
            $errors['media'][] =
                'Upload at least one product image.';
        }

        return $errors;
    }

    /**
     * Return an approved or rejected product to draft after important edits.
     */
    private function returnProductToDraft(
        Product $product
    ): void {
        $this->setExistingAttributes(
            $product,
            [
                'status' =>
                    ProductStatus::DRAFT->value,

                'submitted_at' =>
                    null,

                'approved_at' =>
                    null,

                'approved_by' =>
                    null,

                'rejected_at' =>
                    null,

                'rejected_by' =>
                    null,

                'rejection_reason' =>
                    null,

                'suspended_at' =>
                    null,

                'suspended_by' =>
                    null,

                'suspension_reason' =>
                    null,
            ]
        );
    }

    /**
     * Fields that require a fresh moderation review when changed.
     *
     * @return array<int, string>
     */
    private function moderatedProductFields(): array
    {
        return [
            'category_id',
            'brand_id',
            'name',
            'slug',
            'short_description',
            'description',
            'condition',
            'warranty_months',
            'specifications',
        ];
    }

    /**
     * Load relationships required by SellerProductResource.
     */
    private function loadProductRelations(
        Product $product
    ): void {
        $product->load([
            'sellerProfile',

            'category:id,public_id,parent_id,name,slug,is_active',

            'brand:id,public_id,name,slug,logo_path,is_active',

            'variants' => static function (
                Builder $variantQuery
            ): void {
                $variantQuery
                    ->orderByDesc('is_default')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'variants.price',

            'variants.inventoryStock',

            'variants.media' => static function (
                Builder $mediaQuery
            ): void {
                $mediaQuery
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'media' => static function (
                Builder $mediaQuery
            ): void {
                $mediaQuery
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },

            'moderationReviews' => static function (
                Builder $reviewQuery
            ): void {
                $reviewQuery
                    ->orderByDesc('created_at')
                    ->orderByDesc('id');
            },

            'moderationReviews.reviewer',
        ]);

        $product->loadCount([
            'variants',
            'media',
        ]);
    }

    /**
     * Ensure nested product access cannot cross seller accounts.
     */
    private function ensureProductBelongsToSeller(
        SellerProfile $sellerProfile,
        Product $product
    ): void {
        abort_unless(
            (int) $product->seller_profile_id
                === (int) $sellerProfile->getKey(),
            404,
            'The selected product does not belong to this seller profile.'
        );
    }

    /**
     * Read the product status regardless of whether the model uses an enum cast.
     */
    private function productStatus(
        Product $product
    ): ProductStatus {
        $status = $product->status;

        if ($status instanceof ProductStatus) {
            return $status;
        }

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return ProductStatus::from(
            (string) $status
        );
    }

    /**
     * Determine whether the seller profile is approved.
     */
    private function sellerIsApproved(
        SellerProfile $sellerProfile
    ): bool {
        if (
            method_exists(
                $sellerProfile,
                'isApproved'
            )
        ) {
            return (bool) $sellerProfile
                ->isApproved();
        }

        $status = $sellerProfile->status;

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return (string) $status === 'approved';
    }

    /**
     * Generate a unique product slug.
     */
    private function generateUniqueSlug(
        string $name,
        ?Product $ignoredProduct = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Product::query()
                ->when(
                    $ignoredProduct !== null,
                    static function (
                        Builder $query
                    ) use ($ignoredProduct): void {
                        $query->whereKeyNot(
                            $ignoredProduct->getKey()
                        );
                    }
                )
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Set only database attributes that exist on the current product model.
     *
     * This keeps moderation state updates compatible when optional audit
     * columns are introduced by separate migrations.
     *
     * @param array<string, mixed> $values
     */
    private function setExistingAttributes(
        Product $product,
        array $values
    ): void {
        $attributes = $product->getAttributes();

        foreach ($values as $key => $value) {
            if (
                $key === 'status'
                || array_key_exists(
                    $key,
                    $attributes
                )
            ) {
                $product->setAttribute(
                    $key,
                    $value
                );
            }
        }
    }
}