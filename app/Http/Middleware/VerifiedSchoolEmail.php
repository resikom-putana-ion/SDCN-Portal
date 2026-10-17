<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifiedSchoolEmail
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->usesFirebaseAuth() && ! ($user->email_verified ?? false)) {
            return redirect('/verify-email');
        }
        return $next($request);
    }
}
