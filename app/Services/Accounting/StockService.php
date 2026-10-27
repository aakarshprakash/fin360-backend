<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that changes Product.stock_quantity. Every change writes a
 * movement row with the resulting balance, so the history always adds up.
 */
class StockService
{
    /**
     * Apply a signed quantity change to a tracked product.
     * $allowNegative: sales may oversell (stock is corrected later);
     * manual stock-out may not.
     */
    public function move(Product $product, string $type, float $change, array $attrs = [], bool $allowNegative = true): ?ProductStockMovement
    {
        if (abs($change) < 0.0005) {
            return null;
        }

        return DB::transaction(function () use ($product, $type, $change, $attrs, $allowNegative) {
            $locked  = Product::whereKey($product->id)->lockForUpdate()->first();
            $balance = round((float) $locked->stock_quantity + $change, 3);

            if (!$allowNegative && $balance < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Only ' . rtrim(rtrim(number_format((float) $locked->stock_quantity, 3, '.', ''), '0'), '.')
                        . ' ' . ($locked->unit ?: 'units') . ' in stock.',
                ]);
            }

            $locked->forceFill(['stock_quantity' => $balance])->save();
            $product->setRawAttributes($locked->getAttributes(), true);

            return ProductStockMovement::create([
                'product_id'    => $locked->id,
                'type'          => $type,
                'quantity'      => round($change, 3),
                'balance_after' => $balance,
                'date'          => $attrs['date'] ?? now()->toDateString(),
                'reference'     => $attrs['reference'] ?? null,
                'note'          => $attrs['note'] ?? null,
                'invoice_id'    => $attrs['invoice_id'] ?? null,
                'user_id'       => Auth::id(),
            ]);
        });
    }

    /** Deduct stock for the tracked products on a new invoice */
    public function deductForInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('items');
        $qtyByProduct = [];
        foreach ($invoice->items as $item) {
            if ($item->product_id) {
                $qtyByProduct[$item->product_id] = ($qtyByProduct[$item->product_id] ?? 0) + (float) $item->quantity;
            }
        }
        if (!$qtyByProduct) {
            return;
        }

        $products = Product::whereIn('id', array_keys($qtyByProduct))->where('track_stock', true)->get();
        foreach ($products as $product) {
            $this->move($product, 'invoice', -$qtyByProduct[$product->id], [
                'date'       => optional($invoice->date)->toDateString(),
                'reference'  => $invoice->invoice_number,
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    /** Put back whatever an invoice took out (when the invoice is deleted) */
    public function restoreForInvoice(Invoice $invoice): void
    {
        $net = ProductStockMovement::where('invoice_id', $invoice->id)
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        foreach ($net as $productId => $qty) {
            $product = Product::withTrashed()->find($productId);
            if ($product && (float) $qty < 0) {
                $this->move($product, 'invoice_reversal', -(float) $qty, [
                    'reference'  => $invoice->invoice_number,
                    'note'       => 'Invoice deleted',
                    'invoice_id' => $invoice->id,
                ]);
            }
        }
    }
}
