<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GooglePayPurchase extends Model
{
    use HasFactory;

    protected $table = 'google_pay_purchases';

    protected $fillable = [
        'user_id',
        'plan_id',
        'googlepay_transaction_id',
        'googlepay_email',
        'payment_response',
        'status',
    ];

    protected $casts = [
        'payment_response' => 'array',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
