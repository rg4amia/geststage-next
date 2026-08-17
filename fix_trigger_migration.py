import re

file_path = 'app/Console/Commands/MigrateLegacyDataCommand.php'

with open(file_path, 'r') as f:
    content = f.read()

# Replace firstOrCreate back to updateOrCreate
content = content.replace("EvenementParcours::firstOrCreate(", "EvenementParcours::updateOrCreate(")

# Add DB::unprepared before chunk
old_chunk_start = """                LegacyContratEtape::chunk(2000, function ($events) use (&$progress, $instancesMap) {"""
new_chunk_start = """                \DB::unprepared('ALTER TABLE evenements_parcours DISABLE TRIGGER evenements_parcours_immuables;');
                LegacyContratEtape::chunk(2000, function ($events) use (&$progress, $instancesMap) {"""

content = content.replace(old_chunk_start, new_chunk_start)

# Add DB::unprepared after chunk
old_chunk_end = """                });

                $this->info("\nMigration de l'historique terminée.");"""

new_chunk_end = """                });
                \DB::unprepared('ALTER TABLE evenements_parcours ENABLE TRIGGER evenements_parcours_immuables;');

                $this->info("\nMigration de l'historique terminée.");"""

content = content.replace(old_chunk_end, new_chunk_end)

with open(file_path, 'w') as f:
    f.write(content)
