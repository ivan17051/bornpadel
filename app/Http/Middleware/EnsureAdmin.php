<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Hanya admin yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
