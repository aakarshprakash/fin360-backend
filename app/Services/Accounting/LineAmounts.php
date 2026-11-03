<?php

namespace App\Services\Accounting;

/**
 * The amounts on one invoice / quotation / proforma line, each rounded to
 * paise, so the lines always add up exactly to the document's totals:
 * subtotal = sum of gross, discount = sum of discount, tax = sum of tax,
 * total = subtotal - discount + tax. A discount can't exceed the line's value.
 * Mirrored by lineAmounts() in the web client.
 */
class LineAmounts
{
    /** @return array{gross: float, discount: float, net: float, tax: float, total: float} */
    public static function of(array $item): array
    {
        $gross    = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
        $discount = min(round((float) ($item['discount'] ?? 0), 2), $gross);
        $net      = round($gross - $discount, 2);
        $tax      = round($net * ((float) ($item['tax_rate'] ?? 0)) / 100, 2);

        return [
            'gross'    => $gross,
            'discount' => $discount,
            'net'      => $net,
            'tax'      => $tax,
            'total'    => round($net + $tax, 2),
        ];
    }
}
