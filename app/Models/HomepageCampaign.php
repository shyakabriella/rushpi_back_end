<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomepageCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'desktop_image_path',
        'mobile_image_path',
        'background_color',
        'text_color',
        'button_text',
        'link_type',
        'link_value',
        'card_size',
        'position',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $appends = [
        'desktop_image_url',
        'mobile_image_url',
        'destination_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HomepageCampaign $campaign): void {
            if (blank($campaign->public_id)) {
                $campaign->public_id = (string) Str::ulid();
            }

            static::normalize($campaign);
        });

        static::updating(function (HomepageCampaign $campaign): void {
            static::normalize($campaign);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where('is_active', true);
    }

    public function scopeCurrentlyVisible(
        Builder $query
    ): Builder {
        return $query
            ->active()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('position')
            ->orderBy('id');
    }

    public function getDesktopImageUrlAttribute(): ?string
    {
        return $this->imageUrl(
            $this->desktop_image_path
        );
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        return $this->imageUrl(
            $this->mobile_image_path
        );
    }

    public function getDestinationUrlAttribute(): ?string
    {
        if (
            $this->link_type === 'none'
            || blank($this->link_value)
        ) {
            return null;
        }

        $value = trim((string) $this->link_value);

        return match ($this->link_type) {
            'category' => '/categories/'.$value,
            'brand' => '/brands/'.$value,
            'product' => '/products/'.$value,
            'custom' => $value,
            default => null,
        };
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if (
            $this->starts_at !== null
            && $this->starts_at->isFuture()
        ) {
            return 'scheduled';
        }

        if (
            $this->ends_at !== null
            && $this->ends_at->isPast()
        ) {
            return 'expired';
        }

        return 'active';
    }

    private static function normalize(
        HomepageCampaign $campaign
    ): void {
        $campaign->title = trim(
            (string) $campaign->title
        );

        foreach ([
            'subtitle',
            'button_text',
            'link_value',
        ] as $attribute) {
            if (! is_string($campaign->{$attribute})) {
                continue;
            }

            $value = trim($campaign->{$attribute});

            $campaign->{$attribute} =
                $value === '' ? null : $value;
        }

        $campaign->background_color =
            strtolower(
                trim(
                    (string) $campaign->background_color
                )
            );

        $campaign->text_color =
            strtolower(
                trim(
                    (string) $campaign->text_color
                )
            );
    }

    private function imageUrl(
        mixed $path
    ): ?string {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
