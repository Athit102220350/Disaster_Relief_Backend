<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    protected $fillable = [
        'warehouse_id',
        'request_id',
        'rescue_team_id',
        'coordinator_id',
        'items_detail',
        'total_value',
        'status',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'items_detail' => 'json',
            'total_value' => 'decimal:2',
            'delivered_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReliefRequest::class, 'request_id');
    }

    public function rescueTeam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescue_team_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }
}

