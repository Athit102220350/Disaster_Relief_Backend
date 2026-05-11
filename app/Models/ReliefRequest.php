<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReliefRequest extends Model
{
    protected $fillable = [
        'citizen_id',
        'coordinator_id',
        'title',
        'description',
        'disaster_type',
        'urgency_level',
        'people_count',
        'status',
        'latitude',
        'longitude',
        'address',
        'required_skills',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'required_skills' => 'json',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'completed_at' => 'datetime',
        ];
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'request_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'request_id');
    }
}

