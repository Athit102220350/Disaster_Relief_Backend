<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $fillable = [
        'request_id',
        'rescue_team_id',
        'algorithm',
        'cost_score',
        'distance_km',
        'route_data',
        'status',
        'arrived_at',
        'completed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'route_data' => 'json',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReliefRequest::class, 'request_id');
    }

    public function rescueTeam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescue_team_id');
    }
}

