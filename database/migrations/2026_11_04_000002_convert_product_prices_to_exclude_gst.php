<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Data correction. Product prices were entered with 5% GST already in them
 * (650000 meant 650000 including GST), but invoices and quotations treat a
 * product's price as before GST and add the tax on top, so 650000 came out
 * as 682500.
 *
 * Every product is set to 5% GST and its price becomes the price before GST:
 * price / 1.05, to the paisa (650000 -> 619047.62, which shows as 650000.00
 * again once GST is added). A few products come back a paisa off when GST is
 * added again; that can't be avoided with prices stored before GST.
 *
 * Documents that are already saved are not touched. The old prices and rates
 * are saved first in storage/app/backups/product-prices-before-gst-correction.csv,
 * and down() puts them back from that file.
 *
 * Migrations run once, so this cannot divide the prices twice.
 */
return new class extends Migration
{
    private const RATE = 5;
    private const BACKUP = 'backups/product-prices-before-gst-correction.csv';

    public function up()
    {
        $products = DB::table('products')->orderBy('id')->get(['id', 'name', 'unit_price', 'tax_rate']);
        if ($products->isEmpty()) {
            return;
        }

        // Keep the old figures first, so the change can be undone
        $csv = "id,name,unit_price,tax_rate\n";
        foreach ($products as $p) {
            $csv .= $p->id . ',"' . str_replace('"', '""', $p->name) . '",' . $p->unit_price . ',' . $p->tax_rate . "\n";
        }
        $path = storage_path('app/' . self::BACKUP);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $csv);

        DB::transaction(function () use ($products) {
            foreach ($products as $p) {
                DB::table('products')->where('id', $p->id)->update([
                    'unit_price' => round((float) $p->unit_price / (1 + self::RATE / 100), 2),
                    'tax_rate'   => self::RATE,
                ]);
            }
        });
    }

    public function down()
    {
        $path = storage_path('app/' . self::BACKUP);
        if (!File::exists($path)) {
            return;
        }

        $lines = array_slice(preg_split('/\r?\n/', trim(File::get($path))), 1);
        DB::transaction(function () use ($lines) {
            foreach ($lines as $line) {
                // id,"name",unit_price,tax_rate: the name may hold commas, the rest are plain numbers
                if (!preg_match('/^(\d+),".*",([\d.]+),([\d.]+)$/s', $line, $m)) {
                    continue;
                }
                DB::table('products')->where('id', $m[1])->update(['unit_price' => $m[2], 'tax_rate' => $m[3]]);
            }
        });
    }
};
