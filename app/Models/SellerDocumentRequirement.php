<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SellerDocumentRequirement extends Model
{
    use HasFactory;

    public const LEVEL_REQUIRED =
        'required';

    public const LEVEL_CONDITIONAL =
        'conditional';

    public const LEVEL_RECOMMENDED =
        'recommended';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'name',
        'requirement_level',
        'condition',
        'description',
        'allow_multiple',
        'supports_expiry_date',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_multiple' =>
                'boolean',

            'supports_expiry_date' =>
                'boolean',

            'is_active' =>
                'boolean',

            'sort_order' =>
                'integer',
        ];
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeRequired(
        Builder $query
    ): Builder {
        return $query->where(
            'requirement_level',
            self::LEVEL_REQUIRED
        );
    }

    public function scopeConditional(
        Builder $query
    ): Builder {
        return $query->where(
            'requirement_level',
            self::LEVEL_CONDITIONAL
        );
    }

    public function scopeRecommended(
        Builder $query
    ): Builder {
        return $query->where(
            'requirement_level',
            self::LEVEL_RECOMMENDED
        );
    }

    public function isRequired(): bool
    {
        return $this->requirement_level ===
            self::LEVEL_REQUIRED;
    }

    public function isConditional(): bool
    {
        return $this->requirement_level ===
            self::LEVEL_CONDITIONAL;
    }

    public function isRecommended(): bool
    {
        return $this->requirement_level ===
            self::LEVEL_RECOMMENDED;
    }
}