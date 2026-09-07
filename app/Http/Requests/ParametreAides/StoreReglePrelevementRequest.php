<?php

namespace App\Http\Requests\ParametreAides;

use App\Models\Payment\ReglePrelevement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreReglePrelevementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gerer_parametres_systeme');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'type_prelevement' => ['required', Rule::in([ReglePrelevement::TYPE_CMU])],
            'source_financement_id' => ['required', 'integer', 'exists:sources_financement,id'],
            'type_stage_id' => ['nullable', 'integer', 'exists:types_stage,id'],
            'type_paiement' => ['required', Rule::in([ReglePrelevement::PAIEMENT_DEMARRAGE])],
            'montant' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            // Le legacy raisonne au mois : on conserve la saisie `Y-m`.
            'effet_du' => ['required', 'date_format:Y-m'],
            'effet_au' => ['nullable', 'date_format:Y-m', 'after_or_equal:effet_du'],
            'actif' => ['boolean'],
        ];
    }

    /**
     * Identifiant de la règle exclue du contrôle de chevauchement (aucun en création).
     */
    protected function regleIgnoree(): ?int
    {
        return null;
    }

    /**
     * Deux règles actives de même portée ne peuvent pas couvrir un même mois :
     * le calcul du prélèvement doit rester déterministe.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('actif', true)) {
                    return;
                }

                $debut = Carbon::createFromFormat('Y-m', $this->input('effet_du'))->startOfMonth();
                $fin = $this->filled('effet_au')
                    ? Carbon::createFromFormat('Y-m', $this->input('effet_au'))->endOfMonth()
                    : Carbon::parse(ReglePrelevement::FIN_OUVERTE);

                $chevauche = ReglePrelevement::query()
                    ->memePortee([
                        'source_financement_id' => (int) $this->input('source_financement_id'),
                        'type_stage_id' => $this->filled('type_stage_id') ? (int) $this->input('type_stage_id') : null,
                        'type_paiement' => $this->input('type_paiement'),
                    ])
                    ->when($this->regleIgnoree(), fn ($query, $id) => $query->whereKeyNot($id))
                    ->where('effet_du', '<=', $fin)
                    ->whereRaw('COALESCE(effet_au, ?) >= ?', [ReglePrelevement::FIN_OUVERTE, $debut])
                    ->exists();

                if ($chevauche) {
                    $validator->errors()->add(
                        'effet_du',
                        'Une règle active couvre déjà cette période pour la même source de financement et le même type de stage.'
                    );
                }
            },
        ];
    }

    /**
     * Bornes de mois converties en dates stockables.
     *
     * @return array<string, mixed>
     */
    public function donneesPersistables(): array
    {
        $donnees = $this->safe()->except(['effet_du', 'effet_au']);

        $donnees['effet_du'] = Carbon::createFromFormat('Y-m', $this->input('effet_du'))->startOfMonth()->toDateString();
        $donnees['effet_au'] = $this->filled('effet_au')
            ? Carbon::createFromFormat('Y-m', $this->input('effet_au'))->endOfMonth()->toDateString()
            : null;
        $donnees['actif'] = $this->boolean('actif', true);
        $donnees['type_stage_id'] = $this->filled('type_stage_id') ? (int) $this->input('type_stage_id') : null;

        return $donnees;
    }
}
