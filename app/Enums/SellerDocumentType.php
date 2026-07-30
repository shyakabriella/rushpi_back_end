<?php

declare(strict_types=1);

namespace App\Enums;

enum SellerDocumentType: string
{
    case BUSINESS_REGISTRATION_CERTIFICATE =
        'business_registration_certificate';

    case TAX_CERTIFICATE = 'tax_certificate';

    case AUTHORIZED_REPRESENTATIVE_ID =
        'authorized_representative_id';

    case TRADING_LICENSE = 'trading_license';

    case PAYOUT_ACCOUNT_PROOF = 'payout_account_proof';

    case PROOF_OF_ADDRESS = 'proof_of_address';

    case STORE_PHOTO = 'store_photo';

    case OTHER = 'other';
}