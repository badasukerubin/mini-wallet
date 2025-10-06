<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\CreateTransactionsRequest;
use App\Services\TransferService;

class CreateTransactionsController extends Controller
{
    public function __construct(private TransferService $transfer) {}

    public function __invoke(CreateTransactionsRequest $request)
    {

        $sender = $request->user();

        try {
            $transaction = $this->transfer->handle($sender, (int) $request->receiver_id, (string) $request->amount);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transaction], 201);

    }
}
