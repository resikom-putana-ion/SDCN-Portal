<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class SchoolAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->active === true && array_key_exists((string) $request->user()?->role, User::ROLES), 403, 'Akses khusus pengelola sekolah.');
        app()->setLocale(session('locale', 'id'));

        return $next($request);
    }
}
