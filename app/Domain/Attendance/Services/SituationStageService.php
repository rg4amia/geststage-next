<?php

namespace App\Domain\Attendance\Services;

use App\Models\Internship\Stage;
use App\Models\Reference\SituationStage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Corbeilles CIP des stagiaires sortis du pointage courant (abandon, suspension)
 * et réactivation d'une suspension.
 *
 * Portage de `PointageSituationStageService` (legacy) : la situation courante est lue sur
 * `stages.situation_stage`, la table d'historique `situations_stages` n'ayant jamais été
 * alimentée par la migration.
 */
class SituationStageService
{
    public function abandonsQuery(array $filtres = []): Builder
    {
        return $this->corbeilleQuery(SituationStage::CODE_ABANDON, $filtres);
    }

    public function suspensionsQuery(array $filtres = []): Builder
    {
        return $this->corbeilleQuery(SituationStage::CODE_SUSPENSION, $filtres);
    }

    /**
     * Agences sur lesquelles l'utilisateur courant est habilité, ou `null` s'il n'a aucun
     * périmètre défini — auquel cas aucune restriction n'est appliquée, comme partout
     * ailleurs dans l'application (la table `perimetres_agences_utilisateurs` n'est pas
     * encore alimentée).
     *
     * @return array<int, int>|null
     */
    private function agencesAutorisees(): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()
            ->where(function ($q) {
                $q->whereNull('valide_au')->orWhere('valide_au', '>=', now());
            })
            ->pluck('agences.id')
            ->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    private function corbeilleQuery(string $codeSituation, array $filtres = []): Builder
    {
        return Stage::query()
            ->with(['beneficiaire', 'entreprise', 'agence', 'sourceFinancement', 'typeStage'])
            ->where('situation_stage', $codeSituation)
            ->when(
                $this->agencesAutorisees(),
                fn (Builder $q, array $agenceIds) => $q->whereIn('agence_id', $agenceIds)
            )
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
            ->orderByDesc('date_debut');
    }

    /**
     * Réactive un stage suspendu : la date de fin est repoussée du nombre de mois
     * effectivement suspendus, sinon le stagiaire perdrait les mois non pointés.
     *
     * @return array{nom: string, ancienne_date_fin: ?string, nouvelle_date_fin: string, mois_suspendus: int}
     */
    public function reactiverSuspension(Stage $stage, ?int $auteurId = null): array
    {
        $situationEnCours = SituationStage::query()
            ->where('code', SituationStage::CODE_EN_COURS)
            ->firstOrFail();

        $situationSuspension = SituationStage::query()
            ->where('code', SituationStage::CODE_SUSPENSION)
            ->firstOrFail();

        $moisSuspendus = $stage->pointages()
            ->where('situation_stage_id', $situationSuspension->id)
            ->count();

        $ancienneDateFin = $stage->date_fin_prevue;
        $nouvelleDateFin = $this->repousserDateFin($ancienneDateFin, $moisSuspendus);

        DB::transaction(function () use ($stage, $nouvelleDateFin, $situationEnCours, $auteurId) {
            $stage->forceFill([
                'situation_stage' => SituationStage::CODE_EN_COURS,
                'date_fin_prevue' => $nouvelleDateFin,
            ])->save();

            // La ligne d'historique n'existe pas forcément (table non backfillée) : on clôt
            // la période de suspension ouverte s'il y en a une, et on ouvre la reprise.
            DB::table('situations_stages')
                ->where('stage_id', $stage->id)
                ->whereNull('termine_le')
                ->update(['termine_le' => now(), 'updated_at' => now()]);

            DB::table('situations_stages')->insert([
                'stage_id' => $stage->id,
                'situation_stage_id' => $situationEnCours->id,
                'auteur_id' => $auteurId,
                'debute_le' => now(),
                'motif' => 'Réactivation après suspension',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $beneficiaire = $stage->beneficiaire;

        return [
            'nom' => trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')) ?: 'Stagiaire',
            'ancienne_date_fin' => $ancienneDateFin?->format('Y-m-d'),
            'nouvelle_date_fin' => $nouvelleDateFin->format('Y-m-d'),
            'mois_suspendus' => $moisSuspendus,
        ];
    }

    /**
     * Legacy `getDateFin()` : un stage d'un mois et demi se rattrape en 45 jours calendaires
     * quand il n'a pas démarré le 1er, sinon en 1 mois + 14 jours.
     */
    private function repousserDateFin(?CarbonInterface $dateFin, int|float $duree): CarbonInterface
    {
        $date = $dateFin ? $dateFin->copy() : now();

        if ($duree <= 0) {
            return $date;
        }

        if ((float) $duree === 1.5) {
            return $date->day > 1
                ? $date->addDays(45)
                : $date->addMonth()->addDays(14);
        }

        return $date->addMonths((int) $duree)->subDay();
    }

    /**
     * @return array<string, int>
     */
    public function compteurs(array $filtres = []): array
    {
        return [
            'abandon' => $this->abandonsQuery($filtres)->count(),
            'suspension' => $this->suspensionsQuery($filtres)->count(),
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
            'observations' => $stage->observations,
        ];
    }
}
