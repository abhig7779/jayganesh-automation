<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerCar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCar>
 */
class CustomerCarFactory extends Factory
{
    /** make => [model => [variants]] */
    private const CARS = [
        'Hyundai' => ['Creta' => ['SX', 'SX(O)', 'E'], 'Venue' => ['S', 'SX'], 'i20' => ['Asta', 'Sportz'], 'Verna' => ['SX', 'SX(O) Turbo']],
        'Toyota' => ['Innova Hycross' => ['VX', 'ZX'], 'Innova Crysta' => ['GX', 'VX'], 'Fortuner' => ['4x2 AT', 'Legender'], 'Urban Cruiser Hyryder' => ['G', 'V']],
        'Mahindra' => ['Scorpio-N' => ['Z4', 'Z8', 'Z8L'], 'XUV700' => ['AX5', 'AX7'], 'Thar' => ['LX', 'Roxx MX5'], 'XUV 3XO' => ['MX3', 'AX7']],
        'Maruti Suzuki' => ['Swift' => ['VXi', 'ZXi+'], 'Baleno' => ['Delta', 'Alpha'], 'Brezza' => ['VXi', 'ZXi'], 'Ertiga' => ['VXi CNG', 'ZXi'], 'Dzire' => ['VXi', 'ZXi'], 'Grand Vitara' => ['Zeta', 'Alpha']],
        'Tata' => ['Nexon' => ['Creative', 'Fearless+'], 'Punch' => ['Adventure', 'Creative'], 'Harrier' => ['Adventure', 'Fearless'], 'Altroz' => ['XZ', 'XZ+']],
        'Kia' => ['Seltos' => ['HTK+', 'GTX+'], 'Sonet' => ['HTX', 'GTX+'], 'Carens' => ['Prestige', 'Luxury']],
        'Honda' => ['City' => ['V', 'ZX'], 'Elevate' => ['V', 'ZX']],
    ];

    private const COLOURS = ['White', 'Pearl White', 'Silver', 'Grey', 'Black', 'Red', 'Blue', 'Brown', 'Everest White', 'Deep Forest'];

    public function definition(): array
    {
        $make = fake()->randomElement(array_keys(self::CARS));
        $model = fake()->randomElement(array_keys(self::CARS[$make]));

        return [
            'customer_id' => Customer::factory(),
            'make' => $make,
            'model' => $model,
            'variant' => fake()->randomElement(self::CARS[$make][$model]),
            'year' => fake()->numberBetween(2017, (int) now()->year),
            // Mostly Pune (MH-12), some Pimpri-Chinchwad (MH-14); stored normalised by the model.
            'reg_no' => fake()->randomElement(['MH12', 'MH12', 'MH12', 'MH14'])
                .' '.fake()->randomElement(['AB', 'BX', 'CD', 'DE', 'FT', 'GK', 'HN', 'JP', 'KR', 'LW', 'SY', 'TZ', 'UV', 'XA'])
                .' '.fake()->unique()->numerify('####'),
            'colour' => fake()->randomElement(self::COLOURS),
            'notes' => null,
        ];
    }

    public function withoutPlate(): static
    {
        return $this->state(fn () => ['reg_no' => null]);
    }
}
