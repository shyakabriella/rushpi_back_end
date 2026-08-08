<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\CommissionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreCommissionRuleRequest extends FormRequest
{
    /**
     * Authorization is already handled
     * by the Admin route middleware.
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

        /*
        |--------------------------------------------------------------------------
        | Normalize text fields
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Normalize scope
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Normalize commission type
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Normalize currency
        |--------------------------------------------------------------------------
        */

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

        /*
         * Default currency.
         */
        if (
            !$this->exists('currency')
            || $this->input('currency') === null
            || trim(
                (string)
                $this->input('currency')
            ) === ''
        ) {
            $normalized['currency'] =
                'RWF';
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
            /*
            |--------------------------------------------------------------------------
            | Rule identification
            |--------------------------------------------------------------------------
            */

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Commission scope
            |--------------------------------------------------------------------------
            |
            | global
            | department
            | category
            |
            */

            'scope' => [
                'required',
                Rule::in([
                    CommissionRule::SCOPE_GLOBAL,
                    CommissionRule::SCOPE_DEPARTMENT,
                    CommissionRule::SCOPE_CATEGORY,
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Department target
            |--------------------------------------------------------------------------
            */

            'department_public_id' => [
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

            /*
            |--------------------------------------------------------------------------
            | Category target
            |--------------------------------------------------------------------------
            */

            'category_public_id' => [
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

            /*
            |--------------------------------------------------------------------------
            | Commission type
            |--------------------------------------------------------------------------
            |
            | percentage
            | fixed
            |
            */

            'commission_type' => [
                'required',

                Rule::in([
                    CommissionRule::TYPE_PERCENTAGE,
                    CommissionRule::TYPE_FIXED,
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Commission value
            |--------------------------------------------------------------------------
            */

            'commission_value' => [
                'required',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Percentage floor / cap
            |--------------------------------------------------------------------------
            */

            'minimum_commission' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'maximum_commission' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Currency
            |--------------------------------------------------------------------------
            */

            'currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            /*
            |--------------------------------------------------------------------------
            | Priority
            |--------------------------------------------------------------------------
            |
            | Higher priority wins when multiple rules
            | exist at the SAME scope.
            |
            */

            'priority' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            /*
            |--------------------------------------------------------------------------
            | Effective period
            |--------------------------------------------------------------------------
            */

            'starts_at' => [
                'nullable',
                'date',
            ],

            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

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
                $this->validateScope(
                    $validator
                );

                $this->validateCommission(
                    $validator
                );
            }
        );
    }

    /**
     * Validate the target based on scope.
     */
    private function validateScope(
        Validator $validator
    ): void {
        $scope = (string)
            $this->input(
                'scope'
            );

        $departmentPublicId =
            $this->input(
                'department_public_id'
            );

        $categoryPublicId =
            $this->input(
                'category_public_id'
            );

        /*
        |--------------------------------------------------------------------------
        | Global rule
        |--------------------------------------------------------------------------
        |
        | No Department.
        | No Category.
        |
        */

        if (
            $scope ===
            CommissionRule::SCOPE_GLOBAL
        ) {
            if (
                $departmentPublicId !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'department_public_id',
                        'A global commission rule must not target a department.'
                    );
            }

            if (
                $categoryPublicId !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'category_public_id',
                        'A global commission rule must not target a category.'
                    );
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Department rule
        |--------------------------------------------------------------------------
        */

        if (
            $scope ===
            CommissionRule::SCOPE_DEPARTMENT
        ) {
            if (
                $departmentPublicId === null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'department_public_id',
                        'Select a department for a department commission rule.'
                    );
            }

            if (
                $categoryPublicId !== null
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
        | Category rule
        |--------------------------------------------------------------------------
        */

        if (
            $scope ===
            CommissionRule::SCOPE_CATEGORY
        ) {
            if (
                $categoryPublicId === null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'category_public_id',
                        'Select a category for a category commission rule.'
                    );
            }

            if (
                $departmentPublicId !== null
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
     * Validate commission-specific constraints.
     */
    private function validateCommission(
        Validator $validator
    ): void {
        $type = (string)
            $this->input(
                'commission_type'
            );

        $value =
            $this->input(
                'commission_value'
            );

        /*
        |--------------------------------------------------------------------------
        | Percentage validation
        |--------------------------------------------------------------------------
        */

        if (
            $type ===
            CommissionRule::TYPE_PERCENTAGE
            && is_numeric($value)
            && (float) $value > 100
        ) {
            $validator
                ->errors()
                ->add(
                    'commission_value',
                    'A percentage commission cannot be greater than 100%.'
                );
        }

        $minimum =
            $this->input(
                'minimum_commission'
            );

        $maximum =
            $this->input(
                'maximum_commission'
            );

        /*
        |--------------------------------------------------------------------------
        | Fixed commission validation
        |--------------------------------------------------------------------------
        |
        | Minimum / maximum do not apply
        | to a fixed commission.
        |
        */

        if (
            $type ===
            CommissionRule::TYPE_FIXED
        ) {
            if (
                $minimum !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'minimum_commission',
                        'Minimum commission is only available for percentage commission rules.'
                    );
            }

            if (
                $maximum !== null
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
        | Minimum / maximum relationship
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
     * Friendly validation messages.
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

            'ends_at.after_or_equal' =>
                'The commission rule end date must be after or equal to its start date.',
        ];
    }
}