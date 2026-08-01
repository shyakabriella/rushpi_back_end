<?php

declare(strict_types=1);

namespace App\Http\Requests\API\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewSellerApplicationRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * This request supports:
     * - approve
     * - reject
     * - requestInformation
     * - suspend
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();

        $commonRules = [
            'internal_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];

        return match ($action) {
            'approve' => $commonRules,

            'reject', 'suspend' => array_merge(
                [
                    'reason' => [
                        'required',
                        'string',
                        'min:10',
                        'max:3000',
                    ],
                ],
                $commonRules
            ),

            'requestInformation' => array_merge(
                [
                    'message' => [
                        'required',
                        'string',
                        'min:10',
                        'max:3000',
                    ],
                ],
                $commonRules
            ),

            /*
             * Fallback rules when this request is used with a
             * single generic review endpoint.
             */
            default => [
                'decision' => [
                    'required',
                    'string',
                    Rule::in([
                        'approved',
                        'rejected',
                        'information_requested',
                        'suspended',
                    ]),
                ],

                'reason' => [
                    Rule::requiredIf(
                        fn (): bool => in_array(
                            $this->input('decision'),
                            [
                                'rejected',
                                'suspended',
                            ],
                            true
                        )
                    ),
                    'nullable',
                    'string',
                    'min:10',
                    'max:3000',
                ],

                'message' => [
                    Rule::requiredIf(
                        fn (): bool =>
                            $this->input('decision')
                            === 'information_requested'
                    ),
                    'nullable',
                    'string',
                    'min:10',
                    'max:3000',
                ],

                'internal_notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ],
        };
    }

    /**
     * Prepare the request data before validation.
     */
    protected function prepareForValidation(): void
    {
        $fields = [
            'decision',
            'reason',
            'message',
            'internal_notes',
        ];

        $prepared = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $prepared[$field] = trim($value);
            }
        }

        if ($prepared !== []) {
            $this->merge($prepared);
        }
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' =>
                'Please select a seller verification decision.',

            'decision.in' =>
                'The selected seller verification decision is invalid.',

            'reason.required' =>
                'Please provide a reason for this decision.',

            'reason.min' =>
                'The reason must contain at least 10 characters.',

            'reason.max' =>
                'The reason must not contain more than 3,000 characters.',

            'message.required' =>
                'Please provide the information requested from the seller.',

            'message.min' =>
                'The information request must contain at least 10 characters.',

            'message.max' =>
                'The information request must not contain more than 3,000 characters.',

            'internal_notes.string' =>
                'The internal notes must be valid text.',

            'internal_notes.max' =>
                'The internal notes must not contain more than 2,000 characters.',
        ];
    }

    /**
     * Get user-friendly validation attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => 'review decision',
            'reason' => 'decision reason',
            'message' => 'information request',
            'internal_notes' => 'internal notes',
        ];
    }
}