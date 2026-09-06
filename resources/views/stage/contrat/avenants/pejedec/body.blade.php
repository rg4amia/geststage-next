<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $fileName }}</title>
</head>

<body>
    <style>
        * {
            font-family: 'Times New Roman', serif;
            line-height: 1.3;
            /* margin-left: 30px; */
            /* margin-right: 30px; */
        }

        .paragraph-standard {
            text-align: justify;
            margin: 0cm 0cm 8pt;
            line-height: 107%;
            font-size: 12pt;
            font-family: 'Times New Roman', serif;
        }

        .paragraph-line-height-115 {
            text-align: justify;
            line-height: 115%;
            margin: 0cm 0cm 8pt;
            font-size: 12pt;
            font-family: 'Times New Roman', serif;
        }

        .section-title {
            text-decoration: underline;
            font-weight: bold;
            font-size: 12pt;
            font-family: 'Times New Roman', serif;
        }

        .section-title-center {
            text-align: center;
            font-size: 12pt;
            line-height: 107%;
            font-family: 'Times New Roman', serif;
        }

        .margin-left-18pt {
            margin: 0cm 0cm 0cm 18pt;
        }

        .list-item-standard {
            text-align: justify;
            line-height: 115%;
            font-size: 12pt;
            font-family: 'Times New Roman', serif;
            margin-bottom: 10px;
        }

        ol li {
            margin: 0cm 0cm 0cm 0px;
            text-align: justify;
            line-height: normal;
            font-size: 12pt;
            font-family: 'Times New Roman', serif;
        }

        /* Extracted inline styles */
        .text-align-left {
            text-align: left;
        }

        .title-h1 {
            text-align: center;
            margin-bottom: 30px;
            text-decoration: underline;
        }

        .title-span {
            font-size: 16pt;
        }

        .span-12pt-107 {
            font-size: 12.0pt;
            line-height: 107%;
            font-family: 'Times New Roman', serif;
        }

        .span-12pt-115 {
            font-size: 12.0pt;
            font-family: 'Times New Roman', serif;
        }

        .page-break {
            page-break-before: always;
        }

        .section-title-center-p {
            text-align: center;
            margin: 0cm 0cm 8pt;
        }

        .ol-standard {
            margin-top: 0cm;
            margin-bottom: 0cm;
        }

        .paragraph-standard-mt20 {
            margin-top: 20px;
        }

        .margin-left-18pt-p {
            text-align: justify;
            line-height: 107%;
            margin: 0cm 0cm 8pt 18pt;
        }

        .ul-square {
            list-style-type: square;
            margin-top: 0cm;
            margin-bottom: 0cm;
        }

        .table-standard {
            width: 100%;
            margin-top: 20px;
        }

        .td-text-right {
            text-align: right;
        }

        .td-signature-header {
            width: 33%;
            text-align: center;
            font-weight: bold;
        }

        .font-weight-normal {
            font-weight: normal;
        }

        .underline-dotted {
            text-decoration: underline dotted;
        }

        .td-signature-space {
            width: 33%;
            text-align: center;
            padding-top: 40px;
        }

        .signature-dots {
            margin-top: 30px;
            display: inline-block;
        }
    </style>
    <div class="container">
        @include('stage.contrat.avenants.pejedec.header')
        <div class="text-align-left">
            {{-- Title --}}
            <h1 class="title-h1">
                <span class="font-weight-bold title-span">
                    AVENANT AU CONTRAT DE STAGE DE QUALIFICATION<br>
                    OU D'EXPERIENCE PROFESSIONNELLE
                </span>
            </h1>

            {{-- Introduction --}}
            <p class="paragraph-standard">
                <span class="span-12pt-107">Entre les
                    soussigné(e) s&nbsp;:</span>
            </p>

            {{-- L'Agence Nationale pour l'Insertion et l'Emploi des Jeunes --}}
            <p class="paragraph-line-height-115">
                <strong><span class="span-12pt-115">L'Agence
                        Nationale pour l'Insertion et l'Emploi des Jeunes, </span></strong>
                <span class="span-12pt-115">structure créée par
                    Ordonnance N°2015-228 du 08 avril 2015, ayant son siège social à Abidjan Plateau, 39 Boulevard
                    Clozel,
                    Immeuble Pérignon, B.P.V 108 Abidjan, Tel&nbsp;: 27 20 21 25 90 / 27&nbsp; 20 21 06 69, représentée
                    par
                    Monsieur/Madame {{ optional($agence)->chef_agence ?? '...........................' }}, Chef d'Agence
                    Régionale de
                    {{ optional($agence)->libelle_agence ?? '...........................' }}, sur délégation de la
                    signature de
                    <strong>Monsieur Mamadou TOURE, Président du Conseil d'Orientation, Ministre de la Promotion de la
                        Jeunesse,
                        de l'Insertion Professionnelle et du Service Civique</strong> agissant en qualité de
                    <strong>Tuteur</strong>, </span>
            </p>

            <p class="paragraph-line-height-115">
                <span class="span-12pt-115">Ci-après désignée «
                    <strong>l'Agence Emploi Jeunes</strong> ».</span>
            </p>

            <p class="paragraph-standard">&nbsp;</p>

            {{-- L'EMPLOYEUR Section --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">L'EMPLOYEUR</span></u></strong>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">La société
                    <strong>{{ $stagiaire->entreprise->libelle_entreprise ?? '....................................' }}</strong>,
                    ayant pour activité principale
                    <strong>{{ $stagiaire->entreprise->secteur ?? '....................................' }}</strong>,
                    dont le
                    siège social est situé à
                    <strong>{{ $stagiaire->entreprise->ville ?? '....................................' }}</strong>, Toit
                    rouge,
                    N°CNPS&nbsp;:&nbsp;<strong>{{ optional($stagiaire->entreprise)->cnps ?? '....................................' }}</strong>,
                    N° Compte
                    Contribuable&nbsp;:&nbsp;<strong>{{ optional($stagiaire->entreprise)->compte_contri ?? '....................................' }}</strong>,
                    Tél&nbsp;:
                    <strong>{{ $stagiaire->entreprise->contact ?? '....................................' }}</strong>,
                    représentée par Monsieur
                    /Madame&nbsp;<strong>{{ $stagiaire->entreprise->dg ?? '....................................' }}</strong>,
                    &nbsp;<strong>{{ $stagiaire->entreprise->fonction ?? '....................................' }}</strong>,
                    dûment habilité(e) aux fins des présentes,</span>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Ci-après désignée «
                    <strong>{{ $stagiaire->entreprise->libelle_entreprise ?? '....................................' }}</strong>
                    <strong>»</strong></span>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">D'une part, </span>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Et </span>
            </p>

            {{-- LE/LA STAGIAIRE Section --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">{{ strtolower($stagiaire->sexe) === 'homme' ? 'LE' : 'LA' }}
                            STAGIAIRE</span></u></strong>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">
                    {{ strtolower($stagiaire->sexe) === 'homme' ? 'Monsieur' : 'Mademoiselle' }}
                    <strong>{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire ?? '....................................' }}</strong>,
                    {{ strtolower($stagiaire->sexe) === 'homme' ? 'né' : 'née' }} le
                    <strong>{{ isset($stagiaire->date_de_naissance) ? \Carbon\Carbon::parse($stagiaire->date_de_naissance)->isoFormat('DD/MM/Y') : '....................................' }}</strong>
                    à <strong>{{ $stagiaire->lieu_de_naissance ?? '....................................' }}</strong>,
                    {{ strtolower($stagiaire->sexe) === 'homme' ? 'domicilié' : 'domiciliée' }}
                    à
                    <strong>{{ $stagiaire->communeresidence?->name ?? '....................................' }}</strong>,
                    Tél&nbsp;: <strong>{{ $stagiaire->contact1 ?? '....................................' }}</strong>,
                    titulaire
                    {{ strtolower($stagiaire->sexe) === 'homme' ? "d'un" : "d'une" }}
                    @if(isset($stagiaire->diplome) && $stagiaire->diplome !== "AUTRE (A PRECISER)")
                        <strong>{{ $stagiaire->diplome }}</strong>
                    @else
                        <strong>{{ $stagiaire->autre_diplome ?? '....................................' }}</strong>
                    @endif
                    @if(isset($stagiaire->option_diplome) && $stagiaire->option_diplome)
                        ,&nbsp; Option <strong>{{ $stagiaire->option_diplome }}</strong>
                    @endif
                    , {{ strtolower($stagiaire->sexe) === 'homme' ? 'inscrit' : 'inscrite' }} à l'Agence Emploi Jeunes
                    sous le numéro
                    <strong>{{ $stagiaire->numero_aej ?? '....................................' }}</strong></span>
            </p>

            <p class="paragraph-standard">&nbsp;</p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">D'autre part, </span>
            </p>


            {{-- Collectivement appelés --}}
            <p class="section-title-center section-title-center-p">
                <span>Collectivement appelés <strong>«&nbsp;les parties&nbsp;»</strong></span>
            </p>

            <div class="page-break"></div>

            <p class="paragraph-standard">&nbsp;</p>

            {{-- Il a été convenu --}}
            <p class="paragraph-standard">
                <strong><span class="span-12pt-107">Il a été
                        convenu ce qui suit&nbsp;:</span></strong>
            </p>

            {{-- PREAMBULE --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">PREAMBULE</span></u></strong>
            </p>

            <p class="paragraph-line-height-115">
                <span class="span-12pt-115">Dans le cadre du Projet Emploi Jeune et Développement des Compétences (PEJEDEC),
                    <strong>l'Agence Emploi Jeunes</strong> et la Société
                    <strong>{{ $stagiaire->entreprise->libelle_entreprise ?? '...........................' }}</strong>
                    <strong>ont conclu</strong> un contrat de stage d'une durée de <strong>Six (06) mois</strong> avec
                    {{ strtolower($stagiaire->sexe) === 'homme' ? 'Monsieur' : 'Mademoiselle' }}
                    <strong>{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire ?? '...........................' }}</strong>
                    qui s'est déroulé du
                    <strong>{{ isset($stagiaire->date_debut_original) ? \Carbon\Carbon::parse($stagiaire->date_debut_original)->isoFormat('DD/MM/Y') : (isset($stagiaire->date_debut) ? \Carbon\Carbon::parse($stagiaire->date_debut)->isoFormat('DD/MM/Y') : '../../....') }}</strong>
                    au
                    <strong>{{ isset($stagiaire->date_fin_original) ? \Carbon\Carbon::parse($stagiaire->date_fin_original)->isoFormat('DD/MM/Y') : (isset($stagiaire->date_fin) ? \Carbon\Carbon::parse($stagiaire->date_fin)->isoFormat('DD/MM/Y') : '../../....') }}</strong>
                    <strong>inclus</strong>.</span>
            </p>

            {{-- Article 1 --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">Article
                            1</span></u></strong><strong><span class="span-12pt-107">&nbsp;: Valeur de
                        l'exposé</span></strong>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">L'exposé ci-dessus a
                    la même valeur juridique que les clauses du présent Avenant dont il fait partie intégrante.</span>
            </p>

            {{-- Article 2 --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">Article
                            2</span></u></strong><strong><span class="span-12pt-107">&nbsp;: Objet de
                        l'Avenant</span></strong>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Le Présent Avenant a
                    pour objet de renouveler les<strong> Points 3 </strong>intitulé «&nbsp;<strong>Obligations de
                        l'Employeur</strong>&nbsp;» <strong>et 6 </strong>intitulé<strong>s
                    </strong>«&nbsp;<strong>Durée du
                        Contrat</strong>&nbsp;» du contrat de stage.</span>
            </p>

            <p class="paragraph-standard">&nbsp;</p>

            {{-- 1- OBLIGATIONS DE L'EMPLOYEUR --}}
            <ol class="ol-standard">
                <li class="section-title">
                    <strong><u><span>OBLIGATIONS DE L'EMPLOYEUR</span></u></strong>
                </li>
            </ol>


            <p class="paragraph-standard paragraph-standard-mt20">
                <span class="span-12pt-107">La Société
                    <strong>{{ $stagiaire->entreprise->libelle_entreprise ?? '....................................' }}</strong>
                    s'engage à prendre comme stagiaire
                    {{ strtolower($stagiaire->sexe) === 'homme' ? 'Monsieur' : 'Mademoiselle' }}
                    <strong>{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire ?? '....................................' }}</strong>
                    en qualité de
                    <strong>{{ $stagiaire->intitule_poste_stage ?? '....................................' }}</strong> à
                    compter
                    du
                    <strong>{{ isset($dateDebut) ? \Carbon\Carbon::parse($dateDebut)->isoFormat('DD/MM/Y') : '../../....' }}</strong>
                    au
                    <strong>{{ isset($dateFin) ? \Carbon\Carbon::parse($dateFin)->isoFormat('DD/MM/Y') : '../../....' }}</strong>
                    inclus.</span>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Elle s'engage par
                    ailleurs à&nbsp;: </span>
            </p>

            <p class="margin-left-18pt margin-left-18pt-p">&nbsp;</p>

            <ul class="ul-square">
                <li class="list-item-standard">
                    <span class="span-12pt-115">Lui permettre de
                        suivre éventuellement des formations selon le programme mis en place par l'Agence Emploi
                        Jeunes&nbsp;;</span>
                </li>
                <li class="list-item-standard ">
                    <span class="span-12pt-115">Désigner
                        Monsieur/Madame&nbsp;<strong>{{ $stagiaire->nom_encadreur ?? '...........................' }}</strong>,
                        Fonction&nbsp;<strong>{{ $stagiaire->fonction_encadreur ?? '...........................' }}</strong>,
                        comme Encadreur
                        pour suivre {{ strtolower($stagiaire->sexe) === 'homme' ? 'le' : 'la' }} stagiaire lors de sa
                        formation&nbsp;; l'encadreur est tenu de remettre trimestriellement à l'Agence Emploi Jeunes, un
                        rapport d'évaluation des aptitudes
                        {{ strtolower($stagiaire->sexe) === 'homme' ? 'du' : 'de la' }} stagiaire&nbsp;;</span>
                </li>
                <li class="list-item-standard">
                    <span class="span-12pt-115">Contrôler son
                        assiduité&nbsp;;</span>
                </li>
                <li class="list-item-standard">
                    <span class="span-12pt-115">Prévenir l'Agence
                        Emploi Jeunes des insuffisances professionnelles
                        {{ strtolower($stagiaire->sexe) === 'homme' ? 'du' : 'de la' }} stagiaire, des fautes graves
                        qu'{{ strtolower($stagiaire->sexe) === 'homme' ? 'il' : 'elle' }} pourrait
                        commettre ainsi que des absences ou faits de nature à motiver son intervention&nbsp;; le cas
                        échéant {{ strtolower($stagiaire->sexe) === 'homme' ? 'le' : 'la' }} remettre à la disposition
                        de l'Agence Emploi Jeunes&nbsp;;</span>
                </li>
                <li class="list-item-standard">
                    <span class="span-12pt-115">Fournir
                        mensuellement à l'Agence Emploi Jeunes, une liste d'émargement des stagiaires&nbsp;;</span>
                </li>
                <li class="list-item-standard">
                    <span class="span-12pt-115">Octroyer mensuellement
                        {{ strtolower($stagiaire->sexe) === 'homme' ? 'au' : 'à la' }} stagiaire, une prime de stage
                        d'un
                        Montant
                        de&nbsp;<strong>{{ isset($prime) ? number_format($prime, 0, ',', ' ') : '....................................' }}
                            F CFA</strong>&nbsp;;</span>
                </li>
                <li class="list-item-standard">
                    <span class="span-12pt-115">Cosigner avec
                        l'Agence Emploi Jeunes, l'attestation de fin de stage.</span>
                </li>
            </ul>

            <p class="paragraph-standard">&nbsp;</p>

            <p class="paragraph-standard">&nbsp;</p>

            <p class="paragraph-standard">&nbsp;</p>

            <div class="page-break"></div>

            {{-- 2- DUREE DU CONTRAT --}}
            <ol class="ol-standard" start="2">
                <li class="section-title">
                    <strong><u><span>DUREE DU CONTRAT</span></u></strong>
                </li>
            </ol>

            <p class="paragraph-standard">&nbsp;</p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Le présent Contrat
                    est conclu pour une durée de <strong>six (06) mois</strong> à compter du jour de la mise en stage du
                    candidat. </span>
            </p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Pendant cette
                    période, le Contrat pourra être résilié par la volonté de l'une ou l'autre des Parties sans autres
                    obligations.</span>
            </p>

            <p class="paragraph-standard">&nbsp;</p>

            {{-- Article 3 --}}
            <p class="paragraph-standard">
                <strong><u><span class="span-12pt-107">Article
                            3</span></u></strong><strong><span class="span-12pt-107">&nbsp;:
                        Dispositions Diverses</span></strong>
            </p>

            <p class="paragraph-standard">&nbsp;</p>

            <p class="paragraph-standard">
                <span class="span-12pt-107">Les autres clauses du
                    contrat de stage non contraire aux dispositions du présent Avenant restent inchangées.</span>
            </p>

            @include('stage.contrat.avenants.footer')
        </div>
    </div>
</body>