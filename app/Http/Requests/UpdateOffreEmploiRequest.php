<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOffreEmploiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('offre_emploi'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entreprise_id' => ['required', 'exists:entreprises,id'],
            'agence_id' => ['required', 'exists:agences,id'],
            'type_stage_id' => ['required', 'exists:types_stage,id'],
            'source_financement_id' => ['required', 'exists:sources_financement,id'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'numero' => ['required', 'string', \Illuminate\Validation\Rule::unique('offres_emploi')->ignore($this->route('offre_emploi')->id)],
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'nombre_places' => ['required', 'integer', 'min:1'],
            'valide_du' => ['nullable', 'date'],
            'valide_au' => ['nullable', 'date', 'after_or_equal:valide_du'],
            'statut' => ['nullable', 'string', 'in:BROUILLON,PUBLIEE,CLOTUREE,ANNULEE'],
        ];
    }
}
