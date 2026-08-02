<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\ProductCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductSpecificationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class UpdateProductRequest extends FormRequest
{
    /**
     * Product resolved from the route.
     */
    private ?Product $resolvedProduct = null;

    /**
     * Final category after applying the requested update.
     */
    private ?Category $resolvedCategory = null;

    /**
     * Submitted brand after applying the requested update.
     */
    private ?Brand $resolvedBrand = null;

    /**
     * Normalized category-controlled specifications.
     *
     * @var array<string, mixed>|null
     */
    private ?array $normalizedSpecifications = null;

    /**
     * Whether normalized specifications should be included in validated data.
     */
    private bool $persistNormalizedSpecifications = false;

    /**
     * Authorization is handled by the authenticated seller routes and
     * seller-product ownership checks inside the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize submitted product fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('category_public_id')) {
            $normalized['category_public_id'] = trim(
                (string) $this->input(
                    'category_public_id',
                    ''
                )
            );
        }

        if ($this->exists('brand_public_id')) {
            $brandPublicId = trim(
                (string) $this->input(
                    'brand_public_id',
                    ''
                )
            );

            $normalized['brand_public_id'] =
                $brandPublicId !== ''
                    ? $brandPublicId
                    : null;
        }

        if ($this->exists('name')) {
            $normalized['name'] = trim(
                (string) $this->input(
                    'name',
                    ''
                )
            );
        }

        if ($this->exists('slug')) {
            $submittedSlug = trim(
                (string) $this->input(
                    'slug',
                    ''
                )
            );

            $normalized['slug'] =
                $submittedSlug !== ''
                    ? Str::slug($submittedSlug)
                    : null;
        }

        if ($this->exists('short_description')) {
            $shortDescription = trim(
                (string) $this->input(
                    'short_description',
                    ''
                )
            );

            $normalized['short_description'] =
                $shortDescription !== ''
                    ? $shortDescription
                    : null;
        }

        if ($this->exists('description')) {
            $description = trim(
                (string) $this->input(
                    'description',
                    ''
                )
            );

            $normalized['description'] =
                $description !== ''
                    ? $description
                    : null;
        }

        if ($this->exists('condition')) {
            $normalized['condition'] = strtolower(
                trim(
                    (string) $this->input(
                        'condition',
                        ''
                    )
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Product specifications
        |--------------------------------------------------------------------------
        |
        | Sending null clears the product specification object before
        | category defaults are reapplied by the specification validator.
        |
        */

        if (
            $this->exists('specifications')
            && $this->input('specifications') === null
        ) {
            $normalized['specifications'] = [];
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Validation rules for partial product updates.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->currentProduct();

        $slugRule = Rule::unique(
            'products',
            'slug'
        );

        if ($product instanceof Product) {
            $slugRule->ignore(
                $product->getKey()
            );
        }

        return [
            /*
            |--------------------------------------------------------------------------
            | Catalog relationships
            |--------------------------------------------------------------------------
            */

            'category_public_id' => [
                'sometimes',
                'required',
                'string',
                'size:26',

                Rule::exists(
                    'categories',
                    'public_id'
                )->where(
                    static fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            'brand_public_id' => [
                'sometimes',
                'nullable',
                'string',
                'size:26',

                Rule::exists(
                    'brands',
                    'public_id'
                )->where(
                    static fn ($query) =>
                        $query->where(
                            'is_active',
                            true
                        )
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Product identity
            |--------------------------------------------------------------------------
            */

            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:255',
                $slugRule,
            ],

            /*
            |--------------------------------------------------------------------------
            | Product information
            |--------------------------------------------------------------------------
            */

            'short_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:50000',
            ],

            'condition' => [
                'sometimes',
                'required',
                Rule::enum(
                    ProductCondition::class
                ),
            ],

            'warranty_months' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:240',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category-controlled specifications
            |--------------------------------------------------------------------------
            */

            'specifications' => [
                'sometimes',
                'nullable',
                'array',
                'max:200',
            ],
        ];
    }

    /**
     * Validate the complete product specification state.
     *
     * Even when specifications were not submitted, the stored values are
     * revalidated against the product's final category. This prevents a
     * category change from leaving incompatible specification values.
     *
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $product = $this->currentProduct();

        if (!$product instanceof Product) {
            throw ValidationException::withMessages([
                'product' => [
                    'The product being updated could not be resolved.',
                ],
            ]);
        }

        $category = $this->category();

        if (!$category instanceof Category) {
            throw ValidationException::withMessages([
                'category_public_id' => [
                    'The final product category could not be resolved.',
                ],
            ]);
        }

        /*
         * When a brand is submitted, confirm that its model can still be
         * resolved after the basic database validation.
         *
         * A null brand_public_id intentionally removes the product brand.
         */

        if (
            $this->exists('brand_public_id')
            && $this->input('brand_public_id') !== null
            && !$this->submittedBrand() instanceof Brand
        ) {
            throw ValidationException::withMessages([
                'brand_public_id' => [
                    'The selected active brand could not be resolved.',
                ],
            ]);
        }

        $specifications = $this->finalSpecifications(
            $product
        );

        $this->normalizedSpecifications = app(
            ProductSpecificationValidator::class
        )->validateDraft(
            category: $category,
            specifications: $specifications,
            attribute: 'specifications'
        );

        /*
         * Persist normalized specifications when:
         *
         * 1. The seller submitted specification values.
         * 2. The seller changed or resubmitted the product category.
         *
         * A category update must store the revalidated specification state,
         * even when the seller did not explicitly submit specifications.
         */

        $this->persistNormalizedSpecifications =
            $this->exists('specifications')
            || $this->exists('category_public_id');

        if ($this->persistNormalizedSpecifications) {
            $this->merge([
                'specifications' =>
                    $this->normalizedSpecifications,
            ]);
        }
    }

    /**
     * Return validated data with normalized specification values.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function validated(
        $key = null,
        $default = null
    ) {
        $validated = parent::validated();

        if (
            $this->persistNormalizedSpecifications
            && $this->normalizedSpecifications !== null
        ) {
            $validated['specifications'] =
                $this->normalizedSpecifications;
        }

        if ($key === null) {
            return $validated;
        }

        return data_get(
            $validated,
            $key,
            $default
        );
    }

    /**
     * Resolve the current product from route-model binding.
     */
    public function currentProduct(): ?Product
    {
        if ($this->resolvedProduct instanceof Product) {
            return $this->resolvedProduct;
        }

        $routeValue = $this->route('product');

        if ($routeValue instanceof Product) {
            $this->resolvedProduct = $routeValue;

            return $this->resolvedProduct;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedProduct = Product::query()
                ->find((int) $routeValue);

            return $this->resolvedProduct;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedProduct = Product::query()
                ->where(
                    'public_id',
                    trim($routeValue)
                )
                ->first();

            return $this->resolvedProduct;
        }

        return null;
    }

    /**
     * Resolve the product's final category.
     *
     * When category_public_id is submitted, only an active category may be
     * selected. Otherwise, the product's existing category is returned.
     */
    public function category(): ?Category
    {
        if ($this->resolvedCategory instanceof Category) {
            return $this->resolvedCategory;
        }

        if ($this->exists('category_public_id')) {
            $publicId = trim(
                (string) $this->input(
                    'category_public_id',
                    ''
                )
            );

            if ($publicId === '') {
                return null;
            }

            $this->resolvedCategory = Category::query()
                ->where(
                    'public_id',
                    $publicId
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

            return $this->resolvedCategory;
        }

        $product = $this->currentProduct();

        if (!$product instanceof Product) {
            return null;
        }

        if (
            $product->relationLoaded('category')
            && $product->category instanceof Category
        ) {
            $this->resolvedCategory =
                $product->category;

            return $this->resolvedCategory;
        }

        $this->resolvedCategory = $product
            ->category()
            ->first();

        return $this->resolvedCategory;
    }

    /**
     * Resolve the submitted active brand.
     *
     * This returns null when the seller is removing the product brand or
     * when brand_public_id was not submitted.
     */
    public function submittedBrand(): ?Brand
    {
        if ($this->resolvedBrand instanceof Brand) {
            return $this->resolvedBrand;
        }

        if (!$this->exists('brand_public_id')) {
            return null;
        }

        $publicId = trim(
            (string) $this->input(
                'brand_public_id',
                ''
            )
        );

        if ($publicId === '') {
            return null;
        }

        $this->resolvedBrand = Brand::query()
            ->where(
                'public_id',
                $publicId
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        return $this->resolvedBrand;
    }

    /**
     * Return the final specification input to validate.
     *
     * Submitted specifications replace the stored specification object.
     * When no specification field is submitted, stored values are validated.
     *
     * @return array<string, mixed>
     */
    private function finalSpecifications(
        Product $product
    ): array {
        if ($this->exists('specifications')) {
            $submitted = $this->input(
                'specifications',
                []
            );

            return is_array($submitted)
                ? $submitted
                : [];
        }

        $stored = $product->specifications;

        if (is_array($stored)) {
            return $stored;
        }

        /*
         * Defensive support in case the model cast has not yet been applied.
         */

        if (
            is_string($stored)
            && trim($stored) !== ''
        ) {
            $decoded = json_decode(
                $stored,
                true
            );

            return is_array($decoded)
                ? $decoded
                : [];
        }

        return [];
    }

    /**
     * Return normalized specification values.
     *
     * @return array<string, mixed>
     */
    public function normalizedSpecifications(): array
    {
        return $this->normalizedSpecifications
            ?? [];
    }

    /**
     * Determine whether the request changes the product category.
     */
    public function changesCategory(): bool
    {
        if (!$this->exists('category_public_id')) {
            return false;
        }

        $product = $this->currentProduct();
        $category = $this->category();

        if (
            !$product instanceof Product
            || !$category instanceof Category
        ) {
            return false;
        }

        return (int) $product->category_id
            !== (int) $category->getKey();
    }

    /**
     * Determine whether the request explicitly changes the brand.
     */
    public function changesBrand(): bool
    {
        if (!$this->exists('brand_public_id')) {
            return false;
        }

        $product = $this->currentProduct();

        if (!$product instanceof Product) {
            return false;
        }

        if ($this->input('brand_public_id') === null) {
            return $product->brand_id !== null;
        }

        $brand = $this->submittedBrand();

        if (!$brand instanceof Brand) {
            return false;
        }

        return (int) $product->brand_id
            !== (int) $brand->getKey();
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_public_id.required' =>
                'A product category is required.',

            'category_public_id.size' =>
                'The selected category identifier is invalid.',

            'category_public_id.exists' =>
                'The selected product category does not exist or is inactive.',

            'brand_public_id.size' =>
                'The selected brand identifier is invalid.',

            'brand_public_id.exists' =>
                'The selected product brand does not exist or is inactive.',

            'name.required' =>
                'The product name cannot be empty.',

            'slug.unique' =>
                'This product slug is already being used.',

            'condition.required' =>
                'The product condition cannot be empty.',

            'warranty_months.max' =>
                'The product warranty cannot be longer than 240 months.',

            'specifications.array' =>
                'The product specifications must be submitted as an object.',

            'specifications.max' =>
                'A product cannot contain more than 200 specification values.',
        ];
    }

    /**
     * Human-readable validation attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_public_id' =>
                'product category',

            'brand_public_id' =>
                'product brand',

            'short_description' =>
                'short description',

            'warranty_months' =>
                'warranty period',

            'specifications' =>
                'product specifications',
        ];
    }
}