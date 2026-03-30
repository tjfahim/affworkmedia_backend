<?php
// app/Models/AffiliatePayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliatePayment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'aff_user_id',
        'title',
        'email',
        'price',
        'pay_method',
        'description',
        'status',
        'paid_at',
        'transaction_id',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the affiliate user that owns the payment.
     */
    public function affiliate()
    {
        return $this->belongsTo(User::class, 'aff_user_id');
    }

    /**
     * Scope a query to only include payments with specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include payments for a specific affiliate.
     */
    public function scopeForAffiliate($query, $affUserId)
    {
        return $query->where('aff_user_id', $affUserId);
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute()
    {
        return [
            'pending' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
        ][$this->status] ?? 'secondary';
    }

    /**
     * Get the formatted price.
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted($transactionId = null)
    {
        $this->status = 'completed';
        $this->paid_at = now();
        
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        
        return $this->save();
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed($notes = null)
    {
        $this->status = 'failed';
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        return $this->save();
    }

    /**
     * Mark payment as cancelled.
     */
    public function markAsCancelled($notes = null)
    {
        $this->status = 'cancelled';
        
        if ($notes) {
            $this->notes = $notes;
        }
        
        return $this->save();
    }
}