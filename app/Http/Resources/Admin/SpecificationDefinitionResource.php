<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SpecificationDataType;
use App\Models\CategorySpecification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SpecificationDefinition
 */
final class SpecificationDefinitionResource extends JsonResource
{
    /**
     * Transform the specification definition into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dataType = $this->data_type;

        if (!$dataType instanceof SpecificationDataType) {
            $dataType = SpecificationDataType::tryFrom(
                (string) $dataType
            );
        }

        return [
            'public_id' => (string) $this->public_id,

            'name' => (string) $this->name,

            'code' => (string) $this->code,

            'description' => $this->description,

            'data_type' => [
                'value' => $dataType?->value,

                'label' => $dataType?->label(),

                'api_type' => $dataType?->apiType(),

                'uses_options' =>
                    $dataType?->usesOptions() ?? false,

                'accepts_multiple_values' =>
                    $dataType?->acceptsMultipleValues()
                    ?? false,

                'is_numeric' =>
                    $dataType?->isNumeric() ?? false,
            ],

            'unit' => $this->unit,

            'options' => $this->optionItems(),

            'option_values' => $this->optionValues(),

            'validation_rules' =>
                $this->validationConfiguration(),

            'base_validation_rules' =>
                $this->baseValidationRules(),

            'default_value' =>
                $this->default_value,

            'is_filterable' =>
                (bool) $this->is_filterable,

            'is_variant_attribute' =>
                (bool) $this->is_variant_attribute,

            'is_active' =>
                (bool) $this->is_active,

            'sort_order' =>
                (int) $this->sort_order,

            'category_assignments_count' =>
                $this->assignmentCount(),

            'category_assignments' =>
                $this->whenLoaded(
                    'categorySpecifications',
                    fn (): array => $this
                        ->categorySpecifications
                        ->map(
                            static fn (
                                CategorySpecification $assignment
                            ): array => [
                                'public_id' =>
                                    (string) $assignment->public_id,

                                'category' => [
                                    'public_id' =>
                                        $assignment->relationLoaded(
                                            'category'
                                        )
                                            ? $assignment
                                                ->category
                                                ?->public_id
                                            : null,

                                    'name' =>
                                        $assignment->relationLoaded(
                                            'category'
                                        )
                                            ? $assignment
                                                ->category
                                                ?->name
                                            : null,

                                    'slug' =>
                                        $assignment->relationLoaded(
                                            'category'
                                        )
                                            ? $assignment
                                                ->category
                                                ?->slug
                                            : null,
                                ],

                                'label' =>
                                    $assignment->effectiveLabel(),

                                'help_text' =>
                                    $assignment
                                        ->effectiveHelpText(),

                                'is_required' =>
                                    $assignment->isRequired(),

                                'is_filterable' =>
                                    $assignment->isFilterable(),

                                'is_variant_attribute' =>
                                    $assignment
                                        ->isVariantAttribute(),

                                'is_active' =>
                                    (bool) $assignment->is_active,

                                'options' =>
                                    $assignment
                                        ->effectiveOptions(),

                                'validation_rules' =>
                                    $assignment
                                        ->effectiveValidationConfiguration(),

                                'default_value' =>
                                    $assignment
                                        ->effectiveDefaultValue(),

                                'sort_order' =>
                                    (int) $assignment->sort_order,
                            ]
                        )
                        ->values()
                        ->all()
                ),

            'audit' => [
                'created_by' => $this->whenLoaded(
                    'createdBy',
                    fn (): ?array => $this->createdBy === null
                        ? null
                        : [
                            'id' => $this->createdBy->getKey(),

                            'name' => $this->createdBy->name,

                            'email' => $this->createdBy->email,
                        ]
                ),

                'updated_by' => $this->whenLoaded(
                    'updatedBy',
                    fn (): ?array => $this->updatedBy === null
                        ? null
                        : [
                            'id' => $this->updatedBy->getKey(),

                            'name' => $this->updatedBy->name,

                            'email' => $this->updatedBy->email,
                        ]
                ),
            ],

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'deleted_at' =>
                $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * Return the category-assignment count without causing unnecessary
     * queries when the relationship or count has already been loaded.
     */
    private function assignmentCount(): int
    {
        if (
            array_key_exists(
                'category_specifications_count',
                $this->resource->getAttributes()
            )
        ) {
            return (int) $this
                ->category_specifications_count;
        }

        if (
            $this->resource->relationLoaded(
                'categorySpecifications'
            )
        ) {
            return $this
                ->categorySpecifications
                ->count();
        }

        return $this
            ->categorySpecifications()
            ->count();
    }
}
