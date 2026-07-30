<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationDecision: string
{
    case REVIEW_STARTED = 'review_started';
    case INFORMATION_REQUESTED = 'information_requested';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';
}