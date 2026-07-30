<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerMemberStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case REMOVED = 'removed';
}