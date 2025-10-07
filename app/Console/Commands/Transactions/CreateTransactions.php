<?php

namespace App\Console\Commands\Transactions;

use App\Models\User;
use App\Services\Transactions\TransactionsService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-transactions {--sender_id= : Sender User ID} {--receiver_id= : Receiver User ID} {--amount= : Amount to transact}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a transaction between two users (Used for concurrency testing)';

    public function __construct(private TransactionsService $transactionsService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $senderId = (int) $this->option('sender_id');
            $receiverId = (int) $this->option('receiver_id');
            $amount = (string) $this->option('amount');

            if ($senderId <= 0 || $receiverId <= 0 || ! is_numeric($amount) || (float) $amount <= 0) {
                $this->error('Invalid parameters. Please provide valid sender_id, receiver_id, and amount.');

                return Command::FAILURE;
            }

            $transaction = $this->transactionsService->handle(
                sender: User::findOrFail($senderId),
                receiverId: $receiverId,
                amount: $amount
            );

            // For demonstration purposes, we'll just print the details
            $this->info("Transaction successful: Trnsaction ID: {$transaction->id}, Sender ID: {$senderId}, Receiver ID: {$receiverId}, Amount: {$amount}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Transaction failed: '.$e->getMessage());
            Log::error('Transaction failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
