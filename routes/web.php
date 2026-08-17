<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    DashboardController,
    UnitBisnisController,
    KaryawanController,
    LaporanController,
    LeaderboardController,
    AuditLogController,
    KpiValidasiController
};

// ==========================================
// Guest routes (belum login)
// ==========================================
Route::middleware('guest.api')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// ==========================================
// Authenticated routes
// ==========================================
Route::middleware('auth.api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/skor-company', [DashboardController::class, 'skorCompany'])->name('skor-company');
    Route::post('/bobot-kategori', [DashboardController::class, 'updateBobotKategori'])->name('bobot-kategori.update');

    // Unit Bisnis & KPI
    Route::prefix('unit-bisnis')->name('unit-bisnis.')->group(function () {
    Route::get('/',              [UnitBisnisController::class, 'index'])->name('index');
    Route::post('/',             [UnitBisnisController::class, 'store'])->name('store');
    Route::get('/{id}',          [UnitBisnisController::class, 'show'])->name('show');
    Route::put('/{id}',          [UnitBisnisController::class, 'update'])->name('update');
    Route::delete('/{id}',       [UnitBisnisController::class, 'destroy'])->name('destroy');

    // KPI dalam unit bisnis
    Route::post('/{unitBisnisId}/kpi', [UnitBisnisController::class, 'tambahKpi'])->name('kpi.store');
    Route::post('/kpi/{kpiId}/target', [UnitBisnisController::class, 'setTarget'])->name('kpi.target');
    Route::delete('/kpi/{kpiId}',      [UnitBisnisController::class, 'hapusKpi'])->name('kpi.destroy');

    // BARU: route untuk "Kelola Form" — sebelumnya tidak terdaftar sama
    // sekali, sehingga submit modal Kelola Form selalu 404 / gagal.
    Route::put('/kpi/{kpiId}/form-template', [UnitBisnisController::class, 'updateFormTemplate'])->name('kpi.form-template.update');
});

    // Karyawan (User Management)
    Route::prefix('karyawan')->name('karyawan.')->group(function () {
        Route::get('/',              [KaryawanController::class, 'index'])->name('index');
        Route::post('/',             [KaryawanController::class, 'store'])->name('store');
        Route::put('/{id}',          [KaryawanController::class, 'update'])->name('update');
        Route::delete('/{id}',       [KaryawanController::class, 'destroy'])->name('destroy');
        Route::patch('/{id}/toggle', [KaryawanController::class, 'toggle'])->name('toggle');
    });

    // Laporan
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/',                  [LaporanController::class, 'index'])->name('index');
        Route::delete('/harian/{id}',    [LaporanController::class, 'destroyHarian'])->name('harian.destroy');
        Route::patch('/sop/{id}/review', [LaporanController::class, 'reviewSop'])->name('sop.review');
        Route::delete('/sop/{id}',       [LaporanController::class, 'destroySop'])->name('sop.destroy');
    });

    // Validasi Input KPI
    Route::prefix('kpi-validasi')->name('kpi-validasi.')->group(function () {
        Route::get('/',                    [KpiValidasiController::class, 'index'])->name('index');
        Route::put('/{id}/realisasi',      [KpiValidasiController::class, 'updateRealisasi'])->name('realisasi.update');
    });

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

    // Audit Log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});