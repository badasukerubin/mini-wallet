<?php

use App\Models\User;

it('creates a transaction successfully', function () {
    $amountToSend = 100.00;

    $sender = User::factory()->create(['balance' => 1000.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $response = $this->actingAs($sender)->postJson(route('api.v1.transactions.create'), [
        'receiver_id' => $receiver->id,
        'amount' => $amountToSend,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id',
                'sender_id',
                'receiver_id',
                'amount',
                'commission_fee',
                'created_at',
            ],
        ]);

    $this->assertDatabaseHas('transactions', [
        'amount' => 100.00,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $sender->refresh();
    expect($sender->balance)->toBe('898.50');

    $receiver->refresh();
    expect($receiver->balance)->toBe('600.00');
});

it('fails to create a transaction due to insufficient balance', function () {
    $amountToSend = 200.00;

    $sender = User::factory()->create(['balance' => 100.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $response = $this->actingAs($sender)->postJson(route('api.v1.transactions.create'), [
        'receiver_id' => $receiver->id,
        'amount' => $amountToSend,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Insufficient balance.',
        ]);

    $this->assertDatabaseMissing('transactions', [
        'amount' => 200.00,
        'sender_id' => $sender->id,
        'receiver_id' => $receiver->id,
    ]);

    $sender->refresh();
    expect($sender->balance)->toBe('100.00');

    $receiver->refresh();
    expect($receiver->balance)->toBe('500.00');
});
