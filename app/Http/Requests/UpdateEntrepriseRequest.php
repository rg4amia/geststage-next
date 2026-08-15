<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEntrepriseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('entreprise'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agence_id' => ['required', 'exists:agences,id'],
            'commune_id' => ['nullable', 'exists:communes,id'],
            'type_structure_id' => ['nullable', 'exists:types_structure,id'],
            'raison_sociale' => ['required', 'string', 'max:255'],
            'sigle' => ['nullable', 'string', 'max:100'],
            'numero_contribuable' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('entreprises')->ignore($this->route('entreprise')->id)],
            'registre_commerce' => ['nullable', 'string', 'max:100', \Illuminate\Validation\Rule::unique('entreprises')->ignore($this->route('entreprise')->id)],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'actif' => ['boolean'],
        ];
    }
}
