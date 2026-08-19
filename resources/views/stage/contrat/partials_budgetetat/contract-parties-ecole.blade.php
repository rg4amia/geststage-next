<style>
    .budgetetat-ecole-parties {
        font-family: 'Cambria', 'Times New Roman', serif;
        font-size: 11pt;
        line-height: 1.3;
        text-align: justify;
        margin: 0 12px;
    }

    .budgetetat-ecole-parties h3 {
        font-size: 11.5pt;
        font-weight: bold;
        margin: 10px 0 8px 0;
    }

    .budgetetat-ecole-parties label,
    .budgetetat-ecole-parties span {
        font-weight: normal;
    }

    .budgetetat-ecole-parties p {
        margin: 4px 0;
        line-height: 1.3;
    }

    .budgetetat-ecole-parties strong {
        font-weight: bold;
    }
</style>

<div class="budgetetat-ecole-parties">
    <h3>Entre les soussigné(e)s :</h3>

    {{-- USEP Section --}}
    @include('stage.contrat.partials_budgetetat.usep-section', ['agence' => $stagiaire->agence])

    {{-- Company Section --}}
    @include('stage.contrat.partials_budgetetat.company-section', [
        'entreprise' => $stagiaire->entreprise,
    ])

    {{-- Trainee Section --}}
    @include('stage.contrat.partials_budgetetat.trainee-section-ecole', [
        'stagiaire' => $stagiaire,
        'montant' => $montant,
    ])

    <p align="right"><strong>D'autre part,</strong></p>
    <p align="center" style="margin: 8px 0;">Collectivement appelé(e)s <strong>"LES PARTIES"</strong></p>
</div>
