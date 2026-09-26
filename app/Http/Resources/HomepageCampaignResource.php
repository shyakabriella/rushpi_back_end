<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomepageCampaignResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'public_id' => $this->public_id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,

            'desktop_image_path' => $this->desktop_image_path,

            'desktop_image_url' => $this->desktop_image_url,

            'mobile_image_path' => $this->mobile_image_path,

            'mobile_image_url' => $this->mobile_image_url,

            'background_color' => $this->background_color,

            'text_color' => $this->text_color,
            'button_text' => $this->button_text,
            'link_type' => $this->link_type,
            'link_value' => $this->link_value,

            'destination_url' => $this->destination_url,

            'card_size' => $this->card_size,
            'position' => $this->position,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'status' => $this->status,

            'created_at' => $this->created_at?->toIso8601String(),

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
