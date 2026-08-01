<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MediaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductMedia
 */
class SellerProductMediaResource extends JsonResource
{
    /**
     * Transform product media into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,

            'media_type' => $this->mediaTypeValue(),

            'original_name' => $this->original_name,

            'mime_type' => $this->mime_type,

            'extension' => $this->extension,

            'size_bytes' => $this->size_bytes !== null
                ? (int) $this->size_bytes
                : null,

            'formatted_size' => $this->formattedSize(),

            'alt_text' => $this->alt_text,

            'sort_order' => (int) $this->sort_order,

            'is_primary' => (bool) $this->is_primary,

            /*
             * The API returns the generated public URL instead
             * of exposing the internal storage path.
             */
            'url' => $this->url(),

            /*
             * Indicates whether the underlying file still exists.
             * This helps sellers detect broken or missing uploads.
             */
            'file_exists' => $this->exists(),

            /*
             * Variant information is included only when the
             * variant relationship has been loaded.
             */
            'variant' => $this->whenLoaded(
                'variant',
                function (): ?array {
                    if ($this->variant === null) {
                        return null;
                    }

                    return [
                        'public_id' => $this->variant->public_id,
                        'sku' => $this->variant->sku,
                        'name' => $this->variant->name,
                    ];
                }
            ),

            /*
             * Product information is included only when the
             * product relationship has been loaded.
             */
            'product' => $this->whenLoaded(
                'product',
                function (): ?array {
                    if ($this->product === null) {
                        return null;
                    }

                    return [
                        'public_id' => $this->product->public_id,
                        'name' => $this->product->name,
                        'slug' => $this->product->slug,
                    ];
                }
            ),

            /*
             * Uploader information is available to the seller
             * when the controller loads the relationship.
             */
            'uploaded_by' => $this->whenLoaded(
                'uploadedBy',
                function (): ?array {
                    if ($this->uploadedBy === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->uploadedBy->public_id,

                        'name' =>
                            $this->uploadedBy->name,

                        'email' =>
                            $this->uploadedBy->email,
                    ];
                }
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return the media type as a string.
     */
    private function mediaTypeValue(): ?string
    {
        if ($this->media_type instanceof MediaType) {
            return $this->media_type->value;
        }

        return is_string($this->media_type)
            ? $this->media_type
            : null;
    }

    /**
     * Return a readable file-size value.
     */
    private function formattedSize(): ?string
    {
        if ($this->size_bytes === null) {
            return null;
        }

        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return round(
            $bytes / (1024 * 1024),
            2
        ).' MB';
    }
}
