<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $fillable = [
        'date',
        'opening_amount',
        'expected_amount',
        'counted_amount',
        'difference',
        'opened_at',
        'closed_at',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'opening_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'counted_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
