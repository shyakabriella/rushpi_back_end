<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerProfileStatus: string
{
    case DRAFT = 'draft';

    case PENDING_VERIFICATION = 'pending_verification';

    case APPROVED = 'approved';

    case REJECTED = 'rejected';

    case SUSPENDED = 'suspended';

    case BLOCKED = 'blocked';
}
