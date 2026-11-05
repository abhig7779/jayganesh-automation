<?php

namespace App\Support;

/**
 * Common cars on Pune roads, for the make/model picker on the customer and bill screens.
 * The picker searches this list as you type ("cret" → Hyundai Creta). A model that isn't here
 * can still be typed in full; it's saved as written.
 */
final class CarModels
{
    public const LIST = [
        'Maruti Suzuki' => ['Swift', 'Dzire', 'Baleno', 'Wagon R', 'Alto K10', 'Celerio', 'S-Presso', 'Ignis', 'Brezza', 'Fronx', 'Ertiga', 'XL6', 'Ciaz', 'Grand Vitara', 'Jimny', 'Invicto', 'Eeco'],
        'Hyundai' => ['Creta', 'Venue', 'i20', 'Grand i10 Nios', 'Verna', 'Exter', 'Alcazar', 'Tucson', 'Aura', 'Ioniq 5'],
        'Tata' => ['Nexon', 'Punch', 'Harrier', 'Safari', 'Altroz', 'Tiago', 'Tigor', 'Curvv', 'Nexon EV', 'Punch EV', 'Tiago EV'],
        'Mahindra' => ['Scorpio-N', 'Scorpio Classic', 'XUV700', 'XUV 3XO', 'Thar', 'Thar Roxx', 'Bolero', 'Bolero Neo', 'XUV400', 'BE 6', 'XEV 9e', 'Marazzo'],
        'Toyota' => ['Innova Hycross', 'Innova Crysta', 'Fortuner', 'Urban Cruiser Hyryder', 'Glanza', 'Rumion', 'Camry', 'Hilux', 'Taisor'],
        'Kia' => ['Seltos', 'Sonet', 'Carens', 'Syros', 'Carnival', 'EV6'],
        'Honda' => ['City', 'Amaze', 'Elevate', 'City e:HEV', 'WR-V', 'Jazz'],
        'MG' => ['Hector', 'Hector Plus', 'Astor', 'ZS EV', 'Comet EV', 'Windsor EV', 'Gloster'],
        'Skoda' => ['Kushaq', 'Slavia', 'Kylaq', 'Kodiaq', 'Octavia', 'Superb'],
        'Volkswagen' => ['Taigun', 'Virtus', 'Tiguan', 'Polo', 'Vento'],
        'Renault' => ['Kiger', 'Triber', 'Kwid', 'Duster'],
        'Nissan' => ['Magnite', 'Kicks', 'X-Trail'],
        'Citroen' => ['C3', 'C3 Aircross', 'Basalt', 'eC3'],
        'Jeep' => ['Compass', 'Meridian', 'Wrangler'],
        'Ford' => ['EcoSport', 'Endeavour', 'Figo', 'Aspire'],
        'BYD' => ['Atto 3', 'Seal', 'eMAX 7'],
        'Mercedes-Benz' => ['C-Class', 'E-Class', 'GLA', 'GLC', 'GLE'],
        'BMW' => ['3 Series', '5 Series', 'X1', 'X3', 'X5'],
        'Audi' => ['A4', 'A6', 'Q3', 'Q5', 'Q7'],
    ];

    /** Flat list for the picker: [['make' => 'Hyundai', 'model' => 'Creta', 'label' => 'Hyundai Creta'], …]. */
    public static function options(): array
    {
        $out = [];
        foreach (self::LIST as $make => $models) {
            foreach ($models as $model) {
                $out[] = ['make' => $make, 'model' => $model, 'label' => "$make $model"];
            }
        }

        return $out;
    }

    /**
     * Splits what was typed or picked into [make, model]. "Hyundai Creta" or just "creta" both
     * give ['Hyundai', 'Creta']; an unknown "Force Gurkha" gives ['Force', 'Gurkha'].
     */
    public static function split(?string $make, string $typed): array
    {
        $typed = trim(preg_replace('/\s+/', ' ', $typed));
        if ($make && isset(self::LIST[$make])) {
            $model = trim(preg_replace('/^'.preg_quote($make, '/').'\s*/i', '', $typed));

            return [$make, $model !== '' ? $model : $typed];
        }
        foreach (self::options() as $o) {
            if (strcasecmp($o['label'], $typed) === 0 || strcasecmp($o['model'], $typed) === 0) {
                return [$o['make'], $o['model']];
            }
        }
        $parts = explode(' ', $typed, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['Other', $typed];
    }

    /**
     * Flat list for the New bill car picker: every common model plus models already saved on
     * customers' cars (billing module).
     *
     * @return array<int, array{make:string, model:string, label:string}>
     */
    public static function list(): array
    {
        $rows = [];
        foreach (self::LIST as $make => $models) {
            foreach ($models as $model) {
                $rows["$make|$model"] = ['make' => $make, 'model' => $model, 'label' => "$make $model"];
            }
        }
        foreach (\App\Models\CustomerCar::query()->select('make', 'model')->distinct()->limit(300)->get() as $c) {
            $rows["{$c->make}|{$c->model}"] ??= ['make' => $c->make, 'model' => $c->model, 'label' => trim("{$c->make} {$c->model}")];
        }

        return array_values($rows);
    }
}
