<?php

namespace App\Domain\Payment\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\LigneDossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class DmgService
{
    /** @param array<string, mixed> $filters */
    public function attentePaiementDemarrage(array $filters, ?string $mois = null): Builder
    {
        return $this->attentePaiement(CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE, $filters, $mois);
    }

    /** @param array<string, mixed> $filters */
    public function attentePaiementPresence(array $filters, ?string $mois = null): Builder
    {
        return $this->attentePaiement(CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE, $filters, $mois);
    }

    /**
     * Applique le filtre "attestation demarrage" : le stage doit commencer dans le mois selectionne.
     * Equivalent legacy : ContratsPae::scopeAttestationDemarrage()
     */
    private function applyAttestationDemarrageFilter(Builder $query, string $mois): Builder
    {
        $date = Carbon::parse($mois);

        return $query->whereHas('droitPaiement.stage', function (Builder $s) use ($date): void {
            $s->whereYear('date_debut', $date->year)
              ->whereMonth('date_debut', $date->month);
        });
    }

    /**
     * Applique le filtre "attestation presence" : le stage doit etre actif pendant le mois selectionne.
     * Equivalent legacy : ContratsPae::scopeAttestationPresence()
     */
    private function applyAttestationPresenceFilter(Builder $query, string $mois): Builder
    {
        $startDate = Carbon::parse($mois)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($mois)->endOfMonth()->toDateString();

        return $query->whereHas('droitPaiement.stage', function (Builder $s) use ($startDate, $endDate): void {
            $s->where('date_debut', '<=', $endDate)
              ->where('date_fin_prevue', '>=', $startDate);
        });
    }

    /** @param array<string, mixed> $filters */
    private function attentePaiement(CorbeilleEnum $corbeille, array $filters, ?string $mois): Builder
    {
        $query = Paiement::query()
            ->with([
                'droitPaiement.stage.beneficiaire', 'droitPaiement.stage.entreprise.typeStructure',
                'droitPaiement.stage.agence', 'droitPaiement.stage.sourceFinancement',
                'droitPaiement.stage.typeStage', 'droitPaiement.stage.contrats',
                'droitPaiement.stage.instanceParcours', 'droitPaiement.periode',
            ])
            ->where('statut', 'A_TRAITER')
            ->whereHas('droitPaiement', function (Builder $droit) use ($corbeille, $mois): void {
                $droit->whereNull('annule_le')
                    ->when($mois, fn (Builder $q) => $q->whereHas('periode', fn (Builder $p) => $p->where('code', $mois)))
                    ->whereHas('stage.contrats')
                    ->whereHas('stage.instanceParcours', function (Builder $instance) use ($corbeille): void {
                        $instance->whereNull('terminee_le')
                            ->where(function (Builder $workflow) use ($corbeille): void {
                                // 1) Priorité : tâche ouverte dans la corbeille cible
                                $workflow->whereHas('taches', fn (Builder $tache) => $tache
                                    ->where('code_corbeille', $corbeille->value)
                                    ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE']))
                                // 2) Fallback : aucune tâche ouverte ET corbeille_actuelle correspondante
                                //    (couvre le cas où les tâches n'existent pas encore ou sont toutes fermées)
                                ->orWhere(function (Builder $fallback) use ($corbeille): void {
                                    $fallback->where('corbeille_actuelle', $corbeille->value)
                                        ->where(function (Builder $noOpenTasks): void {
                                            $noOpenTasks->whereDoesntHave('taches')
                                                ->orWhere(function (Builder $onlyClosed): void {
                                                    $onlyClosed->whereDoesntHave('taches', fn (Builder $t) => $t
                                                        ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE']));
                                                });
                                        });
                                });
                            });
                    });
            });

        $this->applyFilters($query, $filters);

        // Filtre de cohérence : les paiements doivent concerner des stages
        // actifs durant la periode selectionnee (match legacy attestationDemarrage / attestationPresence)
        if ($mois) {
            if ($corbeille === CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE) {
                $this->applyAttestationDemarrageFilter($query, $mois);
            } elseif ($corbeille === CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE) {
                $this->applyAttestationPresenceFilter($query, $mois);
            }
        }

        // Mur Pare-feu (Doublon DESSE) : on exclut de la DMG (Démarrage et Présence) tous les stagiaires détectés comme doublons non traités.
        app(\App\Domain\Workflow\Services\DesseDoublonService::class)->applyDuplicateExclusionFilter($query, 'droitPaiement.stage');

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['agence_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('droitPaiement.stage', fn (Builder $s) => $s->where('agence_id', $id)))
            ->when($filters['entreprise_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('droitPaiement.stage', fn (Builder $s) => $s->where('entreprise_id', $id)))
            ->when($filters['source_financement_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('droitPaiement', fn (Builder $d) => $d->where('source_financement_id', $id)))
            ->when($filters['type_stage_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('droitPaiement.stage', fn (Builder $s) => $s->where('type_stage_id', $id)))
            ->when($filters['type_structure_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('droitPaiement.stage.entreprise', fn (Builder $e) => $e->where('type_structure_id', $id)))
            ->when(($filters['date_debut'] ?? null) && ($filters['date_fin'] ?? null), fn (Builder $q) => $q
                ->whereHas('droitPaiement.stage', fn (Builder $s) => $s->whereBetween('date_debut', [$filters['date_debut'], $filters['date_fin']])))
            ->when($filters['dossier_physique'] ?? null, fn (Builder $q, $statut) => $q->where('statut_dossier_physique', $statut))
            ->when($filters['search'] ?? null, function (Builder $q, string $search): void {
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $term = '%'.addcslashes($search, '%_').'%';
                $q->whereHas('droitPaiement.stage.beneficiaire', fn (Builder $b) => $b
                    ->where('nom', $operator, $term)->orWhere('prenoms', $operator, $term)
                    ->orWhere('numero_aej', $operator, $term));
            });
    }

    public function applyCohorteFilter(Builder $query, string $cohorte): Builder
    {
        $day = fn (string $column) => $this->sqlDay($column);
        $month = fn (string $column) => $this->sqlMonth($column);

        $c1 = function (Builder $d) use ($day, $month) {
            $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
              ->whereRaw($day('s.date_debut').' BETWEEN 1 AND 5')
              ->where(function (Builder $q) use ($day, $month) {
                  $q->whereRaw($month('droits_paiement.created_at').' = '.$month('s.date_debut').' AND '.$day('droits_paiement.created_at').' >= 11')
                    ->orWhereRaw($month('droits_paiement.created_at').' > '.$month('s.date_debut'));
              });
        };

        $c2 = function (Builder $d) use ($day, $month) {
            $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
              ->whereRaw($day('s.date_debut').' = 10')
              ->where(function (Builder $q) use ($day, $month) {
                  $q->whereRaw($month('droits_paiement.created_at').' = '.$month('s.date_debut').' AND '.$day('droits_paiement.created_at').' >= 21')
                    ->orWhereRaw($month('droits_paiement.created_at').' > '.$month('s.date_debut'));
              });
        };

        $c3 = function (Builder $d) use ($day, $month) {
            $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
              ->whereRaw($day('s.date_debut').' = 20')
              ->whereRaw($month('droits_paiement.created_at').' > '.$month('s.date_debut'));
        };

        return match (str_replace('cohorte', '', strtolower($cohorte))) {
            '1' => $query->whereHas('droitPaiement', $c1),
            '2' => $query->whereHas('droitPaiement', $c2),
            '3' => $query->whereHas('droitPaiement', $c3),
            default => $query->whereDoesntHave('droitPaiement', $c1)
                             ->whereDoesntHave('droitPaiement', $c2)
                             ->whereDoesntHave('droitPaiement', $c3),
        };
    }

    /**
     * `EXTRACT(... FROM ...)` n'existe que sous Postgres : la suite de tests tourne sous SQLite
     * (voir phpunit.xml), qui expose les composantes de date via `strftime()`.
     */
    private function sqlDay(string $column): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "EXTRACT(DAY FROM {$column})"
            : "CAST(strftime('%d', {$column}) AS INTEGER)";
    }

    private function sqlMonth(string $column): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "EXTRACT(MONTH FROM {$column})"
            : "CAST(strftime('%m', {$column}) AS INTEGER)";
    }

    /** @param list<int> $paiementIds @return Collection<int, DossierPaiement> */
    public function genererDossiersPaiement(int $periodeId, array $paiementIds, User $auteur): Collection
    {
        return DB::transaction(function () use ($periodeId, $paiementIds, $auteur): Collection {
            $ids = array_values(array_unique($paiementIds));
            $paiements = Paiement::query()->lockForUpdate()
                ->with(['droitPaiement.stage.instanceParcours'])
                ->whereIn('id', $ids)->where('statut', 'A_TRAITER')
                ->whereHas('droitPaiement', fn (Builder $d) => $d->where('periode_id', $periodeId)->whereNull('annule_le'))
                ->get();
            if ($paiements->count() !== count($ids)) {
                throw ValidationException::withMessages(['paiement_ids' => 'La selection contient un paiement absent, annule ou deja traite.']);
            }

            $corbeilles = [CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value, CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value];
            if ($paiements->contains(fn (Paiement $p) => ! in_array($p->droitPaiement?->stage?->instanceParcours?->corbeille_actuelle, $corbeilles, true))) {
                throw ValidationException::withMessages(['paiement_ids' => 'Un paiement ne se trouve plus dans une corbeille DMG.']);
            }

            $dossiers = collect();
            $groupes = $paiements->groupBy(function (Paiement $paiement): string {
                $droit = $paiement->droitPaiement;
                $nature = $droit->stage->instanceParcours->corbeille_actuelle === CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE->value ? 'DM' : 'PS';

                return "{$droit->stage->agence_id}:{$droit->source_financement_id}:{$nature}";
            });

            $now = now();
            $lignes = [];
            $decisions = [];

            foreach ($groupes as $cle => $groupe) {
                [$agenceId, $financementId, $nature] = explode(':', $cle);
                $dossier = DossierPaiement::create([
                    'uuid_public' => (string) Str::uuid(), 'periode_id' => $periodeId,
                    'agence_id' => (int) $agenceId, 'source_financement_id' => (int) $financementId,
                    'numero' => $this->numero('DOS-'.$nature), 'nature' => $nature,
                    'statut' => 'BROUILLON', 'montant_total' => $groupe->sum('montant'),
                ]);
                foreach ($groupe as $paiement) {
                    $lignes[] = [
                        'dossier_paiement_id' => $dossier->id, 'paiement_id' => $paiement->id,
                        'montant' => $paiement->montant, 'ajoute_le' => $now,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                    $decisions[] = [
                        'paiement_id' => $paiement->id, 'auteur_id' => $auteur->id,
                        'decision' => 'VALIDE_DMG', 'statut_avant' => 'A_TRAITER', 'statut_apres' => 'EN_DOSSIER',
                        'decide_le' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
                $dossiers->push($dossier);
            }

            DB::table('lignes_dossiers_paiement')->insert($lignes);
            DB::table('decisions_paiements')->insert($decisions);
            Paiement::whereIn('id', $paiements->modelKeys())->update(['statut' => 'EN_DOSSIER']);

            return $dossiers;
        });
    }

    /** @param list<int> $paiementIds */
    public function ajournerPaiements(array $paiementIds, string $motif, User $auteur): int
    {
        return DB::transaction(function () use ($paiementIds, $motif, $auteur): int {
            $ids = array_values(array_unique($paiementIds));
            $paiements = Paiement::query()->lockForUpdate()->with('droitPaiement.stage.instanceParcours')
                ->whereIn('id', $ids)->where('statut', 'A_TRAITER')->get();
            if ($paiements->count() !== count($ids)) {
                throw ValidationException::withMessages(['paiement_ids' => 'Selection de paiements invalide.']);
            }
            foreach ($paiements as $paiement) {
                $paiement->update(['statut' => 'AJOURNE_DMG']);
                $paiement->droitPaiement?->stage?->instanceParcours?->update(['corbeille_actuelle' => CorbeilleEnum::CIP_MES_STAGIAIRES->value]);
                DecisionPaiement::enregistrer($paiement, $auteur, 'AJOURNE_DMG', $motif, 'A_TRAITER', 'AJOURNE_DMG');
            }

            return $paiements->count();
        });
    }

    /** @param list<int> $paiementIds */
    public function marquerDossiersPhysiques(array $paiementIds, string $statut, User $auteur): int
    {
        return DB::transaction(function () use ($paiementIds, $statut, $auteur): int {
            $paiements = Paiement::query()->lockForUpdate()->whereIn('id', array_unique($paiementIds))->where('statut', 'A_TRAITER')->get();
            foreach ($paiements as $paiement) {
                $paiement->update(['statut_dossier_physique' => $statut, 'dossier_physique_marque_par_id' => $auteur->id, 'dossier_physique_marque_le' => now()]);
                DecisionPaiement::enregistrer($paiement, $auteur, 'DOSSIER_PHYSIQUE_'.$statut);
            }

            return $paiements->count();
        });
    }

    public function transmettreDossierCb(DossierPaiement $dossier): void
    {
        $this->changerStatut($dossier, 'BROUILLON', 'TRANSMIS_CB');
    }

    /** @param list<int> $dossierIds */
    public function grouperDossiers(int $periodeId, array $dossierIds, ?string $observation, User $auteur): DossierGroupe
    {
        return DB::transaction(function () use ($periodeId, $dossierIds, $observation, $auteur): DossierGroupe {
            $ids = array_values(array_unique($dossierIds));
            $dossiers = DossierPaiement::query()->lockForUpdate()
                ->whereIn('id', $ids)
                ->where('periode_id', $periodeId)
                ->where('statut', 'BROUILLON')
                ->whereNull('ordre_paiement_id')
                ->whereDoesntHave('groupes')
                ->get();

            if ($dossiers->isEmpty() || $dossiers->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'dossiers' => 'Chaque dossier doit etre en brouillon, sans OP et sans multi-dossier actif.',
                ]);
            }
            if ($dossiers->pluck('nature')->unique()->count() !== 1 || $dossiers->pluck('source_financement_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages([
                    'dossiers' => 'Un multi-dossier doit partager la meme nature et la meme source de financement.',
                ]);
            }

            $groupe = DossierGroupe::create([
                'uuid_public' => (string) Str::uuid(),
                'periode_id' => $periodeId,
                'source_financement_id' => $dossiers->first()->source_financement_id,
                'cree_par_id' => $auteur->id,
                'numero' => $this->numero('GRP'),
                'nature' => $dossiers->first()->nature,
                'statut' => 'BROUILLON',
                'montant_total' => $dossiers->sum('montant_total'),
                'observation' => $observation,
            ]);
            $codePeriode = (string) Periode::whereKey($periodeId)->value('code');
            $groupe->update([
                'numero' => $groupe->nature.substr($codePeriode, -2).'-'.$groupe->id.'-G',
            ]);

            $now = now();
            DB::table('lignes_dossiers_groupes')->insert($dossiers->map(fn (DossierPaiement $dossier) => [
                'dossier_groupe_id' => $groupe->id,
                'dossier_paiement_id' => $dossier->id,
                'ajoute_le' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            return $groupe->load(['dossiers', 'periode', 'sourceFinancement']);
        });
    }

    public function transmettreGroupeCb(DossierGroupe $groupe): void
    {
        DB::transaction(function () use ($groupe): void {
            $locked = DossierGroupe::query()->lockForUpdate()->with('dossiers')->findOrFail($groupe->id);
            if (
                $locked->statut !== 'BROUILLON'
                || $locked->dossiers->isEmpty()
                || $locked->dossiers->contains(fn (DossierPaiement $dossier) => $dossier->statut !== 'BROUILLON')
            ) {
                throw ValidationException::withMessages(['groupe' => 'Le multi-dossier ne peut plus etre transmis.']);
            }
            DossierPaiement::whereKey($locked->dossiers->modelKeys())->where('statut', 'BROUILLON')->update(['statut' => 'TRANSMIS_CB']);
            $locked->update(['statut' => 'TRANSMIS_CB']);
        });
    }

    public function retirerDossierGroupe(DossierGroupe $groupe, DossierPaiement $dossier, string $motif): void
    {
        DB::transaction(function () use ($groupe, $dossier, $motif): void {
            $groupe = DossierGroupe::query()->lockForUpdate()->findOrFail($groupe->id);
            if ($groupe->statut !== 'BROUILLON') {
                throw ValidationException::withMessages(['groupe' => 'Seul un multi-dossier en brouillon peut etre modifie.']);
            }
            $ligne = DB::table('lignes_dossiers_groupes')
                ->where('dossier_groupe_id', $groupe->id)
                ->where('dossier_paiement_id', $dossier->id)
                ->whereNull('retire_le')
                ->lockForUpdate()
                ->first();
            if (! $ligne) {
                throw ValidationException::withMessages(['dossier_id' => 'Ce dossier ne fait pas partie du multi-dossier.']);
            }
            DB::table('lignes_dossiers_groupes')->where('id', $ligne->id)->update([
                'retire_le' => now(), 'motif_retrait' => $motif, 'updated_at' => now(),
            ]);
            $reste = $groupe->dossiers()->sum('dossiers_paiement.montant_total');
            $groupe->update(['montant_total' => $reste, 'statut' => $reste > 0 ? 'BROUILLON' : 'ANNULE']);
        });
    }

    public function retirerPaiementDossier(DossierPaiement $dossier, Paiement $paiement, string $motif, User $auteur): void
    {
        DB::transaction(function () use ($dossier, $paiement, $motif, $auteur): void {
            $ligne = LigneDossierPaiement::query()->lockForUpdate()->where('dossier_paiement_id', $dossier->id)
                ->where('paiement_id', $paiement->id)->whereNull('retire_le')->firstOrFail();
            $ligne->update(['retire_le' => now(), 'motif_retrait' => $motif]);
            $paiement->update(['statut' => 'A_TRAITER']);
            $dossier->decrement('montant_total', $ligne->montant);
            DecisionPaiement::enregistrer($paiement, $auteur, 'RETIRE_DOSSIER', $motif, 'EN_DOSSIER', 'A_TRAITER');
        });
    }

    /** @param list<int> $dossierIds */
    public function elaborerOp(array $dossierIds, int $periodeId): OrdrePaiement
    {
        return DB::transaction(function () use ($dossierIds, $periodeId): OrdrePaiement {
            $ids = array_values(array_unique($dossierIds));
            $dossiers = DossierPaiement::query()->lockForUpdate()->whereIn('id', $ids)
                ->where('periode_id', $periodeId)->where('statut', 'VALIDE_CB')->get();
            if ($dossiers->count() !== count($ids) || $dossiers->isEmpty() || $dossiers->pluck('source_financement_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['dossiers' => 'Les dossiers doivent etre valides CB et partager le meme financement.']);
            }
            $op = OrdrePaiement::create(['uuid_public' => (string) Str::uuid(), 'numero' => $this->numero('OP'),
                'periode_id' => $periodeId, 'source_financement_id' => $dossiers->first()->source_financement_id,
                'montant_total' => $dossiers->sum('montant_total'), 'statut' => 'BROUILLON']);
            DossierPaiement::whereKey($ids)->update(['ordre_paiement_id' => $op->id, 'statut' => 'EN_OP']);

            return $op;
        });
    }

    /** @param list<int> $opIds */
    public function creerBordereau(array $opIds, int $periodeId): BordereauPaiement
    {
        return DB::transaction(function () use ($opIds, $periodeId): BordereauPaiement {
            $ids = array_values(array_unique($opIds));
            $ops = OrdrePaiement::query()->lockForUpdate()->whereIn('id', $ids)
                ->where('periode_id', $periodeId)->where('statut', 'BROUILLON')->get();
            if ($ops->count() !== count($ids) || $ops->isEmpty() || $ops->pluck('source_financement_id')->unique()->count() !== 1) {
                throw ValidationException::withMessages(['ops' => 'Les OP doivent etre disponibles et partager le meme financement.']);
            }
            $bordereau = BordereauPaiement::create(['uuid_public' => (string) Str::uuid(), 'numero' => $this->numero('BORD'),
                'periode_id' => $periodeId, 'source_financement_id' => $ops->first()->source_financement_id,
                'montant_total' => $ops->sum('montant_total'), 'statut' => 'BROUILLON']);
            OrdrePaiement::whereKey($ids)->update(['bordereau_paiement_id' => $bordereau->id, 'statut' => 'EN_BORDEREAU']);

            return $bordereau;
        });
    }

    public function transmettreBordereauAc(BordereauPaiement $bordereau): void
    {
        $this->changerStatut($bordereau, 'BROUILLON', 'TRANSMIS_AC');
    }

    private function changerStatut(DossierPaiement|BordereauPaiement $model, string $attendu, string $nouveau): void
    {
        if ($model->newQuery()->whereKey($model->getKey())->where('statut', $attendu)->update(['statut' => $nouveau]) !== 1) {
            throw ValidationException::withMessages(['statut' => "Le statut {$attendu} n est plus courant."]);
        }
    }

    private function numero(string $prefixe): string
    {
        return $prefixe.'-'.now()->format('Ym').'-'.strtoupper(Str::random(8));
    }
}
