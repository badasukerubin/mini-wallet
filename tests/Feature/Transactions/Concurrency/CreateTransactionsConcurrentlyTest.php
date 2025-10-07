<?php

uses(Illuminate\Foundation\Testing\DatabaseMigrations::class);

use App\Models\Transaction;
use App\Models\User;
use Symfony\Component\Process\Process;

it('creates a transaction successfully', function () {
    $sender = User::factory()->create(['balance' => '1000.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    $concurrentProcesses = 10;
    $amountPerTransaction = '10.00';

    $processes = [];

    for ($i = 0; $i < $concurrentProcesses; $i++) {
        $process = new Process([
            PHP_BINARY,
            base_path('artisan'),
            'app:create-transactions',
            '--sender_id='.$sender->id,
            '--receiver_id='.$receiver->id,
            '--amount='.$amountPerTransaction,
        ]);

        $process->setEnv(array_merge($_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => env('DB_CONNECTION'),
            'DB_HOST' => env('DB_HOST'),
            'DB_PORT' => env('DB_PORT'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'DB_USERNAME' => env('DB_USERNAME'),
            'DB_PASSWORD' => env('DB_PASSWORD'),
        ]));

        $process->start();
        $processes[] = $process;
    }

    foreach ($processes as $process) {
        $process->wait();

        expect($process->isSuccessful())->toBeTrue();
    }

    $sender->refresh();
    $receiver->refresh();

    $commissionFeePerTransaction = calculateCommissionFee($amountPerTransaction);
    $debitPerTransaction = bcadd($amountPerTransaction, $commissionFeePerTransaction, Transaction::DECIMAL_PLACES);
    $totalDebit = bcmul($debitPerTransaction, (string) $concurrentProcesses, Transaction::DECIMAL_PLACES);

    expect($sender->balance)->toBe(bcsub('1000.00', $totalDebit, Transaction::DECIMAL_PLACES));
    expect($receiver->balance)->toBe(bcmul($amountPerTransaction, (string) $concurrentProcesses, Transaction::DECIMAL_PLACES));

    $this->assertDatabaseCount('transactions', $concurrentProcesses);
})->only();
