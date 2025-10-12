<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    //
     protected $fillable = ['name', 'price', 'duration_days', 'ads_enabled'];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
