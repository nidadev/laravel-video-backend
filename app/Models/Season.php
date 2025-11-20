<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    //
   public function up()
{
    Schema::create('seasons', function (Blueprint $table) {
        $table->id();
        $table->string('name');        // Example: "Season 1"
        $table->string('slug')->nullable(); // Optional: "S01"
        $table->timestamps();
    });
}
}
