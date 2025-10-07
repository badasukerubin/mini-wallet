<?php

use App\Models\Transaction;
use App\Models\User;

it('creates a transaction successfully', function () {
    $sender = User::factory()->create(['balance' => '1000.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    $concurrentProcesses = 10;
    $amountPerTransaction = '10.00';

    $processes = [];

    for ($i = 0; $i < $concurrentProcesses; $i++) {
        // spawn multiple processes to simulate concurrency
        $processes[] = spawnTransferProcess($sender->id, $receiver->id, $amountPerTransaction);
    }

    $results = waitAllProcesses($processes);

    $successCount = collect($results)->where('success', true)->count();
    $failCount = collect($results)->where('success', false)->count();

    $sender->refresh();
    $receiver->refresh();

    $transactionsCount = Transaction::where('sender_id', $sender->id)->count();

    $commissionFeePerTransaction = calculateCommissionFee($amountPerTransaction);
    $debitPerTransaction = bcadd($amountPerTransaction, $commissionFeePerTransaction, Transaction::DECIMAL_PLACES);
    $totalDebit = bcmul($debitPerTransaction, (string) $concurrentProcesses, Transaction::DECIMAL_PLACES);

    expect($sender->balance)->toBe(bcsub('1000.00', $totalDebit, Transaction::DECIMAL_PLACES));
    expect($receiver->balance)->toBe(bcmul($amountPerTransaction, (string) $concurrentProcesses, Transaction::DECIMAL_PLACES));
    expect($transactionsCount)->toBe($successCount);
    expect($failCount)->toBe(0);

    $this->assertDatabaseCount('transactions', $concurrentProcesses);
})->group('concurrency');

it('does not allow overdraft, some concurrent transfers must fail when funds insufficient', function () {
    $initialBalance = '315.00';
    $sender = User::factory()->create(['balance' => $initialBalance]);
    $receiver = User::factory()->create(['balance' => '0.00']);

    $attempts = 8;
    $amountPerTransaction = '100.00';

    $processes = [];
    for ($i = 0; $i < $attempts; $i++) {
        $processes[] = spawnTransferProcess($sender->id, $receiver->id, $amountPerTransaction);
    }

    $results = waitAllProcesses($processes);

    $successCount = collect($results)->where('success', true)->count();

    $sender->refresh();
    $receiver->refresh();

    $transactions = Transaction::where('sender_id', $sender->id)->get();

    expect((float) $sender->balance)->toBeGreaterThanOrEqual(0.0);

    // Sum of all transaction amounts credited to receiver equals DB sum of incoming amounts
    $totalDebit = $transactions->reduce(function ($carry, $transaction) {
        return bcadd($carry, bcadd((string) $transaction->amount, (string) $transaction->commission_fee, Transaction::DECIMAL_PLACES), Transaction::DECIMAL_PLACES);
    }, '0.00');

    expect(bccomp($totalDebit, $initialBalance, Transaction::DECIMAL_PLACES))->toBeLessThan(0);

    expect((int) $transactions->count())->toBe($successCount);
})->group('concurrency');

it('handles many senders concurrently sending to a single receiver', function () {
    $receiver = User::factory()->create(['balance' => '0.00']);
    $senders = User::factory()->count(8)->create(['balance' => '200.00'])->all();

    $amountPerTransaction = '20.00';
    $processes = [];

    foreach ($senders as $sender) {
        // each sender will attempt 5 transfers concurrently
        for ($i = 0; $i < 5; $i++) {
            $processes[] = spawnTransferProcess($sender->id, $receiver->id, $amountPerTransaction);
        }
    }

    $results = waitAllProcesses($processes);

    $successes = collect($results)->where('success', true)->count();

    // Validate no negative balances and ledger sum equals expected
    foreach ($senders as $sender) {
        $sender->refresh();
        expect((float) $sender->balance)->toBeGreaterThanOrEqual(0.0);
    }

    $receiver->refresh();

    // Sum of all transaction amounts credited to receiver equals DB sum of incoming amounts
    $incoming = Transaction::where('receiver_id', $receiver->id)->get();
    $sumIncoming = $incoming->reduce(fn ($carry, $transaction) => bcadd($carry, (string) $transaction->amount, Transaction::DECIMAL_PLACES), '0.00');

    expect((string) $receiver->balance)->toBe($sumIncoming);
    expect($incoming->count())->toBe($successes);
})->group('concurrency');
