<?php

namespace App\Http\Controllers;

use App\Exceptions\FirebaseAuthException;
use App\Services\FirebaseAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->user()->usesFirebaseAuth() || ($request->user()->email_verified ?? false)) {
            return redirect('/admin');
        }
        return response()->view('auth.verify-email')->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, FirebaseAuth $firebase)
    {
        $user = $request->user();
        if (! $user->usesFirebaseAuth() || ($user->email_verified ?? false)) return redirect('/admin');
        $data = $request->validate(['password' => 'required|string']);
        $key = 'verify-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['email' => 'Tunggu satu menit sebelum meminta email verifikasi lagi.']);
        }
        RateLimiter::hit($key, 60);
        try {
            if (! $firebase->sendVerification($user->firebase_uid, $user->email, $data['password'])) {
                return back()->withErrors(['password' => 'Kata sandi tidak sesuai.']);
            }
        } catch (FirebaseAuthException) {
            return back()->withErrors(['email' => 'Email verifikasi belum dapat dikirim. Coba lagi dalam satu menit.']);
        }
        return back()->with('success', 'Email verifikasi telah dikirim. Buka tautan di email, lalu klik Saya sudah verifikasi. Periksa folder spam juga.');
    }
}
