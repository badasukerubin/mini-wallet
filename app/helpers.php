<?php

use App\Models\Transaction;

if (! function_exists('calculateCommissionFee')) {
    function calculateCommissionFee(string $amount): string
    {
        // For every successful transfer, a commission of 1.5% of the transferred amount must be charged.
        $feePercentage = 0.015;

        $fee = bcmul($amount, (string) $feePercentage, Transaction::DECIMAL_PLACES);

        return number_format((float) $fee, Transaction::DECIMAL_PLACES, '.', '');
    }
}
