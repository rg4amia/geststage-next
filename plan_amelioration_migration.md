# Plan d'Amélioration des Performances (Optimisation des Requêtes)

L'objectif de ce plan est de réduire drastiquement le temps d'exécution (actuellement de plusieurs heures) des scripts de migration ou des traitements de masse, **sans casser la logique métier existante** ni perdre la sécurité d'idempotence.

## Phase 1 : Suppression des requêtes N+1 (Lectures) - *Identity Map*
C'est l'optimisation la plus urgente et la plus sûre (elle a déjà été appliquée sur `migratePointages` lors de ma dernière intervention).

* **Le Problème :** À l'intérieur d'une boucle (ex: 285 000 pointages), faire un appel comme `VersionPointage::where('ancien_id', $id)->first()` génère 285 000 requêtes SQL.
* **La Solution :** 
  1. Utiliser `chunk()` pour récupérer les données par lot (ex: 5000 lignes).
  2. Extraire tous les IDs de ce lot (`$ids = $chunk->pluck('id')`).
  3. Faire **une seule requête** pour charger les données existantes associées : `Modèle::whereIn('id', $ids)->get()`.
  4. Mapper ces résultats dans un tableau PHP (dictionnaire/hash map) avec `keyBy()`.
  5. Dans la boucle, interroger le tableau PHP au lieu de la base de données (`$valeur = $map[$id] ?? null;`).
* **Gain estimé :** Temps divisé par 10. Aucune régression possible sur la logique métier.

## Phase 2 : Optimisation des référentiels (Caching Global)
* **Le Problème :** Les requêtes pour s'assurer que des éléments statiques existent (comme `firstOrCreate` sur `DefinitionParcours`, `EtapeParcours` ou la recherche de `Periodes`).
* **La Solution :** Charger ces tables entièrement en mémoire au tout début du script de la commande.
  ```php
  $periodesMap = DB::table('periodes')->pluck('id', 'code')->toArray();
  // Utilisation dans la boucle : $periodeId = $periodesMap['2024-01'] ?? null;
  ```
* **Gain estimé :** Disparition totale des verrous (`locks`) sur les tables référentielles et suppression de milliers de micro-requêtes.

## Phase 3 : Optimisation des Écritures (Upserts / Bulk Inserts)
* **Le Problème :** Même si on supprime les requêtes de lecture, Eloquent effectuera toujours 285 000 `INSERT` ou `UPDATE` un par un.
* **La Solution (sans casser la logique) :**
  Plutôt que d'utiliser `$modele->save()`, préparer un grand tableau associatif contenant les données préparées dans la boucle, puis utiliser la méthode de base de données :
  ```php
  Pointage::upsert($donneesPreparees, ['stage_id', 'periode_id', 'nature'], ['statut', 'version_courante', 'deleted_at']);
  ```
* **Attention :** L'utilisation de requêtes de masse (`upsert`, `insert`) contourne les évènements Eloquent (`creating`, `created`). Si des *Listeners* critiques sont attachés à ces événements, on ne peut pas utiliser cette technique sans réécrire la logique des événements. Il faut l'appliquer uniquement sur les tables qui ne déclenchent pas d'actions secondaires cachées.

## Phase 4 : Parallélisation du traitement (Jobs)
* **Le Problème :** PHP exécute la migration sur un seul cœur de processeur (mono-thread).
* **La Solution :** Diviser la tâche. Le script de migration principal ne fait que lire la base legacy et "pousse" (dispatch) des morceaux (ex: 1000 IDs par Job) dans une file d'attente (Queue) Laravel ou Redis. 
  Ensuite, on lance 4 ou 8 workers en parallèle (`php artisan queue:work --concurrency=8`) qui traitent les chunks simultanément.
* **Gain estimé :** Temps de traitement divisé par le nombre de workers (ex: 4 fois plus rapide).

## Phase 5 : Optimisations Postgres (Si besoin)
* Si les écritures restent lentes malgré l'optimisation PHP, le goulot d'étranglement est Postgres (mise à jour des index B-Tree à chaque ligne insérée).
* **Solution de dernier recours :** Désactiver temporairement les index non essentiels et les contraintes de clés étrangères avant le script, puis faire un `REINDEX` massif à la fin.
