<?php

namespace App\Console\Commands\Legacy;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Phase B du plan de migration (docs/05_PLAN_MIGRATION_LEGACY.md,
 * docs/10_CONSERVATION_CONTRATS_PAE_REFERENTIELS.md) : migre les
 * référentiels de l'ancien Gestage (MySQL) vers Gestage Next.
 *
 * Hors périmètre volontaire :
 * - `etapes` → `etapes_parcours` : nécessite une définition de parcours
 *   (Phase D, machine à états), pas encore conçue.
 * - `niveaux_etude`, `programmes`, `types_document`, `motifs_ajournement` :
 *   aucune table legacy dédiée identifiée, à dériver de `contrats_pae`
 *   lors des phases suivantes.
 * - `roles`/`permissions` : archivés uniquement, le RBAC cible est déjà
 *   redéfini par `RolePermissionSeeder` et structurellement différent.
 */
class MigrerReferentielsCommand extends Command
{
    protected $signature = 'legacy:migrer-referentiels
        {--dry-run : Exécute la migration puis annule toutes les écritures}
        {--only= : Ne traiter que ces référentiels cibles (séparés par des virgules)}';

    protected $description = "Migre les référentiels de l'ancien Gestage (MySQL) vers Gestage Next";

    private const VERSION_SOURCE = 'legacy-referentiels-v1';

    private int $executionId;

    /** @var array<string, array{source: int, migrees: int, rejetees: int}> */
    private array $compteurs = [];

    private ?array $only = null;

    public function handle(): int
    {
        $this->only = $this->parseOnly();

        try {
            DB::connection('legacy')->getPdo();
        } catch (Throwable $e) {
            $this->error("Connexion à la base legacy impossible : {$e->getMessage()}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $this->executionId = $this->ouvrirExecution();

            $this->migrerRegions();
            $this->migrerCommunes();
            $this->migrerAgences();
            $this->lierRegionsEtAgences();
            $this->migrerConseillers();

            foreach ($this->referentielsSimples() as $definition) {
                $this->migrerReferentielSimple($definition);
            }

            $this->archiverRolesEtPermissions('roles');
            $this->archiverRolesEtPermissions('permissions');

            $this->cloturerExecution();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("Migration interrompue : {$e->getMessage()}");

            throw $e;
        }

        if ($dryRun) {
            DB::rollBack();
            $this->warn('Dry-run : toutes les écritures ont été annulées.');
        } else {
            DB::commit();
        }

        $this->afficherRapport();

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{legacy: string, legacyPk: string, cible: string, nom: \Closure, description: \Closure, actif: \Closure}>
     */
    private function referentielsSimples(): array
    {
        return [
            ['legacy' => 'type_stage', 'legacyPk' => 'id', 'cible' => 'types_stage',
                'nom' => fn ($r) => $r->libelle_type_stage, 'description' => fn ($r) => null,
                'actif' => fn ($r) => empty($r->deleted_at)],
            ['legacy' => 'type_financements', 'legacyPk' => 'id', 'cible' => 'sources_financement',
                'nom' => fn ($r) => $r->libelle_financement, 'description' => fn ($r) => null,
                'actif' => fn ($r) => (bool) ($r->state ?? 1)],
            ['legacy' => 'type_structures', 'legacyPk' => 'id', 'cible' => 'types_structure',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => null,
                'actif' => fn ($r) => true],
            ['legacy' => 'situation_stage', 'legacyPk' => 'id_situation_stage', 'cible' => 'situations_stage',
                'nom' => fn ($r) => $r->libelle_situation_stage, 'description' => fn ($r) => null,
                'actif' => fn ($r) => empty($r->deleted_at)],
            ['legacy' => 'statut_stage', 'legacyPk' => 'id', 'cible' => 'statuts_stage',
                'nom' => fn ($r) => $r->libelle_statut_stage, 'description' => fn ($r) => null,
                'actif' => fn ($r) => true],
            ['legacy' => 'type_paiements', 'legacyPk' => 'id', 'cible' => 'types_paiement',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => null,
                'actif' => fn ($r) => true],
            ['legacy' => 'lien_parentes', 'legacyPk' => 'id', 'cible' => 'liens_parente',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->desc,
                'actif' => fn ($r) => (bool) $r->actif],
            ['legacy' => 'type_enseignements', 'legacyPk' => 'id', 'cible' => 'types_enseignement',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->desc,
                'actif' => fn ($r) => (bool) $r->actif],
            ['legacy' => 'handicaps', 'legacyPk' => 'id', 'cible' => 'handicaps',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->desc,
                'actif' => fn ($r) => (bool) $r->actif],
            ['legacy' => 'type_handicaps', 'legacyPk' => 'id', 'cible' => 'types_handicap',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->desc,
                'actif' => fn ($r) => (bool) $r->actif],
            ['legacy' => 'type_piece_identites', 'legacyPk' => 'id', 'cible' => 'types_piece_identite',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->description,
                'actif' => fn ($r) => true],
            ['legacy' => 'specialitie_diplomes', 'legacyPk' => 'id', 'cible' => 'specialites_diplome',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->description,
                'actif' => fn ($r) => true],
            ['legacy' => 'etat_validation', 'legacyPk' => 'id_etat_validation', 'cible' => 'etats_validation',
                'nom' => fn ($r) => $r->libelle_etat_validation, 'description' => fn ($r) => null,
                'actif' => fn ($r) => true],
            ['legacy' => 'diplome', 'legacyPk' => 'id', 'cible' => 'diplomes',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->abrege,
                'actif' => fn ($r) => true],
            ['legacy' => 'origine_stagiaires', 'legacyPk' => 'id', 'cible' => 'origines_stagiaire',
                'nom' => fn ($r) => $r->libelle, 'description' => fn ($r) => $r->desc,
                'actif' => fn ($r) => (bool) $r->actif],
        ];
    }

    private function migrerReferentielSimple(array $definition): void
    {
        if (! $this->estActif($definition['cible'])) {
            return;
        }

        $legacyTable = $definition['legacy'];
        $pk = $definition['legacyPk'];
        $cible = $definition['cible'];

        $lignes = DB::connection('legacy')->table($legacyTable)->orderBy($pk)->get();
        $migrees = 0;

        foreach ($lignes as $ligne) {
            $ancienId = (int) $ligne->{$pk};
            $this->archiverReferentiel($legacyTable, (string) $ancienId, (array) $ligne);

            $nom = trim((string) ($definition['nom'])($ligne)) ?: "REF-{$ancienId}";
            $description = ($definition['description'])($ligne);
            $actif = ($definition['actif'])($ligne);

            $code = $this->genererCode($cible, $nom, $ancienId);

            $idCible = $this->upsertCible($cible, $ancienId, [
                'code' => $code,
                'nom' => $nom,
                'description' => $description,
                'actif' => $actif,
            ]);

            $this->enregistrerCorrespondances($legacyTable, (string) $ancienId, $cible, $idCible, $nom);
            $migrees++;
        }

        $this->compteurs[$cible] = ['source' => $lignes->count(), 'migrees' => $migrees, 'rejetees' => 0];
    }

    private function migrerRegions(): void
    {
        if (! $this->estActif('regions')) {
            return;
        }

        $lignes = DB::connection('legacy')->table('regions')->orderBy('id')->get();
        $migrees = 0;

        foreach ($lignes as $ligne) {
            $ancienId = (int) $ligne->id;
            $this->archiverReferentiel('regions', (string) $ancienId, (array) $ligne);

            $nom = trim((string) $ligne->libelle) ?: "REGION-{$ancienId}";
            $code = $this->genererCode('regions', $ligne->code ?: $nom, $ancienId);

            $idCible = $this->upsertCible('regions', $ancienId, [
                'code' => $code,
                'nom' => $nom,
                'actif' => true,
            ]);

            $this->enregistrerCorrespondances('regions', (string) $ancienId, 'regions', $idCible, $nom);
            $migrees++;
        }

        $this->compteurs['regions'] = ['source' => $lignes->count(), 'migrees' => $migrees, 'rejetees' => 0];
    }

    private function migrerCommunes(): void
    {
        if (! $this->estActif('communes')) {
            return;
        }

        $lignes = DB::connection('legacy')->table('communes')->orderBy('id')->get();
        $migrees = 0;

        foreach ($lignes as $ligne) {
            $ancienId = (int) $ligne->id;
            $this->archiverReferentiel('communes', (string) $ancienId, (array) $ligne);

            $nom = trim((string) ($ligne->name ?? '')) ?: "COMMUNE-{$ancienId}";
            $code = $this->genererCode('communes', $nom, $ancienId);
            $actif = empty($ligne->deleted_at) && (is_null($ligne->status) || (int) $ligne->status !== 0);

            // Le legacy ne relie pas explicitement une commune à une région : on ne l'invente pas.
            $idCible = $this->upsertCible('communes', $ancienId, [
                'region_id' => null,
                'code' => $code,
                'nom' => $nom,
                'actif' => $actif,
            ]);

            $this->enregistrerCorrespondances('communes', (string) $ancienId, 'communes', $idCible, $nom);
            $migrees++;
        }

        $this->compteurs['communes'] = ['source' => $lignes->count(), 'migrees' => $migrees, 'rejetees' => 0];
    }

    private function migrerAgences(): void
    {
        if (! $this->estActif('agences')) {
            return;
        }

        $lignes = DB::connection('legacy')->table('agences')->orderBy('id')->get();
        $migrees = 0;

        foreach ($lignes as $ligne) {
            $ancienId = (int) $ligne->id;
            $this->archiverReferentiel('agences', (string) $ancienId, (array) $ligne);

            $nom = trim((string) $ligne->libelle_agence) ?: "AGENCE-{$ancienId}";
            $code = $this->genererCode('agences', $nom, $ancienId);

            $idCible = $this->upsertCible('agences', $ancienId, [
                'region_id' => null,
                'commune_id' => null,
                'code' => $code,
                'nom' => $nom,
                'adresse' => null,
                'actif' => true,
            ]);

            $this->enregistrerCorrespondances('agences', (string) $ancienId, 'agences', $idCible, $nom);
            $migrees++;
        }

        $this->compteurs['agences'] = ['source' => $lignes->count(), 'migrees' => $migrees, 'rejetees' => 0];
    }

    /**
     * `regions.agence_id` (legacy) indique explicitement l'agence de chaque
     * région : relation existante à reprendre, pas à inventer.
     */
    private function lierRegionsEtAgences(): void
    {
        if (! $this->estActif('agences')) {
            return;
        }

        $liens = DB::connection('legacy')->table('regions')->whereNotNull('agence_id')->get(['id', 'agence_id']);
        $liees = 0;

        foreach ($liens as $lien) {
            $agence = DB::table('agences')->where('ancien_id', (int) $lien->agence_id)->first();
            $region = DB::table('regions')->where('ancien_id', (int) $lien->id)->first();

            if (! $agence || ! $region) {
                $this->signalerAnomalie(
                    'REGION_AGENCE_INTROUVABLE',
                    'regions',
                    (string) $lien->id,
                    "La région legacy {$lien->id} référence l'agence legacy {$lien->agence_id}, introuvable côté cible.",
                    ['ancien_agence_id' => $lien->agence_id, 'ancien_region_id' => $lien->id],
                    'NON_BLOQUANTE',
                );

                continue;
            }

            DB::table('agences')->where('id', $agence->id)->update(['region_id' => $region->id, 'updated_at' => now()]);
            $liees++;
        }

        $this->compteurs['agences_region'] = ['source' => $liens->count(), 'migrees' => $liees, 'rejetees' => $liens->count() - $liees];
    }

    private function migrerConseillers(): void
    {
        if (! $this->estActif('conseillers')) {
            return;
        }

        $lignes = DB::connection('legacy')->table('conseiller')->orderBy('id_conseiller')->get();
        $migrees = 0;
        $rejetees = 0;

        foreach ($lignes as $ligne) {
            $ancienId = (int) $ligne->id_conseiller;
            $this->archiverReferentiel('conseiller', (string) $ancienId, (array) $ligne);

            $agenceCible = $ligne->agence_id !== null
                ? DB::table('agences')->where('ancien_id', (int) $ligne->agence_id)->first()
                : null;

            if (! $agenceCible) {
                $this->signalerAnomalie(
                    'CONSEILLER_SANS_AGENCE',
                    'conseiller',
                    (string) $ancienId,
                    "Le conseiller legacy {$ancienId} n'a pas d'agence cible correspondante (agence_id legacy = ".($ligne->agence_id ?? 'NULL').').',
                    ['ancien_agence_id' => $ligne->agence_id],
                );
                $rejetees++;

                continue;
            }

            // Pas de délimiteur fiable dans "nom_prenoms" : on ne devine pas la coupure nom/prénoms.
            $nom = trim((string) $ligne->nom_prenoms) ?: "CONSEILLER-{$ancienId}";

            $idCible = $this->upsertCible('conseillers', $ancienId, [
                'agence_id' => $agenceCible->id,
                'user_id' => null,
                'matricule' => null,
                'nom' => $nom,
                'prenoms' => null,
                'actif' => (bool) $ligne->status_compte,
            ]);

            $this->enregistrerCorrespondances('conseiller', (string) $ancienId, 'conseillers', $idCible, $nom);
            $migrees++;
        }

        $this->compteurs['conseillers'] = ['source' => $lignes->count(), 'migrees' => $migrees, 'rejetees' => $rejetees];
    }

    /**
     * Le RBAC cible est déjà redéfini par RolePermissionSeeder (structure
     * différente) : on archive les lignes legacy sans les recréer.
     */
    private function archiverRolesEtPermissions(string $table): void
    {
        if (! $this->estActif($table)) {
            return;
        }

        $lignes = DB::connection('legacy')->table($table)->orderBy('id')->get();

        foreach ($lignes as $ligne) {
            $this->archiverReferentiel($table, (string) $ligne->id, (array) $ligne);

            $this->upsertParClesUniques(
                'correspondances_valeurs_referentiels',
                ['nom_table_source' => $table, 'id_source' => (string) $ligne->id, 'table_cible' => 'rbac_non_recree'],
                [
                    'libelle_source' => $ligne->name,
                    'id_cible' => null,
                    'statut_correspondance' => 'A_RECONCILIER',
                    'commentaire' => 'RBAC redéfini pour Gestage Next (RolePermissionSeeder) : archivage seul, aucune recréation ni correspondance automatique.',
                ],
            );
        }

        $this->compteurs[$table] = ['source' => $lignes->count(), 'migrees' => $lignes->count(), 'rejetees' => 0];
    }

    private function enregistrerCorrespondances(string $tableSource, string $idSource, string $tableCible, int $idCible, ?string $libelleSource): void
    {
        $this->upsertParClesUniques(
            'correspondances_valeurs_referentiels',
            ['nom_table_source' => $tableSource, 'id_source' => $idSource, 'table_cible' => $tableCible],
            [
                'id_cible' => $idCible,
                'libelle_source' => $libelleSource,
                'statut_correspondance' => 'MIGREE',
            ],
        );

        $this->upsertParClesUniques(
            'correspondances_ancien_systeme',
            ['table_source' => $tableSource, 'id_source' => $idSource, 'table_cible' => $tableCible],
            [
                'execution_migration_id' => $this->executionId,
                'id_cible' => $idCible,
            ],
        );
    }

    private function archiverReferentiel(string $tableSource, string $idSource, array $donnees): void
    {
        $this->upsertParClesUniques(
            'conservations_referentiels_legacy',
            ['nom_table_source' => $tableSource, 'id_source' => $idSource],
            [
                'donnees_originales' => json_encode($donnees, JSON_THROW_ON_ERROR),
                'empreinte_originale' => $this->empreinte($donnees),
                'execution_migration_id' => $this->executionId,
                'importe_le' => now(),
            ],
        );
    }

    private function empreinte(array $donnees): string
    {
        ksort($donnees);

        return hash('sha256', json_encode($donnees, JSON_THROW_ON_ERROR));
    }

    private function signalerAnomalie(string $code, string $tableSource, ?string $idSource, string $description, array $donnees = [], string $gravite = 'BLOQUANTE'): void
    {
        $existe = DB::table('anomalies_migration')
            ->where('code', $code)
            ->where('table_source', $tableSource)
            ->where('id_source', $idSource)
            ->where('statut', 'A_RECONCILIER')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('anomalies_migration')->insert([
            'execution_migration_id' => $this->executionId,
            'code' => $code,
            'table_source' => $tableSource,
            'id_source' => $idSource,
            'gravite' => $gravite,
            'statut' => 'A_RECONCILIER',
            'description' => $description,
            'donnees' => $donnees ? json_encode($donnees, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function genererCode(string $table, ?string $valeur, int $ancienId): string
    {
        $base = (string) Str::of($valeur ?: 'REF')
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->limit(35, '');

        if ($base === '') {
            $base = 'REF';
        }

        $conflit = DB::table($table)
            ->where('code', $base)
            ->where(function ($query) use ($ancienId): void {
                $query->whereNull('ancien_id')->orWhere('ancien_id', '!=', $ancienId);
            })
            ->exists();

        return $conflit ? "{$base}_{$ancienId}" : $base;
    }

    private function upsertCible(string $table, int $ancienId, array $valeurs): int
    {
        $existant = DB::table($table)->where('ancien_id', $ancienId)->first();

        if ($existant) {
            DB::table($table)->where('id', $existant->id)->update($valeurs + ['updated_at' => now()]);

            return $existant->id;
        }

        return DB::table($table)->insertGetId($valeurs + [
            'ancien_id' => $ancienId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertParClesUniques(string $table, array $cles, array $valeurs): void
    {
        $existe = DB::table($table)->where($cles)->exists();

        if ($existe) {
            DB::table($table)->where($cles)->update($valeurs + ['updated_at' => now()]);

            return;
        }

        DB::table($table)->insert($cles + $valeurs + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function parseOnly(): ?array
    {
        $valeur = $this->option('only');

        if (! $valeur) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $valeur))));
    }

    private function estActif(string $tableCible): bool
    {
        return $this->only === null || in_array($tableCible, $this->only, true);
    }

    private function ouvrirExecution(): int
    {
        return DB::table('executions_migration')->insertGetId([
            'uuid_public' => (string) Str::uuid(),
            'version_source' => self::VERSION_SOURCE,
            'statut' => 'EN_COURS',
            'demarree_le' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cloturerExecution(): void
    {
        DB::table('executions_migration')->where('id', $this->executionId)->update([
            'statut' => 'TERMINEE',
            'compteurs' => json_encode($this->compteurs, JSON_THROW_ON_ERROR),
            'terminee_le' => now(),
            'updated_at' => now(),
        ]);
    }

    private function afficherRapport(): void
    {
        $this->newLine();
        $this->info('Rapport de migration des référentiels');

        $rows = [];
        foreach ($this->compteurs as $cible => $valeurs) {
            $rows[] = [$cible, $valeurs['source'], $valeurs['migrees'], $valeurs['rejetees']];
        }

        $this->table(['Référentiel', 'Lignes source', 'Migrées / archivées', 'Rejetées'], $rows);
    }
}
