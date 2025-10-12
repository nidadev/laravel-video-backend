<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        //
        public function run()
    {
        Plan::updateOrCreate([
            'name' => 'Free',
        ], [
            'price' => 0,
            'duration_days' => 0,
            'ads_enabled' => true,
        ]);

        Plan::updateOrCreate([
            'name' => 'Premium',
        ], [
            'price' => 9.99,
            'duration_days' => 30,
            'ads_enabled' => false,
        ]);
    }
    
}
