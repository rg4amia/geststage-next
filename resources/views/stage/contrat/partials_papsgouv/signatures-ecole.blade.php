<div style="page-break-before: always; font-family: 'Cambria', 'Times New Roman', serif; font-size: 11pt; margin: 0 12px;">
    <style>
        .papsgouv-ecole-signature-article {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Cambria', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.3;
            text-align: justify;
        }

        .papsgouv-ecole-signature-article td {
            width: 50%;
            vertical-align: top;
            padding: 0 12px;
        }

        .papsgouv-ecole-signature-article h2 {
            font-size: 11.5pt;
            font-weight: 700;
            margin: 8px 0 5px 0;
            text-transform: uppercase;
        }

        .papsgouv-ecole-signature-article p {
            margin: 3px 0;
            line-height: 1.3;
        }

        .papsgouv-ecole-signature-article .important {
            font-weight: bold;
        }
    </style>
    <table class="papsgouv-ecole-signature-article">
        <tr>
            <td>
                <h2>Article 11 : Volontariat-Bénévolat</h2>
                <p>Le/la stagiaire s'engage à participer aux programmes de <span class="important">volontariat et bénévolat</span> initiés par le Ministère de la Promotion de la Jeunesse, de l'Insertion Professionnelle et du Service Civique et</p>
            </td>
            <td>
                <p>ses démembrements. Il/elle s'abstient d'exiger une contrepartie quelle qu'en soit sa nature pour sa participation.</p>
                <p>Il/elle déclare adhérer aux valeurs de civisme, de citoyenneté, de solidarité, de cohésion sociale, de paix et œuvrer à les promouvoir.</p>
            </td>
        </tr>
    </table>
    @php
        $entrepriseLibelle = $stagiaire->entreprise->libelle_entreprise ?? '';
        $isAgenceEmploiJeunes = str_contains(strtolower($entrepriseLibelle), 'agence emploi');
        $signatureColumns = $isAgenceEmploiJeunes ? 2 : 3;
        $signatureColumnWidth = $isAgenceEmploiJeunes ? '50%' : '33.33%';
        $signatureTableWidth = $isAgenceEmploiJeunes ? '78%' : '100%';
    @endphp

    <div class="last-page" style="margin-top: 30px;">
        <table style="width: {{ $signatureTableWidth }}; margin: 0 auto; border-collapse: collapse;">
            <tr>
                <td colspan="{{ $signatureColumns }}" style="text-align: right; padding-bottom: 15px;">
                    <p style="margin: 2px 0;">Fait à {{ $agence->libelle_agence }}, le {{ date('d/m/Y', strtotime($stagiaire->date_debut)) }}</p>
                    <p style="margin: 2px 0;">En quatre (4) exemplaires originaux</p>
                </td>
            </tr>
            @if($isAgenceEmploiJeunes)
            <tr style="text-align: center; vertical-align: top;">
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Le / La Stagiaire</p>
                </td>
            </tr>
            <tr>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0;">{{ $agence->chef_agence }}</p>
                    </div>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0; text-align: center;">{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}</p>
                    </div>
                </td>
            </tr>
            @else
            <tr style="text-align: center; vertical-align: top;">
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Le / La Stagiaire</p>
                </td> 
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Pour {{ $stagiaire->entreprise->libelle_entreprise }}<br>Le / La {{ $fonction }}</p>
                </td>
            </tr>
            <tr>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0;">{{ $agence->chef_agence }}</p>
                    </div>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0; text-align: center;">{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}</p>
                    </div>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0; text-align: center;">{{ $stagiaire->entreprise->dg ?? "" }}</p>
                    </div>
                </td>
            </tr>
            @endif
        </table>
    </div>
</div>
