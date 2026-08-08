<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateCommissionRuleRequest extends FormRequest
{
    /**
     * Authorization is handled by
     * the Admin route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming values before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'name',
                'scope',
                'commission_type',
                'currency',
                'department_public_id',
                'category_public_id',
            ] as $field
        ) {
            if (!$this->exists($field)) {
                continue;
            }

            $value = trim(
                (string) $this->input(
                    $field,
                    ''
                )
            );

            $normalized[$field] =
                $value !== ''
                    ? $value
                    : null;
        }

        if (
            isset(
                $normalized['scope']
            )
        ) {
            $normalized['scope'] =
                strtolower(
                    (string)
                    $normalized['scope']
                );
        }

        if (
            isset(
                $normalized[
                    'commission_type'
                ]
            )
        ) {
            $normalized[
                'commission_type'
            ] = strtolower(
                (string)
                $normalized[
                    'commission_type'
                ]
            );
        }

        if (
            isset(
                $normalized['currency']
            )
        ) {
            $normalized['currency'] =
                strtoupper(
                    (string)
                    $normalized['currency']
                );
        }

        if ($normalized !== []) {
            $this->merge(
                $normalized
            );
        }
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'scope' => [
                'sometimes',
                'required',

                Rule::in([
                    CommissionRule::SCOPE_GLOBAL,
                    CommissionRule::SCOPE_DEPARTMENT,
                    CommissionRule::SCOPE_CATEGORY,
                ]),
            ],

            'department_public_id' => [
                'sometimes',
                'nullable',
                'string',
                'size:26',

                Rule::exists(
                    'departments',
                    'public_id'
                )->where(
                    static function (
                        $query
                    ): void {
                        $query
                            ->whereNull(
                                'deleted_at'
                            )
                            ->where(
                                'is_active',
                                true
                            );
                    }
                ),
            ],

            'category_public_id' => [
                'sometimes',
                'nullable',
                'string',
                'size:26',

                Rule::exists(
                    'categories',
                    'public_id'
                )->where(
                    static function (
                        $query
                    ): void {
                        $query
                            ->whereNull(
                                'deleted_at'
                            )
                            ->where(
                                'is_active',
                                true
                            );
                    }
                ),
            ],

            'commission_type' => [
                'sometimes',
                'required',

                Rule::in([
                    CommissionRule::TYPE_PERCENTAGE,
                    CommissionRule::TYPE_FIXED,
                ]),
            ],

            'commission_value' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'minimum_commission' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'maximum_commission' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            'priority' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Cross-field validation.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $rule =
                    $this->currentRule();

                if ($rule === null) {
                    return;
                }

                $scope = (string)
                    $this->input(
                        'scope',
                        $rule->scope
                    );

                $commissionType =
                    (string)
                    $this->input(
                        'commission_type',
                        $rule->commission_type
                    );

                $departmentPublicId =
                    $this->exists(
                        'department_public_id'
                    )
                        ? $this->input(
                            'department_public_id'
                        )
                        : $rule
                            ->department
                            ?->public_id;

                $categoryPublicId =
                    $this->exists(
                        'category_public_id'
                    )
                        ? $this->input(
                            'category_public_id'
                        )
                        : $rule
                            ->category
                            ?->public_id;

                $this->validateScope(
                    $validator,
                    $scope,
                    $departmentPublicId,
                    $categoryPublicId
                );

                $this->validateCommission(
                    $validator,
                    $rule,
                    $commissionType
                );

                $this->validateDates(
                    $validator,
                    $rule
                );
            }
        );
    }

    /**
     * Resolve the commission rule
     * from route model binding.
     */
    private function currentRule(): ?CommissionRule
    {
        $routeValue =
            $this->route(
                'commissionRule'
            );

        if (
            $routeValue
            instanceof CommissionRule
        ) {
            $routeValue->loadMissing([
                'department',
                'category',
            ]);

            return $routeValue;
        }

        $routeValue =
            $routeValue
            ?? $this->route(
                'commission_rule'
            );

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            return CommissionRule::query()
                ->with([
                    'department',
                    'category',
                ])
                ->where(
                    'public_id',
                    trim($routeValue)
                )
                ->first();
        }

        return null;
    }

    /**
     * Validate rule scope.
     */
    private function validateScope(
        Validator $validator,
        string $scope,
        mixed $departmentPublicId,
        mixed $categoryPublicId
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Global
        |--------------------------------------------------------------------------
        */

        if (
            $scope ===
            CommissionRule::SCOPE_GLOBAL
        ) {
            /*
             * When changing to global,
             * controller/model will clear targets.
             */
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Department
        |--------------------------------------------------------------------------
        */

        if (
            $scope ===
            CommissionRule::SCOPE_DEPARTMENT
        ) {
            if (
                $departmentPublicId === null
                || $departmentPublicId === ''
            ) {
                $validator
                    ->errors()
                    ->add(
                        'department_public_id',
                        'Select a department for a department commission rule.'
                    );
            }

            /*
             * If request explicitly tries to keep
             * a category while scope is department,
             * reject it.
             */
            if (
                $this->exists(
                    'category_public_id'
                )
                && $categoryPublicId !== null
                && $categoryPublicId !== ''
            ) {
                $validator
                    ->errors()
                    ->add(
                        'category_public_id',
                        'A department commission rule must not target a category.'
                    );
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        if (
            $scope ===
            CommissionRule::SCOPE_CATEGORY
        ) {
            if (
                $categoryPublicId === null
                || $categoryPublicId === ''
            ) {
                $validator
                    ->errors()
                    ->add(
                        'category_public_id',
                        'Select a category for a category commission rule.'
                    );
            }

            if (
                $this->exists(
                    'department_public_id'
                )
                && $departmentPublicId !== null
                && $departmentPublicId !== ''
            ) {
                $validator
                    ->errors()
                    ->add(
                        'department_public_id',
                        'A category commission rule must not directly target a department.'
                    );
            }
        }
    }

    /**
     * Validate commission rules.
     */
    private function validateCommission(
        Validator $validator,
        CommissionRule $rule,
        string $commissionType
    ): void {
        $commissionValue =
            $this->input(
                'commission_value',
                $rule->commission_value
            );

        /*
        |--------------------------------------------------------------------------
        | Percentage limit
        |--------------------------------------------------------------------------
        */

        if (
            $commissionType ===
            CommissionRule::TYPE_PERCENTAGE
            && is_numeric(
                $commissionValue
            )
            && (float)
                $commissionValue > 100
        ) {
            $validator
                ->errors()
                ->add(
                    'commission_value',
                    'A percentage commission cannot be greater than 100%.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Effective minimum
        |--------------------------------------------------------------------------
        */

        $minimum =
            $this->exists(
                'minimum_commission'
            )
                ? $this->input(
                    'minimum_commission'
                )
                : $rule
                    ->minimum_commission;

        /*
        |--------------------------------------------------------------------------
        | Effective maximum
        |--------------------------------------------------------------------------
        */

        $maximum =
            $this->exists(
                'maximum_commission'
            )
                ? $this->input(
                    'maximum_commission'
                )
                : $rule
                    ->maximum_commission;

        /*
        |--------------------------------------------------------------------------
        | Fixed commissions
        |--------------------------------------------------------------------------
        */

        if (
            $commissionType ===
            CommissionRule::TYPE_FIXED
        ) {
            /*
             * When converting an existing percentage
             * rule to fixed, old min/max values will
             * automatically be cleared by the model.
             *
             * But explicitly submitting min/max
             * together with a fixed rule is invalid.
             */

            if (
                $this->exists(
                    'minimum_commission'
                )
                && $this->input(
                    'minimum_commission'
                ) !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'minimum_commission',
                        'Minimum commission is only available for percentage commission rules.'
                    );
            }

            if (
                $this->exists(
                    'maximum_commission'
                )
                && $this->input(
                    'maximum_commission'
                ) !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'maximum_commission',
                        'Maximum commission is only available for percentage commission rules.'
                    );
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Min / Max validation
        |--------------------------------------------------------------------------
        */

        if (
            is_numeric($minimum)
            && is_numeric($maximum)
            && (float) $minimum >
                (float) $maximum
        ) {
            $validator
                ->errors()
                ->add(
                    'maximum_commission',
                    'Maximum commission must be greater than or equal to minimum commission.'
                );
        }
    }

    /**
     * Validate effective date range.
     */
    private function validateDates(
        Validator $validator,
        CommissionRule $rule
    ): void {
        $startsAt =
            $this->exists(
                'starts_at'
            )
                ? $this->date(
                    'starts_at'
                )
                : $rule->starts_at;

        $endsAt =
            $this->exists(
                'ends_at'
            )
                ? $this->date(
                    'ends_at'
                )
                : $rule->ends_at;

        if (
            $startsAt !== null
            && $endsAt !== null
            && $endsAt->lt(
                $startsAt
            )
        ) {
            $validator
                ->errors()
                ->add(
                    'ends_at',
                    'The commission rule end date must be after or equal to the start date.'
                );
        }
    }

    /**
     * Friendly messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' =>
                'Enter a name for the commission rule.',

            'scope.required' =>
                'Select the commission rule scope.',

            'commission_type.required' =>
                'Select the commission type.',

            'commission_value.required' =>
                'Enter the commission value.',

            'commission_value.numeric' =>
                'Commission value must be a valid number.',

            'department_public_id.exists' =>
                'The selected department is invalid or inactive.',

            'category_public_id.exists' =>
                'The selected category is invalid or inactive.',

            'currency.size' =>
                'Currency must use a three-letter code such as RWF, USD or EUR.',
        ];
    }
}