<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

class GoogleController extends Controller
{
    /**
     * Send the visitor to Google's consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Sign in (or create) the account for a verified Google address.
     */
    public function callback(): RedirectResponse
    {
        /** @var GoogleUser $google */
        $google = Socialite::driver('google')->user();

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $google->getEmail())->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => $google->getName() ?: Str::before((string) $google->getEmail(), '@'),
                'email' => $google->getEmail(),
                'password' => Str::random(40),
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->is_blocked) {
            return redirect()->route('login')->withErrors([
                'email' => 'This account has been blocked. Contact support for help.',
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
