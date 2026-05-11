<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RescueProfile extends Model
{
    protected $fillable = [
        'user_id',
        'specialization',
        'certificate',
        'organization',
        'status',
        'vehicle_type',
        'total_missions',
        'current_lat',
        'current_lng',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'last_seen' => 'datetime',
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

