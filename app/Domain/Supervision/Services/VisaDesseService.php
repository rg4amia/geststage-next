<?php

namespace App\Domain\Supervision\Services;

use App\Enums\VisaDesseEnum;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Visa DESSE des dossiers validés par le chef d'agence.
 *
 * Portage des écrans legacy `Validation_Stagiaire_Desse` (`etat_chef_agence = 2` et
 * `etat_desse = 0`) et `Liste_Stagiaires_Rejetes_Desse` (`etat_desse = 1`).
 *
 * Le visa est une supervision parallèle, pas une étape bloquante : les 63 890 dossiers
 * legacy en attente de visa poursuivaient déjà leur pointage. Il est donc porté par
 * `stages.visa_desse` et non par une corbeille de parcours.
 *
 * Le filtre legacy `agent_id = 3` n'est pas porté : il vaut 3 sur la totalité des
 * `contrats_pae`, c'est une constante et non un critère.
 */
class VisaDesseService
{
    /**
     * @param  array<string, mixed>  $filtres
     */
    public function attenteQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::EN_ATTENTE->value);
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function rejetesQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::REJETE->value);
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function visesQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)->where('visa_desse', VisaDesseEnum::VISE->value);
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    private function baseQuery(array $filtres = []): Builder
    {
        return Stage::query()
            ->with(['beneficiaire', 'entreprise', 'agence', 'sourceFinancement', 'typeStage'])
            ->when($filtres['agence_id'] ?? null, fn (Builder $q, $v) => $q->where('agence_id', $v))
            ->when($filtres['entreprise_id'] ?? null, fn (Builder $q, $v) => $q->where('entreprise_id', $v))
            ->when($filtres['source_financement_id'] ?? null, fn (Builder $q, $v) => $q->where('source_financement_id', $v))
            ->when($filtres['type_stage_id'] ?? null, fn (Builder $q, $v) => $q->where('type_stage_id', $v))
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $terme) {
                $q->whereHas('beneficiaire', function (Builder $b) use ($terme) {
                    $b->where('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%")
                        ->orWhere('numero_aej', 'like', "%{$terme}%");
                });
            })
            ->orderBy('date_debut');
    }

    /**
     * Accorde le visa. Le dossier ne change pas de corbeille : le parcours suit son cours.
     */
    public function viser(Stage $stage, ?int $auteurId = null): void
    {
        $this->trancher($stage, VisaDesseEnum::VISE, null, $auteurId);
    }

    /**
     * Refuse le visa, motif obligatoire : c'est lui qui dit au CIP quoi corriger.
     */
    public function rejeter(Stage $stage, string $motif, ?int $auteurId = null): void
    {
        $this->trancher($stage, VisaDesseEnum::REJETE, $motif, $auteurId);
    }

    /**
     * Remet en attente un dossier rejeté, une fois le CIP passé dessus.
     */
    public function remettreEnAttente(Stage $stage): void
    {
        $stage->forceFill([
            'visa_desse' => VisaDesseEnum::EN_ATTENTE->value,
            'motif_visa_desse' => null,
            'visa_desse_le' => null,
            'visa_desse_par_id' => null,
        ])->save();
    }

    private function trancher(Stage $stage, VisaDesseEnum $visa, ?string $motif, ?int $auteurId): void
    {
        DB::transaction(function () use ($stage, $visa, $motif, $auteurId) {
            $stage->forceFill([
                'visa_desse' => $visa->value,
                'motif_visa_desse' => $motif,
                'visa_desse_le' => now(),
                'visa_desse_par_id' => $auteurId,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return array<string, int>
     */
    public function compteurs(array $filtres = []): array
    {
        return [
            'attente' => $this->attenteQuery($filtres)->count(),
            'rejetes' => $this->rejetesQuery($filtres)->count(),
            'vises' => $this->visesQuery($filtres)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLigne(Stage $stage): array
    {
        $beneficiaire = $stage->beneficiaire;

        return [
            'id' => $stage->id,
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
            ],
            'entreprise' => $stage->entreprise?->raison_sociale ?? '-',
            'agence' => $stage->agence?->nom ?? '-',
            'source_financement' => $stage->sourceFinancement?->nom ?? '-',
            'type_stage' => $stage->typeStage?->nom ?? '-',
            'date_debut' => $stage->date_debut?->format('d/m/Y') ?? '-',
            'date_fin_prevue' => $stage->date_fin_prevue?->format('d/m/Y') ?? '-',
            'visa_desse' => $stage->visa_desse?->value,
            'visa_desse_label' => $stage->visa_desse?->label(),
            'motif_visa_desse' => $stage->motif_visa_desse,
            'visa_desse_le' => $stage->visa_desse_le?->format('d/m/Y'),
        ];
    }
}
