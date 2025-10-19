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
    // 🆓 Free Plan
    Plan::updateOrCreate(
        ['name' => 'Free'],
        [
            'price' => 0,
            'duration_days' => 0, // Free plan never expires or has restricted access
            'ads_enabled' => true,
        ]
    );

    // 📅 Weekly Plan
    Plan::updateOrCreate(
        ['name' => 'Weekly'],
        [
            'price' => 2.99,
            'duration_days' => 7,
            'ads_enabled' => false,
        ]
    );

    // 🗓️ Monthly Plan
    Plan::updateOrCreate(
        ['name' => 'Monthly'],
        [
            'price' => 9.99,
            'duration_days' => 30,
            'ads_enabled' => false,
        ]
    );

    // 📆 Yearly Plan
    Plan::updateOrCreate(
        ['name' => 'Yearly'],
        [
            'price' => 99.99,
            'duration_days' => 365,
            'ads_enabled' => false,
        ]
    );
}

    
}
