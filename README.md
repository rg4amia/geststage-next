# GestStage Next

Bienvenue dans le nouveau système de gestion de stage (GestStage Next). Ce projet vise à moderniser, assainir et consolider les données et règles de gestion du système legacy.

## État du Projet et Mises à jour Récentes

### 1. Refonte UI et DataTables
* Correction du comportement de filtrage client vs serveur pour le rechargement des tableaux de bord.
* Les onglets de Cohortes (Cohorte Globale, Cohorte 1, Cohorte 2, etc.) utilisent désormais le système de routage Inertia pour récupérer dynamiquement les données depuis le serveur, assurant des décomptes exacts.

### 2. Migration des Données et Rétro-compatibilité
* **Backfill des Paiements (Présence) :** Dans le système Legacy, les enregistrements de "présence" attendaient leur paiement sans structure rigide. Le nouveau système exige un modèle `DroitPaiement` lié à un `Paiement`. Une commande dédiée a été écrite (`BackfillPresencePaymentsCommand`) pour générer rétroactivement ces structures, ramenant le nombre de stagiaires en "Présence" de 182 à plus de 3 400.
* **Exclusion PEJEDEC :** Les stages financés par le PEJEDEC ont été strictement exclus de la boucle de paiement de la DMG, se calquant avec la règle Legacy stricte (`source_financement != 5`).
* **Gestion des Renouvellements :** Un stagiaire ayant un contrat renouvelé (`etatrenouvellement_id = 1`) voit sa période basculer directement dans le circuit de "Présence" selon les règles métier, sans repasser par le "Démarrage".

### 3. Corrections des Flux (Workflows)
* **Ajournements CIP / CA (`etat_chef_agence = 100`) :** Des stagiaires dont le pointage avait été rejeté par la DMG puis corrigé par le CIP étaient bloqués car leur contrat portait l'état `100`. Le système de migration les redirige désormais correctement vers la corbeille de validation `CA_VALIDATION_POINTAGES` au lieu de les laisser parasiter la DMG.
* **Mur Pare-feu (Doublon DESSE) :** Les contrôles de doublon (numéro de pièce, téléphone, AEJ, numéro Trésor, Wave) qui excluaient dynamiquement les candidats des requêtes de paiement DMG sur le Legacy ont été portés sur la version Next. Un `applyDuplicateExclusionFilter` empêche désormais automatiquement tout stagiaire en doublon (non-traité par la DESSE) d'apparaître en corbeille "Démarrage" ou "Présence".

## Stack Technique
* PHP / Laravel
* React / Inertia.js
* PostgreSQL
