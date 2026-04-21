<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

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
        ], [
            'email.in' => 'This private beta is invite-only. Ask Francesco to add this email before creating an account.',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

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
