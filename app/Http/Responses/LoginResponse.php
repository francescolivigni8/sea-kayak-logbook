<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var Request $request */
        if ($request->user()->requiresLegalAcceptance()) {
            return redirect()->to(route('legal.acceptance.edit', absolute: false));
        }

        $profile = $request->user()->resolveActiveProfile();

        if ($profile->requiresSetup()) {
            return redirect()->to(route('profile.edit', ['setup' => 1], false));
        }

        return redirect()->to(route('dashboard', absolute: false));
    }
}
