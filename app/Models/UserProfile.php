<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * Profile fields that may be assigned.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',

        'first_name',
        'last_name',
        'display_name',

        'avatar',
        'bio',

        'gender',
        'date_of_birth',

        'alternative_phone',
        'whatsapp',

        'country',
        'province',
        'district',
        'sector',
        'cell',
        'village',
        'address',

        'latitude',
        'longitude',

        'completed_at',
    ];

    /**
     * Appended values.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Profile attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' =>
                'date',

            'latitude' =>
                'decimal:7',

            'longitude' =>
                'decimal:7',

            'completed_at' =>
                'datetime',

            'created_at' =>
                'datetime',

            'updated_at' =>
                'datetime',
        ];
    }

    /**
     * User who owns this profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /**
     * Public avatar URL.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (
            ! is_string($this->avatar)
            ||
            trim($this->avatar) === ''
        ) {
            return null;
        }

        if (
            str_starts_with(
                $this->avatar,
                'https://'
            )
            ||
            str_starts_with(
                $this->avatar,
                'http://'
            )
        ) {
            return $this->avatar;
        }

        $path = preg_replace(
            '#^/?storage/#',
            '',
            $this->avatar
        );

        if (! is_string($path)) {
            return null;
        }

        return Storage::disk(
            'public'
        )->url(
            $path
        );
    }

    /**
     * Return user's preferred display name.
     */
    public function fullName(): string
    {
        if (
            is_string($this->display_name)
            &&
            trim($this->display_name) !== ''
        ) {
            return trim(
                $this->display_name
            );
        }

        $fullName = trim(
            sprintf(
                '%s %s',
                $this->first_name ?? '',
                $this->last_name ?? ''
            )
        );

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->user?->name
            ?? '';
    }

    /**
     * Calculate personal profile completion.
     */
    public function completionPercentage(): int
    {
        $fields = [
            $this->first_name,
            $this->last_name,
            $this->avatar,
            $this->bio,

            $this->country,
            $this->province,
            $this->district,
            $this->sector,
            $this->address,
        ];

        $completed = collect(
            $fields
        )
            ->filter(
                static function (
                    mixed $value
                ): bool {
                    return $value !== null
                        &&
                        trim(
                            (string) $value
                        ) !== '';
                }
            )
            ->count();

        return (int) round(
            (
                $completed /
                count($fields)
            ) * 100
        );
    }
}