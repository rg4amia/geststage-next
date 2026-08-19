<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de Stage PAE - Budget État</title>
    <style>
        @page {
            margin: 2cm 1.5cm;
        }

        body {
            font-family: 'Cambria', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #000;
        }

        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0 0 10px 0;
            text-transform: uppercase;
        }

        .header p {
            margin: 5px 0;
            font-size: 10pt;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 10px;
            text-decoration: underline;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            min-width: 150px;
        }

        .article {
            margin-bottom: 20px;
        }

        .article-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .article-content {
            margin-left: 20px;
            text-align: justify;
        }

        .signatures {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            margin-top: 60px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Contrat de Stage Professionnel</h1>
        <p><strong>Programme d'Appui à l'Emploi des Jeunes (PAE)</strong></p>
        <p>Source de Financement : Budget État</p>
    </div>

    <div class="section">
        <div class="section-title">ENTRE LES SOUSSIGNÉS :</div>

        <div class="info-row">
            <span class="info-label">L'Agence :</span>
            <span>{{ $stagiaire->agence->libelle_agence ?? $stagiaire->agence->nom ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Représentée par :</span>
            <span>Le Chef d'Agence</span>
        </div>

        <p style="text-align: center; font-weight: bold; margin: 20px 0;">ET</p>

        <div class="info-row">
            <span class="info-label">L'Entreprise :</span>
            <span>{{ $stagiaire->entreprise->libelle_entreprise ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Adresse :</span>
            <span>{{ $stagiaire->entreprise->adresse ?? 'N/A' }}</span>
        </div>

        <p style="text-align: center; font-weight: bold; margin: 20px 0;">ET</p>

        <div class="info-row">
            <span class="info-label">Le/La Stagiaire :</span>
            <span>{{ $stagiaire->nom_stagiaire }} {{ $stagiaire->prenoms_stagiaire }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Né(e) le :</span>
            <span>{{ $stagiaire->date_naissance ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Matricule AEJ :</span>
            <span>{{ $stagiaire->numero_aej }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Pièce d'identité :</span>
            <span>{{ $stagiaire->nature_piece ?? 'N/A' }} N° {{ $stagiaire->num_piece ?? 'N/A' }}</span>
        </div>

        <div class="info-row">
            <span class="info-label">Résidence :</span>
            <span>{{ $stagiaire->commune_residence ?? 'N/A' }}</span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">OBJET DU CONTRAT</div>
        <p class="article-content">
            Le présent contrat a pour objet de définir les conditions de stage professionnel de
            <strong>{{ $stagiaire->nom_stagiaire }} {{ $stagiaire->prenoms_stagiaire }}</strong>
            au sein de l'entreprise <strong>{{ $stagiaire->entreprise->libelle_entreprise }}</strong>
            dans le cadre du Programme d'Appui à l'Emploi des Jeunes (PAE).
        </p>
    </div>

    <div class="section">
        <div class="article">
            <div class="article-title">ARTICLE 1 : Durée du Stage</div>
            <div class="article-content">
                <p>Le stage se déroulera du <strong>{{ $stagiaire->date_debut }}</strong> au <strong>{{ $stagiaire->date_fin_prevue }}</strong>,
                soit une durée de <strong>{{ $stagiaire->duree_mois ?? 'N/A' }}</strong> mois.</p>
            </div>
        </div>

        <div class="article">
            <div class="article-title">ARTICLE 2 : Fonction et Missions</div>
            <div class="article-content">
                <p>Le/La stagiaire occupera le poste de : <strong>{{ $fonction ?? $stagiaire->intitule_poste_stage }}</strong></p>
                <p>Il/Elle sera chargé(e) de missions en rapport avec sa fonction, sous la supervision d'un encadreur désigné par l'entreprise.</p>
            </div>
        </div>

        <div class="article">
            <div class="article-title">ARTICLE 3 : Indemnité de Stage</div>
            <div class="article-content">
                <p>Le/La stagiaire percevra une indemnité mensuelle de stage de :
                    <strong>{{ number_format($montant ?? $stagiaire->montant_indemnite, 0, ',', ' ') }} FCFA</strong>
                </p>
                <p>Cette indemnité sera versée par le Trésor Public conformément aux procédures en vigueur.</p>
            </div>
        </div>

        <div class="article">
            <div class="article-title">ARTICLE 4 : Obligations du Stagiaire</div>
            <div class="article-content">
                <p>Le/La stagiaire s'engage à :</p>
                <ul>
                    <li>Respecter le règlement intérieur de l'entreprise ;</li>
                    <li>Exécuter consciencieusement les tâches qui lui sont confiées ;</li>
                    <li>Observer la confidentialité sur les informations de l'entreprise ;</li>
                    <li>Être assidu(e) et ponctuel(le).</li>
                </ul>
            </div>
        </div>

        <div class="article">
            <div class="article-title">ARTICLE 5 : Obligations de l'Entreprise</div>
            <div class="article-content">
                <p>L'entreprise s'engage à :</p>
                <ul>
                    <li>Fournir au/à la stagiaire les conditions de travail nécessaires ;</li>
                    <li>Désigner un encadreur pour superviser le stage ;</li>
                    <li>Permettre l'acquisition de compétences professionnelles ;</li>
                    <li>Remplir les documents de suivi du stage.</li>
                </ul>
            </div>
        </div>

        <div class="article">
            <div class="article-title">ARTICLE 6 : Résiliation</div>
            <div class="article-content">
                <p>Le présent contrat peut être résilié en cas de manquement grave aux obligations de l'une des parties,
                après notification écrite à l'Agence Emploi Jeunes.</p>
            </div>
        </div>
    </div>

    <div class="signatures">
        <div class="signature-block">
            <p><strong>Pour l'Agence Emploi Jeunes</strong></p>
            <div class="signature-line">
                <p>Le Chef d'Agence</p>
            </div>
        </div>

        <div class="signature-block">
            <p><strong>Pour l'Entreprise</strong></p>
            <div class="signature-line">
                <p>Le Responsable</p>
            </div>
        </div>
    </div>

    <div style="margin-top: 50px;">
        <div class="signature-block" style="width: 100%;">
            <p><strong>Le/La Stagiaire</strong></p>
            <div class="signature-line">
                <p>{{ $stagiaire->nom_stagiaire }} {{ $stagiaire->prenoms_stagiaire }}</p>
            </div>
        </div>
    </div>
</body>
</html>
