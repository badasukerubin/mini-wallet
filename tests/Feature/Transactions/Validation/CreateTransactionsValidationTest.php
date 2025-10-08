<?php

use App\Models\User;

it('requires receiver_id', function () {
    $sender = User::factory()->create(['balance' => '100.00']);

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), ['amount' => '10.00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['receiver_id']);
})->group('validation');

it('requires receiver to exist', function () {
    $sender = User::factory()->create(['balance' => '100.00']);

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), [
            'receiver_id' => 999999,
            'amount' => '10.00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['receiver_id']);
})->group('validation');

it('rejects self transfers', function () {
    $sender = User::factory()->create(['balance' => '100.00']);

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), [
            'receiver_id' => $sender->id,
            'amount' => '10.00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['receiver_id']);
})->group('validation');

it('validates amount format and minimum', function () {
    $sender = User::factory()->create(['balance' => '100.00']);
    $receiver = User::factory()->create();

    // zero amount
    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), ['receiver_id' => $receiver->id, 'amount' => '0'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    // negative amount
    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), ['receiver_id' => $receiver->id, 'amount' => '-5.00'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    // too many dps
    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), ['receiver_id' => $receiver->id, 'amount' => '1.234'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
})->group('validation');

it('fails when sender has insufficient balance (pre-check)', function () {
    $sender = User::factory()->create(['balance' => '50.00']); // insufficient to cover 50 + fee (51.5)
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('api.v1.transactions.create'), [
            'receiver_id' => $receiver->id,
            'amount' => '50.00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
})->group('validation');
