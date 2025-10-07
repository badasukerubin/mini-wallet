<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Http\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;

class GetTransactionsController extends Controller
{
    public function __invoke(Request $request)
    {

        $user = $request->user();

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        // fetch transactions where the user is sender or receiver
        $query = Transaction::query()
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
            ->with(['sender:id,name', 'receiver:id,name'])
            ->orderByDesc('created_at');

        $paginated = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => [
                'balance' => formatAmount((string) $user->balance),
                'transactions' => TransactionResource::collection($paginated),
                'meta' => [
                    'pagination' => [
                        'total' => $paginated->total(),
                        'per_page' => $paginated->perPage(),
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                    ],
                ],
            ],
        ]);

    }
}
