<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    /**
     * Log out a session whose account has been blocked since it signed in.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_blocked) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been blocked. Contact support for help.',
            ]);
        }

        return $next($request);
    }
}
