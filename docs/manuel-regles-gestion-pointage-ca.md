# Manuel des règles de gestion — Corbeilles Pointage CIP / Chef d'Agence

Date : 2026-09-03

## Portée

Ce manuel documente les règles de gestion des cinq corbeilles legacy suivantes, prises comme référence
du nouveau projet, et leur équivalent dans Gestage Next :

| # | Corbeille (legacy) | Route legacy | Contrôleur / méthode legacy | Équivalent Next |
|---|---|---|---|---|
| 1 | CIP : attente de pointage | — | `Cip\PointageCipController@stagiaireAttentePointage` | `Cip\PointageCipController@stagiaireAttentePointage` (`tab=attente` / `attente_pejedec`) |
| 2 | CA : ajourner DMG | `/chef-agence/stagiaires/pointer-ajourner-dmg` | `ChefAgence\AttestationPresenceController@ajournerByDmg` | corbeille `cip_pointage_ajourne_dmg` (`LegacyMapperService::mapPointageToCorbeille`) |
| 3 | CA : attente validation pointage | `/pointage-chef-agence/attente` | `ChefAgence\PointageChefAgenceController@pointageAttenteValidationByChefAgence` | corbeille `ca_validation_pointages` |
| 4 | CA : valider AR ajourné DMG | `/chef-agence/stagiaires/pointer-ajourner-valid-ar-dmg` | `ChefAgence\AttestationPresenceController@pointerAjournerValidByArDmgStagiaire` | corbeille `dmg_attente_paiement_*` avec renvoi DMG (`paiementRenvoyeAuDmg`) |
| 5 | CA : attente validation démarrage | `/chef-agence/liste-stagiaire-attente-validation` | `ChefAgence\IndexChefAgenceController@listeStagiaireAttenteValidation` | corbeille `ca_attente_validation_demarrage` / `ca_attente_validation_omis` |

Le pivot commun entre les deux mondes est le suivant : le legacy pilote chaque écran par une combinaison de
colonnes d'état sur `pointage_models` / `stagiaire` (`status_cip`, `status_ca`, `status_dmg`, `etat_chef_agence`,
`etape_id`, `situationstage_id`...), alors que Next centralise le même état dans une seule valeur,
`InstanceParcours::corbeille_actuelle` (énumération `CorbeilleEnum`), accompagnée d'une tâche de travail
(`TacheParcours`) que l'utilisateur du bon rôle peut revendiquer.

---

## 1. CIP : attente de pointage

**Legacy** — `stagiaireAttentePointage` liste les stages :
- `situation_stage = EN_COURS` (le stagiaire est toujours actif) ;
- dont la date de fin prévue n'est pas dépassée pour la période demandée ;
- sans pointage déjà soumis/validé/corrigé pour ce mois ;
- **et dont le dossier a déjà été validé par le Chef d'Agence** (`etat_chef_agence = 2`,
  cf. `WaitCheckedChefAgenceService`). Un stage encore en attente de validation démarrage, de validation
  « omis », ou en retour d'ajournement, n'apparaît jamais dans cette corbeille : le CIP ne peut pas pointer
  un dossier que le CA n'a pas encore laissé entrer dans le cycle mensuel.

**Next** — même filtre, porté par `CorbeilleEnum::nonValideesParCa()` :
```php
public static function nonValideesParCa(): array
{
    return [
        self::CIP_MES_STAGIAIRES,
        self::CA_ATTENTE_VALIDATION_DEMARRAGE,
        self::CA_ATTENTE_VALIDATION_OMIS,
        self::CA_RETOUR_AJOURNEMENT,
    ];
}
```
Un stage dont l'instance de parcours est dans une de ces quatre corbeilles est exclu de l'onglet « attente »
et « attente PEJEDEC », ainsi que du badge de comptage (`PointageService::getCountsByTab`).

**Écarts corrigés (voir § Gaps)** : A et B ci-dessous.

---

## 2. CA : ajourner DMG

**Legacy** — le nom de la page (« Pointage Ajourné par la DMG ») est trompeur : c'est en réalité la file
d'attente DMG, pas les rejets. `ajournerByDmg()` délègue l'affichage à
`PointageChefAgenceService::getPointageAjournerDmg($mois, $filters, valid=0)`, qui liste les stagiaires dont
un paiement (`paiement_models`) a `status_dmg = 0` (en attente de traitement DMG, PAS `status_dmg = 2`
réellement ajourné) pour le `mois` donné, avec `status_ar != 1` (accusé de réception non reçu), scopé par
agence pour un Chef d'Agence. La condition `status_ca = 1` / `date_cip` vue dans `ajournerByDmg()` ne sert
qu'à construire la liste déroulante des mois affichés au CA — elle ne filtre pas les lignes du tableau.

**Next** — `LegacyMapperService::mapPointageToCorbeille()` route les pointages migrés vers
`cip_pointage_ajourne_dmg` lorsque `legacy_etape_id` vaut 15 ou 16, ou lorsque le statut calculé est
`AJOURNE_DMG` (dérivé par `mapStatutPointage()` : `status_dmg = 2` ⇒ `AJOURNE_DMG`). C'est un mapping
différent de l'onglet CIP « AJOURNÉ / DMG » (`/cip/pointages?tab=ajourne_dmg`,
`PointageCipController::buildLegacyAjourneDmgQuery()`), qui reproduit la page Chef d'Agence ci-dessus en
filtrant `paiements.statut = 'A_TRAITER'` (équivalent `status_dmg = 0`) sur la période sélectionnée — voir
Gap D ci-dessous.

---

## 3. CA : attente validation pointage

**Legacy** — `pointageAttenteValidationByChefAgence` délègue à `PointageChefAgenceService::getPointage()` :
`status_ca = 0` (pas encore statué), `date_cip` non nulle, et surtout
**`situationstage_id NOT IN (2, 3, 6)`** — un stagiaire en abandon (2), suspension (3) ou désistement sans
paiement (6) est totalement absent de la file du Chef d'Agence : il est sorti du dispositif.

**Next** — `mapPointageToCorbeille()` route par défaut (aucun `etape_id` reconnu, statut ni `AJOURNE_*` ni
`VALIDE`) vers `ca_validation_pointages`. Ce mapping **ne connaissait pas** `situationstage_id` : c'est le
Gap C ci-dessous.

---

## 4. CA : valider AR ajourné DMG

**Legacy** — `pointerAjournerValidByArDmgStagiaire` sélectionne les stagiaires avec
`etat_chef_agence = 100 AND active_chef_agence = 100` (`const AJOURNER_DMG_FOR_VALIDATION_AR = 100`) :
un dossier ajourné par la DMG, dont le CA a produit un accusé de réception, en attente de re-validation.
`PointageChefAgenceService::getPointageAjournerDmg($mois, $filters, $valid=1)` filtre en parallèle sur
`mespaiements.status_dmg = 0 AND status_ar != 1`.

**Next** — `mapPointageToCorbeille()` court-circuite tout le reste dès que `etatChefAgenceContrat === 100` :
```php
if ($etatChefAgenceContrat === 100) {
    return CorbeilleEnum::CA_VALIDATION_POINTAGES;
}
```
**Point de vigilance non corrigé** — Next ne teste que `etat_chef_agence`, pas `active_chef_agence`. Vérifié
sur la base legacy en production : les 67 lignes actuelles à `etat_chef_agence = 100` ont toutes également
`active_chef_agence = 100` ; il n'existe donc à ce jour aucune divergence réelle. Ne pas ajouter de code
défensif pour un cas sans occurrence observée — mais si `active_chef_agence` venait à diverger un jour de
`etat_chef_agence` (données legacy corrigées manuellement, par exemple), Next classerait ce dossier comme
« attente validation pointage » à la place du legacy qui l'exclurait. À surveiller lors de futures
migrations, pas à corriger préventivement.

---

## 5. CA : attente validation démarrage

**Legacy** — `listeStagiaireAttenteValidation` délègue à `WaitCheckedChefAgenceService::stagiaireWaitValidation()` :
`etat_chef_agence = 0`, `agent_id = 3`, `avis_contrat = 1`, `file_contrat` renseigné, plus un filtrage par
étape de traitement (`etapetraitement_id IN [ETAPE_ONE, ETAPE_FOUR]`) et la portée agence de l'utilisateur
courant (`mine()`).

**Next** — corbeilles `ca_attente_validation_demarrage` / `ca_attente_validation_omis`, alimentées par
`LegacyMapperService::mapChefAgenceCorbeille()` à partir de `etapetraitement_id` / `etat_chef_agence`.

---

## Gaps identifiés et corrigés

### Gap A — Onglet CIP « attente de pointage » vide sur toute donnée migrée

**Cause** — `PointageCipController` et `PointageService::getCountsByTab()` filtraient sur
`Stage::where('situation_stage', 'EN_COURS')`, une chaîne littérale, alors que la colonne
`stages.situation_stage` contient le **code** de référence (`SituationStage::CODE_EN_COURS`, ex. `SS-001`).
Aucune ligne ne pouvait jamais correspondre : l'onglet et son badge de comptage affichaient toujours zéro
résultat pour l'intégralité des données migrées.

**Correction** — remplacement par `SituationStage::CODE_EN_COURS` dans les deux contrôleurs concernés
(`PointageCipController.php`, `PointageService::getCountsByTab()`).

### Gap B — Fuite de dossiers non encore validés par le CA dans l'onglet CIP

**Cause** — même après correction du Gap A, rien n'empêchait un stage dont le CA n'a pas encore validé le
démarrage (corbeille `ca_attente_validation_demarrage`, `..._omis`, `ca_retour_ajournement`, ou encore
`cip_mes_stagiaires`) d'apparaître dans l'onglet de pointage CIP, contrairement au legacy
(`etat_chef_agence = 2` strict). Vérifié sur données migrées : **5 484 stages** concernés.

Une première piste — restreindre strictement à `corbeille_actuelle = 'en_stage'` — a été écartée après
vérification : elle aurait exclu à tort **57 647 stages** légitimement en attente de pointage pour une
nouvelle période, mais dont l'instance de parcours (à évolution glissante par stage, pas par période) avait
déjà progressé vers une corbeille avale (ex. `dmg_attente_paiement_presence`) pour son dernier pointage.

**Correction** — ajout de `CorbeilleEnum::nonValideesParCa()` (voir § 1) et exclusion via
`whereDoesntHave('instanceParcours', ...)` dans les deux contrôleurs.

### Gap C — Pointages hors dispositif créant une tâche CA fantôme

**Cause** — `PointageChefAgenceService::getPointage()` (legacy) exclut du crible du CA tout pointage dont
`situationstage_id` vaut 2 (abandon), 3 (suspension) ou 6 (désistement sans paiement). Le mapping Next
(`mapPointageToCorbeille()`) ignorait cette information et pouvait router ces pointages vers
`ca_validation_pointages`, ce qui déclenchait la création d'une tâche ouverte pour le CA — un dossier que
celui-ci ne devrait jamais voir. Vérifié sur données migrées : **2 205+ lignes** de `pointage_models`
concernées.

**Correction** — dans `MigrateLegacyDataCommand` (`migratePointages()` et `fixPointageRevisions()`, les deux
points d'écriture qui alimentent cette corbeille), calcul de :
```php
$stagiaireSortiHorsCorbeilleCa = $corbeilleEnum === CorbeilleEnum::CA_VALIDATION_POINTAGES->value
    && in_array((int) ($legacyPointage->situationstage_id ?? 0), [2, 3, 6], true);
```
La corbeille reste enregistrée sur l'`InstanceParcours` pour la traçabilité, mais `syncOpenTask()` est
appelé avec `$terminee = true` : toute tâche active existante est clôturée et **aucune tâche n'est créée**.
Une anomalie non bloquante `POINTAGE_STAGIAIRE_SORTI_HORS_CORBEILLE_CA` est enregistrée
(`anomalies_migration`) pour permettre la réconciliation/l'audit sans jamais faire échouer la migration.

Le troisième point d'écriture de `mapPointageToCorbeille()` dans la commande
(`backfillCorbeillesDmgCb`-style, autour de la synchronisation DMG/CB) ne peut pas produire
`ca_validation_pointages` — il ne retient que les corbeilles `dmg_attente_paiement_*` /
`cb_etat_paiement_ajourne` (`$corbeillesConcernees`) — et n'a donc pas besoin de cette exclusion.

**Test de non-régression** — `MigrateLegacyDataCommandTest::test_pointage_for_a_stagiaire_who_left_the_program_does_not_create_a_ca_task`.

### Gap D — Onglet CIP « AJOURNÉ / DMG » vide + mauvais critère de filtrage

**Cause** — `PointageCipController::buildLegacyAjourneDmgQuery()` interrogeait `InstanceParcours` sur
`corbeille_actuelle = cip_ajourne_dmg`, une valeur que le mapping de migration (`mapPointageToCorbeille()`)
ne produit **jamais** (seule `cip_pointage_ajourne_dmg` — une corbeille distincte — est utilisée), et
restreignait en plus par un recoupement approximatif des dates de stage au lieu du mois exact. Résultat :
0 ligne sur toute donnée migrée, quel que soit le mois sélectionné, alors que la page legacy équivalente
(`ChefAgence/AttestationPresenceController::ajournerByDmg`) affichait des dizaines de lignes pour la même
période. Une fois la jointure corrigée vers `Paiement → DroitPaiement → Pointage` (déjà en place avant cette
correction), il restait deux écarts sémantiques :

1. Le filtre utilisait `paiements.statut = 'AJOURNE_DMG'` (équivalent legacy `status_dmg = 2`, réellement
   ajourné), alors que la page legacy filtre `status_dmg = 0` (en attente de traitement DMG — cf. § 2).
2. Un filtre `pointage.statut = 'VALIDE'` (équivalent `status_ca = 1`) avait été ajouté par erreur : dans
   `getPointageAjournerDmg()`, `status_ca = 1` ne sert qu'à construire la liste déroulante des mois dans
   `ajournerByDmg()`, pas à filtrer les lignes affichées.

**Correction** — `buildLegacyAjourneDmgQuery()` (et le comptage associé dans
`PointageService::getCountsByTab()`) filtrent maintenant `Paiement::where('statut', 'A_TRAITER')` scopé par
`droits_paiement.periode_id`, sans condition sur le statut du pointage.

**Gap restant (non corrigeable en l'état)** — `paiement_models.status_ar` (accusé de réception, mis à jour
hors du flux de validation CA — cf. `ValiderPaiementJob`/`ValidateSinglePaiementJob` en legacy) n'a aucun
équivalent dans le modèle `Paiement` de Next (colonne absente de `paiements`). La page Next peut donc
afficher des lignes que la page legacy masquerait déjà (AR reçu). De même, `paiement_models.observation`
(motif libre saisi côté agence) n'est pas repris dans `paiements` : la colonne `observation_dmg` renvoyée
par `mapLegacyAjourneDmgRow()` est `null` faute de source de données.

---

## Récapitulatif des codes legacy utiles

| Champ legacy | Valeurs | Signification |
|---|---|---|
| `pointage_models.status_cip` | 0/1/2 | soumis / validé / ajourné (n'intervient pas seul) |
| `pointage_models.status_ca` | 0/1/2 | en attente / validé / ajourné CA |
| `pointage_models.status_dmg` | 0/1/2 | en attente / traité / ajourné DMG |
| `pointage_models.situationstage_id` | 2 / 3 / 6 | abandon / suspension / désistement sans paiement — exclu de la file CA |
| `stagiaire.etat_chef_agence` | 0 / 2 / 100 | attente validation démarrage / validé / ajourné DMG en attente d'AR |
| `stagiaire.active_chef_agence` | 100 | confirme `etat_chef_agence = 100` (cf. point de vigilance § 4) |
| `paiement_models.status_dmg` | 0/1/2 | en attente / traité / ajourné — pilote `ajournerByDmg` |
| `paiement_models.status_ar` | 0/1 | accusé de réception non produit / produit — pilote la validation AR (§ 4) |

## Récapitulatif des corbeilles Next concernées

| `CorbeilleEnum` | Rôle | Origine legacy |
|---|---|---|
| `CIP_MES_STAGIAIRES` | CIP | dossier pas encore validé CA |
| `CA_ATTENTE_VALIDATION_DEMARRAGE` | CA | `etat_chef_agence = 0`, démarrage |
| `CA_ATTENTE_VALIDATION_OMIS` | CA | démarrage omis |
| `CA_RETOUR_AJOURNEMENT` | CA | retour d'un ajournement CIP |
| `CA_VALIDATION_POINTAGES` | CA | `PointageChefAgenceService::getPointage()`, hors situationstage 2/3/6 |
| `CIP_POINTAGE_AJOURNE_DMG` | CIP | `ajournerByDmg` / `status_dmg = 2` |
| `CIP_AJOURNE_CA` | CIP | `status_ca = 2` |
| `DMG_ATTENTE_PAIEMENT_DEMARRAGE` / `..._PRESENCE` | DMG | pointage validé CA, ou renvoyé au DMG après AR (§ 4) |
