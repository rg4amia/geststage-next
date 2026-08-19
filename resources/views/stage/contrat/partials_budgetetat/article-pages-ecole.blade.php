@php
    $primeData = getPrimeDisplayDataByFinancementType($stagiaire);
    $primeFormatted = $primeData['formatted_amount'];
    $primeWords = $primeData['amount_in_words'];

    $dureeEnMots = match((string) $stagiaire->nbre_mois_prev) {
        '1' => 'un (01) mois',
        '1.5' => 'un (01) mois et demi',
        '2' => 'deux (02) mois',
        '3' => 'trois (03) mois',
        default => $stagiaire->nbre_mois_prev . ' mois',
    };

    $dureeEnMotsCMU = $dureeEnMots;
    $nbreMois = (float) $stagiaire->nbre_mois_prev;
    $montantCMU = (int) round($nbreMois * 1000);
    $montantCMUFormatted = number_format($montantCMU, 0, ',', ' ');
    $montantCMUWords = match($montantCMU) {
        1000 => 'mille',
        1500 => 'mille',
        2000 => 'deux mille',
        3000 => 'trois mille',
        4000 => 'quatre mille',
        5000 => 'cinq mille',
        6000 => 'six mille',
        default => (string) $montantCMU,
    };
@endphp

<style>
    .budgetetat-ecole-article-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 11pt;
        line-height: 1.3;
    }

    .budgetetat-ecole-article-table td {
        width: 50%;
        vertical-align: top;
        padding: 0 12px;
        text-align: justify;
    }

    .budgetetat-ecole-article-table h2 {
        font-size: 11.5pt;
        font-weight: 700;
        margin: 10px 0 6px 0;
        text-transform: uppercase;
        text-align: left;
    }

    .budgetetat-ecole-article-table p {
        margin: 4px 0;
    }

    .budgetetat-ecole-article-table ul {
        margin: 8px 0;
        padding-left: 22px;
    }

    .budgetetat-ecole-article-table li {
        margin-bottom: 9px;
    }

    .budgetetat-ecole-article-table .important {
        font-weight: bold;
    }
</style>

<div style="page-break-before: always;">
    <p style="font-family: 'Cambria', 'Times New Roman', serif; font-size: 11pt; font-weight: bold; margin: 0 12px 8px;">
        Il a été convenu ce qui suit :
    </p>
    <table class="budgetetat-ecole-article-table">
        <tr>
            <td>
                <h2>Article 1 : Nature du contrat</h2>
                <p>Le présent contrat est un <span class="important">contrat de stage école</span>.</p>
                <p>Il est soumis aux dispositions de la <span class="important">loi n°2015-532 du 20 juillet 2015</span> portant Code du Travail et ses décrets d'application.</p>

                <h2>Article 2 : Objet du contrat</h2>
                <p>Le <span class="important">PARTENAIRE</span> accepte d'accueillir, dans les conditions définies ci-après le/la stagiaire.</p>
                <p>La finalité et les modalités de la prestation du programme sont définies dans le présent contrat.</p>

                <h2>Article 3 : Obligations de l'Agence Emploi Jeunes</h2>
                <p>L'Agence Emploi Jeunes s'engage à :</p>
                <ul>
                    <li>organiser un <span class="important">test de sélection gratuit</span> pour le/la stagiaire ;</li>
                    <li>assurer en relation avec le Maître de stage, le <span class="important">suivi du/de la stagiaire</span> en entreprise ;</li>
                    <li>s'assurer à la fin du stage que le <span class="important">matériel prêté</span> au/à la stagiaire a été restitué au PARTENAIRE ;</li>
                    <li>contrôler l'<span class="important">assiduité</span> du/de la stagiaire ;</li>
                    <li>remettre au/à la stagiaire, une <span class="important">indemnité mensuelle</span> d'un montant de <span class="important">{{ $primeWords }} ({{ $primeFormatted }}) FCFA</span> (trois (03) mois pour les étudiants du premier Cycle Universitaire et six (06) mois pour les étudiants du deuxième et du troisième Cycle Universitaire) ;</li>
                    <li>assurer la <span class="important">couverture sociale</span> du/de la stagiaire, au titre des accidents du travail et une assurance couvrant sa responsabilité civile ;</li>
                    <li>délivrer au/à la stagiaire, une <span class="important">attestation en fin de stage</span>, cosignée par le PARTENAIRE, indiquant sa qualification, l'objet et la durée du stage.</li>
                </ul>
            </td>
            <td>
                <h2>Article 4 : Obligations du PARTENAIRE</h2>
                <p>Le PARTENAIRE s'engage à :</p>
                <ul>
                    <li>participer à la <span class="important">sélection définitive</span> du/de la stagiaire ;</li>
                    <li>contrôler l'<span class="important">assiduité</span> du/de la stagiaire ;</li>
                    <li>désigner un <span class="important">Maître de stage</span> pour suivre le/la stagiaire ;</li>
                    <li>prévenir l'Agence Emploi Jeunes des <span class="important">fautes graves</span> que le/la stagiaire pourrait commettre ainsi que des absences ou faits de nature à motiver son intervention ; le cas échéant le/la remettre à la disposition de l'Agence Emploi Jeunes ;</li>
                    <li>fournir mensuellement, une <span class="important">attestation de présence</span> au stage du/de la stagiaire cosigné par l'Agence Emploi Jeunes ;</li>
                    <li>promouvoir et maintenir le plus haut degré possible de <span class="important">bien-être physique, mental et social</span> du/de la stagiaire ;</li>
                    <li>protéger le/la stagiaire contre les <span class="important">dangers</span> qui menacent sa santé ;</li>
                    <li>placer et maintenir le/la stagiaire dans un <span class="important">environnement de travail adapté</span> à ses conditions physiques et mentales ;</li>
                    <li>organiser une <span class="important">formation en matière d'hygiène et de sécurité</span> au bénéfice des stagiaires nouvellement recrutés ;</li>
                    <li>cosigner l'<span class="important">attestation de fin de stage</span>.</li>
                </ul>

                <h2>Article 5 : Obligations du/de la stagiaire</h2>
                <p>Le/la stagiaire s'engage à :</p>
                <ul>
                    <li>émarger sur la <span class="important">liste de présence</span> en début de chaque semaine ;</li>
                    <li>se consacrer exclusivement aux <span class="important">activités indiquées</span> dans le programme d'activités établi par le Maître de stage ;</li>
                    <li>se soumettre aux <span class="important">liens de subordination</span> de ses supérieurs hiérarchiques ;</li>
                    <li>respecter les dispositions du <span class="important">règlement intérieur</span> ;</li>
                    <li>se conformer aux <span class="important">heures de travail</span> du PARTENAIRE ;</li>
                </ul>
            </td>
        </tr>
    </table>
</div>

<div style="page-break-before: always;">
    <table class="budgetetat-ecole-article-table">
        <tr>
            <td>
                <ul>
                    <li>observer une <span class="important">assiduité exemplaire</span> au sein de l'Entreprise ;</li>
                    <li>n'utiliser en aucun cas, pendant la durée du stage et également après son expiration, les <span class="important">informations recueillies</span> ou obtenues par lui/elle pour en faire l'objet de publication, de communication à des tiers sans accord préalable du PARTENAIRE ;</li>
                    <li>restituer, à la fin du programme, le <span class="important">matériel</span> à lui/elle confié.</li>
                </ul>

                <h2>Article 6 : Conditions de stage</h2>
                <p>Outre les clauses générales figurant au présent contrat et que les Parties, par leur signature ci-dessous, acceptent sans restriction, les conditions particulières de déroulement du programme sont les suivantes :</p>
                <p>L'Agence Emploi Jeunes s'engage à verser mensuellement au/à la stagiaire, la <span class="important">prime de stage de {{ $primeWords }} ({{ $primeFormatted }}) F CFA</span>, par mobile money sur son numéro de téléphone disposant d'un compte <span class="important">TRESORPAY</span> identifié à son nom.</p>
                <p>L'affiliation à la Couverture Maladie Universelle (CMU) est obligatoire pour tout stagiaire. Le prélèvement pour les <span class="important">{{ $dureeEnMotsCMU }}</span> de stage d'un montant de <span class="important">{{ $montantCMUWords }} ({{ $montantCMUFormatted }}) F CFA</span> se fera en une fois sur la prime de stage et la cotisation sera reversée à la Caisse Nationale d'Assurance Maladie (CNAM) pour le compte des stagiaires.</p>

                <h2>Article 7 : Durée du contrat</h2>
                <p>Le présent Contrat est conclu pour une durée de <span class="important">{{ $dureeEnMots }}</span>.</p>
                <p>Pendant cette période, le Contrat pourra être <span class="important">résilié</span> par la volonté de l'une ou l'autre des Parties sans autres obligations.</p>

                <h2>Article 8 : Réclamation</h2>
                <p>En cas d'insatisfaction, le/la stagiaire s'engage à porter toute plainte ou réclamation relative à l'exécution du présent contrat, uniquement auprès de l'Agence Emploi Jeunes en s'adressant d'abord à son <span class="important">Conseiller en Insertion Professionnelle</span> et ensuite au <span class="important">Chef d'Agence Régionale</span> de l'Agence Emploi Jeunes.</p>

                <h2>Article 9 : Sanctions disciplinaires</h2>
                <p>En cas de non-respect de ses obligations, le/la stagiaire s'expose à des <span class="important">sanctions</span> allant de la perte du bénéfice de l'indemnité de Programme à la radiation définitive de la liste des bénéficiaires du programme.</p>
            </td>
            <td>
                <h2>Article 10 : Communication-Utilisation des données à caractère personnel-Publicité</h2>
                <p>Dans le cadre de sa communication sur les programmes d'emploi, l'Agence Emploi Jeunes prévoit faire des films témoignages des jeunes bénéficiaires.</p>
                <p>A cet effet, le/la stagiaire :</p>
                <ul>
                    <li>autorise l'Agence Emploi Jeunes à le/la <span class="important">photographier ou le/la filmer</span> et à utiliser son image. Il/Elle autorise par conséquent, et conformément aux dispositions relatives au droit à l'image, l'Agence Emploi Jeunes à fixer, reproduire et communiquer au public les photographies ou les vidéos prises dans le cadre de la présente ;</li>
                    <li>autorise également, conformément à la loi n°2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel, l'Agence Emploi Jeunes à <span class="important">collecter, traiter, stocker et utiliser</span> ses données à caractère personnel, notamment ses nom et prénoms. Cette captation pourra être exploitée et utilisée directement par l'Agence Emploi Jeunes, sur les supports de médias audiovisuels ;</li>
                    <li>s'engage à communiquer par message, vidéo, etc. sur les programmes de l'Agence Emploi Jeunes lorsqu'il/elle est sollicité(e).</li>
                </ul>
                <p>Le/la stagiaire reconnait être entièrement rempli(e) de ses droits et ne pourra prétendre à aucune rémunération pour l'exploitation des droits visés aux présentes.</p>
                <p>Le/la stagiaire garantit ne pas être lié(e) par un contrat exclusif relatif à l'utilisation de son image ou de son nom.</p>
            </td>
        </tr>
    </table>
</div>
