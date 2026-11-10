<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentExceptionController;
use App\Http\Controllers\Api\TrackingEventController;
use Illuminate\Support\Facades\Route;

// Endpoint publik. Registrasi publik hanya membuat role customer.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Ketiga role boleh melihat daftar; customer otomatis dibatasi ke shipment miliknya.
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::get('/shipments/{shipment}/tracking-events', [TrackingEventController::class, 'index']);
    Route::get('/shipments/{shipment}/exceptions', [ShipmentExceptionController::class, 'index']);

    // Operator mengelola operasional shipment. Admin dan customer bersifat read-only di bagian ini.
    Route::middleware('role:operator')->group(function () {
        Route::post('/shipments', [ShipmentController::class, 'store']);
        Route::put('/shipments/{shipment}', [ShipmentController::class, 'update']);
        Route::patch('/shipments/{shipment}', [ShipmentController::class, 'update']);
        Route::delete('/shipments/{shipment}', [ShipmentController::class, 'destroy']);
        Route::patch('/shipments/{shipment}/status', [ShipmentController::class, 'transition']);
        Route::patch('/shipments/{shipment}/eta', [ShipmentController::class, 'updateEta']);
        Route::post('/shipments/{shipment}/tracking-events', [TrackingEventController::class, 'store']);
        Route::post('/shipments/{shipment}/exceptions', [ShipmentExceptionController::class, 'store']);
        Route::patch('/exceptions/{exception}/status', [ShipmentExceptionController::class, 'updateStatus']);
    });

    // Ketiga role boleh membaca katalog carrier dan lokasi untuk kebutuhan shipment/tracking.
    Route::get('/carriers', [CarrierController::class, 'index']);
    Route::get('/carriers/{carrier}', [CarrierController::class, 'show']);
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations/{location}', [LocationController::class, 'show']);
    Route::get('/locations/geocode/search', [LocationController::class, 'geocode'])->middleware('throttle:10,1');

    // Fitur konfigurasi dan audit khusus administrator.
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('carriers', CarrierController::class)->except(['index', 'show']);
        Route::apiResource('locations', LocationController::class)->except(['index', 'show']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::patch('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });

    // Setiap pengguna hanya melihat dan mengubah notifikasinya sendiri.
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
