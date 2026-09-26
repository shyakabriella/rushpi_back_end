<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BrandModel extends Model
{
    use SoftDeletes;

    protected $table = 'brand_models';

    protected $fillable = [
        'brand_id',
        'brand_series_id',
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
            function (BrandModel $model): void {
                if (blank($model->public_id)) {
                    $model->public_id =
                        (string) Str::ulid();
                }

                $model->name = trim(
                    (string) $model->name
                );

                $model->slug = Str::slug(
                    blank($model->slug)
                        ? $model->name
                        : (string) $model->slug
                );
            }
        );

        static::updating(
            function (BrandModel $model): void {
                if ($model->isDirty('name')) {
                    $model->name = trim(
                        (string) $model->name
                    );
                }

                if ($model->isDirty('slug')) {
                    $model->slug = Str::slug(
                        (string) $model->slug
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

    public function series(): BelongsTo
    {
        return $this->belongsTo(
            BrandSeries::class,
            'brand_series_id'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where('is_active', true);
    }
}
