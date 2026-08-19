<style>
    .c2d-parties {
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 11pt;
        line-height: 1.3;
        text-align: justify;
        margin: 0 12px;
    }

    .c2d-parties h3 {
        font-size: 11.5pt;
        font-weight: bold;
        margin: 10px 0 8px 0;
    }

    .c2d-parties label,
    .c2d-parties span {
        font-weight: normal;
    }

    .c2d-parties p {
        margin: 4px 0;
        line-height: 1.3;
    }

    .c2d-parties strong {
        font-weight: bold;
    }
</style>

<div class="c2d-parties">
    <h3>Entre les soussigné(e)s :</h3>

    {{-- USEP Section --}}
    @include('stage.contrat.partials_c2d.usep-section', ['agence' => $stagiaire->agence])

    {{-- Company Section --}}
    @include('stage.contrat.partials_c2d.company-section', ['entreprise' => $stagiaire->entreprise])

    {{-- Trainee Section --}}
    @include('stage.contrat.partials_c2d.trainee-section', [
        'stagiaire' => $stagiaire,
        'montant' => $montant,
    ])

    <p align="right"><strong>D'autre part,</strong></p>
    <p align="center" style="margin: 8px 0;">Collectivement appelé(e)s <strong>"LES PARTIES"</strong></p>
</div>
