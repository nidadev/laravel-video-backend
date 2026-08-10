<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['name' => 'Free'],
            [
                'price' => 0,
                'duration_days' => 0,
                'ads_enabled' => true,
            ]
        );

        Plan::whereIn('name', ['Weekly', 'Yearly'])->delete();

        Plan::updateOrCreate(
            ['name' => 'Monthly'],
            [
                'price' => 9.99,
                'duration_days' => 30,
                'ads_enabled' => false,
            ]
        );

        Plan::updateOrCreate(
            ['name' => 'Annual'],
            [
                'price' => 119.88,
                'duration_days' => 365,
                'ads_enabled' => false,
            ]
        );
    }
}
