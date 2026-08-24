import re

with open('app/Console/Commands/MigrateLegacyDataCommand.php', 'r') as f:
    content = f.read()

# Fix migrateStages
content = re.sub(
    r'\$preloadedTasks = \$tachesExistantes\[\$instance->id\] \?\? collect\(\);\s*\$this->syncOpenTask\(\$instance, \$etape, \$corbeilleEnum, \$agence_id, \$termineeLe !== null, \$preloadedTasks\);',
    r'$preloadedTasks = $tachesExistantes[$instance->id] ?? collect();\n                $tachesExistantes[$instance->id] = $preloadedTasks;\n                $this->syncOpenTask($instance, $etape, $corbeilleEnum, $agence_id, $termineeLe !== null, $preloadedTasks);',
    content
)

# Fix migratePointages
content = re.sub(
    r'\$preloadedTasks = \$tachesExistantes\[\$instance->id\] \?\? collect\(\);\s*\$this->syncOpenTask\(',
    r'$preloadedTasks = $tachesExistantes[$instance->id] ?? collect();\n                    $tachesExistantes[$instance->id] = $preloadedTasks;\n                    $this->syncOpenTask(',
    content
)

# Fix syncOpenTask
sync_pattern = r'''(private function syncOpenTask.*?\$preloadedTasks = null\s*\):\s*void\s*\{).*?(\$activeTasks = \$preloadedTasks \?\? TacheParcours::query\(\)\s*->where\('instance_parcours_id', \$instance->id\)\s*->whereIn\('statut', \['OUVERTE', 'REVENDIQUEE'\]\)\s*->orderBy\('id'\)\s*->get\(\);)'''
replacement = r'''\1\n        $activeTasks = $preloadedTasks ? $preloadedTasks->filter(fn ($t) => in_array($t->statut, ['OUVERTE', 'REVENDIQUEE'])) : TacheParcours::query()\n            ->where('instance_parcours_id', $instance->id)\n            ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])\n            ->orderBy('id')\n            ->get();'''
content = re.sub(sync_pattern, replacement, content, flags=re.DOTALL)

creation_pattern = r'''(        TacheParcours::create\(\[\s*'instance_parcours_id' => \$instance->id,\s*'etape_parcours_id' => \$etape->id,\s*'role_responsable_id' => \$roleId,\s*'agence_id' => \$agenceId,\s*'code_corbeille' => \$code,\s*'statut' => 'OUVERTE',\s*'priorite' => 0,\s*'ouverte_le' => now\(\),\s*\]\);\s*\})'''
creation_replacement = r'''        $created = TacheParcours::create([\n            'instance_parcours_id' => $instance->id,\n            'etape_parcours_id' => $etape->id,\n            'role_responsable_id' => $roleId,\n            'agence_id' => $agenceId,\n            'code_corbeille' => $code,\n            'statut' => 'OUVERTE',\n            'priorite' => 0,\n            'ouverte_le' => now(),\n        ]);\n\n        if ($preloadedTasks instanceof \\Illuminate\\Support\\Collection) {\n            $preloadedTasks->push($created);\n        }\n    }'''
content = re.sub(creation_pattern, creation_replacement, content, flags=re.DOTALL)

with open('app/Console/Commands/MigrateLegacyDataCommand.php', 'w') as f:
    f.write(content)
