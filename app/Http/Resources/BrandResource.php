<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Brand
 */
class BrandResource extends JsonResource
{
    /**
     * Transform the brand into an API response.
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

            'logo_path' => $this->logo_path,

            'website_url' => $this->website_url,

            'is_active' => (bool) $this->is_active,

            'sort_order' => (int) $this->sort_order,

            /*
             * Returned only when the controller loads
             * the number of products using withCount().
             */
            'products_count' => $this->whenCounted('products'),

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
