<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'received_at',
        'estimated_delivery_at',
        'status',
        'observations',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date:Y-m-d',
            'estimated_delivery_at' => 'date:Y-m-d',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
