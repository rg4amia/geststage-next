<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 10px; }
        h1 { color: #087f5b; font-size: 20px; margin: 0 0 4px; }
        .meta { color: #667085; margin-bottom: 18px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 5px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; }
        .montant { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
@if ($type === 'attestation_demarrage')
    {{--
        Portage tabulaire du legacy `print.attestation_demarrage` : un unique document listant
        tous les bénéficiaires (et non plus une page par stagiaire).
    --}}
    @include('pdf.partials.entete-ministere', [
        'titre' => 'ATTESTATION DE DEMARRAGE DU STAGE DE QUALIFICATION',
    ])
    <table style="margin-top: 12px;">
        <thead>
            <tr>
                <th style="width: 3%">N° d'ordre</th>
                <th style="width: 12%">Agence Régionale</th>
                <th style="width: 16%">Entreprise</th>
                <th style="width: 16%">Nom et prénom(s) du bénéficiaire</th>
                <th style="width: 8%">N° AEJ</th>
                <th style="width: 11%">Contact(s)</th>
                <th style="width: 10%">Date de début de stage</th>
                <th style="width: 12%">Période(s) de la prime</th>
                <th style="width: 12%">N° TresorMoney</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($paiements as $paiement)
            @php($stage = $paiement->droitPaiement->stage)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $stage->agence->nom ?? '-' }}</td>
                <td>{{ $stage->entreprise->raison_sociale ?? '-' }}</td>
                <td>{{ $stage->beneficiaire->nom }} {{ $stage->beneficiaire->prenoms }}</td>
                <td>{{ $stage->beneficiaire->numero_aej }}</td>
                <td>{{ $stage->beneficiaire->telephone_principal ?? '-' }}</td>
                <td>{{ optional($stage->date_debut)->format('d/m/Y') }}</td>
                <td>{{ mb_strtoupper($mois) }}</td>
                <td>{{ $stage->beneficiaire->numero_tresor_money ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <h1>{{ $titre }}</h1>
    <p class="meta">Periode : {{ mb_strtoupper($mois) }} | {{ $paiements->count() }} beneficiaire(s)</p>
    <table>
        <thead><tr><th>#</th><th>AEJ</th><th>Beneficiaire</th><th>Agence</th><th>Entreprise</th><th>Tresor Pay</th><th>Montant</th></tr></thead>
        <tbody>
        @foreach ($paiements as $paiement)
            @php($stage = $paiement->droitPaiement->stage)
            <tr>
                <td>{{ $loop->iteration }}</td><td>{{ $stage->beneficiaire->numero_aej }}</td>
                <td>{{ $stage->beneficiaire->nom }} {{ $stage->beneficiaire->prenoms }}</td>
                <td>{{ $stage->agence->nom }}</td><td>{{ $stage->entreprise->raison_sociale }}</td>
                <td>{{ $stage->beneficiaire->numero_tresor_money ?? '-' }}</td>
                <td class="montant">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
