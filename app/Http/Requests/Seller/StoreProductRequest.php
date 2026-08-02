<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\ProductCondition;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Catalog\ProductSpecificationValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StoreProductRequest extends FormRequest
{
    /**
     * Category resolved from category_public_id.
     */
    private ?Category $resolvedCategory = null;

    /**
     * Brand resolved from brand_public_id.
     */
    private ?Brand $resolvedBrand = null;

    /**
     * Normalized category-controlled product specifications.
     *
     * @var array<string, mixed>|null
     */
    private ?array $normalizedSpecifications = null;

    /**
     * Authorization is handled by:
     *
     * - auth:sanctum
     * - seller.approved
     * - seller ownership checks inside the controller
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize submitted product information before validation.
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
        | A missing or null specifications field is treated as an empty
        | specification object. Required specification fields are enforced
        | later when the seller submits the product for moderation.
        |
        */

        if (
            !$this->exists('specifications')
            || $this->input('specifications') === null
        ) {
            $normalized['specifications'] = [];
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Basic product validation rules.
     *
     * Dynamic specification rules are applied after these rules pass.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Catalog relationships
            |--------------------------------------------------------------------------
            */

            'category_public_id' => [
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
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'min:2',
                'max:255',

                Rule::unique(
                    'products',
                    'slug'
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Product information
            |--------------------------------------------------------------------------
            */

            'short_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'description' => [
                'nullable',
                'string',
                'max:50000',
            ],

            'condition' => [
                'required',
                Rule::enum(
                    ProductCondition::class
                ),
            ],

            'warranty_months' => [
                'nullable',
                'integer',
                'min:0',
                'max:240',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category-controlled specifications
            |--------------------------------------------------------------------------
            |
            | Values inside this object may be strings, numbers, booleans,
            | dates or arrays. ProductSpecificationValidator performs the
            | detailed validation using the selected category configuration.
            |
            */

            'specifications' => [
                'nullable',
                'array',
                'max:200',
            ],
        ];
    }

    /**
     * Perform category-controlled specification validation after the basic
     * request fields pass Laravel validation.
     *
     * @throws ValidationException
     */
    protected function passedValidation(): void
    {
        $category = $this->category();

        if (!$category instanceof Category) {
            throw ValidationException::withMessages([
                'category_public_id' => [
                    'The selected active product category could not be resolved.',
                ],
            ]);
        }

        /*
         * Resolve the brand here as well so a brand removed or deactivated
         * between basic validation and controller execution is rejected.
         */

        if (
            $this->filled('brand_public_id')
            && !$this->brand() instanceof Brand
        ) {
            throw ValidationException::withMessages([
                'brand_public_id' => [
                    'The selected active brand could not be resolved.',
                ],
            ]);
        }

        $this->normalizedSpecifications = app(
            ProductSpecificationValidator::class
        )->validateDraft(
            category: $category,
            specifications: $this->input(
                'specifications',
                []
            ),
            attribute: 'specifications'
        );

        /*
         * Make normalized values available through Request::input().
         */

        $this->merge([
            'specifications' =>
                $this->normalizedSpecifications,
        ]);
    }

    /**
     * Return validated data with normalized specification values.
     *
     * FormRequest's validator keeps its original input internally. This
     * override ensures controllers receive the normalized values produced by
     * ProductSpecificationValidator.
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
            $this->normalizedSpecifications
            !== null
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
     * Resolve the selected active category.
     */
    public function category(): ?Category
    {
        if ($this->resolvedCategory instanceof Category) {
            return $this->resolvedCategory;
        }

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

    /**
     * Resolve the selected active brand.
     */
    public function brand(): ?Brand
    {
        if ($this->resolvedBrand instanceof Brand) {
            return $this->resolvedBrand;
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
                'The product name is required.',

            'slug.unique' =>
                'This product slug is already being used.',

            'condition.required' =>
                'The product condition is required.',

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