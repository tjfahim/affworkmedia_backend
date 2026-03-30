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
        'aff_percent',
        'sale_add',
        'auto_renew',
        'sale_hide',
        'status',
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
        'aff_percent' => 'decimal:2',
        'auto_renew' => 'boolean',
        'sale_add' => 'integer',
        'sale_hide' => 'integer',
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
}