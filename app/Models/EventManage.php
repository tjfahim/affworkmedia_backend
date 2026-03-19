<?php
// app/Models/EventManage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EventManage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'event_manages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_manage_id',
        'first_team_id',
        'second_team_id',
        'start_datetime',
        'end_datetime',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    /**
     * Get the game that owns the event.
     */
    public function game()
    {
        return $this->belongsTo(GameManage::class, 'game_manage_id');
    }

    /**
     * Get the first team for the event.
     */
    public function firstTeam()
    {
        return $this->belongsTo(TeamManage::class, 'first_team_id');
    }

    /**
     * Get the second team for the event.
     */
    public function secondTeam()
    {
        return $this->belongsTo(TeamManage::class, 'second_team_id');
    }

    /**
     * Scope a query to only include upcoming events.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    /**
     * Scope a query to only include running events.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope a query to only include finished events.
     */
    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }

    /**
     * Scope a query to filter by game.
     */
    public function scopeByGame($query, $gameId)
    {
        return $query->where('game_manage_id', $gameId);
    }

    /**
     * Scope a query to filter by team.
     */
    public function scopeByTeam($query, $teamId)
    {
        return $query->where(function($q) use ($teamId) {
            $q->where('first_team_id', $teamId)
              ->orWhere('second_team_id', $teamId);
        });
    }

    /**
     * Get the event duration in minutes.
     */
    public function getDurationAttribute()
    {
        if (!$this->end_datetime) {
            return null;
        }
        
        return $this->start_datetime->diffInMinutes($this->end_datetime);
    }

    /**
     * Get the event status color for UI.
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'upcoming' => 'warning',
            'running' => 'success',
            'finished' => 'secondary',
            default => 'primary'
        };
    }
}