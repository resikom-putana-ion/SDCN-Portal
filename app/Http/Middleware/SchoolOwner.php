<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SchoolOwner
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isOwner() && $request->user()?->active === true, 403, 'Hanya Owner yang dapat mengelola akun pengguna.');

        return $next($request);
    }
}
