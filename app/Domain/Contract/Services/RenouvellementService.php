<?php

declare(strict_types=1);

namespace App\Domain\Contract\Services;

use App\Models\Contract\AvenantContrat;
use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use App\Models\Reference\SituationStage;
use App\Models\Reference\TypeStage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Corbeilles CIP et Chef d'Agence du renouvellement de contrat.
 *
 * Portage unifié de `RenouvellementContratController`, `AnticipeRenewController` et
 * `attenteValidationByChefAgence` (legacy).
 * L'état du renouvellement est modélisé via les relations `contrats` et `avenants_contrats`.
 */
class RenouvellementService
{
    /**
     * Fenêtre legacy d'anticipation : `date_fin <= now()->addDays(10)`.
     */
    private const JOURS_ANTICIPATION = 10;

    /**
     * Stages arrivés à terme et jamais renouvelés ou dont le renouvellement précédent est achevé.
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
     * Renouvellements ajournés / renvoyés au CIP par le chef d'agence pour correction.
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
     * Renouvellements proposés par le CIP, en attente de la décision du chef d'agence.
     *
     * @param  array<string, mixed>  $filtres
     */
    public function chefAgenceValidationQuery(array $filtres = []): Builder
    {
        return $this->baseQuery($filtres)
            ->whereHas(
                'contrats.avenants',
                fn (Builder $q) => $q->where('statut', AvenantContrat::STATUT_ATTENTE_CA)
            );
    }

    /**
     * Un renouvellement « en cours » est celui que le chef d'agence n'a pas encore tranché.
     */
    private function avenantEnCours(): callable
    {
        return fn (Builder $q) => $q->whereIn('statut', [
            AvenantContrat::STATUT_ATTENTE_CA,
            AvenantContrat::STATUT_AJOURNE,
        ]);
    }

    /**
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
     * Agences habilitées pour l'utilisateur courant, ou `null` s'il a les droits administrateur
     * ou aucun périmètre défini.
     *
     * @return array<int, int>|null
     */
    public function agencesAutorisees(): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        // L'administrateur a une vue globale sans restriction
        if (method_exists($user, 'hasRole') && $user->hasRole('administrateur')) {
            return null;
        }

        $agenceIds = [];

        if (method_exists($user, 'perimetresAgences')) {
            $agenceIds = $user->perimetresAgences()
                ->where(function ($q): void {
                    $q->whereNull('valide_au')->orWhere('valide_au', '>=', now());
                })
                ->pluck('agences.id')
                ->all();
        }

        if ($agenceIds === [] && ! empty($user->agence_id)) {
            $agenceIds = [(int) $user->agence_id];
        }

        return $agenceIds === [] ? null : $agenceIds;
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function baseQuery(array $filtres = []): Builder
    {
        return Stage::query()
            ->with([
                'beneficiaire.typePaiement',
                'beneficiaire.communeResidence',
                'entreprise.typeStructure',
                'agence',
                'sourceFinancement',
                'typeStage',
                'contrats.avenants.typeStructure',
                'contrats.avenants.decideur',
            ])
            ->when(
                $this->agencesAutorisees(),
                fn (Builder $q, array $agenceIds) => $q->whereIn('agence_id', $agenceIds)
            )
            ->when($filtres['agence_id'] ?? null, fn (Builder $q, $v) => $q->where('agence_id', $v))
            ->when($filtres['entreprise_id'] ?? null, fn (Builder $q, $v) => $q->where('entreprise_id', $v))
            ->when($filtres['source_financement_id'] ?? null, fn (Builder $q, $v) => $q->where('source_financement_id', $v))
            ->when($filtres['type_stage_id'] ?? null, fn (Builder $q, $v) => $q->where('type_stage_id', $v))
            ->when($filtres['type_structure_id'] ?? null, function (Builder $q, $v): void {
                $q->where(function (Builder $sub) use ($v): void {
                    $sub->whereHas('entreprise', fn ($e) => $e->where('type_structure_id', $v))
                        ->orWhereHas('contrats.avenants', fn ($a) => $a->where('type_structure_id', $v));
                });
            })
            ->when($filtres['date_debut'] ?? null, fn (Builder $q, $v) => $q->whereDate('date_debut', '>=', $v))
            ->when($filtres['date_fin'] ?? null, fn (Builder $q, $v) => $q->whereDate('date_fin_prevue', '<=', $v))
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $terme): void {
                $q->where(function (Builder $sub) use ($terme): void {
                    $sub->whereHas('beneficiaire', function (Builder $b) use ($terme): void {
                        $b->where('nom', 'like', "%{$terme}%")
                            ->orWhere('prenoms', 'like', "%{$terme}%")
                            ->orWhere('numero_aej', 'like', "%{$terme}%");
                    })->orWhereHas('entreprise', function (Builder $e) use ($terme): void {
                        $e->where('raison_sociale', 'like', "%{$terme}%");
                    });
                });
            })
            ->orderBy('date_fin_prevue');
    }

    /**
     * Propose le renouvellement d'un stage : crée l'avenant en attente du chef d'agence et
     * repousse le terme du stage de la durée demandée.
     *
     * @return array{numero: string, nouvelle_date_fin: string, avenant_id: int}
     */
    public function renouveler(
        Stage $stage,
        int $dureeMois,
        ?string $motif = null,
        ?CarbonInterface $dateEffet = null,
        ?float $nouvellePrime = null,
        ?int $typeStructureId = null,
        ?string $documentPath = null,
        ?int $proposeParId = null
    ): array {
        $contrat = $stage->contrats()->latest('date_debut')->first();

        if (! $contrat) {
            throw new \RuntimeException("Ce stage n'a aucun contrat à renouveler.");
        }

        if ($contrat->avenants()->where($this->avenantEnCours())->exists()) {
            throw new \RuntimeException('Un renouvellement est déjà en cours sur ce contrat.');
        }

        $dateEffetEffective = $dateEffet ?? ($stage->date_fin_prevue?->copy()->addDay() ?? now());
        $nouvelleDateFin = $this->termeRenouvellement($stage->date_fin_prevue ?? $dateEffetEffective, $dureeMois);
        $prime = $nouvellePrime ?? (float) ($contrat->prime_mensuelle ?? 0);

        $avenant = DB::transaction(function () use (
            $stage,
            $contrat,
            $dateEffetEffective,
            $nouvelleDateFin,
            $prime,
            $motif,
            $typeStructureId,
            $documentPath,
            $proposeParId
        ) {
            $avenant = AvenantContrat::create([
                'contrat_id' => $contrat->id,
                'numero' => $this->numeroAvenant($contrat),
                'date_effet' => $dateEffetEffective,
                'nouvelle_date_fin' => $nouvelleDateFin,
                'nouvelle_prime_mensuelle' => $prime,
                'motif' => $motif ?: 'Renouvellement du contrat de stage',
                'statut' => AvenantContrat::STATUT_ATTENTE_CA,
                'type_structure_id' => $typeStructureId,
                'document_avenant_path' => $documentPath,
                'propose_par_id' => $proposeParId ?: Auth::id(),
                'propose_le' => now(),
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
            'avenant_id' => (int) $avenant->id,
        ];
    }

    /**
     * Le chef d'agence valide l'avenant de renouvellement.
     */
    public function validerParChefAgence(AvenantContrat $avenant, int $decideurId): void
    {
        $avenant->forceFill([
            'statut' => AvenantContrat::STATUT_VALIDE,
            'motif_ajournement' => null,
            'decide_le' => now(),
            'decideur_id' => $decideurId,
        ])->save();
    }

    /**
     * Le chef d'agence ajourne l'avenant avec motif pour correction par le CIP.
     */
    public function ajournerParChefAgence(AvenantContrat $avenant, string $observation, int $decideurId): void
    {
        $avenant->forceFill([
            'statut' => AvenantContrat::STATUT_AJOURNE,
            'motif_ajournement' => $observation,
            'decide_le' => now(),
            'decideur_id' => $decideurId,
        ])->save();
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
            'chef_validation' => $this->chefAgenceValidationQuery($filtres)->count(),
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
            ->first(fn ($a) => in_array($a->statut, [AvenantContrat::STATUT_ATTENTE_CA, AvenantContrat::STATUT_AJOURNE], true))
            ?? $stage->contrats->flatMap->avenants->sortByDesc('id')->first();

        $typeStructure = $avenant?->typeStructure ?? $stage->entreprise?->typeStructure;
        $typeStructureNom = $typeStructure?->nom;

        $badgeCouleur = match (true) {
            $typeStructureNom !== null && str_contains(strtoupper($typeStructureNom), 'PUB') => 'success',
            $typeStructureNom !== null && str_contains(strtoupper($typeStructureNom), 'PRIV') => 'primary',
            default => 'secondary',
        };

        return [
            'id' => $stage->id,
            'stage_id' => $stage->id,
            'contrat_id' => $stage->contrats->sortByDesc('id')->first()?->id,
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
                'sexe' => $beneficiaire?->sexe ?? '-',
                'date_naissance' => $beneficiaire?->date_naissance?->format('d/m/Y') ?? '-',
                'type_paiement' => $beneficiaire?->typePaiement?->nom ?? '-',
                'numero_tresor_money' => $beneficiaire?->numero_tresor_money ?? '-',
                'numero_wave' => $beneficiaire?->numero_wave ?? '-',
            ],
            'entreprise' => $stage->entreprise?->raison_sociale ?? '-',
            'entreprise_id' => $stage->entreprise_id,
            'agence' => $stage->agence?->nom ?? '-',
            'agence_id' => $stage->agence_id,
            'source_financement' => $stage->sourceFinancement?->nom ?? '-',
            'source_financement_code' => $stage->sourceFinancement?->code ?? '',
            'source_financement_id' => $stage->source_financement_id,
            'type_stage' => $stage->typeStage?->nom ?? '-',
            'type_stage_id' => $stage->type_stage_id,
            'type_structure' => [
                'id' => $typeStructure?->id,
                'nom' => $typeStructureNom,
                'badge_couleur' => $badgeCouleur,
            ],
            'created_at' => $stage->created_at?->format('d/m/Y') ?? '-',
            'date_demande' => $avenant?->propose_le?->format('d/m/Y') ?? $avenant?->created_at?->format('d/m/Y'),
            'date_debut' => $stage->date_debut?->format('d/m/Y') ?? '-',
            'date_fin_prevue' => $stage->date_fin_prevue?->format('d/m/Y') ?? '-',
            'date_effet' => $avenant?->date_effet?->format('d/m/Y'),
            'nouvelle_date_fin' => $avenant?->nouvelle_date_fin?->format('d/m/Y'),
            'jours_restants' => $stage->date_fin_prevue
                ? now()->startOfDay()->diffInDays($stage->date_fin_prevue, false)
                : null,
            'avenant_id' => $avenant?->id,
            'statut_renouvellement' => $avenant?->statut,
            'motif' => $avenant?->motif,
            'motif_ajournement' => $avenant?->motif_ajournement,
            'document_avenant_path' => $avenant?->document_avenant_path,
            'decideur_nom' => $avenant?->decideur?->name,
            'decide_le' => $avenant?->decide_le?->format('d/m/Y H:i'),
            'prime' => $avenant?->nouvelle_prime_mensuelle ?? $stage->contrats->sortByDesc('id')->first()?->prime_mensuelle,
        ];
    }

    /**
     * Prépare les lignes pour un export CSV.
     *
     * @return array<int, array<int, string>>
     */
    public function exportRows(Builder $query): array
    {
        $rows = [];
        $rows[] = [
            'ID Stage',
            'Date Début Stage',
            'Date Fin Prévue',
            'Nouvelle Date Fin',
            'Agence',
            'Entreprise',
            'Source de Financement',
            'Type de Stage',
            'Type Structure',
            'Numéro AEJ',
            'Nom & Prénoms',
            'Sexe',
            'Date Naissance',
            'Moyen Paiement',
            'Numéro Trésor Money',
            'Numéro Wave',
            'Statut Renouvellement',
            'Motif Ajournement',
        ];

        $stages = $query->get();

        foreach ($stages as $stage) {
            $ligne = $this->formatLigne($stage);
            $rows[] = [
                (string) $ligne['id'],
                (string) $ligne['date_debut'],
                (string) $ligne['date_fin_prevue'],
                (string) ($ligne['nouvelle_date_fin'] ?? '-'),
                (string) $ligne['agence'],
                (string) $ligne['entreprise'],
                (string) $ligne['source_financement'],
                (string) $ligne['type_stage'],
                (string) ($ligne['type_structure']['nom'] ?? 'Non renseigné'),
                (string) ($ligne['beneficiaire']['matricule'] ?? '-'),
                (string) ($ligne['beneficiaire']['nom'].' '.$ligne['beneficiaire']['prenoms']),
                (string) ($ligne['beneficiaire']['sexe'] ?? '-'),
                (string) ($ligne['beneficiaire']['date_naissance'] ?? '-'),
                (string) ($ligne['beneficiaire']['type_paiement'] ?? '-'),
                (string) ($ligne['beneficiaire']['numero_tresor_money'] ?? '-'),
                (string) ($ligne['beneficiaire']['numero_wave'] ?? '-'),
                (string) ($ligne['statut_renouvellement'] ?? 'À renouveler'),
                (string) ($ligne['motif_ajournement'] ?? '-'),
            ];
        }

        return $rows;
    }
}
