<?php

namespace App\Console\Commands;

use App\Enums\CorbeilleEnum;
use App\Models\Internship\Stage;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Services\Migration\LegacyMapperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillChefAgenceCorbeillesCommand extends Command
{
    protected $signature = 'legacy:backfill-corbeille-ca {--dry-run : N\'applique aucune modification, affiche seulement ce qui serait changé}';

    protected $description = "Recalcule instances_parcours.corbeille_actuelle (et etape_courante_id) pour les dossiers "
        ."mal classés en CA_ATTENTE_VALIDATION_DEMARRAGE/OMIS par l'ancien LegacyMapperService::mapChefAgenceCorbeille, "
        .'sans rejouer toute la migration.';

    /**
     * Seules ces corbeilles peuvent avoir été mal positionnées par le bug : ce sont les deux
     * seules valeurs que produisait l'ancienne branche "etat_chef_agence=0 && date_chef_agence
     * null" de mapChefAgenceCorbeille(). On ne touche jamais à autre chose.
     */
    private const CORBEILLES_CONCERNEES = [
        CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
        CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value,
    ];

    public function handle(LegacyMapperService $mapper): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error("Impossible de se connecter à la base 'legacy' : {$e->getMessage()}");

            return self::FAILURE;
        }

        $definition = DefinitionParcours::where('code', 'STAGE_LEGACY')->where('version', 1)->first();
        if (! $definition) {
            $this->error("Definition de parcours 'STAGE_LEGACY' introuvable : la migration initiale a-t-elle été jouée ?");

            return self::FAILURE;
        }

        // Seuls les dossiers avec etat_chef_agence=0 && date_chef_agence "vide" passent par la
        // branche corrigée de mapChefAgenceCorbeille(). MySQL stocke parfois une date "zéro"
        // ('0000-00-00 00:00:00') plutôt qu'un vrai NULL pour ce champ : LegacyMapperService::
        // normalizeLegacyDate() traite déjà les deux comme équivalents (null), donc le candidat
        // doit aussi couvrir ce cas, sous peine de laisser des dossiers mal classés par
        // l'ancienne migration (sans le correctif) hors du périmètre du backfill.
        $query = DB::connection('legacy')->table('contrats_pae')
            ->where('etat_chef_agence', 0)
            ->where(function ($q) {
                $q->whereNull('date_chef_agence')
                    ->orWhere('date_chef_agence', '0000-00-00 00:00:00');
            });

        $total = $query->count();
        $this->info("Contrats legacy candidats (etat_chef_agence=0, date_chef_agence null) : {$total}");

        $inspected = 0;
        $changed = 0;
        $transitions = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunk(500, function ($contrats) use (
            $mapper, $definition, $dryRun, &$inspected, &$changed, &$transitions, $bar
        ) {
            $ancienIds = $contrats->pluck('id')->toArray();

            $stagesMap = Stage::withTrashed()->whereIn('ancien_id', $ancienIds)->pluck('id', 'ancien_id');

            $instancesMap = InstanceParcours::whereIn('stage_id', $stagesMap->values()->toArray())
                ->whereIn('corbeille_actuelle', self::CORBEILLES_CONCERNEES)
                ->whereNull('terminee_le')
                ->get()
                ->keyBy('stage_id');

            foreach ($contrats as $legacyContrat) {
                $stageId = $stagesMap[$legacyContrat->id] ?? null;

                if (! $stageId) {
                    $bar->advance();

                    continue;
                }

                $instance = $instancesMap->get($stageId);

                if (! $instance) {
                    // Le dossier n'est plus (ou n'a jamais été) dans une corbeille
                    // CA_ATTENTE_VALIDATION_* : il a déjà avancé dans le vrai workflow depuis
                    // la migration, on n'y touche surtout pas.
                    $bar->advance();

                    continue;
                }

                $inspected++;

                $nouvelleCorbeille = $mapper->mapChefAgenceCorbeille($legacyContrat);
                if ($nouvelleCorbeille === CorbeilleEnum::CIP_MES_STAGIAIRES) {
                    $statutLegacy = (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1);
                    $nouvelleCorbeille = $mapper->mapStatutStageToCorbeille($statutLegacy);
                }

                if ($nouvelleCorbeille->value === $instance->corbeille_actuelle) {
                    $bar->advance();

                    continue;
                }

                $transitionKey = "{$instance->corbeille_actuelle} => {$nouvelleCorbeille->value}";
                $transitions[$transitionKey] = ($transitions[$transitionKey] ?? 0) + 1;

                if (! $dryRun) {
                    $etapeCode = strtoupper($nouvelleCorbeille->value);
                    $etape = EtapeParcours::firstOrCreate(
                        ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                        ['nom' => str_replace('_', ' ', $etapeCode), 'initiale' => false, 'finale' => false]
                    );

                    $instance->update([
                        'corbeille_actuelle' => $nouvelleCorbeille->value,
                        'etape_courante_id' => $etape->id,
                    ]);
                }

                $changed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Dossiers actuellement dans une corbeille CA_ATTENTE_VALIDATION_* : {$inspected}");
        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Dossiers reclassés : {$changed}");

        if (! empty($transitions)) {
            $rows = collect($transitions)->map(fn ($n, $t) => [$t, $n])->values()->toArray();
            $this->table(['Transition', 'Nombre'], $rows);
        }

        return self::SUCCESS;
    }
}
