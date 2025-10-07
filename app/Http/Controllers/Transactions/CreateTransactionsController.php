<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\CreateTransactionsRequest;
use App\Services\Transactions\TransactionsService;
use Exception;

class CreateTransactionsController extends Controller
{
    public function __construct(private TransactionsService $transactionService) {}

    public function __invoke(CreateTransactionsRequest $request)
    {

        $sender = $request->user();

        try {
            $transaction = $this->transactionService->handle($sender, (int) $request->receiver_id, (string) $request->amount);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transaction], 201);

    }
}
