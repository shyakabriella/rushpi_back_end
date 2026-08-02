<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SpecificationDataType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CategorySpecification
 */
final class CategorySpecificationResource extends JsonResource
{
    /**
     * Transform the category specification assignment into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dataType = $this->dataType();

        return [
            'public_id' => (string) $this->public_id,

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            'category' => $this->categoryData(),

            /*
            |--------------------------------------------------------------------------
            | Reusable specification definition
            |--------------------------------------------------------------------------
            */

            'specification_definition' =>
                $this->specificationDefinitionData(
                    $dataType
                ),

            /*
            |--------------------------------------------------------------------------
            | Effective category configuration
            |--------------------------------------------------------------------------
            */

            'code' => $this->code(),

            'label' => $this->effectiveLabel(),

            'help_text' => $this->effectiveHelpText(),

            'data_type' => [
                'value' => $dataType->value,

                'label' => $dataType->label(),

                'api_type' => $dataType->apiType(),

                'uses_options' =>
                    $dataType->usesOptions(),

                'accepts_multiple_values' =>
                    $dataType->acceptsMultipleValues(),

                'is_numeric' =>
                    $dataType->isNumeric(),
            ],

            'unit' => $this->unit(),

            'options' => $this->effectiveOptions(),

            'option_values' =>
                $this->effectiveOptionValues(),

            'validation_rules' =>
                $this->effectiveValidationConfiguration(),

            'base_validation_rules' =>
                $this->baseValidationRules(),

            'default_value' =>
                $this->effectiveDefaultValue(),

            /*
            |--------------------------------------------------------------------------
            | Assignment settings
            |--------------------------------------------------------------------------
            */

            'is_required' =>
                $this->isRequired(),

            'is_filterable' =>
                $this->isFilterable(),

            'is_variant_attribute' =>
                $this->isVariantAttribute(),

            'is_active' =>
                (bool) $this->is_active,

            'is_available' =>
                $this->isAvailable(),

            'sort_order' =>
                (int) $this->sort_order,

            /*
            |--------------------------------------------------------------------------
            | Category-specific overrides
            |--------------------------------------------------------------------------
            |
            | These fields show what is stored directly on the category
            | assignment. The effective values above may fall back to the
            | reusable specification definition.
            |
            */

            'overrides' => [
                'label' => $this->label,

                'help_text' => $this->help_text,

                'options' =>
                    is_array($this->options)
                        ? $this->options
                        : null,

                'validation_rules' =>
                    is_array($this->validation_rules)
                        ? $this->validation_rules
                        : null,

                'default_value' =>
                    $this->default_value,
            ],

            /*
            |--------------------------------------------------------------------------
            | Product-form representation
            |--------------------------------------------------------------------------
            */

            'form_definition' =>
                $this->toFormDefinition(),

            /*
            |--------------------------------------------------------------------------
            | Audit information
            |--------------------------------------------------------------------------
            */

            'audit' => [
                'created_by' =>
                    $this->createdByData(),

                'updated_by' =>
                    $this->updatedByData(),
            ],

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return category information.
     *
     * @return array<string, mixed>|null
     */
    private function categoryData(): ?array
    {
        if (!$this->resource->relationLoaded('category')) {
            return null;
        }

        if ($this->category === null) {
            return null;
        }

        return [
            'public_id' =>
                (string) $this->category->public_id,

            'name' =>
                (string) $this->category->name,

            'slug' =>
                (string) $this->category->slug,

            'is_active' =>
                (bool) $this->category->is_active,

            'parent_id' =>
                $this->category->parent_id,
        ];
    }

    /**
     * Return reusable specification definition information.
     *
     * @return array<string, mixed>|null
     */
    private function specificationDefinitionData(
        SpecificationDataType $dataType
    ): ?array {
        if (
            !$this->resource->relationLoaded(
                'specificationDefinition'
            )
        ) {
            return null;
        }

        if ($this->specificationDefinition === null) {
            return null;
        }

        return [
            'public_id' =>
                (string) $this
                    ->specificationDefinition
                    ->public_id,

            'name' =>
                (string) $this
                    ->specificationDefinition
                    ->name,

            'code' =>
                (string) $this
                    ->specificationDefinition
                    ->code,

            'description' =>
                $this
                    ->specificationDefinition
                    ->description,

            'data_type' => [
                'value' => $dataType->value,

                'label' => $dataType->label(),

                'api_type' => $dataType->apiType(),
            ],

            'unit' =>
                $this
                    ->specificationDefinition
                    ->unit,

            'options' =>
                $this
                    ->specificationDefinition
                    ->optionItems(),

            'validation_rules' =>
                $this
                    ->specificationDefinition
                    ->validationConfiguration(),

            'default_value' =>
                $this
                    ->specificationDefinition
                    ->default_value,

            'is_filterable' =>
                (bool) $this
                    ->specificationDefinition
                    ->is_filterable,

            'is_variant_attribute' =>
                (bool) $this
                    ->specificationDefinition
                    ->is_variant_attribute,

            'is_active' =>
                (bool) $this
                    ->specificationDefinition
                    ->is_active,

            'sort_order' =>
                (int) $this
                    ->specificationDefinition
                    ->sort_order,
        ];
    }

    /**
     * Return the user who created the assignment.
     *
     * @return array<string, mixed>|null
     */
    private function createdByData(): ?array
    {
        if (!$this->resource->relationLoaded('createdBy')) {
            return null;
        }

        if ($this->createdBy === null) {
            return null;
        }

        return [
            'id' =>
                $this->createdBy->getKey(),

            'name' =>
                $this->createdBy->name,

            'email' =>
                $this->createdBy->email,
        ];
    }

    /**
     * Return the user who last updated the assignment.
     *
     * @return array<string, mixed>|null
     */
    private function updatedByData(): ?array
    {
        if (!$this->resource->relationLoaded('updatedBy')) {
            return null;
        }

        if ($this->updatedBy === null) {
            return null;
        }

        return [
            'id' =>
                $this->updatedBy->getKey(),

            'name' =>
                $this->updatedBy->name,

            'email' =>
                $this->updatedBy->email,
        ];
    }
}
