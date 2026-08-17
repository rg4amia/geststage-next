import re

file_path = 'app/Console/Commands/MigrateLegacyDataCommand.php'

with open(file_path, 'r') as f:
    content = f.read()

# 1. Update migratePointages
pointages_replace = """                        VersionPointage::updateOrCreate(
                            ['pointage_id' => $pointage->id, 'numero_version' => 1],
                            [
                                'presence' => 'PRESENT',
                                'jours_presents' => 30,
                                'jours_absents' => 0,
                                'observation' => $legacyPointage->commentaire,
                                'saisi_le' => $date,
                            ]
                        );"""

pointages_new = """                        VersionPointage::updateOrCreate(
                            ['pointage_id' => $pointage->id, 'numero_version' => 1],
                            [
                                'presence' => 'PRESENT',
                                'jours_presents' => 30,
                                'jours_absents' => 0,
                                'observation' => $legacyPointage->commentaire,
                                'saisi_le' => $date,
                            ]
                        );

                        // CREATE PARCOURS FOR POINTAGE
                        $definition = \App\Models\Workflow\DefinitionParcours::firstOrCreate(
                            ['code' => 'POINTAGE_LEGACY', 'version' => 1],
                            ['nom' => 'Parcours Pointage Legacy', 'active' => true]
                        );

                        $corbeilleEnum = 'cip_mes_stagiaires';
                        if ($statut === 'AJOURNE_DMG') {
                            $corbeilleEnum = 'cip_pointage_ajourne_dmg';
                        } elseif ($statut === 'AJOURNE_CA') {
                            $corbeilleEnum = 'cip_ajourne_ca';
                        } elseif ($statut === 'VALIDE') {
                            $corbeilleEnum = 'dmg_attente_paiement_presence';
                        } elseif ($statut === 'SOUMIS') {
                            $corbeilleEnum = 'ca_validation_pointages';
                        }

                        $etapeCode = strtoupper($corbeilleEnum);
                        $etapeNom = str_replace('_', ' ', $etapeCode);

                        $etape = \App\Models\Workflow\EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                            ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                        );

                        \App\Models\Workflow\InstanceParcours::updateOrCreate(
                            ['pointage_id' => $pointage->id],
                            [
                                'definition_parcours_id' => $definition->id,
                                'etape_courante_id' => $etape->id,
                                'corbeille_actuelle' => $corbeilleEnum,
                            ]
                        );"""
content = content.replace(pointages_replace, pointages_new)

# 2. Update migrateEvenements
evenements_replace = """        $query->orderBy('id')->chunk(5000, function ($historique) use (&$bar) {
            $contratIds = $historique->pluck('contrat_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $contratIds)->pluck('id', 'ancien_id')->toArray();
            $instancesMap = InstanceParcours::whereIn('stage_id', array_values($stagesMap))->get()->keyBy('stage_id');

            foreach ($historique as $legacyEvent) {
                $stage_id = $stagesMap[$legacyEvent->contrat_id] ?? null;
                if ($stage_id) {
                    $instance = $instancesMap[$stage_id] ?? null;
                    if ($instance) {"""

evenements_new = """        $query->orderBy('id')->chunk(5000, function ($historique) use (&$bar) {
            // MAP STAGES
            $contratIds = $historique->pluck('contrat_id')->filter()->unique()->toArray();
            $stagesMap = Stage::whereIn('ancien_id', $contratIds)->pluck('id', 'ancien_id')->toArray();
            $instancesStageMap = InstanceParcours::whereIn('stage_id', array_values($stagesMap))->get()->keyBy('stage_id');

            // MAP POINTAGES
            $legacyPointageIds = $historique->pluck('pointage_id')->filter()->unique()->toArray();
            $pointagesMap = \App\Models\Attendance\Pointage::whereIn('ancien_id', $legacyPointageIds)->pluck('id', 'ancien_id')->toArray();
            $instancesPointageMap = InstanceParcours::whereIn('pointage_id', array_values($pointagesMap))->get()->keyBy('pointage_id');

            foreach ($historique as $legacyEvent) {
                $instance = null;
                
                if ($legacyEvent->pointage_id) {
                    $pointage_id = $pointagesMap[$legacyEvent->pointage_id] ?? null;
                    if ($pointage_id) {
                        $instance = $instancesPointageMap[$pointage_id] ?? null;
                    }
                } else {
                    $stage_id = $stagesMap[$legacyEvent->contrat_id] ?? null;
                    if ($stage_id) {
                        $instance = $instancesStageMap[$stage_id] ?? null;
                    }
                }

                if ($instance) {"""

content = content.replace(evenements_replace, evenements_new)

with open(file_path, 'w') as f:
    f.write(content)
