<?php
// app/Models/AffiliateClick.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'game_id',
        'event_id',
        'ip_address',
        'country',
        'city',
        'device_type',
        'browser',
        'sub1',
        'sub2',
        'sub3',
        'sub4',
        'sub5',
        'sub6',
        'referrer',
        'fingerprint',
        'is_unique'
    ];

    protected $casts = [
        'is_unique' => 'boolean'
    ];

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }

    public function game()
    {
        return $this->belongsTo(GameManage::class, 'game_id');
    }

    public function event()
    {
        return $this->belongsTo(EventManage::class, 'event_id');
    }

    public function sales()
    {
        return $this->hasMany(AffiliateSale::class, 'click_id');
    }
}