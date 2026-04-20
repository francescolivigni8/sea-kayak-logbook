<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGarminHistoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'autofill_weather' => $this->boolean('autofill_weather'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'csv_file' => ['nullable', 'required_without_all:gpx_files,fit_files', 'file', 'mimes:csv,txt', 'max:20480'],
            'gpx_files' => ['nullable', 'array'],
            'gpx_files.*' => ['file', 'mimes:gpx,xml', 'max:20480'],
            'fit_files' => ['nullable', 'array'],
            'fit_files.*' => ['file', 'extensions:fit', 'max:20480'],
            'autofill_weather' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required_without_all' => 'Add a Garmin CSV, or upload GPX/FIT files to attach to existing sessions.',
        ];
    }
}
