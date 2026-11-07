<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    /**
     * Send the user to the dashboard after a passkey login.
     */
    public function toResponse($request): Response
    {
        $redirect = redirect()->intended(route('dashboard'));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect->getTargetUrl()], 200)
            : $redirect;
    }
}
