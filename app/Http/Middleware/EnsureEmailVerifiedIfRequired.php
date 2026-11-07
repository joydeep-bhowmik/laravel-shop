<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedIfRequired
{
    /**
     * Redirect unverified customers to the verification notice, but only when the store requires verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail() && app(SettingsService::class)->bool('auth.require_email_verification')) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
