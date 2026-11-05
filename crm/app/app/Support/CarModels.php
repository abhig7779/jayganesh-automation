<?php

namespace App\Support;

use App\Models\CustomerCar;

/** Car model picker for the New bill mini form: the common Indian list plus models already saved. */
final class CarModels
{
    private const COMMON = [
        'Hyundai' => ['Creta', 'Venue', 'i20', 'Grand i10 Nios', 'Verna', 'Alcazar', 'Exter', 'Tucson'],
        'Maruti Suzuki' => ['Swift', 'Baleno', 'Brezza', 'Ertiga', 'Dzire', 'Grand Vitara', 'Fronx', 'WagonR', 'Alto K10', 'XL6', 'Ciaz', 'Celerio', 'Jimny', 'Invicto'],
        'Tata' => ['Nexon', 'Punch', 'Harrier', 'Safari', 'Altroz', 'Tiago', 'Tigor', 'Curvv'],
        'Mahindra' => ['Scorpio-N', 'Scorpio Classic', 'XUV700', 'Thar', 'Thar Roxx', 'XUV 3XO', 'Bolero', 'Bolero Neo', 'XUV400'],
        'Toyota' => ['Innova Hycross', 'Innova Crysta', 'Fortuner', 'Urban Cruiser Hyryder', 'Glanza', 'Rumion', 'Camry'],
        'Kia' => ['Seltos', 'Sonet', 'Carens', 'Syros'],
        'Honda' => ['City', 'Amaze', 'Elevate'],
        'Skoda' => ['Kushaq', 'Slavia', 'Kylaq'],
        'Volkswagen' => ['Taigun', 'Virtus'],
        'MG' => ['Hector', 'Astor', 'Windsor EV', 'Comet EV'],
        'Renault' => ['Kiger', 'Triber', 'Kwid'],
        'Nissan' => ['Magnite'],
    ];

    /** @return array<int, array{make:string, model:string, label:string}> */
    public static function list(): array
    {
        $rows = [];
        foreach (self::COMMON as $make => $models) {
            foreach ($models as $model) {
                $rows["$make|$model"] = ['make' => $make, 'model' => $model, 'label' => "$make $model"];
            }
        }
        foreach (CustomerCar::query()->select('make', 'model')->distinct()->limit(300)->get() as $c) {
            $rows["{$c->make}|{$c->model}"] ??= ['make' => $c->make, 'model' => $c->model, 'label' => trim("{$c->make} {$c->model}")];
        }

        return array_values($rows);
    }
}
