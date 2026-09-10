<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #20252b; font-size: 9px; }
        .entete-financement { border-bottom: 1px solid #20252b; margin-bottom: 10px; padding-bottom: 6px; }
        .meta { color: #667085; margin-bottom: 12px; font-size: 10px; text-align: right; }
        .numero-dossier { text-align: right; margin: 2px 0 0; font-size: 10px; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d0d5dd; padding: 4px 5px; text-align: left; }
        th { background: #e7f5ef; color: #075e45; text-transform: uppercase; font-size: 8px; }
        .pied-de-page { page-break-inside: avoid; margin-top: 24px; min-height: 90px; }
        .signature-unique { text-align: right; margin-top: 40px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>
    @php
        // En-tête et signataire par source de financement (équivalent legacy
        // print.attestation_presence.dmg.{paps-gouv,budget-aej,c2d,pejedec}).
        $signataire = match ($financement) {
            'PA_PS_GOUV' => "LE CHEF D'UNITÉ",
            default => "L'ORDONNATEUR",
        };
        $totalEnLettres = \Illuminate\Support\Str::upper(convertir_en_lettres($paiements->count()));
        $libelleNature = ($nature ?? 'presence') === 'demarrage' ? 'DÉMARRAGE' : 'PRÉSENCE';
    @endphp

    @php($numeroOrdre = 0)
    @php($pages = preparePaginatedDataWithFooterSpace($paiements))
    @foreach ($pages as $pageIndex => $page)
        @if ($pageIndex > 0)
            <div style="page-break-before: always;"></div>
        @endif

        @if ($pageIndex === 0)
            @include('pdf.partials.entete-financement', [
                'financement' => $financement,
                'titre' => "ATTESTATION DE {$libelleNature} DES {$totalEnLettres} ({$paiements->count()}) STAGIAIRE(S) DE L'AGENCE EMPLOI JEUNES",
            ])
            @if (! empty($numeroDossier))
                <p class="numero-dossier">{{ $numeroDossier }}{{ ! empty($initialesValideur) ? '-'.$initialesValideur : '' }}</p>
            @endif
            <p class="meta">Période(s) : {{ \Illuminate\Support\Str::upper($mois) }}</p>
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
                    <th style="width: 8%">N° TresorMoney</th>
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
                        <td>{{ $stage?->beneficiaire?->numero_tresor_money ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($pageIndex === count($pages) - 1)
            <div class="pied-de-page">
                <p style="text-align: left;">
                    Sont effectivement présents au cours du mois de {{ mb_strtolower($mois) }}, au sein des
                    structures d'accueil sus-listées, dans le cadre de leur
                    @if ($financement === 'PA_PS_GOUV')
                        stage financé par le Programme d'Appui à la Politique Sectorielle de l'Emploi (PA-PSGOUV).
                    @elseif ($financement === 'C2D')
                        stage financé dans le cadre du Contrat de Désendettement et de Développement (C2D).
                    @elseif ($financement === 'PEJEDEC')
                        stage de qualification ou d'acquisition d'expérience professionnelle (PEJEDEC).
                    @else
                        stage financé sur le Budget de l'Agence Emploi Jeunes (Budget AEJ).
                    @endif
                </p>
                <p class="signature-unique">{{ $signataire }}</p>
            </div>
        @endif
    @endforeach
</body>
</html>
