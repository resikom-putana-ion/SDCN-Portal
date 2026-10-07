<?php

use App\Http\Controllers\{AdminController, AuthController, WorkflowController, WebsiteController};
use App\Http\Middleware\SchoolAdmin;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', fn () => redirect('/admin'));
Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');
Route::post('/login', [AuthController::class,'login'])->middleware(['guest','throttle:6,1']);
Route::post('/logout', [AuthController::class,'logout'])->middleware('auth');
Route::get('/forgot-password', fn () => view('auth.recovery', ['mode'=>'forgot']))->name('password.request');
Route::post('/forgot-password', [AuthController::class,'forgot'])->middleware('throttle:3,1')->name('password.email');
Route::get('/reset-password/{token}', fn ($token) => view('auth.recovery', ['mode'=>'reset','token'=>$token]))->name('password.reset');
Route::post('/reset-password', [AuthController::class,'reset'])->middleware('throttle:6,1')->name('password.update');
Route::post('/language', function (Request $request) { $request->validate(['locale'=>'required|in:id,en']); session(['locale'=>$request->locale]); return back(); });
Route::get('/website', [WebsiteController::class,'publicPage']);
Route::get('/ppdb', [WebsiteController::class,'application']);
Route::post('/ppdb', [WebsiteController::class,'submitApplication'])->middleware('throttle:5,1');
Route::get('/ppdb/berhasil', fn () => session()->has('registration') ? view('public.success') : redirect('/ppdb'));

Route::middleware(['auth', SchoolAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class,'dashboard'])->name('dashboard');
    Route::get('/export/{kind}', [AdminController::class,'export']);
    Route::get('/dokumen/{id}', [WorkflowController::class,'document']);
    Route::post('/dokumen/{record}', [WorkflowController::class,'uploadDocument']);
    Route::get('/identitas/{record}', [WorkflowController::class,'identity']);
    Route::post('/record/{kind}/{id?}', [AdminController::class,'save']);
    Route::post('/ppdb/{record}/review', [WorkflowController::class,'review']);
    Route::post('/ppdb/{record}/offline', [WorkflowController::class,'offline']);
    Route::post('/pembayaran/catat', [WorkflowController::class,'payment']);
    Route::post('/pembayaran/{id}/verify', [WorkflowController::class,'verifyPayment']);
    Route::get('/kuitansi/{id}', [WorkflowController::class,'receipt']);
    Route::post('/kenaikan-kelas', [WorkflowController::class,'promotion']);
    Route::post('/website/media', [WebsiteController::class,'upload']);
    Route::post('/website/publish', [WebsiteController::class,'publish']);
    Route::get('/website/preview', [WebsiteController::class,'preview']);
    Route::get('/website/{section}/edit', [WebsiteController::class,'edit']);
    Route::post('/website/{section}/edit', [WebsiteController::class,'save']);
    Route::get('/{screen}/{id?}/{tab?}', [AdminController::class,'page'])->where('screen','[a-z-]+');
});
