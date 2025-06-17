<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gender',
        'email',
        'phone',
        'birthday',
        'notes',
        'total_price',
        'payment_amount',
        'change_amount',
        'payment_method',
        'invoice_path',
    ];

    protected $casts = [
        'birthday' => 'date',
        'payment_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // Boot method for auto calculations
    protected static function boot()
    {
        parent::boot();

        // After order is saved, ensure total price is calculated from order items
        static::saved(function ($order) {
            if ($order->orderItems()->exists()) {
                $calculatedTotal = $order->orderItems()->sum('total_price');
                if ($calculatedTotal != $order->total_price) {
                    $order->update(['total_price' => $calculatedTotal]);
                }
            }
        });
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Auto calculate total price from order items
    public function calculateTotalPrice(): void
    {
        $this->total_price = $this->orderItems()->sum('total_price');
        $this->save();
    }

    // Accessor untuk format currency
    protected function totalPriceFormatted(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'IDR ' . number_format($this->total_price, 0, ',', '.')
        );
    }

    // Method untuk generate invoice
    public function generateInvoice()
    {
        $invoiceGenerator = new \App\Services\InvoicePdfGenerator();
        $path = $invoiceGenerator->generateInvoice($this);
       
        $this->update(['invoice_path' => $path]);
       
        return $path;
    }

    // Method untuk check apakah invoice sudah ada
    public function hasInvoice(): bool
    {
        return !empty($this->invoice_path) && Storage::disk('public')->exists($this->invoice_path);
    }

    // Method untuk get invoice URL
    public function getInvoiceUrl(): ?string
    {
        if ($this->hasInvoice()) {
            return Storage::disk('public')->url($this->invoice_path);
        }
       
        return null;
    }

    // Helper method to get total items count
    public function getTotalItemsAttribute(): int
    {
        return $this->orderItems()->sum('quantity');
    }

    // Helper method to get formatted payment amount
    public function getFormattedPaymentAmountAttribute(): string
    {
        return 'IDR ' . number_format($this->payment_amount, 0, ',', '.');
    }

    // Helper method to get formatted change amount
    public function getFormattedChangeAmountAttribute(): string
    {
        return 'IDR ' . number_format($this->change_amount, 0, ',', '.');
    }
}