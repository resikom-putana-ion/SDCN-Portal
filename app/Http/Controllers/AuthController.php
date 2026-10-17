<?php

namespace App\Http\Controllers;

use App\Services\PasswordResetMail;
use App\Exceptions\FirebaseAuthException;
use App\Models\User;
use App\Services\FirebaseAuth;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        try {
            $authenticated = Auth::attempt($credentials, $request->boolean('remember'));
        } catch (FirebaseAuthException) {
            return back()->withErrors(['email' => 'Layanan login belum dapat dihubungi. Silakan coba lagi.'])->onlyInput('email');
        }
        if (! $authenticated) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if (Auth::user()->usesFirebaseAuth()) {
            $request->session()->put('firebase_auth_version', Auth::user()->getAuthPassword());
            if (! (Auth::user()->email_verified ?? false)) return redirect('/verify-email');
        }

        return redirect()->intended('/admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function forgot(Request $request, PasswordResetMail $mail)
    {
        $request->validate(['email' => 'required|email']);
        $email = mb_strtolower(trim($request->string('email')->toString()));
        $user = User::find(hash('sha256', $email));
        if (app(FirebaseAuth::class)->enabled() || $user?->usesFirebaseAuth()) {
            $key = 'firebase-password-reset:'.hash('sha256', $email);
            if (! RateLimiter::tooManyAttempts($key, 1)) {
                RateLimiter::hit($key, 60);
                if ($user?->active === true && $user->firebase_uid) {
                    try {
                        $remote = app(FirebaseAuth::class)->user($user->firebase_uid);
                        if ($remote && ! ($remote['disabled'] ?? false)
                            && ($remote['localId'] ?? '') === $user->firebase_uid
                            && mb_strtolower($remote['email'] ?? '') === mb_strtolower($user->email)) {
                            app(FirebaseAuth::class)->sendReset($user->email);
                        }
                    } catch (FirebaseAuthException $e) {
                        if (! in_array($e->reason, ['EMAIL_NOT_FOUND', 'USER_DISABLED'], true)) {
                            RateLimiter::clear($key);
                            return back()->withErrors(['email' => 'Email pemulihan belum dapat dikirim. Silakan coba lagi nanti atau hubungi pengelola sekolah.'])->onlyInput('email');
                        }
                    }
                }
            }
            return redirect('/forgot-password')->with(['sent' => true, 'firebase_recovery' => true]);
        }
        $status = $mail->send(mb_strtolower(trim($request->string('email')->toString())));

        if ($status === PasswordResetMail::UNAVAILABLE) {
            return back()->withErrors(['email' => 'Email pemulihan belum dapat dikirim. Silakan coba lagi nanti atau hubungi pengelola sekolah.'])->onlyInput('email');
        }

        // Unknown, inactive and recently requested accounts receive the same response.
        return redirect('/forgot-password')->with('sent', true);
    }

    public function reset(Request $request)
    {
        $email = is_string($request->input('email')) ? mb_strtolower(trim($request->input('email'))) : '';
        if (app(FirebaseAuth::class)->enabled() || User::find(hash('sha256', $email))?->usesFirebaseAuth()) {
            return redirect('/forgot-password')->withErrors(['email' => 'Minta tautan pemulihan baru, lalu ubah kata sandi melalui halaman yang dibuka dari email.']);
        }
        $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:8|confirmed']);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->setPassword($password);
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET ? redirect('/login')->with('success', 'Kata sandi diperbarui. Akun siap digunakan kembali.') : back()->withErrors(['email' => 'Tautan tidak valid atau telah kedaluwarsa.']);
    }
}
