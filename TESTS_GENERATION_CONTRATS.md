# Tests Manuels - Génération de Contrats et Trésor Money

## 📋 Prérequis
- Serveur Laravel lancé (`php artisan serve` ou équivalent)
- Serveur Vite lancé (`npm run dev`)
- Base de données migrée avec des données de test
- Utilisateur connecté avec rôle Chef d'Agence

## ✅ Tests à Effectuer

### 1. Test Génération de Contrat Individuel

#### 1.1 Budget État - PAE
- [ ] Naviguer vers `/chefagence/validations`
- [ ] Sélectionner un stagiaire avec source de financement "Budget État" et type "PAE"
- [ ] Cliquer sur l'icône 📄 (générer contrat) dans la colonne Action
- [ ] Vérifier que la modale s'ouvre avec les champs Fonction et Montant
- [ ] **Test 1a** : Générer sans remplir les champs (utilise valeurs par défaut)
  - Vérifier que le PDF se télécharge
  - Vérifier le contenu du PDF (nom, prénoms, agence, entreprise, dates)
  - Vérifier la présence du logo et du header
- [ ] **Test 1b** : Générer avec fonction personnalisée "Assistant RH"
  - Vérifier que la fonction apparaît dans le contrat généré
- [ ] **Test 1c** : Générer avec montant personnalisé "150000"
  - Vérifier que le montant apparaît dans le contrat généré

#### 1.2 Budget État - Stage École
- [ ] Sélectionner un stagiaire avec source "Budget État" et type "Stage École"
- [ ] Générer le contrat
- [ ] Vérifier que le template `budget-etat-stageecole` est utilisé

#### 1.3 PAPS Gouv - PAE
- [ ] Sélectionner un stagiaire avec source "PAPS Gouv" et type "PAE"
- [ ] Générer le contrat
- [ ] Vérifier le template PAPS Gouv

#### 1.4 C2D
- [ ] Sélectionner un stagiaire avec source "C2D"
- [ ] Générer le contrat
- [ ] Vérifier le template C2D

#### 1.5 PEJEDEC
- [ ] Sélectionner un stagiaire avec source "PEJEDEC"
- [ ] Générer le contrat
- [ ] Vérifier le template PEJEDEC

### 2. Test Génération Trésor Money Groupée

#### 2.1 Sélection Multiple
- [ ] Naviguer vers `/chefagence/validations`
- [ ] Sélectionner 3 à 5 stagiaires (checkbox)
- [ ] Cliquer sur le bouton "Trésor Money (sélection)"
- [ ] Vérifier que le PDF se génère et s'ouvre dans un nouvel onglet
- [ ] Vérifier le contenu du tableau :
  - [ ] Toutes les lignes de stagiaires sélectionnés sont présentes
  - [ ] Colonnes : N°, Matricule, Nom & Prénoms, N° Pièce, N° Trésor, Fonction, Entreprise, Agence, Montant, Période
  - [ ] Total des montants calculé correctement
  - [ ] Date de génération affichée

#### 2.2 Sélection Unique
- [ ] Sélectionner un seul stagiaire
- [ ] Générer le Trésor Money
- [ ] Vérifier que le fichier contient bien une seule ligne

### 3. Test Historique de Génération

#### 3.1 Vérification Base de Données
- [ ] Après chaque génération, vérifier dans la table `historique_generations` :
  ```sql
  SELECT * FROM historique_generations ORDER BY created_at DESC LIMIT 5;
  ```
- [ ] Vérifier les colonnes :
  - [ ] `type_document` : 'CONTRAT' ou 'TRESOR_MONEY'
  - [ ] `stage_id` : ID du stage concerné
  - [ ] `user_id` : ID de l'utilisateur connecté
  - [ ] `nom_fichier` : Nom du fichier généré
  - [ ] `parametres` : JSON avec fonction/montant (pour contrat) ou stage_ids (pour Trésor Money)
  - [ ] `source_financement` et `type_stage` : Pour les contrats
  - [ ] `nombre_stagiaires` : 1 pour contrat, N pour Trésor Money
  - [ ] `created_at` : Date/heure de génération

#### 3.2 Vérification Logs
- [ ] Consulter le fichier `storage/logs/laravel.log`
- [ ] Vérifier l'absence d'erreurs liées à la génération
- [ ] Si des warnings sur l'historique : vérifier que la génération PDF a quand même réussi

### 4. Tests d'Erreurs et Edge Cases

#### 4.1 Stage sans Données Complètes
- [ ] Tester avec un stagiaire sans entreprise
- [ ] Tester avec un stagiaire sans dates
- [ ] Vérifier que le PDF se génère avec "N/A" pour les champs manquants

#### 4.2 Montant Invalide
- [ ] Dans la modale, saisir un montant négatif
- [ ] Vérifier le comportement (devrait utiliser la valeur par défaut)

#### 4.3 Trésor Money Sans Sélection
- [ ] Cliquer sur "Trésor Money (sélection)" sans sélectionner de stagiaires
- [ ] Vérifier l'affichage de l'alerte "Veuillez sélectionner au moins un dossier"

### 5. Tests de Performance

#### 5.1 Génération Multiple
- [ ] Générer 5 contrats différents rapidement (un après l'autre)
- [ ] Vérifier que chaque génération est loggée
- [ ] Vérifier qu'il n'y a pas de ralentissement

#### 5.2 Trésor Money avec Beaucoup de Stagiaires
- [ ] Sélectionner 20+ stagiaires
- [ ] Générer le Trésor Money
- [ ] Vérifier le temps de génération (devrait rester < 10 secondes)
- [ ] Vérifier que le PDF est lisible (pas de débordement de tableau)

### 6. Tests Visuels

#### 6.1 Contrats
- [ ] Vérifier les marges du document
- [ ] Vérifier l'alignement des textes
- [ ] Vérifier la présence des logos (header/footer)
- [ ] Vérifier la numérotation des pages
- [ ] Vérifier la police (Cambria)
- [ ] Vérifier que les articles sont bien formatés

#### 6.2 Trésor Money
- [ ] Vérifier l'orientation paysage (landscape)
- [ ] Vérifier que le tableau tient sur la largeur de la page
- [ ] Vérifier l'alignement des montants (à droite)
- [ ] Vérifier le total en gras
- [ ] Vérifier la police (Arial)

## 🐛 Problèmes Connus et Solutions

### Images Manquantes
Si les images (logos) ne s'affichent pas dans les PDF :
```bash
# Vérifier que les images existent
ls -la public/contrat-edition/img/

# Si manquantes, copier depuis le legacy
cp -r /path/to/legacy/public/contrat-edition/img/* public/contrat-edition/img/
```

### Erreur "Helper getInitials not found"
```bash
# Régénérer l'autoload Composer
composer dump-autoload
```

### Erreur DomPDF "Failed to load image"
- Vérifier les permissions sur `public/contrat-edition/`
- Vérifier que les chemins dans les vues Blade utilisent `public_path()` ou `url()`

## 📊 Résultat Attendu

Après tous les tests :
- ✅ Tous les types de contrats se génèrent correctement
- ✅ Le fichier Trésor Money contient tous les stagiaires sélectionnés
- ✅ L'historique est correctement enregistré en base
- ✅ La modale de personnalisation fonctionne
- ✅ Aucune erreur dans les logs
- ✅ Les PDF sont visuellement corrects et imprimables

## 📝 Notes

### Pour consulter l'historique des générations :
```sql
-- Dernières générations
SELECT 
    hg.type_document,
    u.name as utilisateur,
    s.beneficiaire_id,
    hg.nom_fichier,
    hg.nombre_stagiaires,
    hg.created_at
FROM historique_generations hg
LEFT JOIN users u ON hg.user_id = u.id
LEFT JOIN stages s ON hg.stage_id = s.id
ORDER BY hg.created_at DESC
LIMIT 20;

-- Statistiques par type
SELECT 
    type_document,
    COUNT(*) as total,
    SUM(nombre_stagiaires) as total_stagiaires
FROM historique_generations
GROUP BY type_document;
```

### Pour déboguer les templates Blade :
- Ajouter `@dd($stagiaire)` au début du template pour voir les données
- Vérifier les logs dans `storage/logs/laravel.log`
