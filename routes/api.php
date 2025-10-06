<?php

use App\Http\Controllers\Transactions\CreateTransactionsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'as' => 'api.v1.', 'middleware' => ['auth', 'verified']], function () {
    Route::post('transactions', CreateTransactionsController::class)->name('transactions.create');
});
