<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressType: string
{
    case BUSINESS = 'business';
    case BILLING = 'billing';
    case SHIPPING = 'shipping';
    case HOME = 'home';
}