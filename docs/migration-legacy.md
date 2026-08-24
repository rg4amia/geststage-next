# Migration Gestage legacy vers Gestage Next

La commande lit MySQL avec la connexion Laravel `legacy` et écrit dans la connexion PostgreSQL par défaut.
Le fichier `.env` de l’ancien projet n’est pas chargé par Gestage Next : reporter sa connexion sous les clés
`LEGACY_DB_*` dans le `.env` de Gestage Next.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_geststage
DB_USERNAME=postgres
DB_PASSWORD=root

LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=8889
LEGACY_DB_DATABASE=lv_geststage
LEGACY_DB_USERNAME=root
LEGACY_DB_PASSWORD=root
```

## Première exécution

Appliquer d’abord les migrations de schéma :

```bash
php artisan migrate
```

Pour isoler rapidement une erreur, exécuter les phases dans l’ordre plutôt qu’un unique `--step=all` :

```bash
php artisan migrate:legacy-data --step=references
php artisan migrate:legacy-data --step=users
php artisan migrate:legacy-data --step=entreprises
php artisan migrate:legacy-data --step=offres
php artisan migrate:legacy-data --step=beneficiaires
php artisan migrate:legacy-data --step=stages
php artisan migrate:legacy-data --step=pointages
php artisan migrate:legacy-data --step=paiements
php artisan migrate:legacy-data --step=remaining
```

La taille par défaut est sûre pour une limite PHP de 128 Mio : 500 contrats, 1 000 pointages/paiements et
2 000 événements au maximum. `--chunk=N` permet seulement de réduire ces maxima ou de régler les autres phases.

## Reprise

Chaque chunk métier et son checkpoint sont validés dans la même transaction PostgreSQL. Reprendre la phase qui a
échoué :

```bash
php artisan migrate:legacy-data --step=pointages --resume
```

Pour la chaîne tardive dossiers/groupes/OP/bordereaux/événements/DESSE, `remaining` active automatiquement la reprise
et ignore les phases déjà terminées :

```bash
php artisan migrate:legacy-data --step=remaining
```

Ne pas utiliser `--resume` si la source MySQL a changé depuis le début du report. Relancer alors la phase sans cette
option : son checkpoint repartira de zéro et les règles idempotentes réconcilieront les lignes.

## Options de contrôle

- `--dry-run` annule les écritures de la phase, mais conserve une transaction globale : l’utiliser phase par phase.
- `--with-model-audits` réactive les journaux Eloquent ligne par ligne. Par défaut, la migration utilise ses propres
  tables de traçabilité (`conservations_*`, `correspondances_*`, `anomalies_migration`) afin d’éviter des millions
  d’écritures redondantes.
- Les métriques de durée, chunks et mémoire sont affichées et conservées dans les compteurs de l’exécution.

Avant un report réel, effectuer une sauvegarde PostgreSQL et figer les écritures sur la source MySQL pendant la
fenêtre de migration.
