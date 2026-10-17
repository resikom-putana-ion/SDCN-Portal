<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirebaseSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->usesFirebaseAuth()) {
            $version = $request->session()->get('firebase_auth_version');
            if ($version === null && Auth::viaRemember()) {
                // The provider has already checked the remember token's Firebase version.
                $request->session()->put('firebase_auth_version', $user->getAuthPassword());
            } elseif (! is_string($version) || ! hash_equals($user->getAuthPassword(), $version)) {
                Auth::guard()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect('/login')->with('success', 'Silakan masuk kembali untuk melanjutkan.');
            }
        }
        return $next($request);
    }
}
