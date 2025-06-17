<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Tambahkan route ini ke routes/web.php
Route::get('/debug-images', function () {
    echo "<h2>Debug Storage Images</h2>";
    
    // Periksa konfigurasi
    echo "<h3>Configuration:</h3>";
    echo "<p>APP_URL: " . config('app.url') . "</p>";
    echo "<p>Storage Path: " . storage_path('app/public') . "</p>";
    echo "<p>Public Storage: " . public_path('storage') . "</p>";
    echo "<p>Storage Link Exists: " . (is_link(public_path('storage')) ? 'YES' : 'NO') . "</p>";
    
    // Periksa produk dengan gambar
    echo "<h3>Products with Images:</h3>";
    $products = \App\Models\Product::whereNotNull('image')->get();
    
    if ($products->count() == 0) {
        echo "<p>No products with images found.</p>";
    } else {
        foreach ($products as $product) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<h4>Product: {$product->name}</h4>";
            echo "<p><strong>Image Path:</strong> {$product->image}</p>";
            
            $fullPath = storage_path('app/public/' . $product->image);
            echo "<p><strong>Full Path:</strong> {$fullPath}</p>";
            echo "<p><strong>File Exists:</strong> " . (file_exists($fullPath) ? 'YES' : 'NO') . "</p>";
            
            if (file_exists($fullPath)) {
                $url = asset('storage/' . $product->image);
                echo "<p><strong>URL:</strong> <a href='{$url}' target='_blank'>{$url}</a></p>";
                echo "<img src='{$url}' style='max-width: 200px; max-height: 200px;'>";
            }
            echo "</div>";
        }
    }
    
    // Test files di folder products
    echo "<h3>Files in Products Directory:</h3>";
    $productsPath = storage_path('app/public/products');
    if (is_dir($productsPath)) {
        $files = scandir($productsPath);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $url = asset('storage/products/' . $file);
                echo "<p>{$file} - <a href='{$url}' target='_blank'>View</a></p>";
            }
        }
    } else {
        echo "<p>Products directory not found.</p>";
    }
});
