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
            'status' =>
                SellerApplicationStatus::class,

            'submitted_at' =>
                'datetime',

            'review_started_at' =>
                'datetime',

            'decided_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                SellerApplication $application
            ): void {
                $application->public_id ??=
                    (string) Str::uuid();
            }
        );
    }

    /**
     * Seller profile that owns this verification application.
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(
            SellerProfile::class
        );
    }

    /**
     * Verification documents uploaded for this application.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(
            SellerDocument::class
        );
    }

    /**
     * Alias required by Laravel scoped route model binding
     * for the {sellerDocument} route parameter.
     *
     * Example:
     * /seller-applications/{sellerApplication}/documents/{sellerDocument}
     */
    public function sellerDocuments(): HasMany
    {
        return $this->documents();
    }

    /**
     * Administration verification/review history.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(
            VerificationReview::class
        );
    }

    /**
     * Administrator currently reviewing the application.
     */
    public function currentReviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'current_reviewer_id'
        );
    }

    /**
     * Administrator who made the final decision.
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'decided_by'
        );
    }

    /**
     * Determine whether the seller may still edit the application.
     */
    public function canBeEdited(): bool
    {
        return in_array(
            $this->status,
            [
                SellerApplicationStatus::DRAFT,
                SellerApplicationStatus::MORE_INFORMATION_REQUIRED,
            ],
            true
        );
    }
}