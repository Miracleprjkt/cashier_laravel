<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\InvoicePdfGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Auto generate invoice when order is created
        try {
            $invoiceGenerator = new InvoicePdfGenerator();
            $path = $invoiceGenerator->generateInvoice($order);
            $order->update(['invoice_path' => $path]);
        } catch (\Exception $e) {
            // Log the error but don't fail the order creation
            Log::error('Failed to auto-generate invoice for order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Regenerate invoice if important fields changed
        if ($order->isDirty(['name', 'items', 'total_price', 'metode_pembayaran'])) {
            try {
                $invoiceGenerator = new InvoicePdfGenerator();
                $path = $invoiceGenerator->generateInvoice($order);
                $order->updateQuietly(['invoice_path' => $path]); // Use updateQuietly to avoid infinite loop
            } catch (\Exception $e) {
                Log::error('Failed to regenerate invoice for order ' . $order->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Delete invoice file when order is deleted
        if ($order->invoice_path && Storage::disk('public')->exists($order->invoice_path)) {
            Storage::disk('public')->delete($order->invoice_path);
        }
    }
}