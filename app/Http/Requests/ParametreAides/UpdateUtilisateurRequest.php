<?php

namespace App\Http\Requests\ParametreAides;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('utilisateur'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $utilisateurId = $this->route('utilisateur')->id;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateurId)],
            'telephone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'telephone')->ignore($utilisateurId)],
            // Mot de passe optionnel : laisser vide conserve celui en place.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'actif' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'agences' => ['array'],
            'agences.*' => ['integer', 'exists:agences,id'],
        ];
    }
}
