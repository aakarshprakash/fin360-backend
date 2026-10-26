<?php

namespace App\Helpers;

class NumberToWords
{
    private static $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private static $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** Converts a rupee amount into words, Indian numbering (Lakh/Crore). */
    public static function convert($num): string
    {
        $num = (float) $num;
        if (!$num) {
            return 'Zero Rupees Only';
        }

        return self::convertInteger((int) round(abs($num))) . ' Rupees Only';
    }

    private static function convertInteger(int $n): string
    {
        if ($n < 20) {
            return self::$ones[$n];
        }
        if ($n < 100) {
            return self::$tens[intdiv($n, 10)] . ($n % 10 ? ' ' . self::$ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return self::$ones[intdiv($n, 100)] . ' Hundred' . ($n % 100 ? ' and ' . self::convertInteger($n % 100) : '');
        }
        if ($n < 100000) {
            return self::convertInteger(intdiv($n, 1000)) . ' Thousand' . ($n % 1000 ? ' ' . self::convertInteger($n % 1000) : '');
        }
        if ($n < 10000000) {
            return self::convertInteger(intdiv($n, 100000)) . ' Lakh' . ($n % 100000 ? ' ' . self::convertInteger($n % 100000) : '');
        }

        return self::convertInteger(intdiv($n, 10000000)) . ' Crore' . ($n % 10000000 ? ' ' . self::convertInteger($n % 10000000) : '');
    }
}
