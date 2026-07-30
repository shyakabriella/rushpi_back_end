<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerDocumentStatus: string
{
    case QUARANTINED = 'quarantined';
    case PENDING_SCAN = 'pending_scan';
    case CLEAN = 'clean';
    case SCAN_FAILED = 'scan_failed';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case DELETED = 'deleted';
}