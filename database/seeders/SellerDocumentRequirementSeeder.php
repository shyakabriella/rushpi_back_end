<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SellerDocumentRequirementSeeder extends Seeder
{
    /**
     * Seed RushPi seller verification
     * document requirements.
     */
    public function run(): void
    {
        $now = now();

        $documents = [
            /*
            |--------------------------------------------------------------------------
            | Required documents
            |--------------------------------------------------------------------------
            */

            [
                'key' =>
                    'business_registration_certificate',

                'name' =>
                    'RDB Business Registration Certificate',

                'requirement_level' =>
                    'required',

                'condition' =>
                    null,

                'description' =>
                    'Confirms that the seller or business legally exists and is registered.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    10,
            ],

            [
                'key' =>
                    'authorized_representative_id',

                'name' =>
                    'National ID / Passport',

                'requirement_level' =>
                    'required',

                'condition' =>
                    null,

                'description' =>
                    'Identifies the business owner, director, or authorized representative.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    20,
            ],

            [
                'key' =>
                    'taxpayer_registration',

                'name' =>
                    'TIN / Taxpayer Number',

                'requirement_level' =>
                    'required',

                'condition' =>
                    null,

                'description' =>
                    'Confirms that the seller is registered with the tax authority.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    30,
            ],

            [
                'key' =>
                    'proof_of_address',

                'name' =>
                    'Proof of Business Address',

                'requirement_level' =>
                    'required',

                'condition' =>
                    null,

                'description' =>
                    'Confirms the physical location where the shop or business operates.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    40,
            ],

            [
                'key' =>
                    'payout_account_proof',

                'name' =>
                    'Bank / Mobile Money Account Proof',

                'requirement_level' =>
                    'required',

                'condition' =>
                    null,

                'description' =>
                    'Ensures seller payouts are sent to an account belonging to the verified seller or business.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    50,
            ],

            /*
            |--------------------------------------------------------------------------
            | Conditional documents
            |--------------------------------------------------------------------------
            */

            [
                'key' =>
                    'vat_certificate',

                'name' =>
                    'RRA VAT Certificate',

                'requirement_level' =>
                    'conditional',

                'condition' =>
                    'Required when the seller is VAT registered.',

                'description' =>
                    'Confirms the VAT registration status of the seller or business.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    60,
            ],

            [
                'key' =>
                    'trading_license',

                'name' =>
                    'Trading Licence / Patente',

                'requirement_level' =>
                    'conditional',

                'condition' =>
                    'Required where applicable to the seller business activity.',

                'description' =>
                    'Provides evidence that the business is authorized to conduct its applicable commercial activities.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    70,
            ],

            [
                'key' =>
                    'rura_type_approval',

                'name' =>
                    'RURA Type Approval',

                'requirement_level' =>
                    'conditional',

                'condition' =>
                    'Required for applicable telecom or communication equipment.',

                'description' =>
                    'Helps demonstrate regulatory approval for telecom and communication equipment where required.',

                'allow_multiple' =>
                    true,

                'supports_expiry_date' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    80,
            ],

            [
                'key' =>
                    'import_customs_documents',

                'name' =>
                    'Import / Customs Documents',

                'requirement_level' =>
                    'conditional',

                'condition' =>
                    'Required for sellers importing electronics or related products.',

                'description' =>
                    'Helps demonstrate that imported electronics entered the country through applicable customs procedures.',

                'allow_multiple' =>
                    true,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    90,
            ],

            /*
            |--------------------------------------------------------------------------
            | Recommended documents
            |--------------------------------------------------------------------------
            */

            [
                'key' =>
                    'tax_clearance_certificate',

                'name' =>
                    'Tax Clearance Certificate',

                'requirement_level' =>
                    'recommended',

                'condition' =>
                    null,

                'description' =>
                    'Provides additional evidence that the seller is currently tax compliant.',

                'allow_multiple' =>
                    false,

                'supports_expiry_date' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    100,
            ],

            [
                'key' =>
                    'supplier_purchase_invoice',

                'name' =>
                    'Supplier / Purchase Invoice',

                'requirement_level' =>
                    'recommended',

                'condition' =>
                    null,

                'description' =>
                    'Helps establish legitimate product sourcing and reduce counterfeit or stolen-product risk.',

                'allow_multiple' =>
                    true,

                'supports_expiry_date' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    110,
            ],

            [
                'key' =>
                    'manufacturer_distributor_authorization',

                'name' =>
                    'Manufacturer / Distributor Authorization',

                'requirement_level' =>
                    'recommended',

                'condition' =>
                    'Useful for official distributors, agents, and authorized resellers.',

                'description' =>
                    'Provides evidence that the seller is authorized by a manufacturer or official distributor.',

                'allow_multiple' =>
                    true,

                'supports_expiry_date' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    120,
            ],
        ];

        foreach ($documents as $document) {
            DB::table(
                'seller_document_requirements'
            )->updateOrInsert(
                [
                    'key' =>
                        $document['key'],
                ],
                [
                    ...$document,

                    'updated_at' =>
                        $now,

                    'created_at' =>
                        $now,
                ]
            );
        }
    }
}