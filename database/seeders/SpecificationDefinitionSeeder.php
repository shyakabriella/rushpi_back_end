<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SpecificationDataType;
use App\Models\SpecificationDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SpecificationDefinitionSeeder extends Seeder
{
    /**
     * Seed reusable product specification definitions.
     */
    public function run(): void
    {
        DB::transaction(
            function (): void {
                foreach (
                    $this->definitions()
                    as $definition
                ) {
                    SpecificationDefinition::query()
                        ->updateOrCreate(
                            [
                                'code' =>
                                    $definition['code'],
                            ],
                            $definition
                        );
                }
            }
        );
    }

    /**
     * Reusable product specification definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Product identity
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Model Number',

                'code' =>
                    'model_number',

                'description' =>
                    'Manufacturer model number or product reference.',

                'data_type' =>
                    SpecificationDataType::TEXT->value,

                'unit' =>
                    null,

                'options' =>
                    null,

                'validation_rules' => [
                    'min_length' => 1,
                    'max_length' => 100,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    false,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    10,
            ],

            [
                'name' =>
                    'Manufacturing Year',

                'code' =>
                    'manufacturing_year',

                'description' =>
                    'Year in which the product was manufactured.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'year',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1900,
                    'max' => 2100,
                    'step' => 1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    20,
            ],

            /*
            |--------------------------------------------------------------------------
            | Processor
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Processor Brand',

                'code' =>
                    'processor_brand',

                'description' =>
                    'Brand or manufacturer of the product processor.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'intel',
                        'label' => 'Intel',
                    ],
                    [
                        'value' => 'amd',
                        'label' => 'AMD',
                    ],
                    [
                        'value' => 'apple',
                        'label' => 'Apple',
                    ],
                    [
                        'value' => 'qualcomm',
                        'label' => 'Qualcomm',
                    ],
                    [
                        'value' => 'mediatek',
                        'label' => 'MediaTek',
                    ],
                    [
                        'value' => 'samsung',
                        'label' => 'Samsung',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    30,
            ],

            [
                'name' =>
                    'Processor',

                'code' =>
                    'processor',

                'description' =>
                    'Complete processor name, generation and model.',

                'data_type' =>
                    SpecificationDataType::TEXT->value,

                'unit' =>
                    null,

                'options' =>
                    null,

                'validation_rules' => [
                    'min_length' => 2,
                    'max_length' => 150,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    40,
            ],

            [
                'name' =>
                    'Processor Cores',

                'code' =>
                    'processor_cores',

                'description' =>
                    'Number of physical or advertised processor cores.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'cores',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1,
                    'max' => 256,
                    'step' => 1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    50,
            ],

            /*
            |--------------------------------------------------------------------------
            | Memory and storage
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'RAM',

                'code' =>
                    'ram',

                'description' =>
                    'Installed system memory capacity.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'GB',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1,
                    'max' => 1024,
                    'step' => 1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    60,
            ],

            [
                'name' =>
                    'RAM Type',

                'code' =>
                    'ram_type',

                'description' =>
                    'Memory technology used by the product.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'ddr3',
                        'label' => 'DDR3',
                    ],
                    [
                        'value' => 'ddr4',
                        'label' => 'DDR4',
                    ],
                    [
                        'value' => 'ddr5',
                        'label' => 'DDR5',
                    ],
                    [
                        'value' => 'lpddr4',
                        'label' => 'LPDDR4',
                    ],
                    [
                        'value' => 'lpddr4x',
                        'label' => 'LPDDR4X',
                    ],
                    [
                        'value' => 'lpddr5',
                        'label' => 'LPDDR5',
                    ],
                    [
                        'value' => 'lpddr5x',
                        'label' => 'LPDDR5X',
                    ],
                    [
                        'value' => 'unified_memory',
                        'label' => 'Unified Memory',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    70,
            ],

            [
                'name' =>
                    'Storage Capacity',

                'code' =>
                    'storage_capacity',

                'description' =>
                    'Total internal storage capacity.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'GB',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1,
                    'max' => 100000,
                    'step' => 1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    80,
            ],

            [
                'name' =>
                    'Storage Type',

                'code' =>
                    'storage_type',

                'description' =>
                    'Technology used for internal product storage.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'hdd',
                        'label' => 'HDD',
                    ],
                    [
                        'value' => 'ssd',
                        'label' => 'SSD',
                    ],
                    [
                        'value' => 'nvme_ssd',
                        'label' => 'NVMe SSD',
                    ],
                    [
                        'value' => 'emmc',
                        'label' => 'eMMC',
                    ],
                    [
                        'value' => 'ufs',
                        'label' => 'UFS',
                    ],
                    [
                        'value' => 'flash',
                        'label' => 'Flash Storage',
                    ],
                    [
                        'value' => 'hybrid',
                        'label' => 'Hybrid Storage',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    90,
            ],

            /*
            |--------------------------------------------------------------------------
            | Display
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Screen Size',

                'code' =>
                    'screen_size',

                'description' =>
                    'Diagonal screen measurement.',

                'data_type' =>
                    SpecificationDataType::DECIMAL->value,

                'unit' =>
                    'inch',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1,
                    'max' => 150,
                    'step' => 0.1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    100,
            ],

            [
                'name' =>
                    'Screen Resolution',

                'code' =>
                    'screen_resolution',

                'description' =>
                    'Display resolution such as 1920 × 1080.',

                'data_type' =>
                    SpecificationDataType::TEXT->value,

                'unit' =>
                    'pixels',

                'options' =>
                    null,

                'validation_rules' => [
                    'min_length' => 3,
                    'max_length' => 50,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    110,
            ],

            [
                'name' =>
                    'Display Type',

                'code' =>
                    'display_type',

                'description' =>
                    'Technology used by the product display.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'lcd',
                        'label' => 'LCD',
                    ],
                    [
                        'value' => 'led',
                        'label' => 'LED',
                    ],
                    [
                        'value' => 'ips',
                        'label' => 'IPS',
                    ],
                    [
                        'value' => 'oled',
                        'label' => 'OLED',
                    ],
                    [
                        'value' => 'amoled',
                        'label' => 'AMOLED',
                    ],
                    [
                        'value' => 'mini_led',
                        'label' => 'Mini LED',
                    ],
                    [
                        'value' => 'qled',
                        'label' => 'QLED',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    120,
            ],

            [
                'name' =>
                    'Touchscreen',

                'code' =>
                    'touchscreen',

                'description' =>
                    'Indicates whether the product has a touch-enabled display.',

                'data_type' =>
                    SpecificationDataType::BOOLEAN->value,

                'unit' =>
                    null,

                'options' =>
                    null,

                'validation_rules' =>
                    null,

                'default_value' =>
                    false,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    130,
            ],

            /*
            |--------------------------------------------------------------------------
            | Graphics
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Graphics Card',

                'code' =>
                    'graphics_card',

                'description' =>
                    'Graphics processor or graphics card model.',

                'data_type' =>
                    SpecificationDataType::TEXT->value,

                'unit' =>
                    null,

                'options' =>
                    null,

                'validation_rules' => [
                    'min_length' => 2,
                    'max_length' => 150,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    140,
            ],

            [
                'name' =>
                    'Dedicated Graphics',

                'code' =>
                    'dedicated_graphics',

                'description' =>
                    'Indicates whether the product has dedicated graphics.',

                'data_type' =>
                    SpecificationDataType::BOOLEAN->value,

                'unit' =>
                    null,

                'options' =>
                    null,

                'validation_rules' =>
                    null,

                'default_value' =>
                    false,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    150,
            ],

            /*
            |--------------------------------------------------------------------------
            | Software and connectivity
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Operating System',

                'code' =>
                    'operating_system',

                'description' =>
                    'Operating system installed or supported by the product.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'windows',
                        'label' => 'Windows',
                    ],
                    [
                        'value' => 'macos',
                        'label' => 'macOS',
                    ],
                    [
                        'value' => 'linux',
                        'label' => 'Linux',
                    ],
                    [
                        'value' => 'android',
                        'label' => 'Android',
                    ],
                    [
                        'value' => 'ios',
                        'label' => 'iOS',
                    ],
                    [
                        'value' => 'chromeos',
                        'label' => 'ChromeOS',
                    ],
                    [
                        'value' => 'free_dos',
                        'label' => 'FreeDOS',
                    ],
                    [
                        'value' => 'none',
                        'label' => 'No Operating System',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    160,
            ],

            [
                'name' =>
                    'Connectivity',

                'code' =>
                    'connectivity',

                'description' =>
                    'Wireless and wired connectivity supported by the product.',

                'data_type' =>
                    SpecificationDataType::MULTISELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'wifi',
                        'label' => 'Wi-Fi',
                    ],
                    [
                        'value' => 'bluetooth',
                        'label' => 'Bluetooth',
                    ],
                    [
                        'value' => 'ethernet',
                        'label' => 'Ethernet',
                    ],
                    [
                        'value' => 'usb',
                        'label' => 'USB',
                    ],
                    [
                        'value' => 'usb_c',
                        'label' => 'USB-C',
                    ],
                    [
                        'value' => 'hdmi',
                        'label' => 'HDMI',
                    ],
                    [
                        'value' => 'nfc',
                        'label' => 'NFC',
                    ],
                    [
                        'value' => 'gps',
                        'label' => 'GPS',
                    ],
                    [
                        'value' => '4g',
                        'label' => '4G',
                    ],
                    [
                        'value' => '5g',
                        'label' => '5G',
                    ],
                ],

                'validation_rules' => [
                    'min_items' => 1,
                    'max_items' => 10,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    170,
            ],

            /*
            |--------------------------------------------------------------------------
            | Physical information
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Color',

                'code' =>
                    'color',

                'description' =>
                    'Primary exterior color of the product.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'black',
                        'label' => 'Black',
                    ],
                    [
                        'value' => 'white',
                        'label' => 'White',
                    ],
                    [
                        'value' => 'silver',
                        'label' => 'Silver',
                    ],
                    [
                        'value' => 'gray',
                        'label' => 'Gray',
                    ],
                    [
                        'value' => 'blue',
                        'label' => 'Blue',
                    ],
                    [
                        'value' => 'red',
                        'label' => 'Red',
                    ],
                    [
                        'value' => 'green',
                        'label' => 'Green',
                    ],
                    [
                        'value' => 'gold',
                        'label' => 'Gold',
                    ],
                    [
                        'value' => 'pink',
                        'label' => 'Pink',
                    ],
                    [
                        'value' => 'purple',
                        'label' => 'Purple',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    true,

                'is_active' =>
                    true,

                'sort_order' =>
                    180,
            ],

            [
                'name' =>
                    'Weight',

                'code' =>
                    'weight',

                'description' =>
                    'Approximate product weight.',

                'data_type' =>
                    SpecificationDataType::DECIMAL->value,

                'unit' =>
                    'kg',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 0,
                    'max' => 100000,
                    'step' => 0.01,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    false,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    190,
            ],

            [
                'name' =>
                    'Dimensions',

                'code' =>
                    'dimensions',

                'description' =>
                    'Product dimensions in length × width × height format.',

                'data_type' =>
                    SpecificationDataType::TEXT->value,

                'unit' =>
                    'cm',

                'options' =>
                    null,

                'validation_rules' => [
                    'min_length' => 3,
                    'max_length' => 100,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    false,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    200,
            ],

            /*
            |--------------------------------------------------------------------------
            | Power
            |--------------------------------------------------------------------------
            */

            [
                'name' =>
                    'Battery Capacity',

                'code' =>
                    'battery_capacity',

                'description' =>
                    'Battery storage capacity.',

                'data_type' =>
                    SpecificationDataType::INTEGER->value,

                'unit' =>
                    'mAh',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 1,
                    'max' => 1000000,
                    'step' => 1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    210,
            ],

            [
                'name' =>
                    'Battery Life',

                'code' =>
                    'battery_life',

                'description' =>
                    'Estimated operating time from a full battery charge.',

                'data_type' =>
                    SpecificationDataType::DECIMAL->value,

                'unit' =>
                    'hours',

                'options' =>
                    null,

                'validation_rules' => [
                    'min' => 0,
                    'max' => 1000,
                    'step' => 0.1,
                ],

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    220,
            ],

            [
                'name' =>
                    'Power Source',

                'code' =>
                    'power_source',

                'description' =>
                    'Primary source of power used by the product.',

                'data_type' =>
                    SpecificationDataType::SELECT->value,

                'unit' =>
                    null,

                'options' => [
                    [
                        'value' => 'battery',
                        'label' => 'Battery',
                    ],
                    [
                        'value' => 'electric',
                        'label' => 'Electric',
                    ],
                    [
                        'value' => 'battery_and_electric',
                        'label' => 'Battery and Electric',
                    ],
                    [
                        'value' => 'solar',
                        'label' => 'Solar',
                    ],
                    [
                        'value' => 'manual',
                        'label' => 'Manual',
                    ],
                    [
                        'value' => 'other',
                        'label' => 'Other',
                    ],
                ],

                'validation_rules' =>
                    null,

                'default_value' =>
                    null,

                'is_filterable' =>
                    true,

                'is_variant_attribute' =>
                    false,

                'is_active' =>
                    true,

                'sort_order' =>
                    230,
            ],
        ];
    }
}
