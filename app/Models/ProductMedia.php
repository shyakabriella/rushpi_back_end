<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductMedia extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Database table used by this model.
     *
     * @var string
     */
    protected $table = 'product_media';

    /**
     * Fields that may be assigned safely.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'uploaded_by',
        'media_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    /**
     * Product media attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Generate the public identifier automatically.
     */
    protected static function booted(): void
    {
        static::creating(function (ProductMedia $media): void {
            if (blank($media->public_id)) {
                $media->public_id = (string) Str::ulid();
            }

            if (blank($media->media_type)) {
                $media->media_type = MediaType::IMAGE;
            }

            if (blank($media->disk)) {
                $media->disk = 'public';
            }
        });
    }

    /**
     * Use public_id for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Product that owns this media file.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Optional product variant associated with this media.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            related: ProductVariant::class,
            foreignKey: 'product_variant_id'
        );
    }

    /**
     * User who uploaded the media file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'uploaded_by'
        );
    }

    /**
     * Limit media to one product.
     */
    public function scopeForProduct(
        Builder $query,
        int $productId
    ): Builder {
        return $query->where('product_id', $productId);
    }

    /**
     * Limit media to one product variant.
     */
    public function scopeForVariant(
        Builder $query,
        int $productVariantId
    ): Builder {
        return $query->where(
            'product_variant_id',
            $productVariantId
        );
    }

    /**
     * Limit results to general product media.
     *
     * General media is not connected to a specific variant.
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('product_variant_id');
    }

    /**
     * Limit results to primary media.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * Order media for display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Determine whether this media is an image.
     */
    public function isImage(): bool
    {
        return $this->media_type === MediaType::IMAGE;
    }

    /**
     * Determine whether the file exists on its storage disk.
     */
    public function fileExists(): bool
    {
        if (blank($this->disk) || blank($this->path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk)
                ->exists($this->path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Return the publicly accessible media URL.
     *
     * Returns null when the configured disk cannot generate
     * a URL or when the path is missing.
     */
    public function url(): ?string
    {
        if (blank($this->disk) || blank($this->path)) {
            return null;
        }

        try {
            return Storage::disk($this->disk)
                ->url($this->path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return the file size in kilobytes.
     */
    public function sizeInKilobytes(): float
    {
        return round($this->size_bytes / 1024, 2);
    }

    /**
     * Return the file size in megabytes.
     */
    public function sizeInMegabytes(): float
    {
        return round($this->size_bytes / 1024 / 1024, 2);
    }
}