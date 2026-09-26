<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BrandSeries extends Model
{
    use SoftDeletes;

    protected $table = 'brand_series';

    protected $fillable = [
        'brand_id',
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (BrandSeries $series): void {
                if (blank($series->public_id)) {
                    $series->public_id =
                        (string) Str::ulid();
                }

                $series->name = trim(
                    (string) $series->name
                );

                $series->slug = Str::slug(
                    blank($series->slug)
                        ? $series->name
                        : (string) $series->slug
                );
            }
        );

        static::updating(
            function (BrandSeries $series): void {
                if ($series->isDirty('name')) {
                    $series->name = trim(
                        (string) $series->name
                    );
                }

                if ($series->isDirty('slug')) {
                    $series->slug = Str::slug(
                        (string) $series->slug
                    );
                }
            }
        );
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function models(): HasMany
    {
        return $this->hasMany(
            BrandModel::class,
            'brand_series_id'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where('is_active', true);
    }
}
