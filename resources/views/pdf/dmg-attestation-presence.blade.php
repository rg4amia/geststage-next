<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 9px; }
        h1 { color: #087f5b; font-size: 16px; margin: 0 0 4px; }
        .meta { color: #667085; margin-bottom: 12px; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 4px 5px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; text-transform: uppercase; font-size: 8px; }
        .pied-de-page { page-break-inside: avoid; margin-top: 24px; min-height: 90px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 14px; }
        .signature { width: 45%; text-align: center; font-size: 9px; }
        .signature .ligne { border-top: 1px solid #20252b; margin-top: 26px; padding-top: 4px; }
    </style>
</head>
<body>
    @php
        // Différenciation de l'en-tête par source de financement (équivalent legacy
        // print.attestation_presence.dmg.{paps-gouv,budget-aej,c2d}).
        $intitule = match ($financement) {
            'PAPS_GOUV' => 'Attestation de présence — Programme d\'Appui à la Politique Sectorielle (PAPS-GOUV)',
            'C2D' => 'Attestation de présence — Contrat de Désendettement et de Développement (C2D)',
            default => 'Attestation de présence — Budget AEJ',
        };
    @endphp
    <h1>{{ $intitule }}</h1>
    <p class="meta">Période : {{ mb_strtoupper($mois) }} | {{ $paiements->count() }} bénéficiaire(s) | Source : {{ $financement ?? 'Budget AEJ' }}</p>

    @php($numeroOrdre = 0)
    @php($pages = preparePaginatedDataWithFooterSpace($paiements))
    @foreach ($pages as $pageIndex => $page)
        @if ($pageIndex > 0)
            <div style="page-break-before: always;"></div>
        @endif
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">N°</th>
                    <th style="width: 16%">Bénéficiaire</th>
                    <th style="width: 10%">N° AEJ</th>
                    <th style="width: 10%">Date naissance</th>
                    <th style="width: 14%">Agence</th>
                    <th style="width: 20%">Entreprise</th>
                    <th style="width: 9%">Début</th>
                    <th style="width: 9%">Fin</th>
                    <th style="width: 8%">N° Trésor Pay</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($page as $paiement)
                    @php($stage = $paiement->droitPaiement?->stage)
                    @php($numeroOrdre++)
                    <tr>
                        <td>{{ $numeroOrdre }}</td>
                        <td>{{ trim(($stage?->beneficiaire?->nom ?? '').' '.($stage?->beneficiaire?->prenoms ?? '')) ?: '-' }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_aej ?? '-' }}</td>
                        <td>{{ optional($stage?->beneficiaire?->date_naissance)->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $stage?->agence?->nom ?? '-' }}</td>
                        <td>{{ $stage?->entreprise?->raison_sociale ?? '-' }}</td>
                        <td>{{ optional($stage?->date_debut)->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ optional($stage?->date_fin_prevue)->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $stage?->beneficiaire?->numero_tresor_pay ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($pageIndex === count($pages) - 1)
            <div class="pied-de-page">
                <p>
                    Nous attestons que les {{ $paiements->count() }} stagiaires ci-dessus listés ont été
                    effectivement présents au sein de leurs structures d'accueil respectives au cours du mois de
                    {{ mb_strtolower($mois) }}, dans le cadre de leur
                    @if ($financement === 'PAPS_GOUV')
                        stage financé par le Programme d'Appui à la Politique Sectorielle de l'Emploi (PAPS-GOUV).
                    @elseif ($financement === 'C2D')
                        stage financé dans le cadre du Contrat de Désendettement et de Développement (C2D).
                    @else
                        stage financé sur le Budget de l'Agence Emploi Jeunes (Budget AEJ).
                    @endif
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