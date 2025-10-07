<?php

use App\Models\Transaction;

if (! function_exists('formatAmount')) {
    function formatAmount(string $amount): string
    {
        return number_format((float) $amount, Transaction::DECIMAL_PLACES, '.', '');
    }
}

if (! function_exists('calculateCommissionFee')) {
    function calculateCommissionFee(string $amount): string
    {
        // For every successful transfer, a commission of 1.5% of the transferred amount must be charged.
        $feePercentage = 0.015;

        $fee = bcmul($amount, (string) $feePercentage, Transaction::DECIMAL_PLACES);

        return formatAmount($fee);
    }
}
