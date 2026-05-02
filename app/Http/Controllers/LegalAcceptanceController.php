<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalAcceptanceController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->requiresLegalAcceptance()) {
            return redirect()->to(route('dashboard', absolute: false));
        }

        $profile = $user->resolveActiveProfile();

        return Inertia::render('auth/LegalAcceptance', [
            'termsVersion' => $user->currentTermsVersion(),
            'privacyVersion' => $user->currentPrivacyVersion(),
            'setupRequired' => $profile->requiresSetup(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Please accept the Terms before continuing.',
            'accept_privacy.accepted' => 'Please accept the Privacy Policy before continuing.',
        ]);

        unset($validated);

        $user = $request->user();
        $user->acceptCurrentLegal();

        $profile = $user->resolveActiveProfile();
        $fallback = $profile->requiresSetup()
            ? route('profile.edit', ['setup' => 1], false)
            : route('dashboard', absolute: false);

        return redirect()->intended($fallback)->with('success', 'Terms and privacy accepted.');
    }
}
