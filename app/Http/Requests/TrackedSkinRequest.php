<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by the store and update actions: the field set is identical, and the
 * uniqueness check ignores the skin currently being edited via the route.
 */
class TrackedSkinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'market_hash_name.unique' => 'This skin is already being tracked.',
            'min_float.lte' => 'Min float cannot be greater than max float.',
        ];
    }

    /**
     * Normalise the raw input before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('market_hash_name');

        $normalised = [
            'market_hash_name' => is_string($name) ? trim($name) : $name,
            'min_float' => $this->blankToNull($this->input('min_float')),
            'max_float' => $this->blankToNull($this->input('max_float')),
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
