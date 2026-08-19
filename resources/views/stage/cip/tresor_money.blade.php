<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de Paiement - Ministère de l'Économie et des Finances</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background-color: #f8f9fa;
            line-height: 1.2;
            padding: 0px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            page-break-after: always;
            min-height: 277mm;
        }

        .container:last-child {
            page-break-after: auto;
        }

        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 8px 20px;
            background: white;
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
        }

        .header img {
            width: 85%;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .footer {
            background: white;
            padding: 8px 0;
            text-align: center;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top: 2px solid #f0f0f0;
            margin-top: 10px;
        }

        .footer img {
            width: 100%;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        h1 {
            text-align: center;
            font-size: 20px;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .form-section {
            max-width: 190mm;
            margin: 0 auto;
            padding: 5px 25px;
        }

        .form-group {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-bottom: 3px;
        }

        .form-group:last-child {
            border-bottom: none;
        }

        label {
            width: 220px;
            font-weight: bold;
            font-size: 12pt;
            color: #333;
            margin-right: 15px;
            flex-shrink: 0;
        }

        label:nth-child(2) {
            width: auto;
            font-weight: normal;
            color: #000;
            font-size: 12pt;
            min-width: 300px;
        }

        p {
            font-size: 12pt;
            margin-bottom: 3px;
            padding-left: 25px;
            font-weight: 500;
            color: #444;
        }

        .nature-paiement {
            text-align: right;
            margin-top: 0px;
            margin-bottom: 3px;
            padding-right: 25px;
            font-weight: bold;
            font-size: 12pt;
            color: #333;
        }

        .types-depenses {
            text-align: center;
            margin: 10px 0;
            padding: 0 25px;
        }

        .types-depenses h2 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .checkbox-group {
            display: table;
            width: 100%;
            margin: 10px auto;
        }

        .checkbox-item {
            display: table-row;
        }

        .checkbox-box {
            display: table-cell;
            width: 60px;
            padding: 6px 0;
            text-align: center;
            vertical-align: middle;
        }

        .checkbox-box::before {
            content: '';
            display: inline-block;
            width: 35px;
            height: 22px;
            border: 2px solid #000;
            border-radius: 6px;
            background: white;
        }

        .checkbox-label {
            display: table-cell;
            font-size: 12pt;
            font-weight: 500;
            text-align: center;
            padding: 6px 0;
            vertical-align: middle;
        }

        .date-signature {
            margin: 10px 25px 5px 25px;
            font-size: 12pt;
            font-weight: 500;
            border-bottom: 2px solid #000;
            padding-bottom: 3px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .container {
                page-break-inside: avoid;
                page-break-after: always;
                box-shadow: none;
            }

            .container:last-child {
                page-break-after: auto;
            }

            .form-section {
                max-width: 190mm;
                page-break-inside: avoid;
                padding: 5mm 15mm;
            }

            .form-group {
                margin-bottom: 4mm;
                border-bottom: 1px dotted #ccc;
                padding-bottom: 3mm;
            }

            label {
                font-size: 11pt;
            }

            label:nth-child(2) {
                font-size: 11pt;
            }

            @page {
                size: A4;
                margin: 8mm;
            }

            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    @foreach ($stagiaires as $stagiaire)
        <div class="container">
            <!-- En-tête officiel -->
            <div class="header">
                <img src="{{ public_path('assets_tm/tm_header.png') }}" alt="TrésorMoney Header"
                    style="width: 90%; max-width: 100%; height: auto;">
            </div>

            <!-- Contenu du formulaire -->
            <div class="form-section">
                <p>Je soussigné(e)</p>
                <div class="form-group">
                    <label for="nom">Nom :</label>
                    <label for="nom">{{ strtoupper($stagiaire['nom']) }}</label>
                </div>
                <div class="form-group">
                    <label for="prenoms">Prénoms :</label>
                    <label for="prenoms">{{ strtoupper($stagiaire['prenoms']) }}</label>
                </div>
                <div class="form-group">
                    <label for="fonction">Fonction :</label>
                    <label for="fonction">{{ strtoupper($stagiaire['fonction'] ?? 'STAGIAIRE') }}</label>
                </div>
                <div class="form-group">
                    <label for="lieu_residence">Lieu de résidence :</label>
                    <label for="lieu_residence">{{ strtoupper($stagiaire['lieu_residence'] ?? 'N/A') }}</label>
                </div>
                <div class="form-group">
                    <label for="numero_tresormoney">Numéro TrésorMoney :</label>
                    <label for="numero_tresormoney">{{ strtoupper($stagiaire['numero_tresormoney'] ?? 'N/A') }}</label>
                </div>
                <div class="form-group">
                    <label for="identifiant_matricule">Identifiant / Matricule :</label>
                    <label for="identifiant_matricule">{{ strtoupper($stagiaire['matricule'] ?? 'N/A') }}</label>
                </div>
                <div class="form-group">
                    <label for="numero_piece_identite">Numéro de la pièce d'identité
                        <span style="font-size: 10px">(CNI/PASSEPORT/ATTESTATION)</span> :</label>
                    <label for="numero_piece_identite">{{ strtoupper($stagiaire['num_piece'] ?? 'N/A') }}</label>
                </div>
            </div>
            <p class="nature-paiement">Nature du paiement :</p>

            <!-- Types de dépenses -->
            <div class="types-depenses">
                <h2>Types de dépenses</h2>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <div class="checkbox-box"></div>
                        <div class="checkbox-label">Bourses</div>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox-box"></div>
                        <div class="checkbox-label">Primes</div>
                    </div>
                    <div class="checkbox-item">
                        <div class="checkbox-box"></div>
                        <div class="checkbox-label">Autres</div>
                    </div>
                </div>
            </div>

            <!-- Date et Signature -->
            <div class="date-signature">
                Date et Signature :
            </div>

            <!-- Footer TrésorPay -->
            <div class="footer">
                <img src="{{ public_path('assets_tm/tm_footer.png') }}" alt="TrésorMoney Footer"
                    style="width: 100%; max-width: 100%; height: auto;">
            </div>
        </div>
    @endforeach
</body>

</html>
