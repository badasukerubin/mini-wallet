<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('returns the balance from the users table and does not aggregate transactions', function () {
    $user = User::factory()->create(['balance' => '123.45']);

    // create many transactions that would lead to a different sum if the API aggregated transactions
    Transaction::factory()->count(200)->create([
        'sender_id' => $user->id,
        'receiver_id' => User::factory()->create()->id,
        'amount' => '1.00',
        'commission_fee' => '0.02',
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->actingAs($user)
        ->getJson(route('api.v1.transactions.index'))
        ->assertStatus(200)
        ->assertJsonPath('data.balance', '123.45'); // must come from users.balance

    // Ensure no SUM() aggregation is used to compute the balance
    foreach ($queries as $sql) {
        expect(stripos($sql, 'sum(') === false)->toBeTrue();
    }
})->group('scale');

it('uses row-level locks and updates user balances directly when creating a transaction (no transactions aggregation)', function () {
    $sender = User::factory()->create(['balance' => '1000.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), [
            'receiver_id' => $receiver->id,
            'amount' => '100.00',
        ])
        ->assertStatus(201);

    // Basic expectations about queries executed:
    // - there should be at least one SELECT ... FOR UPDATE on users
    // - there should be UPDATE statements on users
    // - there should be an INSERT into transactions
    $joined = implode("\n", $queries);

    expect(stripos($joined, 'for update') !== false)->toBeTrue();
    expect(stripos($joined, 'update "users"') !== false || stripos($joined, 'update users') !== false)->toBeTrue();
    expect(stripos($joined, 'insert into "transactions"') !== false || stripos($joined, 'insert into transactions') !== false)->toBeTrue();

    // Ensure no SUM() aggregation on transactions is used as part of the transfer logic
    foreach ($queries as $sql) {
        expect(stripos($sql, 'sum(') === false)->toBeTrue();
    }

    // Confirm balances updated in users table (no reliance on transactions aggregation)
    $sender->refresh();
    $receiver->refresh();

    expect((string) $sender->balance)->toBe('898.50');
    expect((string) $receiver->balance)->toBe('100.00');
})->group('scale');
