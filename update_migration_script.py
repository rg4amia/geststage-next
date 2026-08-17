import re

file_path = 'app/Console/Commands/MigrateLegacyDataCommand.php'

with open(file_path, 'r') as f:
    content = f.read()

# Update migrateStages
old_stages = """                $definition = DefinitionParcours::firstOrCreate(
                    ['code' => 'STAGE_LEGACY', 'version' => 1],
                    ['nom' => 'Parcours Legacy', 'active' => true]
                );

                $etape = EtapeParcours::firstOrCreate(
                    ['definition_parcours_id' => $definition->id, 'code' => 'INIT_LEGACY'],
                    ['nom' => 'Initiale Legacy', 'initiale' => true, 'finale' => false]
                );

                InstanceParcours::updateOrCreate(
                    ['stage_id' => $stage->id],
                    [
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                    ]
                );"""

new_stages = """                $definition = DefinitionParcours::firstOrCreate(
                    ['code' => 'STAGE_LEGACY', 'version' => 1],
                    ['nom' => 'Parcours Legacy', 'active' => true]
                );

                $etapeCode = strtoupper($corbeilleEnum->value);
                $etapeNom = str_replace('_', ' ', $etapeCode);

                $etape = EtapeParcours::firstOrCreate(
                    ['definition_parcours_id' => $definition->id, 'code' => $etapeCode],
                    ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                );

                InstanceParcours::updateOrCreate(
                    ['stage_id' => $stage->id],
                    [
                        'definition_parcours_id' => $definition->id,
                        'etape_courante_id' => $etape->id,
                        'corbeille_actuelle' => $corbeilleEnum->value,
                    ]
                );"""

# Update migrateEvenements
old_evenements = """                    if ($instance) {
                        $corbeilleCible = $this->mapper->mapStatutStageToCorbeille($legacyEvent->etape_id ?? 1)->value;

                        EvenementParcours::updateOrCreate(
                            [
                                'instance_parcours_id' => $instance->id,
                                'cle_idempotence' => 'mig_'.$legacyEvent->id.'_'.$instance->id,
                            ],
                            [
                                'etape_cible_id' => $instance->etape_courante_id, // we might not know the exact target step, default to current
                                'type' => 'MIGRATION_STATUT',
                                'donnees' => json_encode([
                                    'commentaire' => $legacyEvent->commentaire,
                                    'description' => "Passage à l'étape legacy ID : ".$legacyEvent->etape_id,
                                    'corbeille_cible' => $corbeilleCible,
                                ]),
                                'auteur_id' => 1, // default user
                                'survenu_le' => $this->mapper->normalizeLegacyDate($legacyEvent->created_at ?? null) ?? now(),
                            ]
                        );
                    }"""

new_evenements = """                    if ($instance) {
                        $corbeilleCible = $this->mapper->mapStatutStageToCorbeille($legacyEvent->etape_id ?? 1)->value;
                        
                        $etapeCode = strtoupper($corbeilleCible);
                        $etapeNom = str_replace('_', ' ', $etapeCode);

                        $etapeCible = EtapeParcours::firstOrCreate(
                            ['definition_parcours_id' => $instance->definition_parcours_id, 'code' => $etapeCode],
                            ['nom' => $etapeNom, 'initiale' => false, 'finale' => false]
                        );

                        EvenementParcours::updateOrCreate(
                            [
                                'instance_parcours_id' => $instance->id,
                                'cle_idempotence' => 'mig_'.$legacyEvent->id.'_'.$instance->id,
                            ],
                            [
                                'etape_cible_id' => $etapeCible->id,
                                'type' => 'MIGRATION_STATUT',
                                'donnees' => json_encode([
                                    'commentaire' => $legacyEvent->commentaire,
                                    'description' => "Passage à l'étape legacy ID : ".$legacyEvent->etape_id,
                                    'corbeille_cible' => $corbeilleCible,
                                ]),
                                'auteur_id' => 1, // default user
                                'survenu_le' => $this->mapper->normalizeLegacyDate($legacyEvent->created_at ?? null) ?? now(),
                            ]
                        );
                    }"""

content = content.replace(old_stages, new_stages)
content = content.replace(old_evenements, new_evenements)

with open(file_path, 'w') as f:
    f.write(content)
