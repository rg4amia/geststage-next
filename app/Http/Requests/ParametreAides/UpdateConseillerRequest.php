<?php

namespace App\Http\Requests\ParametreAides;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConseillerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gerer_referentiels');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agence_id' => ['required', 'integer', 'exists:agences,id'],
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['nullable', 'string', 'max:255'],
            'matricule' => [
                'nullable', 'string', 'max:100',
                Rule::unique('conseillers', 'matricule')->ignore($this->route('conseiller')->id),
            ],
            'actif' => ['boolean'],
        ];
    }
}
