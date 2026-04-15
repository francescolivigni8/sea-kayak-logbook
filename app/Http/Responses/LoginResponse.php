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
        $profile = $request->user()->resolveActiveProfile();

        if ($profile->requiresSetup()) {
            return redirect()->to(route('profile.edit', ['setup' => 1], false));
        }

        return redirect()->intended(config('fortify.home'));
    }
}
