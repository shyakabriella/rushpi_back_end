<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductModerationFlag: string
{
    /**
     * Product may violate laws or marketplace restrictions.
     */
    case PROHIBITED_ITEM =
        'prohibited_item';

    /**
     * Product appears to be counterfeit or falsely branded.
     */
    case COUNTERFEIT_GOODS =
        'counterfeit_goods';

    /**
     * Product may be stolen or unlawfully obtained.
     */
    case SUSPECTED_STOLEN_GOODS =
        'suspected_stolen_goods';

    /**
     * Product is a restricted or prohibited weapon.
     */
    case RESTRICTED_WEAPON =
        'restricted_weapon';

    /**
     * Product contains an explosive or dangerous component.
     */
    case EXPLOSIVE_OR_HAZARDOUS_ITEM =
        'explosive_or_hazardous_item';

    /**
     * Product is a controlled, prescription or restricted substance.
     */
    case RESTRICTED_MEDICATION =
        'restricted_medication';

    /**
     * Product contains illegal drugs or related substances.
     */
    case ILLEGAL_DRUGS =
        'illegal_drugs';

    /**
     * Product contains adult or age-restricted content.
     */
    case AGE_RESTRICTED_CONTENT =
        'age_restricted_content';

    /**
     * Product violates wildlife or environmental regulations.
     */
    case WILDLIFE_OR_ENVIRONMENTAL_VIOLATION =
        'wildlife_or_environmental_violation';

    /**
     * Product contains hateful, extremist or terrorist material.
     */
    case EXTREMIST_OR_HATE_CONTENT =
        'extremist_or_hate_content';

    /**
     * Product description, specifications or images are misleading.
     */
    case MISLEADING_INFORMATION =
        'misleading_information';

    /**
     * Product images do not accurately represent the listed product.
     */
    case MISLEADING_MEDIA =
        'misleading_media';

    /**
     * Product is listed in the wrong category.
     */
    case INCORRECT_CATEGORY =
        'incorrect_category';

    /**
     * Product contains incomplete or insufficient information.
     */
    case INCOMPLETE_INFORMATION =
        'incomplete_information';

    /**
     * Product specifications are invalid or inconsistent.
     */
    case INVALID_SPECIFICATIONS =
        'invalid_specifications';

    /**
     * Product price appears fraudulent, deceptive or unrealistic.
     */
    case SUSPICIOUS_PRICING =
        'suspicious_pricing';

    /**
     * Product listing appears duplicated.
     */
    case DUPLICATE_LISTING =
        'duplicate_listing';

    /**
     * Seller may not have authorization to sell the product.
     */
    case UNAUTHORIZED_SELLER =
        'unauthorized_seller';

    /**
     * Product violates intellectual-property rights.
     */
    case INTELLECTUAL_PROPERTY_VIOLATION =
        'intellectual_property_violation';

    /**
     * Product does not comply with marketplace policies.
     */
    case MARKETPLACE_POLICY_VIOLATION =
        'marketplace_policy_violation';

    /**
     * Additional manual review is required.
     */
    case REQUIRES_MANUAL_REVIEW =
        'requires_manual_review';

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PROHIBITED_ITEM =>
                'Prohibited item',

            self::COUNTERFEIT_GOODS =>
                'Counterfeit goods',

            self::SUSPECTED_STOLEN_GOODS =>
                'Suspected stolen goods',

            self::RESTRICTED_WEAPON =>
                'Restricted weapon',

            self::EXPLOSIVE_OR_HAZARDOUS_ITEM =>
                'Explosive or hazardous item',

            self::RESTRICTED_MEDICATION =>
                'Restricted medication',

            self::ILLEGAL_DRUGS =>
                'Illegal drugs',

            self::AGE_RESTRICTED_CONTENT =>
                'Age-restricted content',

            self::WILDLIFE_OR_ENVIRONMENTAL_VIOLATION =>
                'Wildlife or environmental violation',

            self::EXTREMIST_OR_HATE_CONTENT =>
                'Extremist or hate content',

            self::MISLEADING_INFORMATION =>
                'Misleading information',

            self::MISLEADING_MEDIA =>
                'Misleading media',

            self::INCORRECT_CATEGORY =>
                'Incorrect category',

            self::INCOMPLETE_INFORMATION =>
                'Incomplete information',

            self::INVALID_SPECIFICATIONS =>
                'Invalid specifications',

            self::SUSPICIOUS_PRICING =>
                'Suspicious pricing',

            self::DUPLICATE_LISTING =>
                'Duplicate listing',

            self::UNAUTHORIZED_SELLER =>
                'Unauthorized seller',

            self::INTELLECTUAL_PROPERTY_VIOLATION =>
                'Intellectual-property violation',

            self::MARKETPLACE_POLICY_VIOLATION =>
                'Marketplace policy violation',

            self::REQUIRES_MANUAL_REVIEW =>
                'Requires manual review',
        };
    }

    /**
     * Determine whether the flag represents a prohibited or high-risk item.
     */
    public function isProhibited(): bool
    {
        return in_array(
            $this,
            [
                self::PROHIBITED_ITEM,
                self::COUNTERFEIT_GOODS,
                self::SUSPECTED_STOLEN_GOODS,
                self::RESTRICTED_WEAPON,
                self::EXPLOSIVE_OR_HAZARDOUS_ITEM,
                self::RESTRICTED_MEDICATION,
                self::ILLEGAL_DRUGS,
                self::WILDLIFE_OR_ENVIRONMENTAL_VIOLATION,
                self::EXTREMIST_OR_HATE_CONTENT,
            ],
            true
        );
    }

    /**
     * Determine whether the flag usually requires rejection.
     */
    public function requiresRejection(): bool
    {
        return $this->isProhibited();
    }

    /**
     * Determine whether the issue may normally be corrected by the seller.
     */
    public function isCorrectable(): bool
    {
        return in_array(
            $this,
            [
                self::MISLEADING_INFORMATION,
                self::MISLEADING_MEDIA,
                self::INCORRECT_CATEGORY,
                self::INCOMPLETE_INFORMATION,
                self::INVALID_SPECIFICATIONS,
                self::SUSPICIOUS_PRICING,
                self::DUPLICATE_LISTING,
                self::MARKETPLACE_POLICY_VIOLATION,
                self::REQUIRES_MANUAL_REVIEW,
            ],
            true
        );
    }

    /**
     * Return every scalar enum value.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $flag): string =>
                $flag->value,
            self::cases()
        );
    }

    /**
     * Return prohibited flag values.
     *
     * @return array<int, string>
     */
    public static function prohibitedValues(): array
    {
        return array_values(
            array_map(
                static fn (self $flag): string =>
                    $flag->value,
                array_filter(
                    self::cases(),
                    static fn (self $flag): bool =>
                        $flag->isProhibited()
                )
            )
        );
    }

    /**
     * Return API-friendly moderation options.
     *
     * @return array<int, array{
     *     value: string,
     *     label: string,
     *     is_prohibited: bool,
     *     requires_rejection: bool,
     *     is_correctable: bool
     * }>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $flag): array => [
                'value' =>
                    $flag->value,

                'label' =>
                    $flag->label(),

                'is_prohibited' =>
                    $flag->isProhibited(),

                'requires_rejection' =>
                    $flag->requiresRejection(),

                'is_correctable' =>
                    $flag->isCorrectable(),
            ],
            self::cases()
        );
    }
}
