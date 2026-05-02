<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Features;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['email'] = strtolower(trim((string) ($input['email'] ?? '')));
        $profileRules = $this->profileRules();
        $profileRules['email'] = [
            ...$profileRules['email'],
            ...$this->inviteOnlyEmailRules(),
        ];

        Validator::make($input, [
            ...$profileRules,
            'password' => $this->passwordRules(),
            'accept_terms' => ['accepted'],
            'accept_privacy' => ['accepted'],
        ], [
            'email.in' => 'This private beta is invite-only. Ask Francesco to add this email before creating an account.',
            'accept_terms.accepted' => 'Please accept the Terms before creating an account.',
            'accept_privacy.accepted' => 'Please accept the Privacy Policy before creating an account.',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'accepted_terms_at' => now(),
            'accepted_privacy_at' => now(),
            'accepted_terms_version' => (string) config('kayak.legal.terms_version'),
            'accepted_privacy_version' => (string) config('kayak.legal.privacy_version'),
        ]);

        if (! Features::enabled(Features::emailVerification())) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        $user->resolveActiveProfile();

        return $user;
    }

    /**
     * @return array<int, mixed>
     */
    private function inviteOnlyEmailRules(): array
    {
        if (! config('kayak.invite_only')) {
            return [];
        }

        return [Rule::in(config('kayak.invite_emails', []))];
    }
}
