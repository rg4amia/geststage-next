@php
    $primeData = getPrimeDisplayDataByFinancementType($stagiaire);
    $primeFormatted = $primeData['formatted_amount'];
    $primeWords = $primeData['amount_in_words'];
    $isInterne = str_contains(strtolower(@$stagiaire->entreprise->libelle_entreprise ?? ''), 'agence emploi');
    
    // Calcul dynamique de la cotisation CMU basé sur la durée du stage
    // Montant CMU = 1 000 F CFA par mois
    $nbreMois = (float) $stagiaire->nbre_mois_prev;
    $montantCMU = (int) round($nbreMois * 1000);
    $montantCMUFormatted = number_format($montantCMU, 0, ',', ' ');
    
    // Durée en mots pour le texte CMU
    $dureeEnMotsCMU = match((string) $stagiaire->nbre_mois_prev) {
        '1'   => 'un (01) mois',
        '1.5' => 'un (01) mois et demi',
        '2'   => 'deux (02) mois',
        '3'   => 'trois (03) mois',
        '4'   => 'quatre (04) mois',
        '5'   => 'cinq (05) mois',
        '6'   => 'six (06) premiers mois',
        default => $stagiaire->nbre_mois_prev . ' mois',
    };
    
    // Montant CMU en toutes lettres
    $montantCMUWords = match($montantCMU) {
        1000  => 'mille',
        1000  => 'mille cinq cents',
        2000  => 'deux mille',
        3000  => 'trois mille',
        4000  => 'quatre mille',
        5000  => 'cinq mille',
        6000  => 'six mille',
        7000  => 'sept mille',
        8000  => 'huit mille',
        9000  => 'neuf mille',
        10000 => 'dix mille',
        11000 => 'onze mille',
        12000 => 'douze mille',
        default => number_format($montantCMU, 0, ',', ' '),
    };
@endphp

<style>
    .article-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 10.3pt;
        line-height: 1.25;
    }
    .article-table td {
        width: 48%;
        vertical-align: top;
        padding: 0 10px;
        text-align: justify;
    }
    .article-table h2 {
        font-size: 10.3pt;
        font-weight: bold;
        margin: 8px 0 4px 0;
        text-transform: uppercase;
    }
    .article-table p {
        margin: 3px 0;
    }
    .article-table ul {
        margin: 3px 0;
        padding-left: 15px;
    }
    .article-table li {
        margin-bottom: 3px;
    }
    .article-table .important {
        font-weight: bold;
    }
</style>

{{-- Page 1 : Articles 1-4 --}}
<div style="page-break-before: always;">
    <table class="article-table">
        <tr>
            <td>
                <h2>Article 1 : Nature du contrat</h2>
                <p>Le présent contrat est un <span class="important">contrat de stage de qualification</span>.</p>
                <p>Il est soumis aux dispositions de la <span class="important">loi n°2015-532 du 20 juillet
                        2015</span> portant Code du Travail et ses décrets d'application.</p>

                <h2>Article 2 : Objet du contrat</h2>
                <p>Le <span class="important">PARTENAIRE</span> accepte d'accueillir, dans les conditions définies
                    ci-après le/la stagiaire.</p>
                <p>La finalité et les modalités de la prestation du programme sont définies dans le présent contrat.</p>

                <h2>Article 3 : Obligations de l'Agence Emploi Jeunes</h2>
                <p>L'Agence Emploi Jeunes s'engage à :</p>
                <ul>
                    <li>organiser un <span class="important">test de recrutement gratuit</span> pour les primo
                        demandeurs d'emploi ;</li>
                    <li>soumettre le/la stagiaire à des <span class="important">tests d'évaluation</span> et à des
                        entretiens préalablement à sa sélection ;</li>
                    <li>faire effectuer au/à la stagiaire une <span class="important">visite médicale</span> avant le
                        début de son stage selon la spécificité du poste ;</li>
                    <li>fournir pour chaque postulant, un <span class="important">curriculum vitae</span>, une demande
                        de stage, une fiche de renseignement signalétique, une fiche d'inscription à l'Agence Emploi
                        Jeunes et le procès-verbal de sélection ;</li>
                    <li>assurer en relation avec le Maître de stage, le <span class="important">suivi du/de la
                            stagiaire</span> en entreprise ;</li>
                    <li>s'assurer à la fin du stage que le <span class="important">matériel prêté</span> au/à la
                        stagiaire a été restitué au PARTENAIRE ;</li>
                    <li>remettre au/à la stagiaire, une <span class="important">indemnité de stage et de
                            transport</span> d'une valeur de
                        <span class="important">{{ $primeWords }} ({{ $primeFormatted }}) F CFA</span> ;</li>
                    <li>délivrer au/à la stagiaire, une <span class="important">attestation en fin de stage</span>
                        cosignée par le PARTENAIRE et indiquant sa qualification, l'objet et la durée du stage.</li>
                </ul>
            </td>
            <td>
                <h2>Article 4 : Obligations du PARTENAIRE</h2>
                <p>Le PARTENAIRE s'engage à :</p>
                <ul>
                    <li>soumettre tous les stagiaires présélectionnés par l'Agence Emploi Jeunes à un
                        <span class="important">test de recrutement final</span> ;</li>
                    <li>informer l'Agence Emploi Jeunes du <span class="important">choix définitif</span> de
                        candidats ;</li>
                    <li>procéder au <span class="important">démarrage de stage effectif</span> du/des candidat(s)
                        choisi(s) et en informer l'Agence Emploi Jeunes ;</li>
                    <li>prévoir une <span class="important">fiche de poste</span> des stagiaires selon les tâches
                        spécifiques à réaliser ;</li>
                    <li>permettre aux stagiaires de suivre éventuellement des
                        <span class="important">formations</span> organisées par l'Agence Emploi Jeunes ;</li>
                    <li>contrôler l'<span class="important">assiduité</span> des stagiaires ;</li>
                    <li>désigner un <span class="important">Maître de stage</span> pour suivre les stagiaires. Le
                        Maître de stage est tenu de remettre trimestriellement à l'Agence Emploi Jeunes, le rapport
                        d'évaluation de leurs aptitudes ;</li>
                    <li>désigner un <span class="important">encadreur</span> ou toute autre personne ressource dédiée
                        à la signature de la liste d'émargement des stagiaires ;</li>
                    <li>prévenir l'Agence Emploi Jeunes des <span class="important">insuffisances
                            professionnelles</span>, des <span class="important">fautes graves</span>, que le/la
                        stagiaire pourrait commettre ainsi que des absences ou faits de nature à motiver son
                        intervention ; le cas échéant le/la remettre à la disposition de l'Agence Emploi Jeunes ;</li>
                    <li>fournir mensuellement à l'Agence Emploi Jeunes une <span class="important">liste
                            d'émargement</span> des stagiaires ;</li>
                    <li>promouvoir et maintenir le plus haut degré possible de <span class="important">bien-être
                            physique, mental et social</span> des stagiaires ;</li>
                    <li>protéger les stagiaires contre les <span class="important">dangers</span> qui menacent leur
                        santé ;</li>
                    <li>placer et maintenir les stagiaires dans un <span class="important">environnement de travail
                            adapté</span> à leurs conditions physiques et mentales ;</li>
                    <li>organiser une <span class="important">formation en matière d'hygiène et de sécurité</span> au
                        bénéfice des stagiaires nouvellement recrutés ;</li>
                    <li>cosigner avec l'Agence Emploi Jeunes l'<span class="important">attestation de fin de
                            stage</span> indiquant sa qualification, l'objet et la durée du stage dans un délai de
                        deux (2) semaines maximum.</li>
                </ul>
            </td>
        </tr>
    </table>
</div>

{{-- Page 2 : Articles 5-10 --}}
<div style="page-break-before: always;">
    <table class="article-table">
        <tr>
            <td>
                <h2>Article 5 : Obligations du/de la stagiaire</h2>
                <p>Le/la stagiaire s'engage à :</p>
                <ul>
                    <li>constituer son <span class="important">dossier</span> au niveau de son conseiller avant le
                        démarrage du programme. Le dossier comprend : la copie de la Carte Nationale d'Identité ou de
                        l'Attestation d'Identité, la fiche d'inscription sur le site de l'Agence Emploi Jeunes et la
                        copie légalisée du/des diplôme(s) ;</li>
                    <li>émarger sur la <span class="important">liste de présence</span> en début de chaque
                        semaine ;</li>
                    <li>se consacrer exclusivement aux <span class="important">activités indiquées</span> dans le
                        programme d'activités établi par le Maître de stage ;</li>
                    <li>se soumettre aux <span class="important">liens de subordination</span> de ses supérieurs
                        hiérarchiques ;</li>
                    <li>respecter les dispositions du <span class="important">règlement intérieur</span> ;</li>
                    <li>se conformer aux <span class="important">heures de travail</span> du PARTENAIRE ;</li>
                    <li>observer une <span class="important">assiduité exemplaire</span> au sein de
                        l'Entreprise ;</li>
                    <li>n'utiliser en aucun cas, pendant la durée du stage et également après son expiration, les
                        <span class="important">informations recueillies</span> ou obtenues par lui/elle pour en faire
                        l'objet de publication, de communication à des tiers sans accord préalable du
                        PARTENAIRE ;</li>
                    <li>restituer, à la fin du programme, le <span class="important">matériel</span> à lui/elle
                        confié.</li>
                </ul>

                <h2>Article 6 : Conditions de stage</h2>
                <p>Outre les clauses générales figurant au présent contrat et que les Parties, par leur signature
                    ci-dessous, acceptent sans restriction, les conditions particulières de déroulement du programme
                    sont les suivantes :</p>
                <p>La <span class="important">prime de stage et de transport de {{ $primeWords }}
                        ({{ $primeFormatted }}) F CFA</span> à verser mensuellement au/à la stagiaire par l'Agence
                    Emploi Jeunes se fera par mobile money sur ses numéros de téléphone disposant d'un compte
                    <span class="important">TRESORPAY</span> identifié à son nom.</p>
                <p>Le montant de la <span class="important">prime d'encouragement</span> à verser mensuellement au/à
                    la stagiaire par le PARTENAIRE se fera conformément aux dispositions comptables en vigueur au sein
                    de son entreprise.</p>
                <p>
                    L’affiliation à la Couverture Maladie Universelle (CMU) est obligatoire pour tout stagiaire. 
                    Le prélèvement pour les six (06) premiers mois de stage d’un montant de <strong>six mille (6 000) F CFA</strong> se fera en une fois sur la première prime de stage et la cotisation sera reversée à la Caisse Nationale d’Assurance Maladie (CNAM) pour le compte des stagiaires.
                </p>

                <h2>Article 7 : Durée du contrat</h2>
                <p>Le présent Contrat est conclu pour une durée de <span class="important">six (6) mois</span>. Il est
                    renouvelable une fois.</p>
                <p>Au terme de ces six (6) premiers mois, une évaluation sera effectuée en vue d'un renouvellement. Le
                    renouvellement se fera par voie d'avenant.</p>
                <p>Pendant cette période, le Contrat pourra être <span class="important">résilié</span> sur la volonté
                    de l'une ou l'autre des Parties sans autres obligations.</p>
            </td>
            <td>
                <h2>Article 8 : Réclamation</h2>
                <p>Le/la stagiaire s'engage à porter toute plainte ou réclamation relative à l'exécution du présent
                    contrat, uniquement auprès de l'Agence Emploi Jeunes en s'adressant d'abord à son
                    <span class="important">Conseiller en Insertion Professionnelle</span> et ensuite au
                    <span class="important">Chef d'Agence Régionale</span> de l'Agence Emploi Jeunes en cas
                    d'insatisfaction.</p>

                <h2>Article 9 : Sanctions disciplinaires</h2>
                <p>En cas de non-respect de ses obligations, le/la stagiaire s'expose à des
                    <span class="important">sanctions</span> allant de la perte du bénéfice de l'indemnité de
                    Programme à la radiation définitive de la liste des bénéficiaires du programme.</p>

                <h2>Article 10 : Communication-Utilisation des données à caractère personnel-Publicité</h2>
                <p>Dans le cadre de sa communication sur les programmes d'emploi, l'Agence Emploi Jeunes prévoit faire
                    des films témoignages des jeunes bénéficiaires. A cet effet, le/la stagiaire :</p>
                <ul>
                    <li>autorise l'Agence Emploi Jeunes à le/la <span class="important">photographier ou le/la
                            filmer</span> et à utiliser son image. Il/Elle autorise par conséquent, et conformément aux
                        dispositions relatives au droit à l'image, l'Agence Emploi Jeunes à faire reproduire et
                        communiquer au public les photographies ou les vidéos prises dans le cadre de la
                        présente ;</li>
                    <li>autorise également, conformément à la loi n°2013-450 du 19 juin 2013 relative à la protection
                        des données à caractère personnel, l'Agence Emploi Jeunes à <span class="important">collecter,
                            traiter, stocker et utiliser</span> ses données à caractère personnel, notamment ses nom et
                        prénoms. Cette captation pourra être exploitée et utilisée directement par l'Agence Emploi
                        Jeunes, sur les supports de médias audiovisuels ;</li>
                    <li>s'engage à communiquer par message, vidéo, etc… sur les programmes de l'Agence Emploi Jeunes
                        lorsqu'il/elle est sollicité(e).</li>
                </ul>
                <p>Le/la stagiaire reconnait être entièrement rempli(e) de ses droits et ne pourra prétendre à aucune
                    rémunération pour l'exploitation des droits visés aux présentes.</p>
                <p>Le/la stagiaire garantit ne pas être lié(e) par un contrat exclusif relatif à l'utilisation de son
                    image ou de son nom.</p>

                @if ($isInterne)
                    <h2>Article 11 : Volontariat-Bénévolat</h2>
                    <p>Le/la stagiaire s'engage à participer aux programmes de <span class="important">volontariat et
                            bénévolat</span> initiés par le Ministère de la Promotion de la Jeunesse, de l'Insertion
                        Professionnelle et du Service Civique et ses démembrements.</p>
                    <p>Il/elle s'abstient d'exiger une contrepartie quelle qu'en soit sa nature pour sa
                        participation.</p>
                    <p>Il/elle déclare adhérer aux valeurs de <span class="important">civisme, de citoyenneté, de
                            solidarité, de cohésion sociale, de paix</span> et œuvrer à les promouvoir.</p>
                @endif
            </td>
        </tr>
    </table>
</div>
