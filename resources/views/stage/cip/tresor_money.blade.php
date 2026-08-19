<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de Référence Trésor Money</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 16px;
            margin: 0 0 5px 0;
            font-weight: bold;
        }

        .header p {
            margin: 0;
            font-size: 11px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table thead tr {
            background-color: #f0f0f0;
        }

        table th, table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            font-size: 9px;
        }

        table th {
            font-weight: bold;
            background-color: #e0e0e0;
        }

        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 9px;
        }

        .info-row {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FICHE DE RÉFÉRENCE TRÉSOR MONEY</h1>
        <p>Programme d'Appui à l'Emploi des Jeunes</p>
        <p>Date de génération : {{ $date_generation }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">N°</th>
                <th style="width: 12%;">Matricule</th>
                <th style="width: 15%;">Nom & Prénoms</th>
                <th style="width: 10%;">N° Pièce</th>
                <th style="width: 8%;">N° Trésor</th>
                <th style="width: 12%;">Fonction</th>
                <th style="width: 12%;">Entreprise</th>
                <th style="width: 10%;">Agence</th>
                <th style="width: 8%;">Montant</th>
                <th style="width: 10%;">Période</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stagiaires as $index => $stagiaire)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $stagiaire['matricule'] }}</td>
                    <td>{{ $stagiaire['nom'] }} {{ $stagiaire['prenoms'] }}</td>
                    <td>{{ $stagiaire['num_piece'] }}</td>
                    <td>{{ $stagiaire['numero_tresormoney'] }}</td>
                    <td>{{ $stagiaire['fonction'] }}</td>
                    <td>{{ $stagiaire['entreprise'] }}</td>
                    <td>{{ $stagiaire['agence'] }}</td>
                    <td style="text-align: right;">{{ number_format($stagiaire['montant_indemnite'], 0, ',', ' ') }} F</td>
                    <td>{{ $stagiaire['date_debut'] }} - {{ $stagiaire['date_fin_prevue'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8" style="text-align: right;">TOTAL :</th>
                <th style="text-align: right;">
                    {{ number_format(collect($stagiaires)->sum('montant_indemnite'), 0, ',', ' ') }} F
                </th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div class="info-row">
            <strong>Nombre total de stagiaires :</strong> {{ count($stagiaires) }}
        </div>
    </div>
</body>
</html>
