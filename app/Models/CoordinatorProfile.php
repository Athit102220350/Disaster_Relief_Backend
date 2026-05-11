<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinatorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'area_name',
        'organization',
        'authority_level',
        'current_lat',
        'current_lng',
    ];

    protected function casts(): array
    {
        return [
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

