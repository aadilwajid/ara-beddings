<?php
declare(strict_types=1);

/** Configurable Pakistan shipping. Modes stored in settings table. */
class Shipping
{
    /** Compute shipping cost server-side from the post-discount subtotal. */
    public static function cost(float $subtotal, ?string $city = null, ?string $province = null): float
    {
        $mode = setting('shipping_mode', 'flat');

        // Free-shipping threshold applies in every mode once exceeded
        $threshold = (float)setting('free_shipping_threshold', '0');
        if ($threshold > 0 && $subtotal >= $threshold) return 0.0;

        switch ($mode) {
            case 'free':
                return 0.0;
            case 'city':
                if ($city) {
                    $rates = json_decode((string)setting('shipping_city_rates', '{}'), true) ?: [];
                    foreach ($rates as $k => $v) {
                        if (mb_strtolower(trim($k)) === mb_strtolower(trim($city))) return (float)$v;
                    }
                }
                return (float)setting('shipping_flat_cost', '0');
            case 'province':
                if ($province) {
                    $rates = json_decode((string)setting('shipping_province_rates', '{}'), true) ?: [];
                    foreach ($rates as $k => $v) {
                        if (mb_strtolower(trim($k)) === mb_strtolower(trim($province))) return (float)$v;
                    }
                }
                return (float)setting('shipping_flat_cost', '0');
            case 'flat':
            default:
                return (float)setting('shipping_flat_cost', '0');
        }
    }

    public static function methodsEnabled(): array
    {
        $out = [];
        if (setting('cod_enabled', '0') === '1')          $out['cod'] = 'Cash on Delivery';
        if (setting('bank_transfer_enabled', '0') === '1') $out['bank_transfer'] = 'Bank Transfer';
        if (setting('easypaisa_enabled', '0') === '1')     $out['easypaisa'] = 'Easypaisa';
        if (setting('jazzcash_enabled', '0') === '1')      $out['jazzcash'] = 'JazzCash';
        return $out;
    }

    /** Payment instructions shown after placing a manual-payment order. */
    public static function paymentInstructions(string $method, float $amount): string
    {
        switch ($method) {
            case 'bank_transfer':
                return 'Transfer ' . money($amount) . ' to our bank account:'
                     . '<br>Account Title: ' . e(setting('bank_account_title'))
                     . '<br>Bank: ' . e(setting('bank_name'))
                     . '<br>Account No: ' . e(setting('bank_account_number'))
                     . '<br>IBAN: ' . e(setting('bank_iban'))
                     . '<br><em>Share the transaction receipt via WhatsApp for faster confirmation.</em>';
            case 'easypaisa':
                return 'Send ' . money($amount) . ' to Easypaisa account:<br>'
                     . e(setting('easypaisa_title')) . ' — ' . e(setting('easypaisa_number'))
                     . '<br><em>Share the transaction ID via WhatsApp for faster confirmation.</em>';
            case 'jazzcash':
                return 'Send ' . money($amount) . ' to JazzCash account:<br>'
                     . e(setting('jazzcash_title')) . ' — ' . e(setting('jazzcash_number'))
                     . '<br><em>Share the transaction ID via WhatsApp for faster confirmation.</em>';
            case 'cod':
            default:
                return 'Please keep ' . money($amount) . ' ready when the rider arrives. Pay at your doorstep.';
        }
    }
}
