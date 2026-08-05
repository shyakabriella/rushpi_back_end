<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\MediaType;
use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\SpecificationDataType;
use App\Http\Controllers\API\V1\Seller\ProductController;
use App\Models\Category;
use App\Models\CategorySpecification;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductReturnPolicy;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\SellerProfile;
use App\Models\SpecificationDefinition;
use App\Services\Catalog\ProductSpecificationValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SellerProductSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Required category specifications must be completed before submission.
     */
    public function test_submission_requires_all_required_specifications(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
            'definition' => $definition,
        ] = $this->createBaseProduct(
            specifications: []
        );

        $specificationKey =
            'specifications.'
            .(string) $definition->code;

        $variant = $this->createVariant($product);

        $this->createPrice($variant);
        $this->createInventory($variant);
        $this->createMedia($product);

        $errors = $this->expectValidationFailure(
            callback: fn (): JsonResponse =>
                $this->submitProduct(
                    $seller,
                    $product
                ),
            expectedKey: $specificationKey
        );

        $this->assertStringContainsString(
            'required',
            strtolower(
                $errors[$specificationKey][0]
            )
        );

        $this->assertProductStatus(
            $product,
            ProductStatus::DRAFT
        );
    }

    /**
     * A product must have at least one active variant.
     */
    public function test_submission_requires_an_active_variant(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
        ] = $this->createBaseProduct();

        $this->createMedia($product);

        $errors = $this->expectValidationFailure(
            callback: fn (): JsonResponse =>
                $this->submitProduct(
                    $seller,
                    $product
                ),
            expectedKey: 'variants'
        );

        $this->assertStringContainsString(
            'active product variant',
            strtolower(
                $errors['variants'][0]
            )
        );

        $this->assertProductStatus(
            $product,
            ProductStatus::DRAFT
        );
    }

    /**
     * At least one active variant must have a positive selling price.
     */
    public function test_submission_requires_positive_variant_pricing(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
            'definition' => $definition,
        ] = $this->createBaseProduct();

        $specificationCode =
            (string) $definition->code;

        $variant = $this->createVariant($product);

        $this->createInventory($variant);
        $this->createMedia($product);

        $errors = $this->expectValidationFailure(
            callback: fn (): JsonResponse =>
                $this->submitProduct(
                    $seller,
                    $product
                ),
            expectedKey: 'pricing'
        );

        $this->assertStringContainsString(
            'selling price',
            strtolower(
                $errors['pricing'][0]
            )
        );

        $this->assertProductStatus(
            $product,
            ProductStatus::DRAFT
        );
    }

    /**
     * Inventory configuration must exist for an active product variant.
     */
    public function test_submission_requires_variant_inventory(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
        ] = $this->createBaseProduct();

        $variant = $this->createVariant($product);

        $this->createPrice($variant);
        $this->createMedia($product);

        $errors = $this->expectValidationFailure(
            callback: fn (): JsonResponse =>
                $this->submitProduct(
                    $seller,
                    $product
                ),
            expectedKey: 'inventory'
        );

        $this->assertStringContainsString(
            'inventory',
            strtolower(
                $errors['inventory'][0]
            )
        );

        $this->assertProductStatus(
            $product,
            ProductStatus::DRAFT
        );
    }

    /**
     * A product must contain at least one media record before moderation.
     */
    public function test_submission_requires_product_media(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
        ] = $this->createBaseProduct();

        $variant = $this->createVariant($product);

        $this->createPrice($variant);
        $this->createInventory($variant);

        $errors = $this->expectValidationFailure(
            callback: fn (): JsonResponse =>
                $this->submitProduct(
                    $seller,
                    $product
                ),
            expectedKey: 'media'
        );

        $this->assertStringContainsString(
            'product image',
            strtolower(
                $errors['media'][0]
            )
        );

        $this->assertProductStatus(
            $product,
            ProductStatus::DRAFT
        );
    }

    /**
     * A complete product must enter the pending-review state.
     */
    public function test_complete_product_can_be_submitted_for_moderation(): void
    {
        [
            'seller' => $seller,
            'product' => $product,
            'definition' => $definition,
        ] = $this->createBaseProduct();

        $specificationCode =
            (string) $definition->code;

        $variant = $this->createVariant($product);

        $this->createPrice($variant);
        $this->createInventory($variant);
        $this->createMedia($product);
        $this->createReturnPolicy($product);

        $response = $this->submitProduct(
            $seller,
            $product
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $payload = $response->getData(true);

        $this->assertTrue(
            $payload['success']
        );

        $this->assertSame(
            'Product submitted for review successfully.',
            $payload['message']
        );

        $product->refresh();

        $this->assertProductStatus(
            $product,
            ProductStatus::PENDING_REVIEW
        );

        if (
            Schema::hasColumn(
                'products',
                'submitted_at'
            )
        ) {
            $this->assertNotNull(
                $product->submitted_at
            );
        }

        $this->assertSame(
            16,
            $product->specifications[
                $specificationCode
            ]
        );
    }

    /**
     * Create an approved seller, active category, required specification and
     * draft product.
     *
     * @param array<string, mixed> $specifications
     *
     * @return array{
     *     seller: SellerProfile,
     *     category: Category,
     *     product: Product,
     *     definition: SpecificationDefinition,
     *     assignment: CategorySpecification
     * }
     */
    private function createBaseProduct(
        array $specifications = [
            'ram' => 16,
        ]
    ): array {
        $suffix = Str::lower(
            Str::random(10)
        );

        /** @var SellerProfile $seller */
        $seller = $this->persistModel(
            new SellerProfile(),
            [
                'legal_business_name' =>
                    "RushPi Test Electronics {$suffix}",

                'trading_name' =>
                    "Test Electronics {$suffix}",

                'slug' =>
                    "test-electronics-{$suffix}",

                'registration_number' =>
                    "REG-{$suffix}",

                'tax_identification_number' =>
                    "TIN-{$suffix}",

                'business_email' =>
                    "seller-{$suffix}@example.com",

                'business_phone' =>
                    '+250788000000',

                'country_code' =>
                    'RW',

                'status' =>
                    SellerProfileStatus::APPROVED->value,

                'approved_at' =>
                    now(),
            ]
        );

        /** @var Category $category */
        $category = $this->persistModel(
            new Category(),
            [
                'name' =>
                    "Laptops {$suffix}",

                'slug' =>
                    "laptops-{$suffix}",

                'description' =>
                    'Laptop products used for moderation submission tests.',

                'is_active' =>
                    true,

                'sort_order' =>
                    0,
            ]
        );

        /** @var SpecificationDefinition $definition */
        $definition = $this->persistModel(
            new SpecificationDefinition(),
            [
                'name' =>
                    'RAM',

                'code' =>
                    "ram_{$suffix}",

                'description' =>
                    'Installed memory capacity.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'GB',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 4,
                    'max' => 128,
                    'step' => 4,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    0,
            ]
        );

        /*
         * ProductSpecificationValidator stores values by the reusable
         * definition code. Use the generated unique definition code.
         */

        $specificationCode = (string) $definition->code;

        if (
            array_key_exists(
                'ram',
                $specifications
            )
        ) {
            $specifications[
                $specificationCode
            ] = $specifications['ram'];

            unset(
                $specifications['ram']
            );
        }

        /** @var CategorySpecification $assignment */
        $assignment = $this->persistModel(
            new CategorySpecification(),
            [
                'category_id' =>
                    $category->getKey(),

                'specification_definition_id' =>
                    $definition->getKey(),

                'label' =>
                    'RAM',

                'help_text' =>
                    'Select the installed memory capacity.',

                'is_required' =>
                    true,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    true,

                'is_active' =>
                    true,

                'validation_rules' =>
                    null,

                'options' =>
                    null,

                'default_value' =>
                    null,

                'sort_order' =>
                    0,
            ]
        );

        /** @var Product $product */
        $product = $this->persistModel(
            new Product(),
            [
                'seller_profile_id' =>
                    $seller->getKey(),

                'category_id' =>
                    $category->getKey(),

                'brand_id' =>
                    null,

                'name' =>
                    "Professional Laptop {$suffix}",

                'slug' =>
                    "professional-laptop-{$suffix}",

                'short_description' =>
                    'A reliable professional laptop for business and development.',

                'description' =>
                    'A complete laptop product prepared for RushPi moderation testing.',

                'condition' =>
                    ProductCondition::NEW->value,

                'status' =>
                    ProductStatus::DRAFT->value,

                'specifications' =>
                    $specifications,

                'warranty_months' =>
                    12,
            ]
        );

        return [
            'seller' => $seller,
            'category' => $category,
            'product' => $product,
            'definition' => $definition,
            'assignment' => $assignment,
        ];
    }

    /**
     * Create an active default product variant.
     */
    private function createVariant(
        Product $product
    ): ProductVariant {
        $suffix = Str::upper(
            Str::random(10)
        );

        /** @var ProductVariant $variant */
        $variant = $this->persistModel(
            new ProductVariant(),
            [
                'product_id' =>
                    $product->getKey(),

                'sku' =>
                    "LAPTOP-{$suffix}",

                'barcode' =>
                    null,

                'name' =>
                    'Standard Configuration',

                'attributes' => [
                    'ram' => 16,
                    'storage' => 512,
                ],

                'is_default' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    0,
            ]
        );

        return $variant;
    }

    /**
     * Create positive selling-price configuration for a variant.
     */
    private function createPrice(
        ProductVariant $variant,
        float $sellingPrice = 1_250_000
    ): ProductVariantPrice {
        /** @var ProductVariantPrice $price */
        $price = $this->persistModel(
            new ProductVariantPrice(),
            [
                'product_variant_id' =>
                    $variant->getKey(),

                'currency' =>
                    'RWF',

                'currency_code' =>
                    'RWF',

                'cost_price' =>
                    1_000_000,

                'selling_price' =>
                    $sellingPrice,

                'compare_at_price' =>
                    1_350_000,

                'effective_from' =>
                    now(),

                'starts_at' =>
                    now(),

                'effective_to' =>
                    null,

                'ends_at' =>
                    null,

                'is_active' =>
                    true,
            ]
        );

        return $price;
    }

    /**
     * Create inventory settings for a variant.
     */
    private function createInventory(
        ProductVariant $variant
    ): InventoryStock {
        /** @var InventoryStock $inventory */
        $inventory = $this->persistModel(
            new InventoryStock(),
            [
                'product_variant_id' =>
                    $variant->getKey(),

                'quantity_on_hand' =>
                    10,

                'quantity_reserved' =>
                    0,

                'reorder_level' =>
                    2,

                'low_stock_threshold' =>
                    2,

                'allow_backorder' =>
                    false,

                'track_inventory' =>
                    true,
            ]
        );

        return $inventory;
    }

    /**
     * Create one primary product image record.
     */
    private function createMedia(
        Product $product
    ): ProductMedia {
        $filename = Str::lower(
            Str::random(12)
        ).'.jpg';

        /** @var ProductMedia $media */
        $media = $this->persistModel(
            new ProductMedia(),
            [
                'product_id' =>
                    $product->getKey(),

                'product_variant_id' =>
                    null,

                'media_type' =>
                    MediaType::IMAGE->value,

                'disk' =>
                    'public',

                'storage_disk' =>
                    'public',

                'path' =>
                    "products/tests/{$filename}",

                'storage_path' =>
                    "products/tests/{$filename}",

                'original_name' =>
                    $filename,

                'mime_type' =>
                    'image/jpeg',

                'size_bytes' =>
                    102400,

                'alt_text' =>
                    'Professional laptop product image',

                'metadata' =>
                    null,

                'is_primary' =>
                    true,

                'sort_order' =>
                    0,
            ]
        );

        return $media;
    }

    /**
     * Create a valid active return policy for submission readiness.
     */
    private function createReturnPolicy(
        Product $product
    ): ProductReturnPolicy {
        /** @var ProductReturnPolicy $policy */
        $policy = $this->persistModel(
            new ProductReturnPolicy(),
            [
                'product_id' =>
                    $product->getKey(),

                'is_returnable' =>
                    true,

                'return_window_days' =>
                    7,

                'allow_refund' =>
                    true,

                'allow_exchange' =>
                    true,

                'requires_original_packaging' =>
                    true,

                'requires_proof_of_purchase' =>
                    true,

                'restocking_fee_percent' =>
                    0,

                'return_shipping_payer' =>
                    ProductReturnPolicy
                        ::SHIPPING_PAYER_CUSTOMER,

                'accepted_conditions' => [
                    ProductReturnPolicy
                        ::CONDITION_UNUSED,

                    ProductReturnPolicy
                        ::CONDITION_DEFECTIVE,
                ],

                'refund_methods' => [
                    ProductReturnPolicy
                        ::REFUND_ORIGINAL_PAYMENT_METHOD,
                ],

                'instructions' =>
                    'Return the product within seven days with proof of purchase.',

                'non_returnable_reason' =>
                    null,

                'is_active' =>
                    true,

                'created_by' =>
                    null,

                'updated_by' =>
                    null,
            ]
        );

        return $policy;
    }

    /**
     * Invoke the submission controller directly.
     *
     * Authentication, approved-seller middleware and route-model binding have
     * separate middleware tests. This test focuses on product completeness,
     * specification enforcement and the moderation status transition.
     */
    private function submitProduct(
        SellerProfile $seller,
        Product $product
    ): JsonResponse {
        $request = Request::create(
            uri: sprintf(
                '/api/seller/profiles/%s/products/%s/submit',
                $seller->public_id,
                $product->public_id
            ),
            method: 'POST',
            server: [
                'HTTP_ACCEPT' =>
                    'application/json',
            ]
        );

        return app(
            ProductController::class
        )->submitForReview(
            request: $request,
            sellerProfile: $seller,
            product: $product,
            specificationValidator: app(
                ProductSpecificationValidator::class
            )
        );
    }

    /**
     * Assert that submission throws a validation error containing one key.
     *
     * @param callable(): JsonResponse $callback
     *
     * @return array<string, array<int, string>>
     */
    private function expectValidationFailure(
        callable $callback,
        string $expectedKey
    ): array {
        try {
            $callback();

            self::fail(
                sprintf(
                    'Expected submission validation to fail with the "%s" key.',
                    $expectedKey
                )
            );
        } catch (
            ValidationException $exception
        ) {
            $errors = $exception->errors();

            $this->assertArrayHasKey(
                $expectedKey,
                $errors
            );

            return $errors;
        }

        return [];
    }

    /**
     * Assert the product has the expected status regardless of enum casting.
     */
    private function assertProductStatus(
        Product $product,
        ProductStatus $expectedStatus
    ): void {
        $product->refresh();

        $actualStatus = $product->status;

        if ($actualStatus instanceof ProductStatus) {
            $actualStatus =
                $actualStatus->value;
        }

        $this->assertSame(
            $expectedStatus->value,
            (string) $actualStatus
        );
    }

    /**
     * Persist a model while ignoring compatibility attributes that do not
     * exist in the current migration.
     *
     * This allows the tests to support names such as `disk`/`storage_disk`
     * and `currency`/`currency_code` while only writing existing columns.
     *
     * @template TModel of Model
     *
     * @param TModel $model
     * @param array<string, mixed> $attributes
     *
     * @return TModel
     */
    private function persistModel(
        Model $model,
        array $attributes
    ): Model {
        $columns = array_flip(
            Schema::getColumnListing(
                $model->getTable()
            )
        );

        $model->forceFill(
            array_intersect_key(
                $attributes,
                $columns
            )
        );

        $model->saveOrFail();

        return $model;
    }
}
