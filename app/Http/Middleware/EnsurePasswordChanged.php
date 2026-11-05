<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang masih memakai kata sandi awal (NIP) hanya boleh membuka halaman ganti kata sandi dan keluar.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->must_change_password || $request->routeIs('filament.admin.auth.profile', 'filament.admin.auth.logout')) {
            return $next($request);
        }

        return redirect()->route('filament.admin.auth.profile');
    }
}
