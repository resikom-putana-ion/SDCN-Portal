<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\StudentLifecycleController;
use App\Http\Middleware\SchoolAdmin;
use App\Http\Middleware\SchoolRoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::bind('record', fn ($id) => \App\Models\SchoolRecord::findOrFail($id));

Route::get('/', fn () => redirect('/admin'));
Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:6,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::middleware(['auth', \App\Http\Middleware\FirebaseSession::class, SchoolAdmin::class])->group(function () {
    Route::get('/verify-email', [\App\Http\Controllers\EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('/verify-email', [\App\Http\Controllers\EmailVerificationController::class, 'send'])->middleware('throttle:3,1');
});
Route::get('/forgot-password', fn () => view('auth.recovery', ['mode' => 'forgot']))->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1')->name('password.email');
Route::get('/reset-password/{token}', fn ($token) => config('school.firebase_auth_enabled')
    ? redirect('/forgot-password')->withErrors(['email' => 'Minta tautan pemulihan baru untuk akun Anda.'])
    : view('auth.recovery', ['mode' => 'reset', 'token' => $token]))->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
Route::post('/language', function (Request $request) {
    $request->validate(['locale' => 'required|in:id,en']);
    session(['locale' => $request->locale]);

    return back();
});
Route::get('/media/{filename}', [WebsiteController::class, 'media']);
Route::get('/ppdb', [WebsiteController::class, 'application']);
Route::post('/ppdb', [WebsiteController::class, 'submitApplication'])->middleware('throttle:5,1');
Route::get('/ppdb/berhasil', fn () => session()->has('registration') ? view('public.success') : redirect('/ppdb'));

Route::middleware(['auth', \App\Http\Middleware\FirebaseSession::class, SchoolAdmin::class, \App\Http\Middleware\VerifiedSchoolEmail::class, SchoolRoleAccess::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/pengguna', [UserManagementController::class, 'index']);
    Route::post('/pengguna/hapus/konfirmasi', [UserManagementController::class, 'confirmDelete']);
    Route::get('/pengguna/{account}/hapus', [UserManagementController::class, 'confirmDelete']);
    Route::delete('/pengguna/hapus', [UserManagementController::class, 'destroy']);
        Route::get('/pengguna/baru', [UserManagementController::class, 'create']);
        Route::post('/pengguna', [UserManagementController::class, 'store']);
        Route::get('/pengguna/{account}/edit', [UserManagementController::class, 'edit']);
        Route::put('/pengguna/{account}', [UserManagementController::class, 'update']);
    Route::post('/profil-admin', [\App\Http\Controllers\AdminProfileController::class, 'save']);
    Route::get('/foto-admin', [\App\Http\Controllers\AdminProfileController::class, 'photo']);
    Route::get('/export/{kind}', [AdminController::class, 'export']);
    Route::get('/dokumen/{id}', [WorkflowController::class, 'document']);
    Route::post('/dokumen/{record}', [WorkflowController::class, 'uploadDocument']);
    Route::get('/identitas/{record}', [WorkflowController::class, 'identity']);
    Route::get('/foto-siswa/{record}', [AdminController::class, 'studentPhoto']);
    Route::get('/foto-guru/{record}', [AdminController::class, 'staffPhoto']);
    Route::post('/siswa/{record}/status', [StudentLifecycleController::class, 'status']);
    Route::get('/siswa/{record}/hapus', [StudentLifecycleController::class, 'confirmDelete']);
    Route::delete('/siswa/{record}', [StudentLifecycleController::class, 'destroy']);
    Route::post('/record/{kind}/{id?}', [AdminController::class, 'save']);
    Route::post('/ppdb/{record}/review', [WorkflowController::class, 'review']);
    Route::post('/ppdb/{record}/offline', [WorkflowController::class, 'offline']);
    Route::post('/pembayaran/catat', [WorkflowController::class, 'payment']);
    Route::post('/pembayaran/{id}/verify', [WorkflowController::class, 'verifyPayment']);
    Route::get('/kuitansi/{id}', [WorkflowController::class, 'receipt']);
    Route::get('/kuitansi/{id}/pdf', [WorkflowController::class, 'receiptPdf']);
    Route::get('/rapor/{record}/pdf', [WorkflowController::class, 'reportPdf']);
    Route::get('/rapor-arsip/{record}/pdf', [WorkflowController::class, 'archivedReportPdf']);
    Route::get('/periksa-pembayaran/{id}', [WorkflowController::class, 'inspectPayment']);
    Route::post('/kenaikan-kelas', [WorkflowController::class, 'promotion']);
    Route::post('/website/media', [WebsiteController::class, 'upload']);
    Route::post('/website/publish', [WebsiteController::class, 'publish']);
    Route::post('/website/{section}/discard', [WebsiteController::class, 'discard']);
    Route::get('/website/preview', [WebsiteController::class, 'preview']);
    Route::get('/website/preview/frame', [WebsiteController::class, 'frame']);
    Route::get('/website/{section}/edit', [WebsiteController::class, 'edit']);
    Route::post('/website/{section}/edit', [WebsiteController::class, 'save']);
    Route::post('/website/{section}/preview', [WebsiteController::class, 'previewChanges']);
    Route::get('/{screen}/{id?}/{tab?}', [AdminController::class, 'page'])->where('screen', '[a-z-]+');
});
