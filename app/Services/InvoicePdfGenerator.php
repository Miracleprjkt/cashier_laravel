<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfGenerator
{
    public function generateInvoice(Order $order): string
    {
        // ✅ Sanitize order data
        $order = $this->sanitizeOrder($order);

        // ✅ Konfigurasi PDF dengan encoding UTF-8
        $pdf = Pdf::loadView('invoices.template', [
            'order' => $order,
            'company' => $this->getCompanyData()
        ])->setOptions([
            'defaultFont' => 'DejaVu Sans', // Font yang support UTF-8
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultEncoding' => 'UTF-8',
            'chroot' => public_path(),
            'enable_remote' => false
        ]);

        $filename = 'invoice-' . $order->id . '-' . now()->format('Y-m-d') . '.pdf';
        $path = 'invoices/' . $filename;

        // Save PDF to storage
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    public function downloadInvoice(Order $order)
    {
        try {
            // ✅ Sanitize order data
            $order = $this->sanitizeOrder($order);

            // ✅ Konfigurasi PDF dengan encoding UTF-8
            $pdf = Pdf::loadView('invoices.template', [
                'order' => $order,
                'company' => $this->getCompanyData()
            ])->setOptions([
                'defaultFont' => 'DejaVu Sans', // Font yang support UTF-8
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultEncoding' => 'UTF-8',
                'chroot' => public_path(),
                'enable_remote' => false
            ]);

            $filename = 'Invoice-' . $order->id . '.pdf';

            // ✅ Set proper headers untuk UTF-8
            return response()->streamDownload(function() use ($pdf) {
                echo $pdf->output();
            }, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);

        } catch (\Exception $e) {
            // Log error untuk debugging
            \Log::error('Invoice PDF Generation Error: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    private function sanitizeOrder(Order $order): Order
    {
        // ✅ Handle items dengan lebih robust
        if (is_string($order->items)) {
            $decoded = json_decode($order->items, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Jika JSON rusak, coba perbaiki encoding dulu
                $order->items = json_decode(
                    mb_convert_encoding($order->items, 'UTF-8', 'auto'), 
                    true
                ) ?? [];
            } else {
                $order->items = $decoded ?? [];
            }
        }

        // ✅ Sanitize semua string fields di order
        $stringFields = ['customer_name', 'customer_phone', 'customer_address', 'notes'];
        foreach ($stringFields as $field) {
            if (isset($order->$field) && is_string($order->$field)) {
                $order->$field = $this->sanitizeString($order->$field);
            }
        }

        // ✅ Sanitize items array
        if (is_array($order->items)) {
            $order->items = array_map(function ($item) {
                return $this->sanitizeArray($item);
            }, $order->items);
        }

        return $order;
    }

    private function sanitizeString(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        // ✅ Bersihkan karakter yang bermasalah
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        // ✅ Convert ke UTF-8 yang valid
        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        
        // ✅ Pastikan valid UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8//IGNORE');
        }

        return trim($value);
    }

    private function sanitizeArray(array $array): array
    {
        $sanitized = [];
        foreach ($array as $key => $value) {
            $cleanKey = $this->sanitizeString((string)$key);
            
            if (is_array($value)) {
                $sanitized[$cleanKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$cleanKey] = $this->sanitizeString($value);
            } else {
                $sanitized[$cleanKey] = $value;
            }
        }
        return $sanitized;
    }

    private function getCompanyData(): array
    {
        return [
            'name' => $this->sanitizeString(config('app.name', 'Your Company')),
            'address' => $this->sanitizeString('Jl. Contoh No. 123, Jakarta'),
            'phone' => $this->sanitizeString('+62 123 456 789'),
            'email' => $this->sanitizeString('info@company.com')
        ];
    }
}