<?php

use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('customers', CustomerController::class);
Route::apiResource('services', ServiceController::class);
Route::apiResource('orders', OrderController::class);
Route::get('/orders/{id}/ticket-recepcion', [OrderController::class, 'ticketRecepcion']);

Route::apiResource('payments', PaymentController::class);
Route::get('/payments/{payment}/ticket', [PaymentController::class, 'ticket']);

Route::patch('/orders/{id}/deliver', [OrderController::class, 'deliver', ]);

Route::get('/orders/{id}/ticket', [OrderController::class, 'receiptTicket', ]);

Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

Route::get('/orders/{id}/ticket-entrega', [OrderController::class, 'ticketEntrega']);

Route::get('/sales', [SalesController::class, 'index']);
Route::get('/sales/summary', [SalesController::class, 'summary']);

Route::get('/cash-register/current', [CashRegisterController::class, 'current']);
Route::get('/cash-register/history', [CashRegisterController::class, 'history']);

Route::post('/cash-register/open', [CashRegisterController::class, 'open']);
Route::post('/cash-register/movements', [CashRegisterController::class, 'movement']);
Route::post('/cash-register/close', [CashRegisterController::class, 'close']);
