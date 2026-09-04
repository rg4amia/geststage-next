<?php

namespace App\Domain\Contract\Services;

use App\Models\Contract\AvenantContrat;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Reference\SituationStage;
use App\Models\Reference\TypeStage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Corbeilles CIP du renouvellement de contrat.
 *
 * Portage de `RenouvellementContratController` et `AnticipeRenewController` (legacy), où
 * l'état du renouvellement tenait dans quatre drapeaux de `contrats_pae`. Ici il tient dans
 * l'existence et le `statut` d'un `AvenantContrat` :
 *
 * - aucun avenant + stage arrivé à terme  → à renouveler ;
 * - aucun avenant + terme sous 10 jours   → à anticiper ;
 * - avenant `AJOURNE`                     → renvoyé par le chef d'agence.
 *
 * Le filtre legacy `agent_id = 3` n'est pas porté : il vaut 3 sur la totalité des
 * `contrats_pae`, c'est une constante et non un critère.
 */
class RenouvellementService
{
    /**
     * Fenêtre legacy d'anticipation : `date_fin <= now()->addDays(10)`.
     */
    private const JOURS_ANTICIPATION = 10;

    /**
     * Stages arrivés à terme et jamais renouvelés.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function attenteQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)
            ->whereDate('date_fin_prevue', '<=', now())
            ->whereIn('situation_stage', [
                SituationStage::CODE_EN_COURS,
                SituationStage::CODE_FIN_DE_STAGE,
            ])
            ->whereIn('type_stage_id', $this->typesRenouvelables())
            ->whereDoesntHave('contrats.avenants', $this->avenantEnCours());
    }

    /**
     * Stages dont le terme tombe dans les dix jours, sans dépasser le mois courant.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function anticipeQuery(array $filtres = []): Builder
    {
        $limite = min(now()->endOfMonth(), now()->addDays(self::JOURS_ANTICIPATION));

        return $this->baseQuery($filtres)
            ->whereDate('date_fin_prevue', '>=', now())
            ->whereDate('date_fin_prevue', '<=', $limite)
            ->where('situation_stage', SituationStage::CODE_EN_COURS)
            ->whereIn('type_stage_id', $this->typesAnticipables())
            ->whereDoesntHave('contrats.avenants', $this->avenantEnCours());
    }

    /**
     * Renouvellements renvoyés au CIP par le chef d'agence.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function ajourneQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)
            ->whereHas(
                'contrats.avenants',
                fn (Builder $q) => $q->where('statut', AvenantContrat::STATUT_AJOURNE)
            );
    }

    /**
     * Un renouvellement « en cours » est celui que le chef d'agence n'a pas encore tranché.
     *
     * Un avenant `VALIDE` appartient au passé : il a déjà repoussé le terme du stage, donc
     * un stage qui retombe à échéance est de nouveau à renouveler. Le legacy en tenait
     * compte dans la corbeille anticipée mais pas dans la corbeille d'attente, où
     * `etatrenouvellement_id != 1` masquait définitivement les contrats déjà renouvelés
     * une fois.
     */
    private function avenantEnCours(): callable
    {
        return fn (Builder $q) => $q->whereIn('statut', [
            AvenantContrat::STATUT_ATTENTE_CA,
            AvenantContrat::STATUT_AJOURNE,
        ]);
    }

    /**
     * Les deux « stage de qualification » du référentiel (le legacy acceptait
     * `id_type_stage IN (1, 4, 3)`, dont le 3 n'a pas de contrepartie).
     *
     * @return array<int, int>
     */
    private function typesRenouvelables(): array
    {
        return TypeStage::idsPourCodes([
            TypeStage::CODE_QUALIFICATION,
            TypeStage::CODE_QUALIFICATION_HERITE,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function typesAnticipables(): array
    {
        return TypeStage::idsPourCodes([TypeStage::CODE_QUALIFICATION]);
    }

    /**
     * Agences habilitées pour l'utilisateur courant, ou `null` s'il n'a aucun périmètre
     * défini — aucune restriction n'est alors appliquée, comme ailleurs dans l'application.
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
    private function baseQuery(array $filtres = []): Builder
    {
        return Stage::query()
            ->with([
                'beneficiaire', 'entreprise', 'agence', 'sourceFinancement', 'typeStage',
                'contrats.avenants',
            ])
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
            ->orderBy('date_fin_prevue');
    }

    /**
     * Propose le renouvellement d'un stage : crée l'avenant en attente du chef d'agence et
     * repousse le terme du stage de la durée demandée.
     *
     * @return array{numero: string, nouvelle_date_fin: string}
     */
    public function renouveler(Stage $stage, int $dureeMois, ?string $motif = null): array
    {
        $contrat = $stage->contrats()->latest('date_debut')->first();

        if (! $contrat) {
            throw new \RuntimeException("Ce stage n'a aucun contrat à renouveler.");
        }

        if ($contrat->avenants()->where($this->avenantEnCours())->exists()) {
            throw new \RuntimeException('Un renouvellement est déjà en cours sur ce contrat.');
        }

        $dateEffet = $stage->date_fin_prevue?->copy()->addDay() ?? now();
        $nouvelleDateFin = $this->termeRenouvellement($stage->date_fin_prevue, $dureeMois);

        $avenant = DB::transaction(function () use ($stage, $contrat, $dateEffet, $nouvelleDateFin, $motif) {
            $avenant = AvenantContrat::create([
                'contrat_id' => $contrat->id,
                'numero' => $this->numeroAvenant($contrat),
                'date_effet' => $dateEffet,
                'nouvelle_date_fin' => $nouvelleDateFin,
                'nouvelle_prime_mensuelle' => $contrat->prime_mensuelle,
                'motif' => $motif ?: 'Renouvellement du contrat de stage',
                'statut' => AvenantContrat::STATUT_ATTENTE_CA,
            ]);

            $stage->forceFill([
                'date_fin_prevue' => $nouvelleDateFin,
                'situation_stage' => SituationStage::CODE_EN_COURS,
                'statut_stage' => 'STAGE_RENOUVELLE',
            ])->save();

            return $avenant;
        });

        return [
            'numero' => $avenant->numero,
            'nouvelle_date_fin' => $nouvelleDateFin->format('Y-m-d'),
        ];
    }

    /**
     * Renvoie un renouvellement ajourné au chef d'agence après correction par le CIP.
     */
    public function renvoyerAuChefAgence(AvenantContrat $avenant): void
    {
        $avenant->forceFill([
            'statut' => AvenantContrat::STATUT_ATTENTE_CA,
            'motif_ajournement' => null,
            'decide_le' => null,
            'decideur_id' => null,
        ])->save();
    }

    /**
     * Un renouvellement court du lendemain du terme jusqu'au même quantième, `$dureeMois`
     * plus tard — la convention de `getDateFin()` (legacy).
     *
     * Sans `NoOverflow`, un terme au 31 août prolongé de six mois basculerait au 3 mars :
     * on veut le dernier jour de février.
     */
    private function termeRenouvellement(?CarbonInterface $dateFin, int $dureeMois): CarbonInterface
    {
        $depart = $dateFin ? $dateFin->copy() : now();

        return $depart->addMonthsNoOverflow(max($dureeMois, 1));
    }

    private function numeroAvenant(Contrat $contrat): string
    {
        return $contrat->numero.'-R'.($contrat->avenants()->count() + 1);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return array<string, int>
     */
    public function compteurs(array $filtres = []): array
    {
        return [
            'attente' => $this->attenteQuery($filtres)->count(),
            'anticipe' => $this->anticipeQuery($filtres)->count(),
            'ajourne' => $this->ajourneQuery($filtres)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLigne(Stage $stage): array
    {
        $beneficiaire = $stage->beneficiaire;
        $avenant = $stage->contrats
            ->flatMap->avenants
            ->firstWhere('statut', AvenantContrat::STATUT_AJOURNE);

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
            'jours_restants' => $stage->date_fin_prevue
                ? now()->startOfDay()->diffInDays($stage->date_fin_prevue, false)
                : null,
            'avenant_id' => $avenant?->id,
            'motif_ajournement' => $avenant?->motif_ajournement,
        ];
    }
}
