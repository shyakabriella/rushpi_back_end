<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Department
 */
final class DepartmentResource
    extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $hasCategories =
            $this->resource
                ->relationLoaded(
                    'categories'
                )
            || $this->resource
                ->relationLoaded(
                    'activeCategories'
                );

        $categories =
            $this->resource
                ->relationLoaded(
                    'categories'
                )
                ? $this->resource
                    ->categories
                : (
                    $this->resource
                        ->relationLoaded(
                            'activeCategories'
                        )
                        ? $this->resource
                            ->activeCategories
                        : collect()
                );

        return [
            'public_id' =>
                $this->public_id,

            'name' =>
                $this->name,

            'slug' =>
                $this->slug,

            'description' =>
                $this->description,

            'image_path' =>
                $this->image_path,

            'is_active' =>
                (bool)
                $this->is_active,

            'sort_order' =>
                (int)
                $this->sort_order,

            'categories_count' =>
                $this->whenCounted(
                    'categories'
                ),

            'categories' =>
                $this->when(
                    $hasCategories,
                    fn (): array =>
                        $categories
                            ->map(
                                static function (
                                    Category $category
                                ): array {
                                    return [
                                        'public_id' =>
                                            $category
                                                ->public_id,

                                        'name' =>
                                            $category
                                                ->name,

                                        'slug' =>
                                            $category
                                                ->slug,

                                        'description' =>
                                            $category
                                                ->description,

                                        'image_path' =>
                                            $category
                                                ->image_path,

                                        'is_active' =>
                                            (bool)
                                            $category
                                                ->is_active,

                                        'sort_order' =>
                                            (int) (
                                                $category
                                                    ->pivot
                                                    ?->sort_order
                                                ?? 0
                                            ),

                                        'is_featured' =>
                                            (bool) (
                                                $category
                                                    ->pivot
                                                    ?->is_featured
                                                ?? false
                                            ),

                                        'assignment_active' =>
                                            (bool) (
                                                $category
                                                    ->pivot
                                                    ?->is_active
                                                ?? true
                                            ),

                                        'parent' =>
                                            $category
                                                ->relationLoaded(
                                                    'parent'
                                                )
                                            && $category
                                                ->parent
                                                !== null
                                                ? [
                                                    'public_id' =>
                                                        $category
                                                            ->parent
                                                            ->public_id,

                                                    'name' =>
                                                        $category
                                                            ->parent
                                                            ->name,

                                                    'slug' =>
                                                        $category
                                                            ->parent
                                                            ->slug,
                                                ]
                                                : null,
                                    ];
                                }
                            )
                            ->values()
                            ->all()
                ),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }
}