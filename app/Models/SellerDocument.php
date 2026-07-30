<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerDocumentStatus;
use App\Enums\SellerDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SellerDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'seller_profile_id',
        'seller_application_id',
        'uploaded_by',
        'document_type',
        'status',
        'original_name',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'issued_at',
        'expires_at',
        'scanned_at',
        'scan_result',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $hidden = [
        'storage_path',
        'checksum_sha256',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => SellerDocumentType::class,
            'status' => SellerDocumentStatus::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'scanned_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerDocument $document): void {
            $document->public_id ??= (string) Str::uuid();
        });
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            SellerApplication::class,
            'seller_application_id'
        );
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}