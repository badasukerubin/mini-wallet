<?php

use App\Http\Controllers\Transactions\CreateTransactionsController;
use App\Http\Controllers\Transactions\GetTransactionsController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'as' => 'api.v1.', 'middleware' => ['auth:sanctum', 'verified']], function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('transactions', GetTransactionsController::class)->name('transactions.index');
    Route::post('transactions', CreateTransactionsController::class)->name('transactions.create')->middleware([HandlePrecognitiveRequests::class]);
});
