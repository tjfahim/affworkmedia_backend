<?php
// app/Models/LandingManage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingManage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_manage_id',
        'name',
        'image',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the game that owns the landing.
     */
    public function game()
    {
        return $this->belongsTo(GameManage::class, 'game_manage_id');
    }

    /**
     * Scope a query to only include active landings.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}