@php
    $entrepriseLibelle = $stagiaire->entreprise->libelle_entreprise ?? '';
    $isAgenceEmploiJeunes = str_contains(strtolower($entrepriseLibelle), 'agence emploi');
    $signatureColumns = $isAgenceEmploiJeunes ? 2 : 3;
    $signatureColumnWidth = $isAgenceEmploiJeunes ? '50%' : '33.33%';
    $signatureTableWidth = $isAgenceEmploiJeunes ? '78%' : '100%';
@endphp

<table class="table-standard" style="width: {{ $signatureTableWidth }}; margin-left: auto; margin-right: auto;">
    <tr>
        <td colspan="{{ $signatureColumns }}"
            class="td-text-right">
            <p>Fait à {{ optional($agence)->libelle_agence ?? '...........................' }}, le
                {{ date('d/m/Y', strtotime($dateDebut)) }}</p>
            <p>En quatre (4) exemplaires originaux</p>
        </td>
    </tr>
    @if($isAgenceEmploiJeunes)
        <tr style="text-align: center;">
            <td class="td-signature-header" style="width: {{ $signatureColumnWidth }}; font-weight:bold;">
                <p>Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
            </td>
            <td class="td-signature-header" style="width: {{ $signatureColumnWidth }}; font-weight: bold;">
                <p>{{ 'Le / La' }} Stagiaire</p>
            </td>
        </tr>
        <tr>
            <td class="td-signature-space" style="width: {{ $signatureColumnWidth }}; text-align: center;">
                <div
                    style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                    <p style="font-style: italic; color: #666;font-size: 10px;">
                        {{ optional($agence)->chef_agence ?? '' }}</p>
                </div>
            </td>
            <td class="td-signature-space" style="width: {{ $signatureColumnWidth }}; text-align: center;">
                <div
                    style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                    <p style="font-style: italic; color: #666;font-size: 10px;">
                        {{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}
                    </p>
                </div>
            </td>
        </tr>
    @else
        <tr style="text-align: center;">
            <td class="td-signature-header" style="width: {{ $signatureColumnWidth }}; font-weight:bold;">
                <p>Pour l'Agence Emploi Jeunes<br>Le Chef d'Agence Régionale</p>
            </td>
            <td class="td-signature-header" style="width: {{ $signatureColumnWidth }};">
                <p><b> Pour {{ $stagiaire->entreprise->libelle_entreprise }} </b><br>Le / La
                    {{ $fonction ?? '' }}</p>
            </td>
            <td class="td-signature-header" style="width: {{ $signatureColumnWidth }}; font-weight: bold;">
                <p>{{ 'Le / La' }} Stagiaire</p>
            </td>
        </tr>
        <tr>
            <td class="td-signature-space" style="width: {{ $signatureColumnWidth }}; text-align: center;">
                <div
                    style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                    <p style="font-style: italic; color: #666;font-size: 10px;">
                        {{ optional($agence)->chef_agence ?? '' }}</p>
                </div>
            </td>
            <td class="td-signature-space" style="width: {{ $signatureColumnWidth }}; text-align: center;">
                <div
                style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                <p style="font-style: italic; color: #666;font-size: 10px;">
                    {{ $stagiaire->entreprise->dg ?? "" }}
                </p>
            </div>
        </td>
        <td class="td-signature-space" style="width: {{ $signatureColumnWidth }}; text-align: center;">
            <div
                style="border: 1px solid black; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin: 10px auto;">
                <p style="font-style: italic; color: #666;font-size: 10px;">
                    {{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}
                </p>
            </div>
        </td>
        </tr>
    @endif
</table>
