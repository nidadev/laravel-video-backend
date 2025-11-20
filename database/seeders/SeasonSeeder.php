<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Season;
use Illuminate\Support\Str;

class SeasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
         $seasons = [
        'Season 1',
        'Season 2',
        'Season 3',
        'Season 4'
    ];

    foreach ($seasons as $name) {
        \App\Models\Season::create([
            'name' => $name,
            'slug' => Str::slug($name)
        ]);
    }
    }
}
