<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\ProductModerationAction;
use App\Enums\ProductModerationFlag;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ModerateProductRequest extends FormRequest
{
    /**
     * Authorization remains in the administrator middleware, policy or
     * controller so this request focuses only on validation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize moderation input before validation.
     */
    protected function prepareForValidation(): void
    {
        $action = $this->input('action');

        if (is_string($action)) {
            $this->merge([
                'action' =>
                    strtolower(
                        trim($action)
                    ),
            ]);
        }

        $this->merge([
            'reason' =>
                $this->normalizeOptionalText(
                    $this->input('reason')
                ),

            'notes' =>
                $this->normalizeOptionalText(
                    $this->input('notes')
                ),

            'flag_notes' =>
                $this->normalizeOptionalText(
                    $this->input('flag_notes')
                ),

            'moderation_flags' =>
                $this->normalizeFlags(
                    $this->input(
                        'moderation_flags',
                        []
                    )
                ),
        ]);
    }

    /**
     * Validation rules for a product moderation decision.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::enum(
                    ProductModerationAction::class
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Moderation explanation
            |--------------------------------------------------------------------------
            */

            'reason' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this
                            ->actionRequiresReason()
                ),
                'nullable',
                'string',
                'min:10',
                'max:5000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:10000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Structured moderation flags
            |--------------------------------------------------------------------------
            */

            'moderation_flags' => [
                'present',
                'array',
                'max:25',
            ],

            'moderation_flags.*' => [
                'required',
                'string',
                'distinct',
                Rule::enum(
                    ProductModerationFlag::class
                ),
            ],

            'flag_notes' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this
                            ->hasModerationFlags()
                ),
                'nullable',
                'string',
                'min:10',
                'max:10000',
            ],
        ];
    }

    /**
     * Add moderation-specific cross-field validation.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $action =
                    $this->actionValue();

                $flags =
                    $this->moderationFlags();

                /*
                 * An approval must represent a clean moderation result.
                 */
                if (
                    $action === 'approve'
                    && $flags !== []
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'moderation_flags',
                            'An approved product cannot contain moderation flags.'
                        );
                }

                /*
                 * Prohibited-item classifications cannot be approved.
                 */
                if (
                    $action === 'approve'
                    && $this
                        ->containsProhibitedFlag()
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'action',
                            'A product containing prohibited-item flags cannot be approved.'
                        );
                }

                /*
                 * Rejection is the expected moderation decision for a pending
                 * product containing a prohibited-item classification.
                 *
                 * The controller will enforce or normalize the final action
                 * according to the current product lifecycle state.
                 */
                if (
                    $this
                        ->containsProhibitedFlag()
                    && !in_array(
                        $action,
                        [
                            'reject',
                            'suspend',
                        ],
                        true
                    )
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'action',
                            'Prohibited-item flags require rejection or immediate suspension.'
                        );
                }

                /*
                 * Prevent empty rejection explanations containing only spaces.
                 */
                if (
                    $this
                        ->actionRequiresReason()
                    && $this
                        ->reason() === null
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'reason',
                            'A clear moderation reason is required for this action.'
                        );
                }

                /*
                 * Flag notes must accompany selected flags.
                 */
                if (
                    $flags !== []
                    && $this
                        ->flagNotes() === null
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'flag_notes',
                            'Explain why the selected moderation flags apply.'
                        );
                }
            }
        );
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' =>
                'Select a moderation action.',

            'action.enum' =>
                'The selected moderation action is invalid.',

            'reason.required' =>
                'A moderation reason is required for this action.',

            'reason.min' =>
                'The moderation reason must contain at least 10 characters.',

            'reason.max' =>
                'The moderation reason may not exceed 5,000 characters.',

            'notes.max' =>
                'The internal moderation notes may not exceed 10,000 characters.',

            'moderation_flags.present' =>
                'The moderation flags field must be included.',

            'moderation_flags.array' =>
                'Moderation flags must be supplied as an array.',

            'moderation_flags.max' =>
                'A moderation review may contain a maximum of 25 flags.',

            'moderation_flags.*.required' =>
                'Each moderation flag must contain a value.',

            'moderation_flags.*.distinct' =>
                'The moderation flags must not contain duplicates.',

            'moderation_flags.*.enum' =>
                'One or more selected moderation flags are invalid.',

            'flag_notes.required' =>
                'Flag notes are required when moderation flags are selected.',

            'flag_notes.min' =>
                'Flag notes must contain at least 10 characters.',

            'flag_notes.max' =>
                'Flag notes may not exceed 10,000 characters.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validated moderation data
    |--------------------------------------------------------------------------
    */

    /**
     * Return the validated moderation action enum.
     */
    public function moderationAction():
        ProductModerationAction
    {
        return ProductModerationAction::from(
            $this->actionValue()
        );
    }

    /**
     * Return the normalized action value.
     */
    public function actionValue(): string
    {
        return strtolower(
            trim(
                (string) $this->input(
                    'action'
                )
            )
        );
    }

    /**
     * Return the normalized moderation reason.
     */
    public function reason(): ?string
    {
        return $this->normalizeOptionalText(
            $this->input('reason')
        );
    }

    /**
     * Return optional internal moderation notes.
     */
    public function notes(): ?string
    {
        return $this->normalizeOptionalText(
            $this->input('notes')
        );
    }

    /**
     * Return optional notes explaining the selected flags.
     */
    public function flagNotes(): ?string
    {
        return $this->normalizeOptionalText(
            $this->input('flag_notes')
        );
    }

    /**
     * Return normalized moderation-flag enum instances.
     *
     * @return array<int, ProductModerationFlag>
     */
    public function moderationFlags(): array
    {
        return array_values(
            array_filter(
                array_map(
                    static fn (
                        string $flag
                    ): ?ProductModerationFlag =>
                        ProductModerationFlag
                            ::tryFrom($flag),

                    $this
                        ->moderationFlagValues()
                )
            )
        );
    }

    /**
     * Return normalized moderation-flag scalar values.
     *
     * @return array<int, string>
     */
    public function moderationFlagValues():
        array
    {
        return $this->normalizeFlags(
            $this->input(
                'moderation_flags',
                []
            )
        );
    }

    /**
     * Determine whether moderation flags were selected.
     */
    public function hasModerationFlags():
        bool
    {
        return $this
            ->moderationFlagValues()
            !== [];
    }

    /**
     * Determine whether at least one selected flag is prohibited.
     */
    public function containsProhibitedFlag():
        bool
    {
        foreach (
            $this->moderationFlags()
            as $flag
        ) {
            if ($flag->isProhibited()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether selected flags require rejection.
     */
    public function flagsRequireRejection():
        bool
    {
        foreach (
            $this->moderationFlags()
            as $flag
        ) {
            if (
                $flag
                    ->requiresRejection()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the action requires an explanation.
     */
    public function actionRequiresReason():
        bool
    {
        return in_array(
            $this->actionValue(),
            [
                'reject',
                'suspend',
                'return_to_draft',
            ],
            true
        );
    }

    /**
     * Return controller-ready moderation input.
     *
     * @return array{
     *     action: ProductModerationAction,
     *     action_value: string,
     *     reason: string|null,
     *     notes: string|null,
     *     moderation_flags: array<int, string>,
     *     is_prohibited_item: bool,
     *     requires_rejection: bool,
     *     flag_notes: string|null
     * }
     */
    public function moderationData(): array
    {
        return [
            'action' =>
                $this->moderationAction(),

            'action_value' =>
                $this->actionValue(),

            'reason' =>
                $this->reason(),

            'notes' =>
                $this->notes(),

            'moderation_flags' =>
                $this
                    ->moderationFlagValues(),

            'is_prohibited_item' =>
                $this
                    ->containsProhibitedFlag(),

            'requires_rejection' =>
                $this
                    ->flagsRequireRejection(),

            'flag_notes' =>
                $this->flagNotes(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Normalization helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize optional text.
     */
    private function normalizeOptionalText(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Normalize structured moderation flags.
     *
     * @return array<int, string>
     */
    private function normalizeFlags(
        mixed $flags
    ): array {
        if ($flags === null) {
            return [];
        }

        if (is_string($flags)) {
            $flags = [
                $flags,
            ];
        }

        if (!is_array($flags)) {
            return $flags;
        }

        $normalized = [];

        foreach ($flags as $flag) {
            if (
                $flag instanceof
                ProductModerationFlag
            ) {
                $normalized[] =
                    $flag->value;

                continue;
            }

            if (!is_string($flag)) {
                $normalized[] =
                    $flag;

                continue;
            }

            $value = strtolower(
                trim($flag)
            );

            if ($value !== '') {
                $normalized[] =
                    $value;
            }
        }

        return array_values(
            array_unique(
                $normalized,
                SORT_REGULAR
            )
        );
    }
}