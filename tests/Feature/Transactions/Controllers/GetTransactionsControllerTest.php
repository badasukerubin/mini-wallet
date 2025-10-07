<?php

use App\Models\Transaction;
use App\Models\User;

it('returns the auth user transactions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Create transactions for the auth user
    Transaction::factory()->count(3)->create([
        'sender_id' => $user->id,
        'receiver_id' => $otherUser->id,
    ]);

    // Create transactions for the other user
    Transaction::factory()->count(2)->create([
        'sender_id' => $otherUser->id,
        'receiver_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->getJson(route('api.v1.transactions.index'));

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                'balance',
                'transactions' => [
                    '*' => [
                        'id',
                        'amount',
                        'commission_fee',
                        'metadata',
                        'sender_id',
                        'receiver_id',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'last_page',
                    ],
                ],
            ],

        ]);
});
