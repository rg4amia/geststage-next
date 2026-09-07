<?php

namespace App\Http\Requests\ParametreAides;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gerer_agences');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('agences', 'code')->ignore($this->route('agence')->id),
            ],
            'nom' => ['required', 'string', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'commune_id' => ['nullable', 'integer', 'exists:communes,id'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'actif' => ['boolean'],
        ];
    }
}
