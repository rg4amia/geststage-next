<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Attestation de présence (PEJEDEC)</title>
    <style>
        body {
            font-family: 'Calibri', sans-serif;
            font-size: 8pt;
            margin: 0;
        }

        .header {
            width: 100%;
            text-align: center;
            margin: 0;
            border-bottom: 1px solid black;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            padding: 5px;
        }

        .table-item {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table-item th {
            border: 1pt solid black;
            padding: 5px;
            text-align: center;
        }

        .table-item td {
            border: 1pt solid black;
            padding: 5px;
            text-align: left;
            font-size: 7pt
        }

        .item-img {
            position: relative;
            left: 50%;
            transform: translateX(-50%);
            max-width: 100%;
            max-height: calc(100vh - 2cm);
            margin-top: 0.5cm;
            z-index: 9999;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            width: 100%;
            text-align: right;
            margin-top: 30px
        }

        .footer-section {
            page-break-inside: avoid;
            margin-top: 20px;
            min-height: 120px;
        }

        @media print {
            .header {
                position: fixed;
                top: 0;
                width: 100%;
            }

            body {
                margin-top: 3cm;
            }

            .footer {
                position: fixed;
                bottom: 0;
                width: 100%;
                border-top: 1px solid black;
                text-align: center;
                padding: 5px;
            }

            .footer-section {
                page-break-inside: avoid;
                orphans: 3;
                widows: 3;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <table>
            <tr>
                <td style="width: 33%; text-align: center;">
                    <div class="item-img">
                        <img src="{{ public_path('print-assets/logo_ministere.jpeg') }}" width="120">
                    </div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="item-img">
                        <img src="{{ public_path('print-assets/logo_aej_new.png') }}" width="120">
                    </div>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="item-img">
                        <img src="{{ public_path('print-assets/attestation-presence/pejedec.png') }}" width="120">
                    </div>
                </td>
            </tr>
        </table>
        <p style="margin: 0; text-align: center;">
            <strong style="font-size: 20.0pt;">
                ATTESTATION DE {{ $mode_traitement == 2 ? 'DEMARRAGE' : 'PRÉSENCE' }} EN {{ $type_stage }}
            </strong>
        </p>
        <p style="margin: 0; text-align: left; font-size: 11.0pt;">
            Je, soussignés : <br>
            <strong>{{ $data_agence['chef_agence'] }}</strong>,
            Chef d'Agence Régionale de {{ $data_agence['agence'] }} de l'Agence Emploi Jeunes<br>
            Atteste que les {{ strtoupper(convertir_en_lettres($totalContrats)) }} ({{ $totalContrats }})
            stagiaire (s) ci-après listés :
        </p>
        <br>
    </div>

    <table class="table-item">
        <thead>
            <tr>
                <th>N° D'ordre</th>
                <th>Agence Régionale</th>
                <th>Entreprise</th>
                <th>Nom et prénom(s) du bénéficiaire</th>
                <th>N° AEJ</th>
                <th>Contact(s)</th>
                <th>Date Début de Stage</th>
                <th>Date Fin de Stage</th>
                <th>Période(s) de la prime</th>
                <th>N° Compte</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contrats->chunk(15) as $chunk)
                @foreach ($chunk as $key => $pointage)
                    <tr @if ($loop->last && $loop->parent->last) class="table-total-row" @endif>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $pointage->stage->agence->nom ?? '-' }}</td>
                        <td>{{ $pointage->stage->entreprise->raison_sociale ?? '-' }}</td>
                        <td>{{ $pointage->stage->beneficiaire->nom ?? '' }} {{ $pointage->stage->beneficiaire->prenoms ?? '' }}</td>
                        <td>{{ $pointage->stage->beneficiaire->numero_aej ?? '-' }}</td>
                        <td>{{ $pointage->stage->beneficiaire->telephone_principal ?? '-' }}</td>
                        <td>{{ Carbon::parse($pointage->stage->date_debut)->format('d-m-Y') }}</td>
                        <td>{{ Carbon::parse($pointage->stage->date_fin_prevue)->format('d-m-Y') }}</td>
                        <td>{{ strtoupper(Carbon::parse($mois_pointage)->isoFormat('MMMM YYYY')) }}</td>
                        <td>{{ '225' . ($pointage->stage->beneficiaire->numero_wave ?? '') }}</td>
                    </tr>
                @endforeach
                @if (!$loop->last)
                    <div style="page-break-after: always;"></div>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="footer-section">
        <div class="footer">
            <p style="margin-top: 10px; text-align: left; font-size: 11.0pt;">
                Sont effectivement présents au cours du mois de
                {{ Carbon::parse($mois_pointage)->isoFormat('MMMM YYYY') }},
                au sein des structures d'accueil sus-listées, <br>
                dans le cadre d'un {{ strtolower($type_stage) }}
            </p>
        </div>
        <div class="signatures">
            <p style="margin: 0; text-align: right; font-size: 11.0pt;">
                Chef d'Agence Régionale de {{ $data_agence['agence'] }}
            </p>
            <p style="font-size: 11.0pt; margin-top: 30px;">
                Mme/Mlle/M {{ $data_agence['chef_agence'] }}
            </p>
        </div>
    </div>
</body>

</html>
