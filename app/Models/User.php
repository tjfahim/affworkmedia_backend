<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
   protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'address',
        'balance',
        'pay_method',
        'account_email',
        'skype',
        'company',
        'website',
        'promotion_description',
        'payoneer',
        'paypal',
        'default_affiliate_commission_1',
        'default_affiliate_commission_2',
        'default_affiliate_commission_3',
        'sale_hide',
        'status',
        'bank_details',
        'edit_paypal_mail_status',
        'edit_payoneer_mail_status',
        'edit_bank_details_status',
        'edit_binance_mail_status',
        'edit_other_payment_method_description_status',
        'binance',
        'other_payment_method_description',
        'total_earnings',
        'total_sales',
        'unique_clicks',
        'total_clicks',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'balance' => 'decimal:2',
        'default_affiliate_commission_1' => 'decimal:2',
        'default_affiliate_commission_2' => 'decimal:2',
        'default_affiliate_commission_3' => 'decimal:2',
        'sale_hide' => 'decimal:2',
        'edit_paypal_mail_status' => 'string',
        'edit_payoneer_mail_status' => 'string',
        'edit_bank_details_status' => 'string',
        'edit_binance_mail_status' => 'string',
        'edit_other_payment_method_description_status' => 'string',
    ];


    // Accessors
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    public function scopeWithPendingPaymentRequests($query)
    {
        return $query->where(function($q) {
            $q->where('edit_paypal_mail_status', 'requested')
              ->orWhere('edit_payoneer_mail_status', 'requested')
              ->orWhere('edit_bank_details_status', 'requested')
              ->orWhere('edit_binance_mail_status', 'requested')
              ->orWhere('edit_other_payment_method_description_status', 'requested');
        });
    }

    // Add relationship
public function affiliatePayments()
{
    return $this->hasMany(AffiliatePayment::class, 'aff_user_id');
}

// Add method to update balance
public function updateBalance($amount, $operation = 'add')
{
    if ($operation === 'add') {
        $this->balance += $amount;
    } elseif ($operation === 'subtract') {
        $this->balance -= $amount;
    }
    
    return $this->save();
}

  public function hasActivePaymentMethod()
    {
        return $this->edit_paypal_mail_status === 'active' ||
               $this->edit_payoneer_mail_status === 'active' ||
               $this->edit_bank_details_status === 'active' ||
               $this->edit_binance_mail_status === 'active' ||
               $this->edit_other_payment_method_description_status === 'active';
    }
      public function getActivePaymentMethods()
    {
        $methods = [];
        
        if ($this->edit_paypal_mail_status === 'active' && $this->paypal) {
            $methods['paypal'] = $this->paypal;
        }
        
        if ($this->edit_payoneer_mail_status === 'active' && $this->payoneer) {
            $methods['payoneer'] = $this->payoneer;
        }
        
        if ($this->edit_bank_details_status === 'active' && $this->bank_details) {
            $methods['bank'] = $this->bank_details;
        }
        
        if ($this->edit_binance_mail_status === 'active' && $this->binance) {
            $methods['binance'] = $this->binance;
        }
        
        if ($this->edit_other_payment_method_description_status === 'active' && $this->other_payment_method_description) {
            $methods['other'] = $this->other_payment_method_description;
        }
        
        return $methods;
    }
}