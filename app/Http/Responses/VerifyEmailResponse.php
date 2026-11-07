<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * Send the user to the dashboard after verifying their email.
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(route('dashboard').'?verified=1');
    }
}
