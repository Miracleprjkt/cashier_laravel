<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Boot method to automatically calculate total_price
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($orderItem) {
            // Auto-calculate total_price before saving
            $orderItem->total_price = $orderItem->quantity * $orderItem->unit_price;
        });
    }

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessor for formatted unit price
    public function getFormattedUnitPriceAttribute(): string
    {
        return 'IDR ' . number_format($this->unit_price, 0, ',', '.');
    }

    // Accessor for formatted total price
    public function getFormattedTotalPriceAttribute(): string
    {
        return 'IDR ' . number_format($this->total_price, 0, ',', '.');
    }
}