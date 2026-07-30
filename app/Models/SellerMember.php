<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerMemberRole;
use App\Enums\SellerMemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_profile_id',
        'user_id',
        'role',
        'status',
        'invited_by',
        'invited_at',
        'joined_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => SellerMemberRole::class,
            'status' => SellerMemberStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isOwner(): bool
    {
        return $this->role === SellerMemberRole::OWNER;
    }
}