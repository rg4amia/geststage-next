<div style="page-break-before: always; font-family: 'Times New Roman', Times, serif; font-size: 11pt; margin: 0 12px;">
    <div style="line-height: 1.18; text-align: justify; margin-bottom: 26px;">
        <h2 style="font-size: 11.3pt; font-weight: 700; margin: 0 0 6px; text-transform: uppercase; text-align: left;">
            Article 11 : Volontariat-Bénévolat
        </h2>
        <p style="margin: 3px 0;">
            Le/la stagiaire s'engage à participer aux programmes de volontariat et bénévolat initiés par le Ministère de la Promotion de la Jeunesse, de l'Insertion Professionnelle et du Service Civique et ses démembrements.
        </p>
        <p style="margin: 3px 0;">
            Il/elle s'abstient d'exiger une contrepartie quelle qu'en soit sa nature pour sa participation.
        </p>
        <p style="margin: 3px 0;">
            Il/elle déclare adhérer aux valeurs de civisme, de citoyenneté, de solidarité, de cohésion sociale, de paix et œuvrer à les promouvoir.
        </p>
    </div>
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
            <tr style="text-align: center; vertical-align: top;">
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Le / La Stagiaire</p>
                </td>
                @unless($isAgenceEmploiJeunes)
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold; padding: 10px 5px;">
                    <p style="margin: 0;">Pour {{ $stagiaire->entreprise->libelle_entreprise }}<br>Le / La {{ $fonction }}</p>
                </td>
                @endunless
            </tr>
            <tr>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0;">{{ $agence->chef_agence }}</p>
                    </div>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0; text-align: center;">
                            {{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}
                        </p>
                    </div>
                </td>
                @unless($isAgenceEmploiJeunes)
                <td style="width: {{ $signatureColumnWidth }}; text-align: center; padding: 5px;">
                    <div style="border: 1px solid black; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <p style="font-style: italic; color: #666; font-size: 9pt; margin: 0; text-align: center;">
                            {{ $stagiaire->entreprise->dg ?? "" }}
                        </p>
                    </div>
                </td>
                @endunless
            </tr>
        </table>
    </div>
</div>
