<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use App\Services\InvoicePdfGenerator;
use Illuminate\Support\Facades\Response;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Orders';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Info Utama')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(2),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),
                    ]),

                Section::make('Produk dipesan')
                    ->schema([
                        Repeater::make('orderItems')
                            ->relationship()
                            ->schema([
                                Grid::make(4) // Changed from 2 to 4 for better layout
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->required()
                                            ->options(function () {
                                                return Product::where('stock', '>', 0)
                                                    ->get()
                                                    ->mapWithKeys(function ($product) {
                                                        return [$product->id => $product->name . ' (Stock: ' . $product->stock . ')'];
                                                    });
                                            })
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if (empty($state)) {
                                                    $set('unit_price', 0);
                                                    $set('available_stock', 0);
                                                    $set('quantity', 0);
                                                    $set('total_price', 0);
                                                    self::updateTotalPrice($get, $set);
                                                    return;
                                                }

                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('unit_price', $product->price);
                                                    $set('available_stock', $product->stock);
                                                    
                                                    // Set quantity to 1 when product is selected
                                                    $set('quantity', 1);
                                                    $set('total_price', 1 * $product->price);
                                                } else {
                                                    $set('unit_price', 0);
                                                    $set('available_stock', 0);
                                                    $set('quantity', 0);
                                                    $set('total_price', 0);
                                                }
                                                
                                                // Update global total price
                                                self::updateTotalPrice($get, $set);
                                            })
                                            ->placeholder('Select a product')
                                            ->columnSpan(2),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->disabled(fn (Get $get) => empty($get('product_id')))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                $productId = $get('product_id');
                                                
                                                // If no product selected, reset quantity to 0
                                                if (empty($productId)) {
                                                    $set('quantity', 0);
                                                    $set('total_price', 0);
                                                    self::updateTotalPrice($get, $set);
                                                    return;
                                                }

                                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                                $availableStock = (int) ($get('available_stock') ?? 0);
                                                $quantity = (int) ($state ?? 0);

                                                // Validate minimum quantity
                                                if ($quantity < 1) {
                                                    $set('quantity', 1);
                                                    $quantity = 1;
                                                }

                                                // Validate stock
                                                if ($availableStock > 0 && $quantity > $availableStock) {
                                                    $set('quantity', $availableStock);
                                                    $quantity = $availableStock;
                                                    
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('Stock Insufficient')
                                                        ->body("Maximum quantity available: {$availableStock}")
                                                        ->send();
                                                }

                                                // Calculate total price for this item
                                                $totalPrice = $quantity * $unitPrice;
                                                $set('total_price', $totalPrice);

                                                // Update global total price
                                                self::updateTotalPrice($get, $set);
                                            })
                                            ->rules([
                                                function (Get $get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $productId = $get('product_id');
                                                        
                                                        // If product is selected, quantity must be at least 1
                                                        if (!empty($productId) && $value < 1) {
                                                            $fail("Quantity must be at least 1 when product is selected.");
                                                        }
                                                        
                                                        $availableStock = $get('available_stock') ?? 0;
                                                        if ($value > $availableStock) {
                                                            $fail("Quantity cannot exceed available stock ({$availableStock}).");
                                                        }
                                                    };
                                                },
                                            ])
                                            ->columnSpan(1),

                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(1),

                                        // Hidden fields for calculation
                                        Forms\Components\Hidden::make('available_stock')
                                            ->dehydrated(false)
                                            ->default(0),

                                        Forms\Components\Hidden::make('total_price')
                                            ->dehydrated()
                                            ->default(0),
                                    ]),
                            ])
                            ->addActionLabel('Add to items')
                            ->defaultItems(1)
                            ->collapsible()
                            ->cloneable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotalPrice($get, $set);
                            })
                            ->deleteAction(
                                fn ($action) => $action->after(fn (Get $get, Set $set) => self::updateTotalPrice($get, $set))
                            )
                            ->addAction(
                                fn ($action) => $action->after(fn (Get $get, Set $set) => self::updateTotalPrice($get, $set))
                            ),
                    ]),

                Section::make('Pembayaran')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0)
                                    ->live(),

                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->required()
                                    ->options([
                                        'cash' => 'Cash',
                                        'transfer' => 'Transfer',
                                        'card' => 'Card',
                                        'e_wallet' => 'E-Wallet',
                                    ])
                                    ->placeholder('Select an option'),

                                TextInput::make('payment_amount')
                                    ->label('Payment Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $totalPrice = (float) ($get('total_price') ?? 0);
                                        $paymentAmount = (float) ($state ?? 0);
                                        $change = max(0, $paymentAmount - $totalPrice);
                                        $set('change_amount', $change);
                                    }),

                                TextInput::make('change_amount')
                                    ->label('Change Amount')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }

    // Improved helper function to calculate total price
    protected static function updateTotalPrice(Get $get, Set $set): void
    {
        // Get order items with better error handling
        $orderItems = $get('orderItems');
        
        if (!is_array($orderItems) || empty($orderItems)) {
            $set('total_price', 0);
            $set('change_amount', 0);
            return;
        }

        $total = 0;
        
        foreach ($orderItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Only calculate if product is selected and quantity > 0
            $productId = $item['product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            if (!empty($productId) && $quantity > 0 && $unitPrice > 0) {
                $itemTotal = $quantity * $unitPrice;
                $total += $itemTotal;
            }
        }

        $set('total_price', $total);

        // Recalculate change amount if payment amount exists
        $paymentAmount = (float) ($get('payment_amount') ?? 0);
        if ($paymentAmount > 0) {
            $change = max(0, $paymentAmount - $total);
            $set('change_amount', $change);
        } else {
            $set('change_amount', 0);
        }
    }

    // Method to validate stock before saving
    public static function validateStock(array $orderItems): bool
    {
        foreach ($orderItems as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                continue;
            }

            $product = Product::find($item['product_id']);
            if (!$product || $product->stock < $item['quantity']) {
                return false;
            }
        }
        return true;
    }

    // Method to reduce stock after successful order
    public static function reduceStock(array $orderItems): void
    {
        foreach ($orderItems as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                continue;
            }

            $product = Product::find($item['product_id']);
            if ($product) {
                $product->decrement('stock', $item['quantity']);
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
               
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
               
                TextColumn::make('orderItems')
                    ->label('Items')
                    ->formatStateUsing(function ($record) {
                        return $record->orderItems->count() . ' item(s)';
                    })
                    ->tooltip(function ($record) {
                        return $record->orderItems->map(function ($item) {
                            return $item->product->name . ' (Qty: ' . $item->quantity . ')';
                        })->implode(', ');
                    }),
               
                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'transfer' => 'info',
                        'card' => 'warning',
                        'e_wallet' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
               
                TextColumn::make('total_price')
                    ->label('Total Price')
                    ->money('IDR')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download_invoice')
                    ->label('Download Invoice')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (Order $record) {
                        $invoiceGenerator = new InvoicePdfGenerator();
                        return $invoiceGenerator->downloadInvoice($record);
                    }),
                EditAction::make(),
                DeleteAction::make(),
               
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('primary')
                        ->action(function ($records) {
                            $generated = 0;
                            foreach ($records as $record) {
                                if (!$record->hasInvoice()) {
                                    try {
                                        $record->generateInvoice();
                                        $generated++;
                                    } catch (\Exception $e) {
                                        // Log error but continue with other records
                                        \Log::error('Failed to generate invoice for order ' . $record->id . ': ' . $e->getMessage());
                                    }
                                }
                            }
                           
                            Notification::make()
                                ->title('Bulk Invoice Generation')
                                ->body("Generated {$generated} invoices successfully.")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}