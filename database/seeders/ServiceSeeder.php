<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'The signature cut', 'description' => 'A proper consultation, precision cut, and a finish that feels like you.', 'duration_minutes' => 45, 'price_cents' => 2800],
            ['name' => 'Beard & detail', 'description' => 'Shape, balance, and clean edges. A little attention makes all the difference.', 'duration_minutes' => 30, 'price_cents' => 1800],
            ['name' => 'The full ritual', 'description' => 'Our signature haircut paired with a beard sculpt and hot-towel finish.', 'duration_minutes' => 75, 'price_cents' => 4200],
        ] as $service) {
            Service::updateOrCreate(['name' => $service['name']], $service);
        }
    }
}
