<?php

use App\Http\Controllers\Transactions\CreateTransactionsController;
use App\Http\Controllers\Transactions\GetTransactionsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'as' => 'api.v1.', 'middleware' => ['auth', 'verified']], function () {
    Route::get('transactions', GetTransactionsController::class)->name('transactions.index');
    Route::post('transactions', CreateTransactionsController::class)->name('transactions.create');
});
