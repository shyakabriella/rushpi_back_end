<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Category can belong to a parent category.
     * Example: Electronics > Smartphones
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Category can have many child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Only active child categories.
     */
    public function activeChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Later, when Product model is created,
     * this relationship will work.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}