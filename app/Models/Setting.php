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
        'default_master_password',
        'default_payment_mail'
    ];

    protected $casts = [
        'default_sale_hide' => 'integer'
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
                'landerpage_domain' => null,
                'logo' => null,
                'favicon' => null,
                'default_master_password' => null
            ]);
        }
        
        return $settings;
    }

    // Update or create settings
    public static function updateSettings(array $data)
    {
        $settings = self::first();
        
        if ($settings) {
            // Update existing settings
            $settings->update($data);
        } else {
            // Create new settings
            $settings = self::create($data);
        }
        
        return $settings->fresh(); // Return fresh instance with updated data
    }
}