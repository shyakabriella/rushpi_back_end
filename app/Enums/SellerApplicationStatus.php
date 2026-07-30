<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerApplicationStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case MORE_INFORMATION_REQUIRED = 'more_information_required';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';
}