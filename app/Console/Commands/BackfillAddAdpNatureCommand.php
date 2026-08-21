<?php

namespace App\Console\Commands;

use App\Enums\CorbeilleEnum;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAddAdpNatureCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:backfill-add-adp-nature
        {--dry-run : Affiche les changements sans les appliquer}
        {--cohorte= : Filtrer par mois de démarrage (ex: 2026-08) !!}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige la nature des droits de paiement (DEMARRAGE vs PRESENCE) et les corbeilles des instances de workflow après la migration legacy.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cohorte = $this->option('cohorte');

        $this->info('=== Backfill nature ADD/ADP et corbeilles ===');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN : Aucune modification ne sera appliquée.');
        }
        if ($cohorte) {
            $this->info("Filtre cohorte : date_debut en {$cohorte}");
        }
        $this->newLine();

        // ─── Étape 1 : Précharger les mappings ───
        // La nature DEMARRAGE/PRESENCE est déterminée directement en comparant
        // la période du droit de paiement avec la date_debut du stage :
        // - Si la période correspond au mois de date_debut → DEMARRAGE (ADD)
        // - Sinon → PRESENCE (ADP)
        //
        // On n'a PAS besoin de charger les 840K lignes de contrat_etape ici ;
        // la logique est purement côté nouveau schéma (date_debut vs periode.code).

        // ─── Étape 2 : Corriger les natures des droits de paiement ───
        $this->newLine();
        $this->info('Étape 1 : Correction des natures DEMARRAGE/PRESENCE sur les droits de paiement...');

        $droitsQuery = DroitPaiement::query()
            ->with(['stage', 'periode'])
            ->where('nature', 'PRESENCE') // Seulement ceux qui ont été migrés avec la mauvaise nature
            ->whereNull('annule_le');

        if ($cohorte) {
            $droitsQuery->whereHas('stage', function ($q) use ($cohorte) {
                $year = (int) substr($cohorte, 0, 4);
                $month = (int) substr($cohorte, 5, 2);
                $q->whereYear('date_debut', $year)
                    ->whereMonth('date_debut', $month);
            });
        }

        // Grouper par stage pour déterminer le 1er paiement (DEMARRAGE)
        $droits = $droitsQuery->orderBy('stage_id')->orderBy('id')->get();

        $nbCorriges = 0;
        $bar = $this->output->createProgressBar($droits->count());
        $bar->start();

        foreach ($droits as $droit) {
            // Déterminer la vraie nature du paiement :
            // - La nature DEMARRAGE (ADD) correspond au 1er mois du stage
            //   (= mois de date_debut du stage)
            // - La nature PRESENCE (ADP) correspond aux mois suivants
            //
            // On ne peut PAS se fier à contrat_etape pour les paiements individuels
            // car le paiement_models ne pointe pas toujours vers le bon pointage.
            // La règle la plus fiable est : si la période du droit correspond au
            // mois de date_debut du stage → DEMARRAGE, sinon → PRESENCE.
            $natureOrigine = 'PRESENCE';

            if ($droit->stage && $droit->periode) {
                $stageDebut = $droit->stage->date_debut;
                $periodeCode = $droit->periode->code;

                if ($stageDebut && $periodeCode) {
                    $moisDebut = Carbon::parse($stageDebut)->format('Y-m');
                    if ($periodeCode === $moisDebut) {
                        $natureOrigine = 'DEMARRAGE';
                    }
                }
            }

            // Si la nature doit être corrigée
            if ($natureOrigine === 'DEMARRAGE' && $droit->nature === 'PRESENCE') {
                $nbCorriges++;

                if (! $dryRun) {
                    // Mettre à jour le droit existant (même ancien_id) en DEMARRAGE
                    // au lieu de créer un nouveau record (unicité sur ancien_id)
                    $droit->update([
                        'nature' => 'DEMARRAGE',
                        'motif_annulation' => null,
                        'annule_le' => null,
                    ]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Droits de paiement à corriger (PRESENCE → DEMARRAGE) : {$nbCorriges}");

        // ─── Étape 3 : Corriger les corbeilles des instances de workflow ───
        $this->newLine();
        $this->info('Étape 2 : Correction des corbeilles des instances de workflow...');

        // Mapper etapetraitement_id → corbeille pour les stages non terminés
        $query = DB::connection('legacy')->table('contrats_pae')
            ->whereNull('deleted_at')
            ->select('id as ancien_id', 'etapetraitement_id', 'etat_chef_agence', 'date_chef_agence', 'date_debut', 'agent_id', 'avis_contrat', 'file_contrat');

        if ($cohorte) {
            $year = (int) substr($cohorte, 0, 4);
            $month = (int) substr($cohorte, 5, 2);
            $query->whereYear('date_debut', $year)->whereMonth('date_debut', $month);
        }

        $total = $query->count();
        $this->info("  Stages legacy candidats : {$total}");

        $nbCorbeilleChanges = 0;
        $definitionsMap = [];

        $mapper = $this;
        $query->orderBy('id')->chunk(1000, function ($contrats) use (&$nbCorbeilleChanges, &$definitionsMap, $dryRun, $mapper): void {
            $ancienIds = $contrats->pluck('ancien_id')->toArray();
            $stagesMap = Stage::withTrashed()->whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');

            $instances = InstanceParcours::whereIn('stage_id', $stagesMap->values()->toArray())
                ->whereNull('terminee_le')
                ->get()
                ->keyBy('stage_id');

            foreach ($contrats as $legacyContrat) {
                $stageId = $stagesMap[$legacyContrat->ancien_id] ?? null;
                if (! $stageId) {
                    continue;
                }

                $instance = $instances->get($stageId);
                if (! $instance) {
                    continue;
                }

                // Mapper la vraie corbeille
                $etapeId = (int) ($legacyContrat->etapetraitement_id ?? 1);

                $corbeilleEnum = match ($etapeId) {
                    1, 7 => CorbeilleEnum::CIP_MES_STAGIAIRES,
                    2 => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
                    3, 9 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
                    4 => CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER,
                    5, 6 => CorbeilleEnum::DESSE_DOUBLONS_TRAITES,
                    8 => CorbeilleEnum::DESSE_SUIVI_PROCESSUS,
                    10 => CorbeilleEnum::CIP_AJOURNE_DMG,
                    11 => CorbeilleEnum::CA_VALIDATION_POINTAGES,
                    12, 18 => CorbeilleEnum::CIP_AJOURNE_CA,
                    13 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
                    14 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
                    15, 16 => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG,
                    17 => CorbeilleEnum::CA_VALIDATION_POINTAGE_AJOURNE_ADP,
                    19 => CorbeilleEnum::CB_DOSSIER_MULTIPLE,
                    20, 21 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,
                    22 => CorbeilleEnum::DMG_ELABORATION_OP,
                    23 => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU,
                    24, 25, 30, 31 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
                    26, 29 => CorbeilleEnum::DMG_OP_REJETE_AC,
                    27, 28 => CorbeilleEnum::CIP_DIFFERE_AC,
                    default => CorbeilleEnum::CIP_MES_STAGIAIRES,
                };

                // Cas Chef d'Agence : etat_chef_agence=0 et pas de date → pas encore en CA
                if ($etapeId === 1 && (int) ($legacyContrat->etat_chef_agence ?? 0) === 0) {
                    $estEligibleCA = (int) ($legacyContrat->agent_id ?? 0) === 3
                        && (int) ($legacyContrat->avis_contrat ?? 0) === 1
                        && ! empty($legacyContrat->file_contrat);

                    if (! $estEligibleCA) {
                        $corbeilleEnum = CorbeilleEnum::CIP_MES_STAGIAIRES;
                    } else {
                        $dateDebut = $mapper->normalizeDate($legacyContrat->date_debut);
                        $corbeilleEnum = ($dateDebut && $dateDebut->format('Y-m') < now()->format('Y-m'))
                            ? CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS
                            : CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE;
                    }
                }

                if ($instance->corbeille_actuelle === $corbeilleEnum->value) {
                    continue;
                }

                $nbCorbeilleChanges++;

                if (! $dryRun) {
                    $defCode = 'STAGE_LEGACY';
                    if (! isset($definitionsMap[$defCode])) {
                        $definitionsMap[$defCode] = DefinitionParcours::firstOrCreate(
                            ['code' => $defCode, 'version' => 1],
                            ['nom' => 'Parcours Legacy', 'active' => true]
                        );
                    }

                    $etapeCode = strtoupper($corbeilleEnum->value);
                    $etape = EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $definitionsMap[$defCode]->id, 'code' => $etapeCode],
                        ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                    );

                    $instance->update([
                        'corbeille_actuelle' => $corbeilleEnum->value,
                        'etape_courante_id' => $etape->id,
                    ]);
                }
            }
        });

        $this->info("  Instances de workflow reclassées : {$nbCorbeilleChanges}");

        // ─── Résumé ───
        $this->newLine();
        $this->info('=== Résumé ===');
        $this->info("  Droits paiement corrigés (PRESENCE → DEMARRAGE) : {$nbCorriges}");
        $this->info("  Instances workflow reclassées : {$nbCorbeilleChanges}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('Aucune modification appliquée (dry-run). Relancez sans --dry-run pour appliquer.');
        }

        return self::SUCCESS;
    }

    private function normalizeDate(?string $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '' || str_starts_with(trim((string) $value), '0000')) {
            return null;
        }

        try {
            $c = Carbon::parse(trim((string) $value));

            return $c->year < 1970 ? null : $c;
        } catch (\Throwable) {
            return null;
        }
    }
}
