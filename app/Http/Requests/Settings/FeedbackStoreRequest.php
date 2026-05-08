<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FeedbackStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:issue,feedback,idea,question'],
            'subject' => ['required', 'string', 'max:160'],
            'page_context' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:8', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kind' => is_string($this->input('kind')) ? trim($this->input('kind')) : $this->input('kind'),
            'subject' => is_string($this->input('subject')) ? trim($this->input('subject')) : $this->input('subject'),
            'page_context' => is_string($this->input('page_context')) ? trim($this->input('page_context')) : $this->input('page_context'),
            'message' => is_string($this->input('message')) ? trim($this->input('message')) : $this->input('message'),
        ]);
    }
}
