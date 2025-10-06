<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\CreateTransactionsRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTransactionsController extends Controller
{
    public function __invoke(CreateTransactionsRequest $request)
    {

        $sender = $request->user();
        $receiverId = (int) $request->receiver_id;
        $amount = number_format((float) $request->amount, Transaction::DECIMAL_PLACES, '.', '');

        $commission = calculateCommissionFee($amount);
        $totalDebit = bcadd($amount, $commission, Transaction::DECIMAL_PLACES);

        $ids = [$sender->id, $receiverId];
        sort($ids, SORT_NUMERIC);

        $transaction = DB::transaction(function () use ($ids, $sender, $receiverId, $amount, $commission, $totalDebit) {
            $users = User::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if (! isset($users[$sender->id])) {
                abort(404, 'Sender not found.');
            }

            if (! isset($users[$receiverId])) {
                abort(404, 'Receiver not found.');
            }

            $senderRow = $users[$sender->id];
            $receiverRow = $users[$receiverId];

            // Ensure sender has sufficient balance
            if (bccomp((string) $senderRow->balance, $totalDebit, Transaction::DECIMAL_PLACES) < 0) {
                abort(422, 'Insufficient balance.');
            }

            // Update balances
            $senderRow->balance = bcsub((string) $senderRow->balance, $totalDebit, Transaction::DECIMAL_PLACES);
            $receiverRow->balance = bcadd((string) $receiverRow->balance, $amount, Transaction::DECIMAL_PLACES);

            $senderRow->save();
            $receiverRow->save();

            // Create transaction record
            $transaction = Transaction::create([
                'sender_id' => $senderRow->id,
                'receiver_id' => $receiverRow->id,
                'amount' => $amount,
                'commission_fee' => $commission,
                'metadata' => null,
            ]);

            return [
                'transaction' => $transaction,
                'sender_balance' => (string) $senderRow->balance,
                'receiver_balance' => (string) $receiverRow->balance,
            ];
        });

        return response()->json([
            'data' => $transaction['transaction'],
        ], 201);

    }
}
