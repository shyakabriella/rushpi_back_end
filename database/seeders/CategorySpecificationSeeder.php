<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategorySpecification;
use App\Models\SpecificationDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CategorySpecificationSeeder extends Seeder
{
    /**
     * Columns available on the category_specifications table.
     *
     * @var array<int, string>
     */
    private array $assignmentColumns = [];

    /**
     * Number of newly created assignments.
     */
    private int $createdAssignments = 0;

    /**
     * Number of updated assignments.
     */
    private int $updatedAssignments = 0;

    /**
     * Number of unchanged assignments.
     */
    private int $unchangedAssignments = 0;

    /**
     * Missing category groups.
     *
     * @var array<int, string>
     */
    private array $missingCategories = [];

    /**
     * Missing specification definition codes.
     *
     * @var array<int, string>
     */
    private array $missingDefinitions = [];

    /**
     * Seed category-specific product specification assignments.
     */
    public function run(): void
    {
        if (!Schema::hasTable('category_specifications')) {
            $this->writeError(
                'The category_specifications table does not exist. Run migrations first.'
            );

            return;
        }

        if (!Schema::hasTable('categories')) {
            $this->writeError(
                'The categories table does not exist. Run migrations first.'
            );

            return;
        }

        if (!Schema::hasTable('specification_definitions')) {
            $this->writeError(
                'The specification_definitions table does not exist. Run migrations first.'
            );

            return;
        }

        $this->assignmentColumns =
            Schema::getColumnListing(
                'category_specifications'
            );

        $configuration =
            $this->categoryAssignments();

        $definitions =
            $this->loadDefinitions(
                $configuration
            );

        DB::transaction(
            function () use (
                $configuration,
                $definitions
            ): void {
                foreach (
                    $configuration
                    as $groupName => $categoryConfig
                ) {
                    $category =
                        $this->resolveCategory(
                            $categoryConfig['aliases']
                        );

                    if (!$category instanceof Category) {
                        $this->missingCategories[] =
                            $groupName;

                        continue;
                    }

                    foreach (
                        $categoryConfig['specifications']
                        as $assignment
                    ) {
                        $code =
                            $assignment['code'];

                        $definition =
                            $definitions->get(
                                $code
                            );

                        if (
                            !$definition instanceof
                            SpecificationDefinition
                        ) {
                            $this->missingDefinitions[] =
                                $code;

                            continue;
                        }

                        $this->upsertAssignment(
                            category: $category,
                            definition: $definition,
                            assignment: $assignment
                        );
                    }
                }
            }
        );

        $this->reportResults();
    }

    /**
     * Load all required definitions in one database query.
     *
     * @param array<string, array<string, mixed>> $configuration
     *
     * @return Collection<string, SpecificationDefinition>
     */
    private function loadDefinitions(
        array $configuration
    ): Collection {
        $codes = collect(
            $configuration
        )
            ->flatMap(
                static fn (
                    array $category
                ): array => $category[
                    'specifications'
                ]
            )
            ->pluck('code')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return SpecificationDefinition::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');
    }

    /**
     * Resolve a category using the first matching slug alias.
     *
     * @param array<int, string> $aliases
     */
    private function resolveCategory(
        array $aliases
    ): ?Category {
        $categories = Category::query()
            ->whereIn('slug', $aliases)
            ->get()
            ->keyBy('slug');

        foreach ($aliases as $alias) {
            $category = $categories->get(
                $alias
            );

            if ($category instanceof Category) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Create or update one category specification assignment.
     *
     * @param array<string, mixed> $assignment
     */
    private function upsertAssignment(
        Category $category,
        SpecificationDefinition $definition,
        array $assignment
    ): void {
        $model =
            CategorySpecification::query()
                ->firstOrNew([
                    'category_id' =>
                        $category->getKey(),

                    'specification_definition_id' =>
                        $definition->getKey(),
                ]);

        $wasRecentlyCreated =
            !$model->exists;

        $payload = [
            'category_id' =>
                $category->getKey(),

            'specification_definition_id' =>
                $definition->getKey(),

            'label_override' =>
                $assignment[
                    'label_override'
                ],

            'help_text' =>
                $assignment[
                    'help_text'
                ],

            'is_required' =>
                $assignment[
                    'is_required'
                ],

            'is_filterable' =>
                $assignment[
                    'is_filterable'
                ],

            'is_variant_attribute' =>
                $assignment[
                    'is_variant_attribute'
                ],

            'default_value' =>
                $assignment[
                    'default_value'
                ],

            'validation_rules' =>
                $assignment[
                    'validation_rules'
                ],

            'sort_order' =>
                $assignment[
                    'sort_order'
                ],

            'is_active' =>
                true,
        ];

        /*
         * This allows the seeder to remain compatible when optional assignment
         * columns are not present in a particular database migration version.
         */

        $payload = array_intersect_key(
            $payload,
            array_flip(
                $this->assignmentColumns
            )
        );

        $model->forceFill(
            $payload
        );

        if (
            !$wasRecentlyCreated
            && !$model->isDirty()
        ) {
            $this->unchangedAssignments++;

            return;
        }

        $model->save();

        if ($wasRecentlyCreated) {
            $this->createdAssignments++;

            return;
        }

        $this->updatedAssignments++;
    }

    /**
     * Category and specification assignment configuration.
     *
     * @return array<string, array{
     *     aliases: array<int, string>,
     *     specifications: array<int, array<string, mixed>>
     * }>
     */
    private function categoryAssignments(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | General electronics
            |--------------------------------------------------------------------------
            |
            | These assignments are useful when Electronics is used directly
            | as a category or as a parent category whose specifications are
            | inherited by child categories.
            |
            */

            'electronics' => [
                'aliases' => [
                    'electronics',
                    'electronic-devices',
                    'consumer-electronics',
                ],

                'specifications' => [
                    $this->specification(
                        code: 'model_number',
                        required: true,
                        sortOrder: 10,
                        helpText:
                            'Enter the manufacturer model or reference number.'
                    ),

                    $this->specification(
                        code: 'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        code: 'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        code: 'weight',
                        sortOrder: 40
                    ),

                    $this->specification(
                        code: 'dimensions',
                        sortOrder: 50
                    ),

                    $this->specification(
                        code: 'power_source',
                        filterable: true,
                        sortOrder: 60
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Laptops
            |--------------------------------------------------------------------------
            */

            'laptops' => [
                'aliases' => [
                    'laptops',
                    'laptop-computers',
                    'notebooks',
                    'notebook-computers',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'processor_brand',
                        required: true,
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'processor',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'processor_cores',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'ram',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'ram_type',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'storage_type',
                        required: true,
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'screen_size',
                        required: true,
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'screen_resolution',
                        filterable: true,
                        sortOrder: 110
                    ),

                    $this->specification(
                        'display_type',
                        filterable: true,
                        sortOrder: 120
                    ),

                    $this->specification(
                        'touchscreen',
                        filterable: true,
                        sortOrder: 130
                    ),

                    $this->specification(
                        'graphics_card',
                        filterable: true,
                        sortOrder: 140
                    ),

                    $this->specification(
                        'dedicated_graphics',
                        filterable: true,
                        sortOrder: 150
                    ),

                    $this->specification(
                        'operating_system',
                        required: true,
                        filterable: true,
                        sortOrder: 160
                    ),

                    $this->specification(
                        'connectivity',
                        filterable: true,
                        sortOrder: 170
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 180
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 190
                    ),

                    $this->specification(
                        'battery_capacity',
                        filterable: true,
                        sortOrder: 200
                    ),

                    $this->specification(
                        'battery_life',
                        filterable: true,
                        sortOrder: 210
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Desktop computers
            |--------------------------------------------------------------------------
            */

            'desktop-computers' => [
                'aliases' => [
                    'desktop-computers',
                    'desktops',
                    'desktop-pcs',
                    'computers',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'processor_brand',
                        required: true,
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'processor',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'processor_cores',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'ram',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'ram_type',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'storage_type',
                        required: true,
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'graphics_card',
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'dedicated_graphics',
                        filterable: true,
                        sortOrder: 110
                    ),

                    $this->specification(
                        'operating_system',
                        required: true,
                        filterable: true,
                        sortOrder: 120
                    ),

                    $this->specification(
                        'connectivity',
                        filterable: true,
                        sortOrder: 130
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 140
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 150
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 160
                    ),

                    $this->specification(
                        'power_source',
                        filterable: true,
                        sortOrder: 170
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Smartphones
            |--------------------------------------------------------------------------
            */

            'smartphones' => [
                'aliases' => [
                    'smartphones',
                    'mobile-phones',
                    'phones',
                    'cell-phones',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'processor_brand',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'processor',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'processor_cores',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'ram',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'storage_type',
                        filterable: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'screen_size',
                        required: true,
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'screen_resolution',
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'display_type',
                        filterable: true,
                        sortOrder: 110
                    ),

                    $this->specification(
                        'touchscreen',
                        required: true,
                        filterable: true,
                        sortOrder: 120,
                        defaultValue: true
                    ),

                    $this->specification(
                        'operating_system',
                        required: true,
                        filterable: true,
                        sortOrder: 130
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 140
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 150
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 160
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 170
                    ),

                    $this->specification(
                        'battery_capacity',
                        required: true,
                        filterable: true,
                        sortOrder: 180
                    ),

                    $this->specification(
                        'battery_life',
                        filterable: true,
                        sortOrder: 190
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Tablets
            |--------------------------------------------------------------------------
            */

            'tablets' => [
                'aliases' => [
                    'tablets',
                    'tablet-computers',
                    'ipads',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'processor_brand',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'processor',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'ram',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'storage_type',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'screen_size',
                        required: true,
                        filterable: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'screen_resolution',
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'display_type',
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'touchscreen',
                        required: true,
                        filterable: true,
                        sortOrder: 110,
                        defaultValue: true
                    ),

                    $this->specification(
                        'operating_system',
                        required: true,
                        filterable: true,
                        sortOrder: 120
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 130
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 140
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 150
                    ),

                    $this->specification(
                        'battery_capacity',
                        filterable: true,
                        sortOrder: 160
                    ),

                    $this->specification(
                        'battery_life',
                        filterable: true,
                        sortOrder: 170
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Televisions
            |--------------------------------------------------------------------------
            */

            'televisions' => [
                'aliases' => [
                    'televisions',
                    'tvs',
                    'smart-tvs',
                    'television',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'screen_size',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'screen_resolution',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'display_type',
                        required: true,
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'operating_system',
                        filterable: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'connectivity',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 90
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 100
                    ),

                    $this->specification(
                        'power_source',
                        required: true,
                        filterable: true,
                        sortOrder: 110,
                        defaultValue: 'electric'
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Computer monitors
            |--------------------------------------------------------------------------
            */

            'monitors' => [
                'aliases' => [
                    'monitors',
                    'computer-monitors',
                    'displays',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'screen_size',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'screen_resolution',
                        required: true,
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'display_type',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'touchscreen',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 80
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 90
                    ),

                    $this->specification(
                        'power_source',
                        required: true,
                        filterable: true,
                        sortOrder: 100,
                        defaultValue: 'electric'
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Smart watches and wearables
            |--------------------------------------------------------------------------
            */

            'smart-watches' => [
                'aliases' => [
                    'smart-watches',
                    'smartwatches',
                    'wearables',
                    'wearable-technology',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'processor',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'ram',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'storage_capacity',
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'screen_size',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'display_type',
                        filterable: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'touchscreen',
                        filterable: true,
                        sortOrder: 70,
                        defaultValue: true
                    ),

                    $this->specification(
                        'operating_system',
                        filterable: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 110
                    ),

                    $this->specification(
                        'battery_capacity',
                        filterable: true,
                        sortOrder: 120
                    ),

                    $this->specification(
                        'battery_life',
                        filterable: true,
                        sortOrder: 130
                    ),

                    $this->specification(
                        'power_source',
                        filterable: true,
                        sortOrder: 140,
                        defaultValue: 'battery'
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Gaming consoles
            |--------------------------------------------------------------------------
            */

            'gaming-consoles' => [
                'aliases' => [
                    'gaming-consoles',
                    'game-consoles',
                    'consoles',
                    'gaming',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'processor',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'processor_cores',
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'ram',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'storage_type',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'graphics_card',
                        filterable: true,
                        sortOrder: 80
                    ),

                    $this->specification(
                        'operating_system',
                        filterable: true,
                        sortOrder: 90
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 110
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 120
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 130
                    ),

                    $this->specification(
                        'power_source',
                        filterable: true,
                        sortOrder: 140
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Storage devices
            |--------------------------------------------------------------------------
            */

            'storage-devices' => [
                'aliases' => [
                    'storage-devices',
                    'external-storage',
                    'hard-drives',
                    'ssds',
                    'memory-storage',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'storage_capacity',
                        required: true,
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'storage_type',
                        required: true,
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 60
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 70
                    ),

                    $this->specification(
                        'power_source',
                        filterable: true,
                        sortOrder: 80
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Networking equipment
            |--------------------------------------------------------------------------
            */

            'networking-equipment' => [
                'aliases' => [
                    'networking-equipment',
                    'networking',
                    'routers',
                    'modems',
                    'network-devices',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 30,
                        helpText:
                            'Select all supported wired and wireless connection types.'
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 50
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 60
                    ),

                    $this->specification(
                        'power_source',
                        required: true,
                        filterable: true,
                        sortOrder: 70
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Audio equipment
            |--------------------------------------------------------------------------
            */

            'audio-equipment' => [
                'aliases' => [
                    'audio-equipment',
                    'audio',
                    'speakers',
                    'headphones',
                    'earphones',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'connectivity',
                        required: true,
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 40
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 50
                    ),

                    $this->specification(
                        'battery_capacity',
                        filterable: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'battery_life',
                        filterable: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'power_source',
                        required: true,
                        filterable: true,
                        sortOrder: 80
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Cameras
            |--------------------------------------------------------------------------
            */

            'cameras' => [
                'aliases' => [
                    'cameras',
                    'digital-cameras',
                    'photography',
                    'camera-equipment',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        required: true,
                        sortOrder: 10
                    ),

                    $this->specification(
                        'manufacturing_year',
                        filterable: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'screen_size',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'display_type',
                        filterable: true,
                        sortOrder: 40
                    ),

                    $this->specification(
                        'touchscreen',
                        filterable: true,
                        sortOrder: 50
                    ),

                    $this->specification(
                        'connectivity',
                        filterable: true,
                        sortOrder: 60
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 70
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 80
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 90
                    ),

                    $this->specification(
                        'battery_capacity',
                        filterable: true,
                        sortOrder: 100
                    ),

                    $this->specification(
                        'power_source',
                        required: true,
                        filterable: true,
                        sortOrder: 110
                    ),
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | General accessories
            |--------------------------------------------------------------------------
            */

            'accessories' => [
                'aliases' => [
                    'accessories',
                    'electronic-accessories',
                    'computer-accessories',
                    'phone-accessories',
                ],

                'specifications' => [
                    $this->specification(
                        'model_number',
                        sortOrder: 10
                    ),

                    $this->specification(
                        'color',
                        filterable: true,
                        variantAttribute: true,
                        sortOrder: 20
                    ),

                    $this->specification(
                        'connectivity',
                        filterable: true,
                        sortOrder: 30
                    ),

                    $this->specification(
                        'dimensions',
                        sortOrder: 40
                    ),

                    $this->specification(
                        'weight',
                        sortOrder: 50
                    ),

                    $this->specification(
                        'power_source',
                        filterable: true,
                        sortOrder: 60
                    ),
                ],
            ],
        ];
    }

    /**
     * Build a normalized assignment configuration.
     *
     * @return array<string, mixed>
     */
    private function specification(
        string $code,
        bool $required = false,
        bool $filterable = false,
        bool $variantAttribute = false,
        int $sortOrder = 0,
        ?string $labelOverride = null,
        ?string $helpText = null,
        mixed $defaultValue = null,
        ?array $validationRules = null
    ): array {
        return [
            'code' =>
                $code,

            'label_override' =>
                $labelOverride,

            'help_text' =>
                $helpText,

            'is_required' =>
                $required,

            'is_filterable' =>
                $filterable,

            'is_variant_attribute' =>
                $variantAttribute,

            'default_value' =>
                $defaultValue,

            'validation_rules' =>
                $validationRules,

            'sort_order' =>
                $sortOrder,
        ];
    }

    /**
     * Report the seeding result.
     */
    private function reportResults(): void
    {
        $this->missingCategories =
            array_values(
                array_unique(
                    $this->missingCategories
                )
            );

        $this->missingDefinitions =
            array_values(
                array_unique(
                    $this->missingDefinitions
                )
            );

        $this->writeInfo(
            sprintf(
                'Category specification seeding completed: %d created, %d updated and %d unchanged.',
                $this->createdAssignments,
                $this->updatedAssignments,
                $this->unchangedAssignments
            )
        );

        if ($this->missingCategories !== []) {
            $this->writeWarning(
                'Skipped missing category groups: '
                .implode(
                    ', ',
                    $this->missingCategories
                )
            );
        }

        if ($this->missingDefinitions !== []) {
            $this->writeWarning(
                'Skipped missing specification definitions: '
                .implode(
                    ', ',
                    $this->missingDefinitions
                )
            );
        }
    }

    /**
     * Write an informational console message when available.
     */
    private function writeInfo(
        string $message
    ): void {
        if ($this->command !== null) {
            $this->command->info(
                $message
            );
        }
    }

    /**
     * Write a warning console message when available.
     */
    private function writeWarning(
        string $message
    ): void {
        if ($this->command !== null) {
            $this->command->warn(
                $message
            );
        }
    }

    /**
     * Write an error console message when available.
     */
    private function writeError(
        string $message
    ): void {
        if ($this->command !== null) {
            $this->command->error(
                $message
            );
        }
    }
}
