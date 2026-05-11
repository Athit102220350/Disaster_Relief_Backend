<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'donation_id',
        'bank_transaction_id',
        'payment_gateway',
        'actual_amount',
        'status',
        'transfer_content',
        'transacted_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'actual_amount' => 'decimal:2',
            'transacted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }
}

