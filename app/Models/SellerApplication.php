<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SellerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_profile_id',
        'version',
        'status',
        'seller_message',
        'information_request',
        'rejection_reason',
        'submitted_at',
        'review_started_at',
        'decided_at',
        'current_reviewer_id',
        'decided_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellerApplicationStatus::class,
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerApplication $application): void {
            $application->public_id ??= (string) Str::uuid();
        });
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VerificationReview::class);
    }

    public function currentReviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'current_reviewer_id'
        );
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [
            SellerApplicationStatus::DRAFT,
            SellerApplicationStatus::MORE_INFORMATION_REQUIRED,
        ], true);
    }
}