<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    private const FIRST = [
        'Rahul', 'Sachin', 'Amit', 'Sagar', 'Pravin', 'Nikhil', 'Rohit', 'Ganesh', 'Mahesh', 'Sandeep',
        'Akshay', 'Omkar', 'Tushar', 'Vishal', 'Swapnil', 'Prasad', 'Aniket', 'Yogesh', 'Kiran', 'Sneha',
        'Pooja', 'Priyanka', 'Shweta', 'Neha', 'Aarti', 'Vaishali', 'Manoj', 'Suresh', 'Dattatray', 'Shubham',
    ];

    private const LAST = [
        'Kale', 'Jadhav', 'Pawar', 'Shinde', 'Kulkarni', 'Deshmukh', 'Patil', 'Gaikwad', 'Bhosale', 'More',
        'Chavan', 'Joshi', 'Deshpande', 'Kadam', 'Thorat', 'Salunkhe', 'Wagh', 'Mane', 'Kamble', 'Sawant',
        'Gokhale', 'Dhamale', 'Kalbhor', 'Shitole', 'Tupe',
    ];

    private const AREAS = [
        'Loni Kalbhor', 'Hadapsar', 'Uruli Kanchan', 'Kharadi', 'Wagholi', 'Manjari', 'Magarpatta',
        'Kondhwa', 'Undri', 'Theur', 'Fursungi', 'Kesnand', 'Viman Nagar', 'Mundhwa', 'Yavat',
    ];

    public function definition(): array
    {
        $first = fake()->randomElement(self::FIRST);
        $last = fake()->randomElement(self::LAST);

        return [
            'branch_id' => fn () => Branch::query()->value('id') ?? Branch::create(['name' => 'Main', 'code' => 'JG'])->id,
            'name' => "$first $last",
            'mobile' => '+91'.fake()->randomElement(['98', '97', '96', '95', '93', '90', '88', '86', '82', '77', '70'])
                .fake()->unique()->numerify('########'),
            'email' => fake()->boolean(25) ? strtolower($first.'.'.$last).fake()->numberBetween(1, 99).'@gmail.com' : null,
            'area' => fake()->randomElement(self::AREAS).', Pune',
            'source' => fake()->randomElement(['walk_in', 'walk_in', 'walk_in', 'whatsapp_ad', 'instagram', 'google', 'referral']),
            'whatsapp_opt_in' => fake()->boolean(92),
            'tags' => fake()->boolean(30) ? fake()->randomElements(['ppf', 'ceramic', 'seat-covers', 'audio', 'vip', 'fleet'], 2) : null,
            'notes' => null,
        ];
    }

    public function archived(): static
    {
        return $this->afterCreating(function (Customer $c) {
            $c->archived_at = now();
            $c->saveQuietly();
        });
    }
}
