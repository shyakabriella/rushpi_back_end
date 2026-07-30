<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerMemberRole: string
{
    case OWNER = 'owner';
    case STAFF = 'staff';
}