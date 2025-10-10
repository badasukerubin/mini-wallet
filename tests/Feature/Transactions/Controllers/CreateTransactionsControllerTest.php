<?php

use App\Events\Transactions\TransactionCreated;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([TransactionCreated::class]);

});

it('creates a transaction successfully and broadcasts to both parties', function () {
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

    // Commission calculation: 0.5% of 100.00 = 0.50
    // Total debit = 100.00 + 1.50 = 101.50
    // New balance = 1000.00 - 101.50 = 898.50
    expect($sender->balance)->toBe('898.50');

    $receiver->refresh();
    expect($receiver->balance)->toBe('600.00');

    Event::assertDispatched(TransactionCreated::class, function ($event) use ($sender, $receiver) {
        $channels = collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all();

        // This event must be sent to both the sender and the receiver.
        expect($channels)->toContain('private-user.'.$sender->id);
        expect($channels)->toContain('private-user.'.$receiver->id);

        return $event->transaction->sender_id === $sender->id &&
            $event->transaction->receiver_id === $receiver->id &&
            $event->senderBalance === formatAmount((string) $sender->balance) &&
            $event->receiverBalance === formatAmount((string) $receiver->balance);
    });
});

it('fails to create a transaction due to insufficient balance and does not broadcast events', function () {
    $amountToSend = 200.00;

    $sender = User::factory()->create(['balance' => 100.00]);
    $receiver = User::factory()->create(['balance' => 500.00]);

    $response = $this->actingAs($sender)->postJson(route('api.v1.transactions.create'), [
        'receiver_id' => $receiver->id,
        'amount' => $amountToSend,
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Insufficient balance to cover amount plus commission (1.5%).',
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

    Event::assertNotDispatched(TransactionCreated::class);
});
