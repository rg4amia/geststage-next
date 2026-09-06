<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 9px; }
        h1 { color: #087f5b; font-size: 18px; margin: 0 0 4px; }
        .meta { color: #667085; margin-bottom: 14px; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 4px 5px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; text-transform: uppercase; font-size: 8px; }
        .montant { text-align: right; white-space: nowrap; }
        .table-total-row { page-break-inside: avoid; }
        .table-total-row td { background: #f4fbf7; font-weight: bold; }
        .pied-de-page { page-break-inside: avoid; margin-top: 24px; min-height: 90px; }
        .pied-de-page .mention { margin: 0 0 6px; font-size: 9px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 14px; }
        .signature { width: 45%; text-align: center; font-size: 9px; }
        .signature .ligne { border-top: 1px solid #20252b; margin-top: 26px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>État de paiement</h1>
    <p class="meta">Période : {{ mb_strtoupper($mois) }} | {{ $total }} bénéficiaire(s)</p>

    @php($numeroOrdre = 0)
    @foreach ($pages as $pageIndex => $page)
        @if ($pageIndex > 0)
            <div style="page-break-before: always;"></div>
        @endif
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">N°</th>
                    <th style="width: 9%">N° AEJ</th>
                    <th style="width: 17%">Bénéficiaire</th>
                    <th style="width: 11%">Agence</th>
                    <th style="width: 19%">Entreprise</th>
                    <th style="width: 10%">Financement</th>
                    <th style="width: 11%">Type stage</th>
                    <th style="width: 10%">N° Trésor Pay</th>
                    <th style="width: 9%" class="montant">Montant (FCFA)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($page as $paiement)
                    @php($stage = $paiement->droitPaiement?->stage)
                    @php($numeroOrdre++)
                    <tr>
                        <td>{{ $numeroOrdre }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_aej ?? '-' }}</td>
                        <td>{{ trim(($stage?->beneficiaire?->nom ?? '').' '.($stage?->beneficiaire?->prenoms ?? '')) ?: '-' }}</td>
                        <td>{{ $stage?->agence?->nom ?? '-' }}</td>
                        <td>{{ $stage?->entreprise?->raison_sociale ?? '-' }}</td>
                        <td>{{ $stage?->sourceFinancement?->nom ?? '-' }}</td>
                        <td>{{ $stage?->typeStage?->nom ?? '-' }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_tresor_pay ?? '-' }}</td>
                        <td class="montant">{{ number_format((float) $paiement->montant, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @if ($pageIndex === count($pages) - 1)
                    <tr class="table-total-row">
                        <td colspan="8">SOLDE TOTAL DES PRIMES</td>
                        <td class="montant">{{ number_format((float) $solde, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if ($pageIndex === count($pages) - 1)
            <div class="pied-de-page">
                <p class="mention">
                    Arrêté le présent état de paiement à la somme de
                    <strong>{{ \Illuminate\Support\Str::upper(convertir_en_lettres((int) round($solde))) }} ({{ number_format((float) $solde, 0, ',', ' ') }}) francs CFA</strong>,
                    représentant la prime de {{ count($pages) > 1 ? 'présence' : 'stage' }} des {{ $total }} bénéficiaires listés ci-dessus pour la période de {{ mb_strtolower($mois) }}.
                </p>
                <div class="signatures">
                    <div class="signature">
                        <p>Le Chef d'Agence Régionale</p>
                        <div class="ligne">Signature et cachet</div>
                    </div>
                    <div class="signature">
                        <p>La Direction des Moyens Généraux</p>
                        <div class="ligne">Signature et cachet</div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</body>
</html>