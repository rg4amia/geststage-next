<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CONTRAT PAPS-GOUV-PAE</title>
    <style>
        body {
            font-family: 'Cambria', serif;
        }
    </style>
    {{-- Separated CSS files --}}
    <link rel="stylesheet" href="{{ public_path('css/contract-base.css') }}">
    <link rel="stylesheet" href="{{ public_path('css/contract-layout.css') }}">
    <link rel="stylesheet" href="{{ public_path('css/contract-list-numbering.css') }}">
</head>

<body>
    {{-- Header Section --}}
    @include('stage.contrat.partials_papsgouv.header', [
        'agence' => $stagiaire->agence,
        'conseiller' => $stagiaire->conseiller->nom_prenoms
    ])

    {{-- Contract Parties Section --}}
    @include('stage.contrat.partials_papsgouv.contract-parties', [
        'stagiaire' => $stagiaire,
        'montant' => $montant,
        'fonction' => $fonction,
    ])

    {{-- Article Pages --}}
    @include('stage.contrat.partials_papsgouv.article-pages')

    {{-- Signature Section --}}
    @include('stage.contrat.partials_papsgouv.signatures', [
        'stagiaire' => $stagiaire,
        'fonction' => $fonction,
        'agence' => $stagiaire->agence,
    ])
</body>

</html>
