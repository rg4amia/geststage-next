<?php
$file = 'app/Services/Migration/LegacyMapperService.php';
$content = file_get_contents($file);
$search = "public function mapPointageToCorbeille(?int \$legacyEtapeId, string \$statut, string \$nature): CorbeilleEnum\n    {";
$replace = "public function mapPointageToCorbeille(?int \$legacyEtapeId, string \$statut, string \$nature, ?int \$etatChefAgenceContrat = null): CorbeilleEnum\n    {\n        if (\$etatChefAgenceContrat === 100) {\n            return CorbeilleEnum::CA_VALIDATION_POINTAGES;\n        }";
$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);

$file2 = 'app/Console/Commands/MigrateLegacyDataCommand.php';
$content2 = file_get_contents($file2);
$search2 = "\$agencesParStage = Stage::whereIn('ancien_id', \$stagiaireIds)->pluck('agence_id', 'ancien_id')->toArray();";
$replace2 = "\$agencesParStage = Stage::whereIn('ancien_id', \$stagiaireIds)->pluck('agence_id', 'ancien_id')->toArray();\n            \$etatsChefAgence = DB::connection('legacy')->table('contrats_pae')->whereIn('id', \$stagiaireIds)->pluck('etat_chef_agence', 'id')->toArray();";
$content2 = str_replace($search2, $replace2, $content2);

$search3 = "\$corbeilleEnum = \$this->mapper->mapPointageToCorbeille(\n                    isset(\$legacyPointage->etape_id) ? (int) \$legacyPointage->etape_id : null,\n                    \$statut,\n                    \$naturePointage\n                )->value;";
$replace3 = "\$corbeilleEnum = \$this->mapper->mapPointageToCorbeille(\n                    isset(\$legacyPointage->etape_id) ? (int) \$legacyPointage->etape_id : null,\n                    \$statut,\n                    \$naturePointage,\n                    isset(\$etatsChefAgence[\$legacyPointage->stagiaire_id]) ? (int) \$etatsChefAgence[\$legacyPointage->stagiaire_id] : null\n                )->value;";
$content2 = str_replace($search3, $replace3, $content2);
file_put_contents($file2, $content2);
echo "Patched migration.\n";
