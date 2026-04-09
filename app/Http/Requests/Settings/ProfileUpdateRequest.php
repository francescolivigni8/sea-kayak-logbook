<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'paddler_name' => ['nullable', 'string', 'max:255'],
            'kayak_club' => ['nullable', 'string', 'max:255'],
            'registered_kayaks_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'registered_paddles_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bio' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
