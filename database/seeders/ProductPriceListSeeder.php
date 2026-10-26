<?php

namespace Database\Seeders;

use App\Models\Accounting\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductPriceListSeeder extends Seeder
{
    /**
     * Seeds the real distributor product catalog (Price List 2025-26, effective
     * April 1, 2025) so Invoice/Bill/Quotation line items can select real products.
     */
    public function run()
    {
        $userId = User::where('email', 'admin@fin360.app')->value('id');

        $products = [
            // Vascular Screening Products
            ['name' => 'Hand Held Vascular Doppler VD8M', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 24500],
            ['name' => 'Hand Held Vascular Doppler - LIFEDOP L150R', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 83900],
            ['name' => 'ABI Doppler VERSADOP-Dx', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 94500],
            ['name' => 'Semi-Automated Vascular Doppler for ABI/TBI - VASOCHEK+', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 194250],
            ['name' => 'Automated Vascular Doppler for ABI/TBI - VERSALAB Auto', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 297250],
            ['name' => 'Automatic ABI Machine - eZyABI', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 145000],
            ['name' => 'Single Channel tcpO2 Precise 8001', 'category' => 'Vascular Screening Products', 'hsn_code' => '90199099', 'tax_rate' => 5, 'unit_price' => 790000],
            ['name' => 'Consumable for Tcpo2 - 48 rings, 1 bottle', 'category' => 'Vascular Screening Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 19500],

            // Neuropathy Products
            ['name' => 'Monofilament - 10gm (1 box - 25 nos)', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 750, 'unit' => 'BOX'],
            ['name' => 'VIBRATIP / Tip Therm', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 2950],
            ['name' => 'Neuropathy Assessment Kit NA3', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 6500],
            ['name' => 'Handy Biothesiometer - VIBROSCREEN', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 29000],
            ['name' => 'Digital Biothesiometer - VIBROTEST Lx with software', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 35000],
            ['name' => 'Digital Biothesiometer - VIBROMETER', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 38000],
            ['name' => 'Biothesiometer with Vascular Doppler - VIBRODOP', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 145000],
            ['name' => 'Biothesiometer with Vascular Doppler - VIBROABI', 'category' => 'Neuropathy Products', 'hsn_code' => null, 'tax_rate' => 5, 'unit_price' => 165000],
            ['name' => 'Diabetik MINILAB without scanner/printer and PC', 'category' => 'Neuropathy Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 390000],
            ['name' => 'DIABETIK eZyLAB', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 390000],
            ['name' => 'Diabetik Autolab without scanner/printer and PC', 'category' => 'Neuropathy Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 490000],
            ['name' => 'Nuropathy Analyser - Thermomtry HCP', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 85000],
            ['name' => 'Neuropathy Analyser - VIBROTHERM Dx', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 145000],
            ['name' => 'Cardiac Autonomic System Analyser - CANS 504', 'category' => 'Neuropathy Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 150000],

            // Footcare Products
            ['name' => 'Foot Imprinter', 'category' => 'Footcare Products', 'hsn_code' => '90189099', 'tax_rate' => 5, 'unit_price' => 18500],
            ['name' => 'Plantar Pressure System Podiascan without scanner/printer', 'category' => 'Footcare Products', 'hsn_code' => '90189099', 'tax_rate' => 5, 'unit_price' => 65000],
            ['name' => 'Podiastat Foot Scan', 'category' => 'Footcare Products', 'hsn_code' => '90191290', 'tax_rate' => 5, 'unit_price' => 580000],
            ['name' => 'Non-Contact IR Thermometer - FT4', 'category' => 'Footcare Products', 'hsn_code' => '90181990', 'tax_rate' => 5, 'unit_price' => 9750],
            ['name' => 'Electric Pedicure File Heavy Duty - Pedifile-V', 'category' => 'Footcare Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 21500],
            ['name' => 'Heavy Duty Electric File System - PEDIMAX', 'category' => 'Footcare Products', 'hsn_code' => '90181290', 'tax_rate' => 5, 'unit_price' => 26250],

            // Wound Care Therapy Products
            ['name' => 'Electronic Pain Killer - TENS I', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 3000],
            ['name' => 'Electronic Pain Killer - TENS III', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 12500],
            ['name' => 'Neuromuscular Stimulator (Hospital Model) - NEUROSTIM', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 39500],
            ['name' => 'Neuromuscular Stimulator (Patient Model) - NEUROSTIM Home', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 24500],
            ['name' => 'Neurolite Professional with all 4 pads', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 65000],
            ['name' => 'LED IR Light Therapy NEUROLITE - Home', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 24500],
            ['name' => 'Wound Camera - Illuminate Bio', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90181900', 'tax_rate' => 5, 'unit_price' => 650000],
            ['name' => 'Laser Therapy Device - PodoLazer Rx', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189098', 'tax_rate' => 5, 'unit_price' => 240000],
            ['name' => 'Negative Pressure Wound Therapy NPWT - MediVac Pro', 'category' => 'Wound Care Therapy Products', 'hsn_code' => '90189099', 'tax_rate' => 5, 'unit_price' => 185000],

            // Podiatry Products
            ['name' => 'Podiatry Kit Mini (6pcs)', 'category' => 'Podiatry Products', 'hsn_code' => '90189099', 'tax_rate' => 12, 'unit_price' => 7500, 'unit' => 'KIT'],
            ['name' => 'Podiatry Kit Full (10pcs)', 'category' => 'Podiatry Products', 'hsn_code' => '90189099', 'tax_rate' => 12, 'unit_price' => 13750, 'unit' => 'KIT'],
            ['name' => 'Podiatry Chair 3 Motor with Instrument Trolley and Dr Stool', 'category' => 'Podiatry Products', 'hsn_code' => '90181100', 'tax_rate' => 12, 'unit_price' => 205700],
            ['name' => 'Podiatry Chair 5 Motor with Instrument Trolley and Dr Stool', 'category' => 'Podiatry Products', 'hsn_code' => '90181100', 'tax_rate' => 5, 'unit_price' => 236250],
            ['name' => 'Orchidometer', 'category' => 'Podiatry Products', 'hsn_code' => '90189099', 'tax_rate' => 5, 'unit_price' => 9500],
            ['name' => 'Durometer', 'category' => 'Podiatry Products', 'hsn_code' => '90189099', 'tax_rate' => 5, 'unit_price' => 15500],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['name' => $p['name']],
                [
                    'description' => null,
                    'hsn_code'    => $p['hsn_code'] ?? null,
                    'category'    => $p['category'],
                    'unit'        => $p['unit'] ?? 'NOS',
                    'unit_price'  => $p['unit_price'],
                    'tax_rate'    => $p['tax_rate'],
                    'active'      => true,
                    'user_id'     => $userId,
                ]
            );
        }
    }
}
