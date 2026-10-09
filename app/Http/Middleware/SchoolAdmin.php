<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SchoolAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'owner'], true), 403, 'Akses khusus Admin Sekolah.');
        app()->setLocale(session('locale', 'id'));

        return $next($request);
    }
}
