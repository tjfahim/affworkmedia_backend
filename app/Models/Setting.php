<?php
// app/Models/Setting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'logo',
        'favicon',
        'landerpage_domain',
        'default_sale_hide',
        'default_affiliate_commission_1',
        'default_affiliate_commission_2',
        'default_affiliate_commission_3',
        'default_master_password',
        'default_payment_mail'
    ];

    protected $casts = [
        'default_sale_hide' => 'integer',
        'default_affiliate_commission_1' => 'integer',
        'default_affiliate_commission_2' => 'integer',
        'default_affiliate_commission_3' => 'integer',
    ];

    // Get the first settings record or create a default one
    public static function getSettings()
    {
        $settings = self::first();
        
        // If no settings exist, create default settings
        if (!$settings) {
            $settings = self::create([
                'default_sale_hide' => 0,
                'default_payment_mail' => null,
                'landerpage_domain' => 'http://localhost:3000/',
                'logo' => null,
                'favicon' => null,
                'default_master_password' => '123456789',
                'default_affiliate_commission_1' => 70,
                'default_affiliate_commission_2' => 50,
                'default_affiliate_commission_3' => 40,
            ]);
        }
        
        return $settings;
    }

    // Update or create settings
   public static function updateSettings(array $data)
    {
        $settings = self::first();
        
        if (!$settings) {
            $settings = new self();
        }
        
        foreach ($data as $key => $value) {
            $settings->$key = $value;
        }
        
        $settings->save();
        
        return $settings;
    }
}