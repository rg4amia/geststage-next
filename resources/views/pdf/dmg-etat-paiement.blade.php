<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 9px; }
        .entete-financement { border-bottom: 1px solid #20252b; margin-bottom: 10px; padding-bottom: 6px; }
        .meta { color: #667085; margin-bottom: 10px; font-size: 10px; text-align: right; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 4px 5px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; text-transform: uppercase; font-size: 8px; }
        .montant { text-align: right; white-space: nowrap; }
        .table-total-row { page-break-inside: avoid; }
        .table-total-row td { background: #f4fbf7; font-weight: bold; }
        .pied-de-page { page-break-inside: avoid; margin-top: 24px; min-height: 90px; }
        .pied-de-page .mention { margin: 0 0 6px; font-size: 9px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; font-weight: bold; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $showDeductionColumns = (float) $total_prelevement > 0;
        $totalEnLettres = \Illuminate\Support\Str::upper(convertir_en_lettres($total));
    @endphp

    @php($numeroOrdre = 0)
    @foreach ($pages as $pageIndex => $page)
        @if ($pageIndex > 0)
            <div style="page-break-before: always;"></div>
        @endif

        @if ($pageIndex === 0)
            @include('pdf.partials.entete-financement', [
                'financement' => $financement,
                'titre' => "ETAT DE PAIEMENT DES PRIMES DE STAGE DE {$totalEnLettres} ({$total}) STAGIAIRE(S) DE L'AGENCE EMPLOI JEUNES",
            ])
            <p class="meta">Période(s) : {{ \Illuminate\Support\Str::upper($mois) }}</p>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width: 3%">N°</th>
                    <th style="width: 9%">Agence Régionale</th>
                    <th style="width: 13%">Entreprise</th>
                    <th style="width: 14%">Bénéficiaire</th>
                    <th style="width: 7%">N° AEJ</th>
                    <th style="width: 8%">N° TresorMoney</th>
                    @if ($showDeductionColumns)
                        <th style="width: 8%" class="montant">Montant brut FCFA</th>
                        <th style="width: 8%" class="montant">Prélèvement CMU</th>
                        <th style="width: 8%" class="montant">Net à payer FCFA</th>
                    @else
                        <th style="width: 8%" class="montant">Montant FCFA</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($page as $paiement)
                    @php($stage = $paiement->droitPaiement?->stage)
                    @php($numeroOrdre++)
                    <tr>
                        <td>{{ $numeroOrdre }}</td>
                        <td>{{ $stage?->agence?->nom ?? '-' }}</td>
                        <td>{{ $stage?->entreprise?->raison_sociale ?? '-' }}</td>
                        <td>{{ trim(($stage?->beneficiaire?->nom ?? '').' '.($stage?->beneficiaire?->prenoms ?? '')) ?: '-' }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_aej ?? '-' }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_tresor_money ?? '-' }}</td>
                        @if ($showDeductionColumns)
                            <td class="montant">{{ number_format((float) $paiement->montant_brut_calcule, 0, ',', ' ') }}</td>
                            <td class="montant">-{{ number_format((float) $paiement->prelevement_calcule, 0, ',', ' ') }}</td>
                        @endif
                        <td class="montant"><strong>{{ number_format((float) $paiement->montant, 0, ',', ' ') }}</strong></td>
                    </tr>
                @endforeach
                @if ($pageIndex === count($pages) - 1)
                    <tr class="table-total-row">
                        <td colspan="{{ $showDeductionColumns ? 6 : 6 }}" style="text-align: right;">MONTANT TOTAL</td>
                        @if ($showDeductionColumns)
                            <td class="montant">{{ number_format((float) $total_brut, 0, ',', ' ') }}</td>
                            <td class="montant">-{{ number_format((float) $total_prelevement, 0, ',', ' ') }}</td>
                        @endif
                        <td class="montant">{{ number_format((float) $solde, 0, ',', ' ') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @if ($pageIndex === count($pages) - 1)
            <div class="pied-de-page">
                <p class="mention" style="text-align: center; font-weight: bold;">
                    ETAT DE PAIEMENT DES PRIMES DE STAGE ET DE TRANSPORT DE {{ $totalEnLettres }}
                    ({{ $total }}) STAGIAIRES DE L'AGENCE EMPLOI JEUNES
                </p>
                <p class="mention" style="text-align: center; font-weight: bold;">
                    ARRETE LE PRESENT ETAT A LA SOMME DE
                    {{ \Illuminate\Support\Str::upper(convertir_en_lettres((int) round($solde))) }} F CFA.
                </p>

                @if ($financement === 'PA_PS_GOUV')
                    <div class="signatures">
                        <span>CHEF UNITÉ COMPTABLE</span>
                        <span>LE CONTROLEUR FINANCIER</span>
                        <span>L'AGENT COMPTABLE</span>
                    </div>
                @else
                    <div class="signatures">
                        <span>L'ORDONNATEUR</span>
                        <span>LE CONTROLEUR BUDGETAIRE</span>
                        <span>L'AGENT COMPTABLE</span>
                    </div>
                @endif
            </div>
        @endif
    @endforeach
</body>
</html>
