# Audit de proximité legacy — corbeilles Gestage Next

Date: 2026-08-15

## Verdict rapide

Le projet est **proche du legacy sur la structure générale**, mais **pas encore équivalent sur la granularité métier**.

La chaîne principale existe déjà :

- `CIP -> Chef d'Agence -> DMG -> CB -> AC`
- `DESSE -> DAICG`
- `PEJEDEC / AAF`

En revanche, plusieurs comportements legacy restent encore simplifiés ou partiellement câblés.

## Corbeille par corbeille

### 1. `Mes Stagiaires` / génération contrat

**État actuel**

- La corbeille existe côté CIP.
- La page liste les instances.
- Le flux d’export/reporting et de navigation existe.

**Écart legacy**

- Le legacy gère plus explicitement la génération du contrat, la fiche Trésor Money et l’upload des pièces avant passage chef d’agence.
- Les actions métier de création/contrôle dans cette corbeille ne sont pas encore au niveau du legacy.

**Appréciation**

- Proche sur l’accès à la corbeille.
- Partiel sur les actions métier.

### 2. `Stagiaires en attente de validation` / Chef d’Agence

**État actuel**

- La corbeille chef d’agence existe.
- Les sous-contextes `Démarrage` et `Démarrage omis` existent.
- Le workflow envoie déjà les dossiers vers la DMG ou vers le pointage selon le cas.

**Écart legacy**

- La logique `Démarrage omis` reste trop simple.
- L’historique legacy distingue mieux les cas de retour d’ajournement, de pointage préalable et de génération ADD/ADP.
- Le filtrage de période est encore plus approximatif que le legacy.

**Appréciation**

- Proche sur la présence des onglets.
- Partiel sur les règles ADD/ADP et les retours d’ajournement.

### 3. `Présence -> Pointage CIP`

**État actuel**

- La corbeille CIP de pointage existe.
- La variante PEJEDEC existe aussi.

**Écart legacy**

- Le legacy a une granularité plus forte sur les cas de correction, de pointage mensuel, et de retour après ajournement.
- Les chemins liés aux ajournements répétés et aux reprises ne sont pas encore totalement équivalents.

**Appréciation**

- Globalement proche.
- Encore incomplet sur les cas de reprise fine.

### 4. `Présence -> Validation des Pointages (Chef d'Agence)`

**État actuel**

- Validation et ajournement CA sont branchés.
- Le retour vers CIP après ajournement est présent.

**Écart legacy**

- Le legacy gère davantage de variantes autour du pointage ajourné ADP.
- La logique de décision reste plus fine dans les vues et traitements legacy.

**Appréciation**

- Proche.
- Moyennement fidèle sur les cas spéciaux.

### 5. `Stagiaire en attente de paiement — Démarrage / Présence` DMG

**État actuel**

- Les deux files existent côté DMG.
- Les vues Inertia et les filtres de mois existent.
- Le cycle de validation, ajournement et transmission est présent.

**Écart legacy**

- Le legacy sépare davantage les états métiers :
  - édition des états de paiement,
  - génération ADD / ADP,
  - regroupement multi-dossier,
  - retrait d’une ligne d’un dossier groupé.
- Ces mouvements sont encore simplifiés dans le projet courant.

**Appréciation**

- Structure proche.
- Moteur métier encore trop simplifié par rapport au legacy.

### 6. `Dossier Multiple` / `Élaboration des OP` / `Bordereau`

**État actuel**

- Les corbeilles DMG/CB/AC existent.
- La notion de dossier multiple est posée.
- Les routes de validation/rejet/ajournement existent.

**Écart legacy**

- Le legacy gère :
  - regroupement de plusieurs états de paiement,
  - retrait d’un dossier du groupe,
  - création d’OP,
  - passage bordereau,
  - rejet / différé AC avec retour sur la file exacte.
- Le code actuel n’expose pas encore cette finesse complète.

**Appréciation**

- C’est le plus gros écart fonctionnel.
- Le squelette est là, mais pas le cycle complet.

### 7. `CB` / contrôle des bordereaux

**État actuel**

- La corbeille CB existe.
- Validation et ajournement existent.

**Écart legacy**

- Le legacy permet un contrôle plus détaillé des dossiers groupés.
- L’ajournement CB doit renvoyer vers la file DMG d’origine selon le contexte exact.
- Les cas “état de paiement ajourné par CB” sont plus riches dans le legacy.

**Appréciation**

- Proche sur la route.
- Partiel sur les retours et validations fines.

### 8. `AC` / bordereaux et différés

**État actuel**

- La corbeille AC existe.
- Visa et ajournement sont branchés.

**Écart legacy**

- Le legacy distingue :
  - validation,
  - rejet,
  - différé,
  - retour à l’agence/CIP,
  - reprise sur la file AC exacte.
- Le projet actuel n’a pas encore toute cette mécanique.

**Appréciation**

- Présence fonctionnelle.
- Complétude métier insuffisante.

### 9. `DESSE` / `DAICG`

**État actuel**

- Les corbeilles et pages existent.
- Les actions de validation/ajournement/doublon sont branchées.

**Écart legacy**

- Le legacy expose plus de cas de suivi et d’extraction.
- Les écrans actuels restent plus légers.

**Appréciation**

- Proche.
- Moins critique que la chaîne paiement.

### 10. `PEJEDEC / AAF`

**État actuel**

- Le flux est branché.
- Les onglets, validations et corrections existent.

**Écart legacy**

- Le legacy a davantage de branches de correction et d’exports.
- La granularité de traitement est encore partielle.

**Appréciation**

- Proche.
- Encore incomplet sur certaines variantes métier.

## Résumé de proximité

- **Très proche** : architecture des corbeilles, navigation Inertia, séparation CIP / CA / DMG / CB / AC / DESSE / DAICG / PEJEDEC.
- **Moyennement proche** : pointage, validation CA, ajournements simples.
- **Encore loin du legacy** : groupe multi-dossier, bordereau, différé/rejet AC, et traitement fin ADD/ADP.

## Priorité de rattrapage

1. Formaliser le cycle `DMG -> Dossier Multiple -> CB -> OP -> Bordereau -> AC`.
2. Distinguer explicitement `ADD` et `ADP` dans le backend.
3. Rendre les retours `CB / AC / DMG` symétriques avec le legacy.
4. Ajouter les actions manquantes sur `Mes Stagiaires`.
5. Renforcer les filtres de période et d’état pour coller au legacy.

## Conclusion

Le projet est déjà **dans la bonne famille fonctionnelle**, mais il faut encore plusieurs passes pour atteindre la fidélité métier du legacy, surtout sur la chaîne financière et les retours d’ajournement.

---

## Complément (2026-09-03) — Corbeilles sans lecteur UI ni transition de sortie

Inventaire croisé (écrivains = transitions/mapper/backfills ; lecteurs = écrans ; sortie = transition de
l'instance) sur les 43 corbeilles de `CorbeilleEnum`, données dev du 03/09/2026.

### 🟥 A. Cul-de-sac avec données réelles — `desse_doublons_traites` (1 967 instances)

L'onglet DESSE « Doublons traités » lit `DesseDoublonDecision`, **pas** les instances : aucune UI ne lit cette
corbeille et aucune transition n'en sort. Le mapper y envoie pourtant les étapes legacy **5** (avéré) et **6**
(non-avéré) :

| Origine legacy | Stages migrés | Conséquence |
|---|---|---|
| Étape 6 « validé, doublon non avéré » | 1 159 | Dossier **validé** qui doit continuer vers le paiement. 4 442 droits / paiements, 1 107 stages déjà pointés (cycle présence). Les files DMG (`instanceRows(DMG_ATTENTE_PAIEMENT_DEMARRAGE/PRESENCE)`) ne les montrent jamais → **invisibles et bloqués** |
| Étape 5 « ajourné, doublon avéré » | 627 | Dossier **bloqué** qui doit revenir à l'agence pour résolution (correction ou abandon du doublon) → jamais renvoyé |

Les décisions (`DesseDoublonDecision`) existent et restent affichées dans l'historique « Doublons traités » ;
c'est le placement des **instances** qui est fautif.

**Proposition (non appliquée — décision métier requise)**
1. Mapper : étape 6 → file DMG selon le cycle (présence si pointages existants, sinon démarrage) ;
   étape 5 → `cip_ajourne_desse` (résolution agence, cohérent avec l'étape 7 déjà traitée).
2. Nouveau backfill relogeant les 1 967 instances (décisions conservées, garde-fous : ne jamais ramener en
   arrière un dossier déjà engagé ailleurs).

### 🟧 B. Corbeilles jamais écrites mais lues — onglets vides à vie (flux à venir)

| Corbeille | Lecteur | Écrivain |
|---|---|---|
| `DAICG_VALIDES_CA` | Onglet DAICG « Validé CA » | absent (workflow CA→DAICG non branché) |
| `DAICG_SANS_CONTRAT` | Onglet DAICG « Sans contrat » | absent |
| `CIP_AJOURNE_AAF` | Suivi CIP « Suspension/abandon » | absent |
| `CIP_FIN_CONTRAT` | Suivi CIP « Renouvellement » | absent |
| `CIP_DIFFERE_AC` | Suivi CIP « Différé AC » | mapper legacy 27/28 uniquement |

→ Conserver l'UI ; câbler les écrivains (workflow AAF / renouvellement / DAICG).

### 🟨 C. Valeurs mortes — zéro référence code hors enum, zéro donnée

`CIP_POINTAGE`, `CA_STAGIAIRE_DIFFERE_AC`, `DESSE_ATTENTE_CA`, `DESSE_SUIVI_ENREGISTRES`,
`DESSE_SUIVI_VALIDES_AR`, `DAICG_ATTENTE_DMG` (ce dernier n'apparaît que dans un compteur en dur du
CIP et une légende JS). → suppression de l'enum, sans effet sur les données.

### 🟩 D. Fausses alertes — lues ailleurs, non perdues

`cb_dossier_multiple` (135 759), `cb_etat_paiement_ajourne`, `ac_bordereau_op_attente`, `dmg_elaboration_op`,
`dmg_op_attente_bordereau`, `dmg_op_rejete_ac`, `dmg_op_differe_ac`, `ca_validation_pointages`,
`cip_pointage_pejedec` : les écrans CB/AC/DMG/pointages lisent les entités paiement (dossiers, bordereaux,
OP, `pointages.statut`, `paiements.corbeille_actuelle`) via `CorbeilleParcoursQueryService`. Les instances
servent d'historique de workflow — pas de blocage métier.

### 🟩 E. Déjà traité (02–03/09/2026)

`desse_suivi_processus` : 0 donnée. L'étape legacy 8 (validé après retour CA) est remappée vers
`DAICG_VALIDES_DESSE` (elle pointait sur cette corbeille orpheline) et les étapes legacy 7 sont relogées
vers `cip_ajourne_desse` (backfill `backfill_retour_chefagence`, 91 dossiers). La corbeille reste dans
l'enum comme source du backfill (bases migrées avec l'ancien mapping).
