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
        .attestation { page-break-after: always; min-height: 680px; padding: 24px; }
        .attestation:last-child { page-break-after: auto; }
        .signature { margin-top: 80px; text-align: right; }
    </style>
</head>
<body>
@if (str_starts_with($type, 'attestation_'))
    @foreach ($paiements as $paiement)
        @php($stage = $paiement->droitPaiement->stage)
        <section class="attestation">
            <h1>{{ $titre }}</h1>
            <p class="meta">Periode : {{ mb_strtoupper($mois) }} | Reference : PAY-{{ str_pad($paiement->id, 6, '0', STR_PAD_LEFT) }}</p>
            <p>Nous attestons que <strong>{{ $stage->beneficiaire->nom }} {{ $stage->beneficiaire->prenoms }}</strong>, numero AEJ
                <strong>{{ $stage->beneficiaire->numero_aej }}</strong>, effectue son stage au sein de
                <strong>{{ $stage->entreprise->raison_sociale }}</strong>.</p>
            <p>Periode du stage : {{ optional($stage->date_debut)->format('d/m/Y') }} au {{ optional($stage->date_fin_prevue)->format('d/m/Y') }}.</p>
            <p>Montant du droit associe : <strong>{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</strong>.</p>
            <p class="signature">La Direction des Moyens Generaux</p>
        </section>
    @endforeach
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
                <td>{{ $stage->beneficiaire->numero_tresor_pay ?? '-' }}</td>
                <td class="montant">{{ number_format((float) $paiement->montant, 0, ',', ' ') }} FCFA</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
