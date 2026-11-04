<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ServiceController;

// ==========================================
// RUTAS PÚBLICAS
// ==========================================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// ==========================================
// RUTAS PROTEGIDAS
// REQUIEREN INICIAR SESIÓN
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // AUTENTICACIÓN
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);


    // ================== CLIENTES ==========================
    Route::apiResource('customers', CustomerController::class);

    // ================= CATÁLOGO DE SERVICIOS =======================
    Route::apiResource('services', ServiceController::class);

    // =================== ÓRDENES ============================
    Route::apiResource('orders', OrderController::class);
    Route::get('/orders/{id}/ticket-recepcion', [OrderController::class, 'ticketRecepcion']);
    Route::patch('/orders/{id}/deliver', [OrderController::class, 'deliver']);
    Route::get('/orders/{id}/ticket', [OrderController::class, 'receiptTicket']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::get('/orders/{id}/ticket-entrega', [ OrderController::class, 'ticketEntrega']);

    // =================  PAGOS ======================
    Route::apiResource('payments', PaymentController::class);
    Route::get('/payments/{payment}/ticket', [ PaymentController::class, 'ticket']);

    // ============= VENTAS =======================
    Route::get('/sales', [SalesController::class, 'index']);
    Route::get('/sales/summary', [SalesController::class, 'summary']);

    // ============== CAJA =======================
    Route::get('/cash-register/current', [CashRegisterController::class, 'current']);
    Route::get('/cash-register/history', [CashRegisterController::class, 'history']);
    Route::post('/cash-register/open', [CashRegisterController::class, 'open']);
    Route::post('/cash-register/movements', [CashRegisterController::class, 'movement']);
    Route::post('/cash-register/close', [CashRegisterController::class, 'close']);

    // ======================================
    // ADMINISTRACIÓN DE USUARIOS
    // SOLO ADMINISTRADOR
    // ======================================
    Route::middleware('can:admin')->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
        Route::patch('/users/{user}/toggle-active', [AuthController::class, 'toggleActive']);
    });
});