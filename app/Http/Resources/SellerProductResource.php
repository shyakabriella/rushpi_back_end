<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Product
 */
class SellerProductResource extends JsonResource
{
    /**
     * Transform the seller product into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,

            'name' => $this->name,

            'slug' => $this->slug,

            'short_description' => $this->short_description,

            'description' => $this->description,

            'condition' => $this->conditionValue(),

            'condition_label' => $this->conditionLabel(),

            'warranty_months' => $this->warranty_months !== null
                ? (int) $this->warranty_months
                : null,

            'specifications' => $this->specifications ?? [],

            'status' => $this->statusValue(),

            'status_label' => $this->statusLabel(),

            'is_publicly_visible' => $this->isPubliclyVisible(),

            'can_be_edited' => $this->canBeEditedBySeller(),

            /*
             * Category information is returned only when
             * the category relationship is loaded.
             */
            'category' => $this->whenLoaded(
                'category',
                function (): ?array {
                    if ($this->category === null) {
                        return null;
                    }

                    return [
                        'public_id' => $this->category->public_id,
                        'name' => $this->category->name,
                        'slug' => $this->category->slug,
                    ];
                }
            ),

            /*
             * Brand information is returned only when
             * the brand relationship is loaded.
             */
            'brand' => $this->whenLoaded(
                'brand',
                function (): ?array {
                    if ($this->brand === null) {
                        return null;
                    }

                    return [
                        'public_id' => $this->brand->public_id,
                        'name' => $this->brand->name,
                        'slug' => $this->brand->slug,
                        'logo_path' => $this->brand->logo_path,
                    ];
                }
            ),

            /*
             * Seller information is returned only when
             * the seller profile relationship is loaded.
             */
            'seller' => $this->whenLoaded(
                'sellerProfile',
                function (): ?array {
                    if ($this->sellerProfile === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->sellerProfile->public_id,

                        'legal_business_name' =>
                            $this->sellerProfile
                                ->legal_business_name,

                        'trading_name' =>
                            $this->sellerProfile->trading_name,

                        'status' =>
                            $this->sellerProfile->status?->value
                            ?? $this->sellerProfile->status,
                    ];
                }
            ),

            /*
             * Relationship counts are returned only when
             * the controller explicitly loads them.
             */
            'variants_count' => $this->whenCounted('variants'),

            'active_variants_count' =>
                $this->whenCounted('activeVariants'),

            'media_count' => $this->whenCounted('media'),

            'moderation_reviews_count' =>
                $this->whenCounted('moderationReviews'),

            /*
             * Moderation information is visible to the seller.
             */
            'moderation' => [
                'rejection_reason' => $this->rejection_reason,

                'suspension_reason' => $this->suspension_reason,

                'submitted_at' =>
                    $this->submitted_at?->toISOString(),

                'approved_at' =>
                    $this->approved_at?->toISOString(),

                'rejected_at' =>
                    $this->rejected_at?->toISOString(),

                'suspended_at' =>
                    $this->suspended_at?->toISOString(),

                'archived_at' =>
                    $this->archived_at?->toISOString(),
            ],

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return the raw product condition value.
     */
    private function conditionValue(): ?string
    {
        if ($this->condition instanceof ProductCondition) {
            return $this->condition->value;
        }

        return is_string($this->condition)
            ? $this->condition
            : null;
    }

    /**
     * Return the readable product condition label.
     */
    private function conditionLabel(): ?string
    {
        if ($this->condition instanceof ProductCondition) {
            return $this->condition->label();
        }

        if (is_string($this->condition)) {
            return ProductCondition::tryFrom(
                $this->condition
            )?->label();
        }

        return null;
    }

    /**
     * Return the raw product status value.
     */
    private function statusValue(): ?string
    {
        if ($this->status instanceof ProductStatus) {
            return $this->status->value;
        }

        return is_string($this->status)
            ? $this->status
            : null;
    }

    /**
     * Return the readable product status label.
     */
    private function statusLabel(): ?string
    {
        if ($this->status instanceof ProductStatus) {
            return $this->status->label();
        }

        if (is_string($this->status)) {
            return ProductStatus::tryFrom(
                $this->status
            )?->label();
        }

        return null;
    }
}
