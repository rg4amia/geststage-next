<style>
    .papsgouv-parties-ecole {
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 11pt;
        line-height: 1.3;
        text-align: justify;
        margin: 0 12px;
    }

    .papsgouv-parties-ecole h3 {
        font-size: 11.5pt;
        font-weight: bold;
        margin: 10px 0 8px 0;
    }

    .papsgouv-parties-ecole h4 {
        font-size: 11pt;
        font-weight: bold;
        margin: 6px 0;
    }

    .papsgouv-parties-ecole p {
        margin: 4px 0;
        line-height: 1.3;
    }

    .papsgouv-parties-ecole strong {
        font-weight: bold;
    }
</style>

<div class="papsgouv-parties-ecole">
    <h3>Entre les soussigné(e)s :</h3>

    {{-- USEP Section --}}
    @include('stage.contrat.partials_papsgouv.usep-section', ['agence' => $stagiaire->agence])

    {{-- Company Section --}}
    @include('stage.contrat.partials_papsgouv.company-section', ['entreprise' => $stagiaire->entreprise])

    {{-- Trainee Section --}}
    @include('stage.contrat.partials_papsgouv.trainee-section-ecole', [
        'stagiaire' => $stagiaire,
        'montant' => $montant,
    ])

    <p align="right"><strong>D'autre part,</strong></p>
    <p align="center" style="margin: 8px 0;">Collectivement appelé(e)s <strong>"LES PARTIES"</strong></p>
    <p style="font-weight: bold; margin-top: 12px;">Il a été convenu ce qui suit :</p>
</div>
