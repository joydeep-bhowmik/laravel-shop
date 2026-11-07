<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

use function Laravel\Folio\{middleware, name, render};

name('verification.verify');
middleware(['auth', 'signed', 'throttle:6,1']);

render(function (View $view, string $id, string $hash) {
    /** @var User $user */
    $user = Auth::user();

    abort_unless((string) $user->getKey() === $id && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    return redirect()->route('dashboard', ['verified' => 1]);
});
?>
