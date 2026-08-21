<?php

namespace App\Console\Commands;

use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAddAdpNatureCommand extends Command
{
    public function __construct(private LegacyMapperService $mapper)
    {
        parent::__construct();
    }

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
            $natureOrigine = $this->mapper->naturePaiementPourPeriode(
                (string) $droit->stage?->getAttribute('date_debut'),
                (string) $droit->periode?->getAttribute('code')
            );

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

        $query->orderBy('id')->chunk(1000, function ($contrats) use (&$nbCorbeilleChanges, &$definitionsMap, $dryRun): void {
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

                $corbeilleEnum = $this->mapper->mapChefAgenceCorbeille($legacyContrat);

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
}
