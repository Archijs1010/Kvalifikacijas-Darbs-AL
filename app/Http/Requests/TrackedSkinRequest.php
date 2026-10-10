<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackedSkinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'market_hash_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tracked_skins', 'market_hash_name')
                    ->ignore($this->route('skin')),
            ],
            'min_float' => ['nullable', 'numeric', 'between:0,1'],
            'max_float' => ['nullable', 'numeric', 'between:0,1'],
            'phase' => ['nullable', 'string', 'max:50'],
            'enabled' => ['nullable', 'boolean'],
        ];

        $min = $this->input('min_float');
        $max = $this->input('max_float');

        if (is_numeric($min) && is_numeric($max)) {
            if ((float) $min > (float) $max) {
                $rules['min_float'][] = 'lte:max_float';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'market_hash_name.unique' => 'This skin is already being tracked.',
            'min_float.lte' => 'Min float cannot be greater than max float.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('market_hash_name');

        $normalised = [
            'market_hash_name' => is_string($name) ? trim($name) : $name,
            'min_float' => $this->blankToNull($this->input('min_float')),
            'max_float' => $this->blankToNull($this->input('max_float')),
            'phase' => $this->blankToNull($this->input('phase')),
        ];

        if ($this->has('enabled')) {
            $normalised['enabled'] = $this->boolean('enabled');
        }

        $this->merge($normalised);
    }

    private function blankToNull(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
