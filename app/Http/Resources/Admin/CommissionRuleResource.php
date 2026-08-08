<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommissionRule
 */
final class CommissionRuleResource extends JsonResource
{
    /**
     * Transform the commission rule
     * into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'public_id' =>
                (string) $this->public_id,

            'name' =>
                (string) $this->name,

            /*
            |--------------------------------------------------------------------------
            | Rule scope
            |--------------------------------------------------------------------------
            */

            'scope' =>
                (string) $this->scope,

            /*
            |--------------------------------------------------------------------------
            | Department
            |--------------------------------------------------------------------------
            */

            'department' =>
                $this->whenLoaded(
                    'department',
                    fn (): ?array =>
                        $this->department === null
                            ? null
                            : [
                                'public_id' =>
                                    (string)
                                    $this
                                        ->department
                                        ->public_id,

                                'name' =>
                                    (string)
                                    $this
                                        ->department
                                        ->name,

                                'slug' =>
                                    (string)
                                    $this
                                        ->department
                                        ->slug,
                            ]
                ),

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            'category' =>
                $this->whenLoaded(
                    'category',
                    fn (): ?array =>
                        $this->category === null
                            ? null
                            : [
                                'public_id' =>
                                    (string)
                                    $this
                                        ->category
                                        ->public_id,

                                'name' =>
                                    (string)
                                    $this
                                        ->category
                                        ->name,

                                'slug' =>
                                    (string)
                                    $this
                                        ->category
                                        ->slug,
                            ]
                ),

            /*
            |--------------------------------------------------------------------------
            | Commission
            |--------------------------------------------------------------------------
            */

            'commission_type' =>
                (string)
                $this->commission_type,

            'commission_value' =>
                (float)
                $this->commission_value,

            /*
             * Optional percentage floor.
             */
            'minimum_commission' =>
                $this->minimum_commission === null
                    ? null
                    : (float)
                    $this->minimum_commission,

            /*
             * Optional percentage cap.
             */
            'maximum_commission' =>
                $this->maximum_commission === null
                    ? null
                    : (float)
                    $this->maximum_commission,

            'currency' =>
                (string)
                $this->currency,

            /*
            |--------------------------------------------------------------------------
            | Resolution priority
            |--------------------------------------------------------------------------
            */

            'priority' =>
                (int)
                $this->priority,

            /*
            |--------------------------------------------------------------------------
            | Effective period
            |--------------------------------------------------------------------------
            */

            'starts_at' =>
                $this->starts_at
                    ?->toISOString(),

            'ends_at' =>
                $this->ends_at
                    ?->toISOString(),

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'is_active' =>
                (bool)
                $this->is_active,

            /*
             * True only when:
             *
             * - rule is active
             * - start date has passed
             * - end date has not passed
             */
            'is_currently_effective' =>
                $this
                    ->isCurrentlyEffective(),

            /*
            |--------------------------------------------------------------------------
            | Audit information
            |--------------------------------------------------------------------------
            */

            'audit' => [
                'created_by' =>
                    $this->whenLoaded(
                        'createdBy',
                        fn (): ?array =>
                            $this->createdBy === null
                                ? null
                                : [
                                    'id' =>
                                        $this
                                            ->createdBy
                                            ->getKey(),

                                    'name' =>
                                        $this
                                            ->createdBy
                                            ->name,

                                    'email' =>
                                        $this
                                            ->createdBy
                                            ->email,
                                ]
                    ),

                'updated_by' =>
                    $this->whenLoaded(
                        'updatedBy',
                        fn (): ?array =>
                            $this->updatedBy === null
                                ? null
                                : [
                                    'id' =>
                                        $this
                                            ->updatedBy
                                            ->getKey(),

                                    'name' =>
                                        $this
                                            ->updatedBy
                                            ->name,

                                    'email' =>
                                        $this
                                            ->updatedBy
                                            ->email,
                                ]
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),

            'deleted_at' =>
                $this->deleted_at
                    ?->toISOString(),
        ];
    }
}