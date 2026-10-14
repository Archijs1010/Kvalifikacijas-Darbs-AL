<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackedSkinNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $note = $this->input('note');

        if (is_string($note)) {
            $note = trim($note);
        }

        $this->merge([
            'note' => $note === '' ? null : $note,
        ]);
    }
}
