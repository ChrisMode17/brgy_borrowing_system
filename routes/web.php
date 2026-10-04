<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManageEquipmentController;
use App\Http\Controllers\ManageUsersController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// ---- Guest / auth routes ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password/questions', [ForgotPasswordController::class, 'getQuestions'])
        ->middleware('throttle:5,1')
        ->name('password.questions');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('password.reset');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ---- Authenticated routes ----
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/transactions', [DashboardController::class, 'transactions'])->name('dashboard.transactions');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/data', [InventoryController::class, 'data'])->name('inventory.data');

    Route::get('/borrow', [BorrowController::class, 'create'])->name('borrow.create');
    Route::get('/borrow/return-issues', [BorrowController::class, 'returnIssues'])->name('borrow.return-issues');
    Route::post('/borrow', [BorrowController::class, 'store'])->name('borrow.store');

    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservation.index');
    Route::get('/reservations/data', [ReservationController::class, 'data'])->name('reservation.data');
    Route::post('/reservations/{refnum}/confirm', [ReservationController::class, 'confirm'])->name('reservation.confirm');
    Route::delete('/reservations/{refnum}', [ReservationController::class, 'cancel'])->name('reservation.cancel');

    Route::get('/returns', [ReturnController::class, 'index'])->name('return.index');
    Route::post('/returns/{refnum}/return-all', [ReturnController::class, 'returnAll'])->name('return.all');
    Route::post('/returns/{refnum}/partial', [ReturnController::class, 'partialReturn'])->name('return.partial');

    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit.index');
    Route::get('/audit-trail/export', [AuditTrailController::class, 'export'])->name('audit.export');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/settings/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/settings/password', [ProfileController::class, 'updatePassword'])->name('password.update');
    Route::put('/settings/security-questions', [ProfileController::class, 'updateSecurityQuestions'])->name('security-questions.update');

    // ---- Admin-only routes ----
    Route::middleware('admin')->group(function () {
        Route::get('/equipment', [ManageEquipmentController::class, 'index'])->name('equipment.index');
        Route::post('/equipment', [ManageEquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/bulk-template/csv', [ManageEquipmentController::class, 'downloadBulkCsvTemplate'])->name('equipment.bulk-template.csv');
        Route::get('/equipment/bulk-template/excel', [ManageEquipmentController::class, 'downloadBulkExcelTemplate'])->name('equipment.bulk-template.xlsx');
        Route::post('/equipment/bulk', [ManageEquipmentController::class, 'bulkStore'])->name('equipment.bulk-store');
        Route::put('/equipment/{inventory}', [ManageEquipmentController::class, 'update'])->name('equipment.update');
        Route::delete('/equipment/{inventory}', [ManageEquipmentController::class, 'destroy'])->name('equipment.destroy');
        Route::post('/equipment/{inventory}/fix', [ManageEquipmentController::class, 'fix'])->name('equipment.fix');

        Route::get('/users', [ManageUsersController::class, 'index'])->name('users.index');
        Route::post('/users', [ManageUsersController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [ManageUsersController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [ManageUsersController::class, 'destroy'])->name('users.destroy');

        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::get('/system/backup', [SystemController::class, 'downloadBackup'])->name('system.backup');
        Route::post('/system/restore', [SystemController::class, 'restoreBackup'])->name('system.restore');
        Route::post('/system/branding', [SystemController::class, 'updateBranding'])->name('system.branding');
        Route::post('/system/backgrounds', [SystemController::class, 'upload'])->name('system.upload');
        Route::post('/system/backgrounds/default', [SystemController::class, 'setDefault'])->name('system.set-default');
        Route::delete('/system/backgrounds/{backgroundImage}', [SystemController::class, 'destroy'])->name('system.destroy');
    });
});
