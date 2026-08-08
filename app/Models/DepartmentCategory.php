<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class DepartmentCategory extends Pivot
{
    protected $table =
        'department_category';

    public $incrementing = true;

    protected $fillable = [
        'department_id',
        'category_id',
        'sort_order',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'department_id' =>
                'integer',

            'category_id' =>
                'integer',

            'sort_order' =>
                'integer',

            'is_featured' =>
                'boolean',

            'is_active' =>
                'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }
}