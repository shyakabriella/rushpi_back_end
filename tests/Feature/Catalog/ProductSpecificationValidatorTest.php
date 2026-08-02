<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\SpecificationDataType;
use App\Models\Category;
use App\Models\CategorySpecification;
use App\Models\SpecificationDefinition;
use App\Services\Catalog\ProductSpecificationValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductSpecificationValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ProductSpecificationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(
            ProductSpecificationValidator::class
        );
    }

    /**
     * Required category specifications must be present when a product is
     * being prepared for publication or moderation.
     */
    public function test_publication_rejects_missing_required_specification(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'ram',
            dataType: SpecificationDataType::INTEGER,
            required: true
        );

        try {
            $this->validator->validateForPublication(
                category: $category,
                specifications: []
            );

            self::fail(
                'Expected missing required specification validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.ram',
                $errors
            );

            $this->assertSame(
                'The Ram specification is required.',
                $errors['specifications.ram'][0]
            );
        }
    }

    /**
     * Draft products may omit required values because publication readiness
     * is enforced later during moderation submission.
     */
    public function test_draft_allows_missing_required_specification(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'ram',
            dataType: SpecificationDataType::INTEGER,
            required: true
        );

        $result = $this->validator->validateDraft(
            category: $category,
            specifications: []
        );

        $this->assertSame([], $result);
    }

    /**
     * Integer specifications must reject non-integer values.
     */
    public function test_integer_specification_rejects_invalid_type(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'ram',
            dataType: SpecificationDataType::INTEGER,
            required: true
        );

        try {
            $this->validator->validateForPublication(
                category: $category,
                specifications: [
                    'ram' => 'sixteen gigabytes',
                ]
            );

            self::fail(
                'Expected invalid integer specification validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.ram',
                $errors
            );

            $this->assertStringContainsString(
                'whole number',
                $errors['specifications.ram'][0]
            );
        }
    }

    /**
     * Select values must belong to the options configured by an
     * administrator.
     */
    public function test_select_specification_rejects_unsupported_option(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'color',
            dataType: SpecificationDataType::SELECT,
            required: true,
            options: [
                [
                    'value' => 'black',
                    'label' => 'Black',
                ],
                [
                    'value' => 'silver',
                    'label' => 'Silver',
                ],
            ]
        );

        try {
            $this->validator->validateForPublication(
                category: $category,
                specifications: [
                    'color' => 'purple',
                ]
            );

            self::fail(
                'Expected unsupported select option validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.color',
                $errors
            );

            $this->assertStringContainsString(
                'selected Color value is invalid',
                $errors['specifications.color'][0]
            );
        }
    }

    /**
     * Sellers cannot introduce arbitrary specification keys that are not
     * controlled by the selected product category.
     */
    public function test_unknown_specification_code_is_rejected(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'processor',
            dataType: SpecificationDataType::TEXT,
            required: false
        );

        try {
            $this->validator->validateDraft(
                category: $category,
                specifications: [
                    'secret_feature' => 'Unsupported value',
                ]
            );

            self::fail(
                'Expected unknown specification validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.secret_feature',
                $errors
            );

            $this->assertStringContainsString(
                'not configured',
                $errors[
                    'specifications.secret_feature'
                ][0]
            );
        }
    }

    /**
     * Valid publication specifications must be normalized before being
     * stored on the product.
     */
    public function test_valid_publication_specifications_are_normalized(): void
    {
        $category = $this->createCategory([
            'name' => 'Laptops',
            'slug' => 'laptops',
        ]);

        $this->assignSpecification(
            category: $category,
            code: 'processor',
            dataType: SpecificationDataType::TEXT,
            required: true,
            validationRules: [
                'min_length' => 3,
                'max_length' => 100,
            ]
        );

        $this->assignSpecification(
            category: $category,
            code: 'ram',
            dataType: SpecificationDataType::INTEGER,
            required: true,
            validationRules: [
                'min' => 4,
                'max' => 128,
                'step' => 4,
            ]
        );

        $this->assignSpecification(
            category: $category,
            code: 'screen_size',
            dataType: SpecificationDataType::DECIMAL,
            required: true,
            validationRules: [
                'min' => 10,
                'max' => 25,
                'step' => 0.1,
            ]
        );

        $this->assignSpecification(
            category: $category,
            code: 'color',
            dataType: SpecificationDataType::SELECT,
            required: true,
            options: [
                [
                    'value' => 'black',
                    'label' => 'Black',
                ],
                [
                    'value' => 'silver',
                    'label' => 'Silver',
                ],
            ]
        );

        $this->assignSpecification(
            category: $category,
            code: 'touchscreen',
            dataType: SpecificationDataType::BOOLEAN,
            required: false,
            defaultValue: false
        );

        $result = $this->validator
            ->validateForPublication(
                category: $category,
                specifications: [
                    'processor' => '  Intel Core i7  ',
                    'ram' => '16',
                    'screen_size' => '15.6',
                    'color' => 'black',
                ]
            );

        $this->assertSame(
            'Intel Core i7',
            $result['processor']
        );

        $this->assertSame(
            16,
            $result['ram']
        );

        $this->assertSame(
            15.6,
            $result['screen_size']
        );

        $this->assertSame(
            'black',
            $result['color']
        );

        $this->assertFalse(
            $result['touchscreen']
        );
    }

    /**
     * Numeric specifications must follow their configured increment.
     */
    public function test_numeric_specification_enforces_step_configuration(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'ram',
            dataType: SpecificationDataType::INTEGER,
            required: true,
            validationRules: [
                'min' => 4,
                'max' => 64,
                'step' => 4,
            ]
        );

        try {
            $this->validator->validateForPublication(
                category: $category,
                specifications: [
                    'ram' => 18,
                ]
            );

            self::fail(
                'Expected invalid numeric step validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.ram',
                $errors
            );

            $this->assertStringContainsString(
                'increments of 4',
                $errors['specifications.ram'][0]
            );
        }
    }

    /**
     * Multiselect specifications must reject duplicate values.
     */
    public function test_multiselect_specification_rejects_duplicates(): void
    {
        $category = $this->createCategory();

        $this->assignSpecification(
            category: $category,
            code: 'connectivity',
            dataType: SpecificationDataType::MULTISELECT,
            required: true,
            options: [
                [
                    'value' => 'wifi',
                    'label' => 'Wi-Fi',
                ],
                [
                    'value' => 'bluetooth',
                    'label' => 'Bluetooth',
                ],
                [
                    'value' => 'ethernet',
                    'label' => 'Ethernet',
                ],
            ],
            validationRules: [
                'min_items' => 1,
                'max_items' => 3,
            ]
        );

        try {
            $this->validator->validateForPublication(
                category: $category,
                specifications: [
                    'connectivity' => [
                        'wifi',
                        'wifi',
                    ],
                ]
            );

            self::fail(
                'Expected duplicate multiselect validation to fail.'
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                'specifications.connectivity',
                $errors
            );

            $this->assertStringContainsString(
                'duplicate options',
                $errors[
                    'specifications.connectivity'
                ][0]
            );
        }
    }

    /**
     * Create an active catalog category.
     *
     * @param array<string, mixed> $overrides
     */
    private function createCategory(
        array $overrides = []
    ): Category {
        return Category::query()->create(
            array_merge(
                [
                    'name' =>
                        'Test Category '.Str::random(6),

                    'slug' =>
                        'test-category-'.Str::lower(
                            Str::random(8)
                        ),

                    'description' =>
                        'Category created for specification validation tests.',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        0,
                ],
                $overrides
            )
        );
    }

    /**
     * Create a reusable specification definition and assign it to a category.
     *
     * @param array<int, array<string, mixed>> $options
     * @param array<string, mixed> $validationRules
     */
    private function assignSpecification(
        Category $category,
        string $code,
        SpecificationDataType $dataType,
        bool $required,
        array $options = [],
        array $validationRules = [],
        mixed $defaultValue = null
    ): CategorySpecification {
        $definitionData = [
            'name' =>
                Str::headline($code),

            'code' =>
                $code,

            'description' =>
                "Reusable {$code} specification.",

            'data_type' =>
                $dataType->value,

            'unit' =>
                null,

            'options' =>
                $options !== []
                    ? $options
                    : null,

            'validation_rules' =>
                $validationRules !== []
                    ? $validationRules
                    : null,

            'is_filterable' =>
                true,

            'is_variant_attribute' =>
                false,

            'is_active' =>
                true,

            'sort_order' =>
                0,
        ];

        if ($defaultValue !== null || $dataType === SpecificationDataType::BOOLEAN) {
            $definitionData['default_value'] =
                $defaultValue;
        }

        $definition =
            SpecificationDefinition::query()
                ->create($definitionData);

        return CategorySpecification::query()
            ->create([
                'category_id' =>
                    $category->getKey(),

                'specification_definition_id' =>
                    $definition->getKey(),

                'label' =>
                    Str::headline($code),

                'help_text' =>
                    "Enter the product {$code}.",

                'is_required' =>
                    $required,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    0,
            ]);
    }
}
