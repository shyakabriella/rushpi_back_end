<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the category into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,

            'name' => $this->name,

            'slug' => $this->slug,

            'description' => $this->description,

            'image_path' => $this->image_path,

            'is_active' => (bool) $this->is_active,

            'sort_order' => (int) $this->sort_order,

            /*
             * Parent category is returned only when
             * the parent relationship was loaded.
             */
            'parent' => $this->whenLoaded(
                'parent',
                function (): ?array {
                    if ($this->parent === null) {
                        return null;
                    }

                    return [
                        'public_id' => $this->parent->public_id,
                        'name' => $this->parent->name,
                        'slug' => $this->parent->slug,
                    ];
                }
            ),

            /*
             * Child categories are returned only when
             * the children relationship was loaded.
             */
            'children' => CategoryResource::collection(
                $this->whenLoaded('children')
            ),

            /*
             * Product count is returned only when the
             * controller uses withCount('products').
             */
            'products_count' => $this->whenCounted('products'),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
