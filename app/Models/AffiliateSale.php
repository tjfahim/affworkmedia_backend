<?php
// app/Models/AffiliateSale.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'click_id',
        'game_id',
        'event_id',
        'package_type',
        'package_price',
        'commission_percentage',
        'commission_amount',
        'is_hidden',
        'customer_name',
        'customer_email',
        'customer_country',
        'transaction_id',
        'status',
        'purchased_at'
    ];

    protected $casts = [
        'purchased_at' => 'datetime'
    ];

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    public function click()
    {
        return $this->belongsTo(AffiliateClick::class, 'click_id');
    }

    public function game()
    {
        return $this->belongsTo(GameManage::class, 'game_id');
    }

    public function event()
    {
        return $this->belongsTo(EventManage::class, 'event_id');
    }
}