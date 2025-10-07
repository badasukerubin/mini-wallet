<?php

namespace App\Services\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionsService
{
    public function handle(User $sender, int $receiverId, string $amount): Transaction
    {
        $amount = formatAmount($amount);
        $commission = bcmul($amount, '0.015', Transaction::DECIMAL_PLACES);
        $totalDebit = bcadd($amount, $commission, Transaction::DECIMAL_PLACES);

        $ids = [$sender->id, $receiverId];
        sort($ids, SORT_NUMERIC);

        $result = DB::transaction(function () use ($ids, $sender, $receiverId, $amount, $commission, $totalDebit) {
            $users = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if (! isset($users[$sender->id]) || ! isset($users[$receiverId])) {
                throw new RuntimeException('Sender or receiver not found.');
            }

            $senderRow = $users[$sender->id];
            $receiverRow = $users[$receiverId];

            if (bccomp((string) $senderRow->balance, $totalDebit, Transaction::DECIMAL_PLACES) < 0) {
                throw new RuntimeException('Insufficient balance.');
            }

            $senderRow->balance = bcsub((string) $senderRow->balance, $totalDebit, Transaction::DECIMAL_PLACES);
            $receiverRow->balance = bcadd((string) $receiverRow->balance, $amount, Transaction::DECIMAL_PLACES);

            $senderRow->save();
            $receiverRow->save();

            return Transaction::create([
                'sender_id' => $senderRow->id,
                'receiver_id' => $receiverRow->id,
                'amount' => $amount,
                'commission_fee' => $commission,
            ]);
        });

        return $result;
    }
}
