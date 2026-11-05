<?php

namespace Database\Seeders\Demo;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Stock\StockService;
use Illuminate\Database\Seeder;

/**
 * About 60 realistic products and services from the shop's brands (local demo only).
 * Prices in rupees: our price (selling, GST-inclusive) and the normal price (MRP).
 * GST: 18% for car accessories and services (rates after the Sept 2025 GST change).
 */
class ProductsDemoSeeder extends Seeder
{
    // name, brand, category, type, price, mrp, cost, hsn, stock, reorder, sold, shelf, warranty, fits, aliases, sku, unit
    private const ITEMS = [
        // DHC LED
        ['DHC Gleam 2.0 LED headlight H4', 'DHC', 'LED & lights', 'p', 4999, 6499, 3150, '85395200', 2, 3, 142, 'A3', '1 year', ['Maruti Swift', 'Mahindra Bolero', 'Maruti Ertiga'], 'gleam, gleam 2, dhc led, h4 led, headlight bulb, led bulb, light', 'DHC-GLM2-H4', 'pair'],
        ['DHC Gleam 2.0 LED headlight H7', 'DHC', 'LED & lights', 'p', 4999, 6499, 3150, '85395200', 8, 3, 61, 'A3', '1 year', ['Hyundai Creta', 'Kia Seltos', 'Hyundai Verna'], 'gleam, gleam 2, dhc led, h7 led, headlight bulb, led bulb, light', 'DHC-GLM2-H7', 'pair'],
        ['DHC Gleam 3.0 LED headlight H4', 'DHC', 'LED & lights', 'p', 6499, 7999, 4300, '85395200', 5, 2, 38, 'A3', '2 years', ['Maruti Swift', 'Mahindra Thar'], 'gleam 3, dhc led, h4 led, headlight bulb, led bulb', 'DHC-GLM3-H4', 'pair'],
        ['DHC Gleam 2.0 LED fog lamp H11', 'DHC', 'LED & lights', 'p', 2499, 3199, 1550, '85395200', 14, 4, 77, 'A4', '1 year', ['Tata Nexon', 'Hyundai Creta', 'Maruti Brezza'], 'gleam fog, fog lamp, fog light, h11 led, dhc fog', 'DHC-GLM2-H11', 'pair'],
        ['DHC 9005 HB3 LED high beam', 'DHC', 'LED & lights', 'p', 4999, 6499, 3150, '85395200', 4, 2, 22, 'A3', '1 year', ['Honda City', 'Toyota Innova Crysta'], 'hb3, 9005, high beam, dhc led, headlight bulb', 'DHC-9005', 'pair'],
        ['DHC LED projector fog lamp (universal)', 'DHC', 'LED & lights', 'p', 3999, 4999, 2600, '85122010', 6, 2, 19, 'A4', '1 year', [], 'projector, fog lamp, fog light, dhc fog', 'DHC-PRJ-FOG', 'pair'],
        ['DHC LED interior kit (roof + boot)', 'DHC', 'LED & lights', 'p', 899, 1199, 520, '85395200', 0, 3, 54, 'A5', '6 months', [], 'interior light, roof light, cabin light, boot light, andar ki light', 'DHC-INT-KIT', 'kit'],
        // Night Scene
        ['Night Scene ambient light kit (64 colour)', 'Night Scene', 'LED & lights', 'p', 2499, 2999, 1500, '94054900', 7, 2, 41, 'A6', '6 months', [], 'ambient, mood light, interior light, rgb light, dashboard light', 'NS-AMB-64', 'kit'],
        ['Night Scene LED fog lamp 3-colour', 'Night Scene', 'LED & lights', 'p', 3499, 4199, 2200, '85122010', 3, 2, 27, 'A4', '1 year', ['Mahindra Scorpio-N', 'Mahindra XUV700'], 'fog lamp, fog light, 3 colour fog, yellow fog', 'NS-FOG-3C', 'pair'],
        ['Night Scene LED DRL strip', 'Night Scene', 'LED & lights', 'p', 1999, 2499, 1150, '85122010', 9, 2, 33, 'A6', '6 months', [], 'drl, day light, daytime running light, led strip, patti', 'NS-DRL', 'pair'],
        // PPF and films (installed jobs, so services)
        ['Garware PPF full car (hatchback)', 'Garware', 'PPF & films', 's', 55000, 65000, null, '998714', null, 0, 9, null, '5 years', [], 'ppf, paint protection film, full body ppf, garware ppf, coating film', 'GW-PPF-HB', 'job'],
        ['Garware PPF full car (sedan / SUV)', 'Garware', 'PPF & films', 's', 65000, 75000, null, '998714', null, 0, 14, null, '5 years', [], 'ppf, paint protection film, full body ppf, garware ppf, suv ppf', 'GW-PPF-SUV', 'job'],
        ['Garware PPF bonnet', 'Garware', 'PPF & films', 's', 9500, 11000, null, '998714', null, 0, 21, null, '5 years', [], 'ppf bonnet, hood ppf, panel ppf, garware ppf', 'GW-PPF-BONNET', 'panel'],
        ['3M PPF bonnet + front bumper', '3M', 'PPF & films', 's', 18000, 21000, null, '998714', null, 0, 17, null, '5 years', [], 'ppf front, bonnet bumper ppf, 3m ppf, front kit', '3M-PPF-FRONT', 'job'],
        ['3M PPF full car (SUV)', '3M', 'PPF & films', 's', 95000, 110000, null, '998714', null, 0, 4, null, '7 years', [], 'ppf, 3m ppf, full body ppf, suv ppf, paint protection', '3M-PPF-SUV', 'job'],
        ['Lumar PPF full car (SUV)', 'Lumar', 'PPF & films', 's', 85000, 95000, null, '998714', null, 0, 6, null, '7 years', [], 'ppf, llumar, lumar ppf, full body ppf, paint protection', 'LM-PPF-SUV', 'job'],
        ['Lumar PPF door edge + cup guards', 'Lumar', 'PPF & films', 's', 1499, 1999, null, '998714', null, 0, 48, null, '3 years', [], 'door edge, handle cup, door guard, llumar', 'LM-PPF-EDGE', 'job'],
        ['Aultragard sun control film (full car)', 'Aultragard', 'PPF & films', 's', 4500, 5500, null, '998714', null, 0, 36, null, '3 years', [], 'sun film, black film, tint, window film, kaali film, aultra', 'AG-SUN-FULL', 'job'],
        ['Garware sun control film (full car)', 'Garware', 'PPF & films', 's', 3999, 4999, null, '998714', null, 0, 52, null, '3 years', [], 'sun film, tint, window film, kaali film, garware film', 'GW-SUN-FULL', 'job'],
        // Autoform seat covers
        ['Autoform seat covers Hyundai Creta', 'Autoform', 'Seat covers', 'p', 8500, 9999, 5800, '87089900', 3, 1, 64, 'C2', '1 year', ['Hyundai Creta'], 'seat cover, seat kavar, autoform, cover, seat, creta cover', 'AF-SC-CRETA', 'set'],
        ['Autoform seat covers Tata Nexon', 'Autoform', 'Seat covers', 'p', 7999, 9499, 5400, '87089900', 2, 1, 47, 'C2', '1 year', ['Tata Nexon'], 'seat cover, seat kavar, autoform, cover, seat', 'AF-SC-NEXON', 'set'],
        ['Autoform seat covers Maruti Swift', 'Autoform', 'Seat covers', 'p', 6499, 7499, 4300, '87089900', 4, 1, 58, 'C2', '1 year', ['Maruti Swift'], 'seat cover, seat kavar, autoform, cover, seat', 'AF-SC-SWIFT', 'set'],
        ['Autoform seat covers Mahindra Scorpio-N (7 seat)', 'Autoform', 'Seat covers', 'p', 10500, 12500, 7200, '87089900', 1, 1, 29, 'C3', '1 year', ['Mahindra Scorpio-N'], 'seat cover, seat kavar, autoform, scorpio cover', 'AF-SC-SCORPION', 'set'],
        ['Autoform seat covers Toyota Innova Hycross', 'Autoform', 'Seat covers', 'p', 11500, 13500, 7900, '87089900', 0, 1, 18, 'C3', '1 year', ['Toyota Innova Hycross'], 'seat cover, seat kavar, autoform, innova cover', 'AF-SC-HYCROSS', 'set'],
        ['Autoform seat covers Maruti Brezza', 'Autoform', 'Seat covers', 'p', 7499, 8999, 5000, '87089900', 2, 1, 33, 'C2', '1 year', ['Maruti Brezza'], 'seat cover, seat kavar, autoform, cover', 'AF-SC-BREZZA', 'set'],
        ['Autoform seat covers Kia Seltos', 'Autoform', 'Seat covers', 'p', 8500, 9999, 5800, '87089900', 2, 1, 26, 'C2', '1 year', ['Kia Seltos'], 'seat cover, seat kavar, autoform, cover', 'AF-SC-SELTOS', 'set'],
        ['Autoform seat covers Mahindra XUV700', 'Autoform', 'Seat covers', 'p', 11999, 13999, 8200, '87089900', 1, 1, 15, 'C3', '1 year', ['Mahindra XUV700'], 'seat cover, seat kavar, autoform, xuv cover', 'AF-SC-XUV700', 'set'],
        // 7D mats
        ['7D floor mats Hyundai Creta', '7D', 'Mats', 'p', 3200, 3800, 2050, '87089900', 6, 2, 88, 'D1', '6 months', ['Hyundai Creta'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-CRETA', 'set'],
        ['7D floor mats Tata Nexon', '7D', 'Mats', 'p', 2999, 3600, 1900, '87089900', 5, 2, 71, 'D1', '6 months', ['Tata Nexon'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-NEXON', 'set'],
        ['7D floor mats Maruti Swift', '7D', 'Mats', 'p', 2499, 2999, 1550, '87089900', 1, 2, 93, 'D1', '6 months', ['Maruti Swift'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-SWIFT', 'set'],
        ['7D floor mats Mahindra Scorpio-N', '7D', 'Mats', 'p', 3999, 4699, 2600, '87089900', 3, 2, 40, 'D2', '6 months', ['Mahindra Scorpio-N'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-SCORPION', 'set'],
        ['7D floor mats Toyota Innova Hycross', '7D', 'Mats', 'p', 4299, 4999, 2800, '87089900', 0, 1, 22, 'D2', '6 months', ['Toyota Innova Hycross'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-HYCROSS', 'set'],
        ['7D floor mats Maruti Baleno', '7D', 'Mats', 'p', 2499, 2999, 1550, '87089900', 4, 2, 57, 'D1', '6 months', ['Maruti Baleno'], 'mats, 7d mat, floor mat, matting, paydan, mat', '7D-BALENO', 'set'],
        // Audio and cameras
        ['Android stereo 9" 2+32 GB with frame', null, 'Audio & cameras', 'p', 11500, 13999, 7600, '85272100', 4, 2, 66, 'B1', '1 year', [], 'stereo, music system, android player, android, screen, deck, tape, carplay', 'AND-9-232', 'pcs'],
        ['Android stereo 10" 4+64 GB with frame', null, 'Audio & cameras', 'p', 15999, 18999, 10800, '85272100', 2, 2, 31, 'B1', '1 year', [], 'stereo, music system, android player, android, big screen, deck, tape, carplay', 'AND-10-464', 'pcs'],
        ['Android stereo 9" 1+16 GB with frame', null, 'Audio & cameras', 'p', 7999, 9999, 5200, '85272100', 6, 2, 44, 'B1', '6 months', [], 'stereo, music system, android player, android, screen, budget stereo', 'AND-9-116', 'pcs'],
        ['Reverse parking camera (HD, night vision)', null, 'Audio & cameras', 'p', 1499, 1999, 850, '85258900', 11, 3, 102, 'B2', '6 months', [], 'camera, reverse camera, back camera, parking camera, rear camera', 'CAM-REV-HD', 'pcs'],
        // TVS accessories
        ['TVS digital tyre inflator', 'TVS', 'Accessories', 'p', 2799, 3299, 1900, '84148090', 5, 2, 39, 'E1', '1 year', [], 'pump, air pump, inflator, tyre pump, hawa machine, compressor', 'TVS-INFL-DIG', 'pcs'],
        ['TVS car vacuum cleaner 120 W', 'TVS', 'Accessories', 'p', 1999, 2499, 1300, '85081900', 2, 2, 24, 'E1', '6 months', [], 'vacuum, vaccum, cleaner, vacuum cleaner, safai machine', 'TVS-VAC-120', 'pcs'],
        ['TVS dual USB fast car charger', 'TVS', 'Accessories', 'p', 499, 699, 280, '85044090', 18, 5, 120, 'E2', '6 months', [], 'charger, mobile charger, usb charger, car charger, type c', 'TVS-CHG-DUAL', 'pcs'],
        // Coating and car care
        ['9H ceramic coating kit DIY (30 ml)', 'DHC', 'Car care', 'p', 1999, 2499, 1200, '34059090', 0, 2, 13, 'F1', null, [], 'ceramic, coating, 9h, ceramic coating, glass coating', 'CER-9H-30', 'pcs'],
        ['Microfibre wash glove', null, 'Car care', 'p', 349, 449, 170, '63079090', 22, 5, 85, 'F2', null, [], 'wash glove, glove, mitt, microfiber, microfibre, dastana', 'CARE-GLOVE', 'pcs'],
        ['Microfibre cloth 40x40 (pack of 3)', null, 'Car care', 'p', 299, 399, 140, '63079090', 35, 10, 160, 'F2', null, [], 'cloth, microfiber, microfibre, kapda, duster cloth, polishing cloth', 'CARE-MF-3', 'pack'],
        ['Flexible wax car duster', null, 'Car care', 'p', 599, 799, 300, '96039000', 9, 4, 74, 'F3', null, [], 'duster, jhadu, car duster, wax duster, dust brush', 'CARE-DUSTER', 'pcs'],
        ['Car shampoo 1 litre', null, 'Car care', 'p', 449, 549, 230, '34022090', 14, 5, 63, 'F1', null, [], 'shampoo, car wash, wash liquid, foam', 'CARE-SHAMPOO-1L', 'pcs'],
        ['Dashboard polish spray 500 ml', null, 'Car care', 'p', 249, 299, 120, '34059090', 3, 5, 58, 'F1', null, [], 'dashboard polish, polish, dash shine, spray', 'CARE-DASH-500', 'pcs'],
        ['Car perfume gel (long lasting)', null, 'Car care', 'p', 299, 399, 140, '33074900', 26, 8, 140, 'F4', null, [], 'perfume, fragrance, scent, khushboo, air freshener, gel', 'CARE-PERFUME-GEL', 'pcs'],
        // Other accessories
        ['Leather steering cover', null, 'Accessories', 'p', 599, 799, 280, '87089900', 12, 4, 69, 'E3', null, [], 'steering cover, steering kavar, wheel cover, steering', 'ACC-STEER-COVER', 'pcs'],
        ['Magnetic sun shades (set of 4)', null, 'Accessories', 'p', 999, 1299, 520, '63039990', 7, 3, 46, 'E3', null, [], 'sun shade, curtain, parda, window shade, sunshade', 'ACC-SUNSHADE-4', 'set'],
        ['Door visor rain guard (set of 4)', null, 'Accessories', 'p', 1499, 1899, 850, '87089900', 5, 2, 37, 'E4', null, [], 'door visor, rain guard, wind deflector, visor', 'ACC-VISOR-4', 'set'],
        ['Mud flaps (set of 4)', null, 'Accessories', 'p', 499, 699, 230, '87089900', 15, 4, 81, 'E4', null, [], 'mud flap, mudguard, mud guard, kichad flap', 'ACC-MUDFLAP-4', 'set'],
        ['Windtone horn (pair)', null, 'Accessories', 'p', 1299, 1599, 720, '85123010', 0, 2, 52, 'E4', '6 months', [], 'horn, windtone, pom pom, loud horn', 'ACC-HORN-WT', 'pair'],
        // Services
        ['Fitting charge', null, 'Services', 's', 500, null, null, '998714', null, 0, 310, null, null, [], 'fitting, labour, lagwai, installation, fitment', 'SRV-FIT', 'job'],
        ['Stereo fitting with wiring', null, 'Services', 's', 800, null, null, '998714', null, 0, 120, null, null, [], 'stereo fitting, wiring, installation, fitting', 'SRV-FIT-STEREO', 'job'],
        ['PPF labour (per panel)', null, 'Services', 's', 1500, null, null, '998714', null, 0, 64, null, null, [], 'ppf labour, ppf fitting, panel labour', 'SRV-PPF-PANEL', 'panel'],
        ['Ceramic coating 9H (full car)', null, 'Services', 's', 14999, 17999, null, '998714', null, 0, 42, null, '2 years', [], 'ceramic, coating, 9h, ceramic coating, glass coating, teflon', 'SRV-CER-9H', 'job'],
        ['Interior detailing (deep clean)', null, 'Services', 's', 2999, 3499, null, '998714', null, 0, 77, null, null, [], 'detailing, interior cleaning, dry clean, andar safai, deep clean', 'SRV-DET-INT', 'job'],
        ['Rubbing + polishing (exterior)', null, 'Services', 's', 2499, 2999, null, '998714', null, 0, 91, null, null, [], 'rubbing, polishing, polish, buffing, exterior detailing', 'SRV-DET-EXT', 'job'],
        ['Full car detailing (interior + exterior)', null, 'Services', 's', 4999, 5999, null, '998714', null, 0, 58, null, null, [], 'detailing, full detailing, cleaning, polishing, deep clean', 'SRV-DET-FULL', 'job'],
        ['Headlight restoration (pair)', null, 'Services', 's', 999, 1299, null, '998714', null, 0, 35, null, null, [], 'headlight polish, headlight restoration, yellow headlight, lamp polish', 'SRV-HL-RESTORE', 'job'],
    ];

    public function run(StockService $stock): void
    {
        if (Product::query()->exists()) {
            return;
        }
        $owner = User::query()->where('email', 'owner@crm.test')->first();
        $sales = User::query()->where('email', 'sales@crm.test')->first() ?? $owner;

        foreach (self::ITEMS as $i => [$name, $brand, $category, $type, $price, $mrp, $cost, $hsn, $qty, $reorder, $sold, $shelf, $warranty, $fits, $aliases, $sku, $unit]) {
            $product = Product::create([
                'sku' => $sku,
                'name' => $name,
                'aliases' => $aliases,
                'brand_id' => $brand ? Brand::firstOrCreate(['name' => $brand])->id : null,
                'category_id' => Category::firstOrCreate(['name' => $category, 'parent_id' => null])->id,
                'type' => $type === 's' ? Product::TYPE_SERVICE : Product::TYPE_PRODUCT,
                'hsn' => $hsn,
                'gst_rate' => 18,
                'price_paise' => $price * 100,
                'mrp_paise' => $mrp ? $mrp * 100 : null,
                'cost_paise' => $cost ? $cost * 100 : null,
                'unit' => $unit,
                'reorder_level' => $reorder,
                'shelf' => $shelf,
                'warranty' => $warranty,
                'compatible_cars' => $fits ?: null,
                'created_by' => $owner?->id,
            ]);
            $product->forceFill(['sold_count' => $sold])->saveQuietly();

            if ($product->track_stock && $qty !== null) {
                // A little history so the stock card isn't empty: opening stock, then a few sales.
                $soldNow = 1 + $i % 3;
                $stock->move($product, $qty + $soldNow + ($i % 5 === 0 ? 1 : 0), 'opening', null, $owner, $product->cost_paise, 'Opening stock');
                if ($i % 5 === 0) {
                    $stock->move($product, -1, 'adjustment', null, $owner, null, 'Damaged: box crushed');
                }
                for ($n = 0; $n < $soldNow; $n++) {
                    $stock->move($product, -1, 'sale', null, $sales, null, null);
                }
            }
        }
    }
}
