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
            line-height: 1.4;
            padding: 0px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            page-break-after: always;
        }

        .container:last-child {
            page-break-after: auto;
        }

        .header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 15px 30px;
            background: white;
            text-align: center;
            border-bottom: 2px solid #f0f0f0;
        }

        .header img {
            width: 90%;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .footer {
            background: white;
            padding: 15px 0;
            text-align: center;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            border-top: 2px solid #f0f0f0;
            margin-top: 20px;
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
            padding: 10px 30px;
        }

        .form-group {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding-bottom: 5px;
        }

        .form-group:last-child {
            border-bottom: none;
        }

        label {
            width: 220px;
            font-weight: bold;
            font-size: 13pt;
            color: #333;
            margin-right: 15px;
            flex-shrink: 0;
        }

        label:nth-child(2) {
            width: auto;
            font-weight: normal;
            color: #000;
            font-size: 13pt;
            min-width: 300px;
        }

        p {
            font-size: 13pt;
            margin-bottom: 5px;
            padding-left: 30px;
            font-weight: 500;
            color: #444;
        }

        .nature-paiement {
            text-align: right;
            margin-top: 0px;
            margin-bottom: 5px;
            padding-right: 30px;
            font-weight: bold;
            font-size: 13pt;
            color: #333;
        }

        .types-depenses {
            text-align: center;
            margin: 30px 0;
            padding: 0 30px;
        }

        .types-depenses h2 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }

        .checkbox-group {
            display: table;
            width: 100%;
            margin: 20px auto;
        }

        .checkbox-item {
            display: table-row;
        }

        .checkbox-box {
            display: table-cell;
            width: 60px;
            padding: 10px 0;
            text-align: center;
            vertical-align: middle;
        }

        .checkbox-box::before {
            content: '';
            display: inline-block;
            width: 40px;
            height: 25px;
            border: 2px solid #000;
            border-radius: 8px;
            background: white;
        }

        .checkbox-label {
            display: table-cell;
            font-size: 14pt;
            font-weight: 500;
            text-align: center;
            padding: 10px 0;
            vertical-align: middle;
        }

        .date-signature {
            margin: 30px 30px 10px 30px;
            font-size: 13pt;
            font-weight: 500;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        @media print {
            body {
                margin: 0;
                padding: 10mm;
                page-break-inside: avoid;
            }

            .container {
                page-break-inside: avoid;
                page-break-after: always;
            }

            .container:last-child {
                page-break-after: auto;
            }

            h1 {
                font-size: 16pt;
                margin-bottom: 15mm;
            }

            .form-section {
                max-width: 190mm;
                page-break-inside: avoid;
                padding: 15mm 20mm;
            }

            .form-group {
                margin-bottom: 8mm;
                border-bottom: 1px dotted #ccc;
                padding-bottom: 5mm;
            }

            label {
                font-size: 13pt;
            }

            label:nth-child(2) {
                font-size: 13pt;
            }

            @page {
                size: A4;
                margin: 10mm;
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
                <img src="{{ public_path() }}/assets_tm/tm_header.png" alt="TrésorMoney Header">
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
                <img src="{{ public_path() }}/assets_tm/tm_footer.png" alt="TrésorMoney Footer">
            </div>
        </div>
    @endforeach
</body>

</html>
