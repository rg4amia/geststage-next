<div style="page-break-before: always;" class="declaration-text">
    <p align="justify">
        qu’en soit sa nature pour sa participation.<br>
        Il/elle déclare adhérer aux valeurs de civisme, de citoyenneté, de solidarité, de cohésion sociale, de paix et
        œuvrer à les promouvoir.
    </p>
    @php
        $entrepriseLibelle = $stagiaire->entreprise->libelle_entreprise ?? '';
        $isAgenceEmploiJeunes = str_contains(strtolower($entrepriseLibelle), 'agence emploi');
        $signatureColumns = $isAgenceEmploiJeunes ? 2 : 3;
        $signatureColumnWidth = $isAgenceEmploiJeunes ? '50%' : '33.33%';
        $signatureTableWidth = $isAgenceEmploiJeunes ? '78%' : '100%';
    @endphp

    <div class="last-page" style="font-family: 'Cambria'; font-size:15px">
        <table style="width: {{ $signatureTableWidth }}; margin: 20px auto 0; border-collapse: collapse;">
            <tr>
                <td colspan="{{ $signatureColumns }}" style="text-align: right;">
                    <p>Fait à {{ $agence->libelle_agence }}, le {{  date('d/m/Y', strtotime($stagiaire->date_debut)) }}</p>
                    <p>En quatre (4) exemplaires originaux</p>
                </td>
            </tr>
            <tr style="text-align: center; vertical-align: top;">
                <td style="width: {{ $signatureColumnWidth }}; font-weight:bold;">
                    <p>Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; font-weight: bold;">
                    <p>{{ 'Le / La' }} Stagiaire</p>
                </td>
                @unless($isAgenceEmploiJeunes)
                <td style="width: {{ $signatureColumnWidth }};">
                    <p><b> Pour {{ $stagiaire->entreprise->libelle_entreprise }} </b><br>Le / La {{ $fonction }}</p>
                </td>
                @endunless
            </tr>
            <tr>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center;">
                    <div
                        style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                        <p style="font-style: italic; color: #666;font-size: 10px;">{{ $agence->chef_agence }}</p>
                    </div>
                </td>
                <td style="width: {{ $signatureColumnWidth }}; text-align: center;">
                    <div
                        style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                        <p style="font-style: italic; color: #666;font-size: 10px;">
                            {{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}
                        </p>
                    </div>
                </td>
                @unless($isAgenceEmploiJeunes)
                <td style="width: {{ $signatureColumnWidth }}; text-align: center;">
                    <div
                        style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                        <p style="font-style: italic; color: #666;font-size: 10px;">
                            {{ $stagiaire->entreprise->dg ?? "" }}
                        </p>
                    </div>
                </td>
                @endunless
            </tr>
        </table>
    </div>
</div>
