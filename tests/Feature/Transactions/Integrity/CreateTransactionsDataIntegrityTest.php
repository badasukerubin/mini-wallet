<?php

use App\Models\Transaction;
use App\Models\User;

it('rolls back the entire transaction if an error occurs mid-operation', function () {
    $sender = User::factory()->create(['balance' => '500.00']);
    $receiver = User::factory()->create(['balance' => '0.00']);

    // Cause a failure during Transaction::create() by throwing in the creating event
    Transaction::creating(function ($model) {
        throw new Exception('Simulated failure during transaction.');
    });

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), [
            'receiver_id' => $receiver->id,
            'amount' => '100.00',
        ])
        ->assertStatus(422);

    // Assert: no transaction persisted and balances unchanged
    $sender->refresh();
    $receiver->refresh();

    $this->assertDatabaseCount('transactions', 0);
    $this->assertSame('500.00', (string) $sender->balance);
    $this->assertSame('0.00', (string) $receiver->balance);

    // Clean up to avoid affecting other tests
    Transaction::flushEventListeners();
})->group('integrity');
