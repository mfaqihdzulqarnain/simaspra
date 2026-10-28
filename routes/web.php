<?php

use App\Exports\AssetTemplateExport;
use App\Exports\UserTemplateExport;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KirController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\PublicAssetController;
use App\Http\Controllers\PublicRoomController;
use App\Http\Controllers\RentalReceiptController;
use App\Http\Controllers\RekapLabController;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cek', [HomeController::class, 'check'])->name('asset.check');
Route::post('/cek', [HomeController::class, 'redirectToAsset'])->name('asset.check.submit');

Route::get('/cetak/kir/{room}', [KirController::class, 'print'])->name('kir.print');

// Rekap peminjaman alat per laboratorium: ?lab=1&periode=3&bulan=2026-09
Route::get('/cetak/rekap-lab', [RekapLabController::class, 'print'])->name('rekap.lab.print');

// Halaman QR ruang: identitas dan jadwal pemakaian, tanpa login
Route::get('/r/{code}', [PublicRoomController::class, 'show'])
    ->name('room.public')
    ->middleware('throttle:60,1');

Route::get('/cetak/qr-ruang/{room}', [PublicRoomController::class, 'sign'])->name('room.qr.print');

// Tagihan dan kuitansi sewa ruang pihak luar
Route::get('/cetak/sewa/{roomBooking}/tagihan', [RentalReceiptController::class, 'invoice'])
    ->whereNumber('roomBooking')
    ->name('rental.invoice');

Route::get('/cetak/sewa/{roomBooking}/kuitansi', [RentalReceiptController::class, 'receipt'])
    ->whereNumber('roomBooking')
    ->name('rental.receipt');

Route::get('/templat/aset', function () {
    abort_unless(auth()->check(), 401);
    abort_unless(auth()->user()->hasAnyRole(['kabag_sarpras', 'super_admin']), 403);

    return Excel::download(
        new AssetTemplateExport,
        'templat-impor-aset.xlsx'
    );
})->name('asset.template');

Route::get('/a/{code}', [PublicAssetController::class, 'show'])
    ->name('asset.public')
    ->middleware('throttle:60,1');

Route::post('/a/{code}/opname', [PublicAssetController::class, 'recordOpname'])
    ->name('asset.public.opname')
    ->middleware(['auth', 'throttle:120,1']);

Route::post('/a/{code}/lapor', [PublicAssetController::class, 'report'])
    ->name('asset.public.report')
    ->middleware('throttle:5,60');

Route::get('/cetak/label/{asset}', [LabelController::class, 'single'])
    ->name('label.single');

Route::get('/cetak/label', [LabelController::class, 'batch'])
    ->name('label.batch');

Route::get('/cetak/bast/{handover}', [HandoverController::class, 'print'])
    ->name('bast.print');

Route::get('/cetak/opname/{stockOpname}', [OpnameController::class, 'print'])
    ->name('opname.print');

Route::get('/templat/pengguna', function () {
    abort_unless(auth()->check(), 401);
    abort_unless(auth()->user()->hasAnyRole(['super_admin', 'kabag_sarpras']), 403);

    return Excel::download(
        new UserTemplateExport,
        'templat-impor-pengguna.xlsx'
    );
})->name('user.template');
