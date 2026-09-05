<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 9px; }
        h1 { color: #087f5b; font-size: 18px; margin: 0 0 4px; }
        .meta { color: #667085; margin-bottom: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 4px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; }
        .montant { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>Paiements {{ $libelle }}</h1>
    <p class="meta">Periode : {{ mb_strtoupper($mois) }} | {{ $lignes->count() }} beneficiaire(s)</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>N° dossier</th>
                <th>OP</th>
                <th>Agence</th>
                <th>Entreprise</th>
                <th>Source de financement</th>
                <th>Type de stagiaire</th>
                <th>N° AEJ</th>
                <th>Nom et prénoms</th>
                <th>N° Trésor Money</th>
                <th class="montant">Montant</th>
                <th>Situation</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($lignes as $ligne)
            @php($stagiaire = $ligne['stagiaire'])
            @php($dossier = $ligne['dossier'])
            @php($ordre = $ligne['ordre'])
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $dossier['numero'] }}</td>
                <td>{{ $ordre['numero'] }}</td>
                <td>{{ $dossier['agence'] ?? '-' }}</td>
                <td>{{ $stagiaire['entreprise'] ?? '-' }}</td>
                <td>{{ $dossier['source_financement'] ?? '-' }}</td>
                <td>{{ $stagiaire['type_stage'] ?? '-' }}</td>
                <td>{{ $stagiaire['numero_aej'] ?? '-' }}</td>
                <td>{{ trim(($stagiaire['nom'] ?? '').' '.($stagiaire['prenoms'] ?? '')) }}</td>
                <td>{{ $stagiaire['numero_tresor_money'] ?? '-' }}</td>
                <td class="montant">{{ number_format((float) $stagiaire['montant'], 0, ',', ' ') }} FCFA</td>
                <td>{{ $stagiaire['statut_paiement'] === 'PAYE' ? 'Payé' : 'Non payé' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>