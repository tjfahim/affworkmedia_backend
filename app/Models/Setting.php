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
        'player_page_domain',
        'manager_name',
        'manager_email',
        'manager_telegram',
        'manager_microsoft',
        'default_sale_hide',
        'default_affiliate_commission_1',
        'default_affiliate_commission_2',
        'default_affiliate_commission_3',
        'default_master_password',
        'default_payment_mail',
        'is_paypal_active',
        'is_payoneer_active',
        'is_bank_transfer_active',
        'is_binance_active',
    ];

    protected $casts = [
        'default_sale_hide' => 'integer',
        'default_affiliate_commission_1' => 'integer',
        'default_affiliate_commission_2' => 'integer',
        'default_affiliate_commission_3' => 'integer',
        'is_paypal_active' => 'boolean',
        'is_payoneer_active' => 'boolean',
        'is_bank_transfer_active' => 'boolean',
        'is_binance_active' => 'boolean',
    ];

    protected $hidden = [
        'default_master_password'
    ];

    // Get the first settings record or create a default one
    public static function getSettings()
    {
        $settings = self::first();
        
        // If no settings exist, create default settings
        if (!$settings) {
            $settings = self::create([
                'default_sale_hide' => 3,
                'default_payment_mail' => null,
                'landerpage_domain' => 'http://localhost:3000/',
                'player_page_domain' => 'http://localhost:3000/',
                'manager_name' => 'Test Manager',
                'manager_email' => 'asdf@as.c',
                'manager_telegram' => 'adsf',
                'manager_microsoft' => 'asdf',
                'logo' => null,
                'favicon' => null,
                'default_master_password' => '123456789',
                'default_affiliate_commission_1' => 70,
                'default_affiliate_commission_2' => 50,
                'default_affiliate_commission_3' => 40,
                'default_sale_hide' => 3,
                'is_paypal_active' => false,
                'is_payoneer_active' => false,
                'is_bank_transfer_active' => true,
                'is_binance_active' => true,
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
            if (in_array($key, $settings->getFillable())) {
                $settings->$key = $value;
            }
        }
        
        $settings->save();
        
        return $settings;
    }
}