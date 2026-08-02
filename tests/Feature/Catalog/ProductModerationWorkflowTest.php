<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\ProductModerationFlag;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModerationReview;
use App\Models\SellerProfile;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Throwable;

final class ProductModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unauthenticated users cannot moderate products.
     */
    public function test_unauthenticated_user_cannot_moderate_product(): void
    {
        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'approve',
                'moderation_flags' => [],
            ]
        );

        $response->assertUnauthorized();

        $this->assertSame(
            ProductStatus::PENDING_REVIEW->value,
            $this->productStatus($product->refresh())
        );

        $this->assertSame(
            0,
            $this->reviewCount($product)
        );
    }

    /**
     * Authenticated customers cannot moderate products.
     */
    public function test_non_administrator_cannot_moderate_product(): void
    {
        $customer = $this->createUser(
            'customer'
        );

        Sanctum::actingAs($customer);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'approve',
                'moderation_flags' => [],
            ]
        );

        $response->assertForbidden();

        $this->assertSame(
            ProductStatus::PENDING_REVIEW->value,
            $this->productStatus($product->refresh())
        );

        $this->assertSame(
            0,
            $this->reviewCount($product)
        );
    }

    /**
     * An administrator can approve a pending product.
     */
    public function test_administrator_can_approve_pending_product(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'approve',
                'notes' =>
                    'The listing information was reviewed and verified.',
                'moderation_flags' => [],
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'moderation_review.action',
                'approve'
            )
            ->assertJsonPath(
                'moderation_review.from_status',
                ProductStatus::PENDING_REVIEW->value
            )
            ->assertJsonPath(
                'moderation_review.to_status',
                ProductStatus::APPROVED->value
            )
            ->assertJsonPath(
                'moderation_review.is_prohibited_item',
                false
            );

        $product->refresh();

        $this->assertSame(
            ProductStatus::APPROVED->value,
            $this->productStatus($product)
        );

        $review = $this->latestReview(
            $product
        );

        $this->assertSame(
            'approve',
            $review->actionValue()
        );

        $this->assertSame(
            ProductStatus::PENDING_REVIEW->value,
            $review->fromStatusValue()
        );

        $this->assertSame(
            ProductStatus::APPROVED->value,
            $review->toStatusValue()
        );

        $this->assertFalse(
            $review->hasModerationFlags()
        );

        $this->assertFalse(
            $review->isProhibitedItem()
        );

        $this->assertSame(
            $administrator->getKey(),
            $this->reviewModeratorId($review)
        );
    }

    /**
     * An administrator can reject a listing containing a correctable issue.
     */
    public function test_administrator_can_reject_product_with_correctable_flags(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'reject',

                'reason' =>
                    'The product description contains misleading information.',

                'notes' =>
                    'The seller may correct the description and submit again.',

                'moderation_flags' => [
                    ProductModerationFlag
                        ::MISLEADING_INFORMATION
                        ->value,
                ],

                'flag_notes' =>
                    'The storage capacity in the title differs from the specification.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'moderation_review.action',
                'reject'
            )
            ->assertJsonPath(
                'moderation_review.to_status',
                ProductStatus::REJECTED->value
            )
            ->assertJsonPath(
                'moderation_review.is_prohibited_item',
                false
            )
            ->assertJsonPath(
                'moderation_review.has_correctable_flags',
                true
            );

        $product->refresh();

        $this->assertSame(
            ProductStatus::REJECTED->value,
            $this->productStatus($product)
        );

        $review = $this->latestReview(
            $product
        );

        $this->assertSame(
            [
                ProductModerationFlag
                    ::MISLEADING_INFORMATION
                    ->value,
            ],
            $review->moderationFlagValues()
        );

        $this->assertFalse(
            $review->isProhibitedItem()
        );

        $this->assertTrue(
            $review->hasCorrectableFlags()
        );

        $this->assertNotNull(
            $review->flagged_at
        );
    }

    /**
     * A prohibited flag forces rejection of a pending product.
     */
    public function test_prohibited_flag_forces_pending_product_rejection(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        /*
         * The administrator requests suspension, but pending products cannot
         * be suspended. Because the selected flag is prohibited, the
         * controller must automatically apply rejection.
         */
        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'suspend',

                'reason' =>
                    'The product appears to be counterfeit and cannot be listed.',

                'moderation_flags' => [
                    ProductModerationFlag
                        ::COUNTERFEIT_GOODS
                        ->value,
                ],

                'flag_notes' =>
                    'The serial number and branding do not match the manufacturer records.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'moderation_review.action',
                'reject'
            )
            ->assertJsonPath(
                'moderation_review.to_status',
                ProductStatus::REJECTED->value
            )
            ->assertJsonPath(
                'moderation_review.is_prohibited_item',
                true
            )
            ->assertJsonPath(
                'moderation_review.requires_rejection',
                true
            );

        $product->refresh();

        $this->assertSame(
            ProductStatus::REJECTED->value,
            $this->productStatus($product)
        );

        $review = $this->latestReview(
            $product
        );

        $this->assertTrue(
            $review->hasFlag(
                ProductModerationFlag
                    ::COUNTERFEIT_GOODS
            )
        );

        $this->assertTrue(
            $review->isProhibitedItem()
        );

        $this->assertTrue(
            $review->requiresRejection()
        );

        $this->assertSame(
            'suspend',
            $review->metadata[
                'requested_action'
            ] ?? null
        );

        $this->assertSame(
            'reject',
            $review->metadata[
                'applied_action'
            ] ?? null
        );

        $this->assertTrue(
            (bool) (
                $review->metadata[
                    'action_automatically_changed'
                ] ?? false
            )
        );
    }

    /**
     * A prohibited flag forces suspension of an approved product.
     */
    public function test_prohibited_flag_forces_approved_product_suspension(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::APPROVED
        );

        /*
         * The requested reject action is normalized to suspension because
         * the product is already approved and publicly available.
         */
        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'reject',

                'reason' =>
                    'The approved listing contains a restricted weapon.',

                'moderation_flags' => [
                    ProductModerationFlag
                        ::RESTRICTED_WEAPON
                        ->value,
                ],

                'flag_notes' =>
                    'The listing contains a weapon prohibited by marketplace policy.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'moderation_review.action',
                'suspend'
            )
            ->assertJsonPath(
                'moderation_review.from_status',
                ProductStatus::APPROVED->value
            )
            ->assertJsonPath(
                'moderation_review.to_status',
                ProductStatus::SUSPENDED->value
            )
            ->assertJsonPath(
                'moderation_review.is_prohibited_item',
                true
            );

        $product->refresh();

        $this->assertSame(
            ProductStatus::SUSPENDED->value,
            $this->productStatus($product)
        );

        $review = $this->latestReview(
            $product
        );

        $this->assertSame(
            'suspend',
            $review->actionValue()
        );

        $this->assertTrue(
            $review->hasFlag(
                ProductModerationFlag
                    ::RESTRICTED_WEAPON
            )
        );

        $this->assertTrue(
            $review->isProhibitedItem()
        );
    }

    /**
     * Products cannot be approved while moderation flags remain selected.
     */
    public function test_product_with_moderation_flags_cannot_be_approved(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'approve',

                'moderation_flags' => [
                    ProductModerationFlag
                        ::INCOMPLETE_INFORMATION
                        ->value,
                ],

                'flag_notes' =>
                    'Important technical details are missing from the listing.',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'moderation_flags',
            ]);

        $this->assertSame(
            ProductStatus::PENDING_REVIEW->value,
            $this->productStatus(
                $product->refresh()
            )
        );

        $this->assertSame(
            0,
            $this->reviewCount($product)
        );
    }

    /**
     * Rejection requires a clear reason.
     */
    public function test_rejection_requires_reason(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::PENDING_REVIEW
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'reject',
                'moderation_flags' => [],
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'reason',
            ]);

        $this->assertSame(
            ProductStatus::PENDING_REVIEW->value,
            $this->productStatus(
                $product->refresh()
            )
        );

        $this->assertSame(
            0,
            $this->reviewCount($product)
        );
    }

    /**
     * Invalid lifecycle transitions return a conflict and create no history.
     */
    public function test_invalid_moderation_transition_is_rejected_atomically(): void
    {
        $administrator = $this->createUser(
            'admin'
        );

        Sanctum::actingAs($administrator);

        $product = $this->createProduct(
            ProductStatus::APPROVED
        );

        $response = $this->postJson(
            $this->moderationUrl($product),
            [
                'action' => 'approve',
                'moderation_flags' => [],
            ]
        );

        $response
            ->assertStatus(409)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonValidationErrors([
                'action',
            ]);

        $this->assertSame(
            ProductStatus::APPROVED->value,
            $this->productStatus(
                $product->refresh()
            )
        );

        $this->assertSame(
            0,
            $this->reviewCount($product)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Test fixture helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a user and assign a project-compatible role.
     */
    private function createUser(
        string $roleName
    ): User {
        $user = new User();

        $user->forceFill(
            $this->existingColumns(
                'users',
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'name' =>
                        Str::headline(
                            $roleName
                        ).' Test User',

                    'email' =>
                        $roleName
                        .'-'
                        .Str::lower(
                            Str::random(12)
                        )
                        .'@example.test',

                    'phone' =>
                        '+25078'
                        .random_int(
                            1000000,
                            9999999
                        ),

                    'password' =>
                        Hash::make(
                            'TestPassword123!'
                        ),

                    'role' =>
                        $roleName,

                    'role_slug' =>
                        $roleName,

                    'user_role' =>
                        $roleName,

                    'type' =>
                        $roleName,

                    'status' =>
                        'active',

                    'email_verified_at' =>
                        now(),
                ]
            )
        );

        $user->save();

        $this->assignRoleThroughRelation(
            $user,
            $roleName
        );

        return $user->fresh();
    }

    /**
     * Assign a role through the custom roles relationship when available.
     */
    private function assignRoleThroughRelation(
        User $user,
        string $roleName
    ): void {
        if (
            method_exists(
                $user,
                'assignRole'
            )
        ) {
            try {
                $user->assignRole(
                    $roleName
                );

                return;
            } catch (Throwable) {
                /*
                 * Fall back to the project's custom role_user relationship.
                 */
            }
        }

        if (
            !Schema::hasTable('roles')
            || !Schema::hasTable(
                'role_user'
            )
            || !method_exists(
                $user,
                'roles'
            )
        ) {
            return;
        }

        $roleModelClass =
            \App\Models\Role::class;

        if (
            !class_exists(
                $roleModelClass
            )
        ) {
            return;
        }

        /** @var Model|null $role */
        $role = $roleModelClass::query()
            ->where(
                'name',
                $roleName
            )
            ->first();

        if (!$role instanceof Model) {
            /** @var Model $role */
            $role = new $roleModelClass();

            $role->forceFill(
                $this->existingColumns(
                    'roles',
                    [
                        'public_id' =>
                            (string) Str::ulid(),

                        'name' =>
                            $roleName,

                        'slug' =>
                            $roleName,

                        'display_name' =>
                            Str::headline(
                                $roleName
                            ),

                        'description' =>
                            Str::headline(
                                $roleName
                            ).' account.',

                        'is_active' =>
                            true,
                    ]
                )
            );

            $role->save();
        }

        $user->roles()
            ->syncWithoutDetaching([
                $role->getKey(),
            ]);
    }

    /**
     * Create a product fixture in the requested lifecycle status.
     */
    private function createProduct(
        ProductStatus $status
    ): Product {
        $owner = $this->createUser(
            'customer'
        );

        $sellerProfile =
            $this->createSellerProfile(
                $owner
            );

        $category =
            $this->createCategory();

        $product = new Product();

        $slug =
            'moderation-product-'
            .Str::lower(
                Str::random(10)
            );

        $product->forceFill(
            $this->existingColumns(
                'products',
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'seller_profile_id' =>
                        $sellerProfile
                            ->getKey(),

                    'category_id' =>
                        $category
                            ->getKey(),

                    'brand_id' =>
                        null,

                    'name' =>
                        'Moderation Test Smartphone',

                    'slug' =>
                        $slug,

                    'short_description' =>
                        'A smartphone created for moderation workflow testing.',

                    'description' =>
                        'A complete electronics marketplace listing used to test product moderation decisions.',

                    'model_number' =>
                        'MOD-'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'condition' =>
                        'new',

                    'status' =>
                        $status->value,

                    'is_active' =>
                        true,

                    'warranty_type' =>
                        'seller',

                    'warranty_duration_months' =>
                        12,

                    'warranty_period_months' =>
                        12,

                    'created_by' =>
                        $owner->getKey(),

                    'updated_by' =>
                        $owner->getKey(),

                    'submitted_at' =>
                        $status ===
                        ProductStatus::PENDING_REVIEW
                            ? now()
                            : now()
                                ->subHour(),

                    'approved_at' =>
                        $status ===
                        ProductStatus::APPROVED
                            ? now()
                                ->subMinutes(30)
                            : null,

                    'rejected_at' =>
                        $status ===
                        ProductStatus::REJECTED
                            ? now()
                                ->subMinutes(30)
                            : null,

                    'suspended_at' =>
                        $status ===
                        ProductStatus::SUSPENDED
                            ? now()
                                ->subMinutes(30)
                            : null,
                ]
            )
        );

        $product->save();

        return $product->fresh();
    }

    /**
     * Create an approved seller profile fixture.
     */
    private function createSellerProfile(
        User $owner
    ): SellerProfile {
        $sellerProfile =
            new SellerProfile();

        $identifier =
            Str::upper(
                Str::random(10)
            );

        $sellerProfile->forceFill(
            $this->existingColumns(
                'seller_profiles',
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'user_id' =>
                        $owner->getKey(),

                    'owner_user_id' =>
                        $owner->getKey(),

                    'created_by' =>
                        $owner->getKey(),

                    'legal_business_name' =>
                        'Moderation Electronics '
                        .$identifier
                        .' Ltd',

                    'trading_name' =>
                        'Moderation Electronics',

                    'registration_number' =>
                        'RC-'.$identifier,

                    'tax_identification_number' =>
                        'TIN-'.$identifier,

                    'business_type' =>
                        'company',

                    'business_email' =>
                        'seller-'
                        .Str::lower(
                            $identifier
                        )
                        .'@example.test',

                    'business_phone' =>
                        '+250788123456',

                    'country' =>
                        'Rwanda',

                    'country_code' =>
                        'RW',

                    'city' =>
                        'Kigali',

                    'default_currency' =>
                        'RWF',

                    'status' =>
                        'approved',

                    'approved_at' =>
                        now()->subDay(),
                ]
            )
        );

        $sellerProfile->save();

        return $sellerProfile->fresh();
    }

    /**
     * Create an active product category fixture.
     */
    private function createCategory(): Category
    {
        $category = new Category();

        $slug =
            'smartphones-'
            .Str::lower(
                Str::random(10)
            );

        $category->forceFill(
            $this->existingColumns(
                'categories',
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'parent_id' =>
                        null,

                    'name' =>
                        'Smartphones '
                        .Str::upper(
                            Str::random(5)
                        ),

                    'slug' =>
                        $slug,

                    'description' =>
                        'Mobile smartphones and accessories.',

                    'is_active' =>
                        true,

                    'sort_order' =>
                        1,
                ]
            )
        );

        $category->save();

        return $category->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Assertion helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Build the moderation endpoint URL.
     */
    private function moderationUrl(
        Product $product
    ): string {
        return route(
            'api.admin.products.moderate',
            [
                'product' =>
                    $product->public_id,
            ]
        );
    }

    /**
     * Return the latest moderation review for a product.
     */
    private function latestReview(
        Product $product
    ): ProductModerationReview {
        return ProductModerationReview::query()
            ->where(
                'product_id',
                $product->getKey()
            )
            ->latestFirst()
            ->firstOrFail();
    }

    /**
     * Count moderation reviews belonging to a product.
     */
    private function reviewCount(
        Product $product
    ): int {
        return ProductModerationReview::query()
            ->where(
                'product_id',
                $product->getKey()
            )
            ->count();
    }

    /**
     * Read the current product status as a scalar value.
     */
    private function productStatus(
        Product $product
    ): string {
        $status = $product->status;

        if ($status instanceof BackedEnum) {
            return (string) $status->value;
        }

        return (string) $status;
    }

    /**
     * Resolve the moderator user ID from supported schema alternatives.
     */
    private function reviewModeratorId(
        ProductModerationReview $review
    ): mixed {
        foreach (
            [
                'moderator_id',
                'moderated_by',
                'reviewer_id',
                'reviewed_by',
                'admin_user_id',
                'created_by',
            ] as $column
        ) {
            if (
                Schema::hasColumn(
                    'product_moderation_reviews',
                    $column
                )
            ) {
                return $review
                    ->getAttribute(
                        $column
                    );
            }
        }

        return null;
    }

    /**
     * Keep only values whose columns exist in the current test database.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function existingColumns(
        string $table,
        array $values
    ): array {
        $columns =
            Schema::getColumnListing(
                $table
            );

        return array_intersect_key(
            $values,
            array_flip($columns)
        );
    }
}
