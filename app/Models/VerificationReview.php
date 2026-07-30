<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_application_id',
        'reviewer_id',
        'decision',
        'reason',
        'internal_notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'decision' => VerificationDecision::class,
            'metadata' => 'array',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            SellerApplication::class,
            'seller_application_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}