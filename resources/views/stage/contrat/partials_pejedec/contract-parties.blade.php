<div class="declaration-text">
    <h3>Entre les soussigné(e)s : </h3>

    {{-- USEP Section --}}
    @include('stage.contrat.partials_pejedec.usep-section', ['agence' => $stagiaire->agence])

    {{-- Company Section --}}
    @include('stage.contrat.partials_pejedec.company-section', ['entreprise' => $stagiaire->entreprise])

    {{-- Trainee Section --}}
    @include('stage.contrat.partials_pejedec.trainee-section', [
        'stagiaire' => $stagiaire,
        'montant' => $montant,
    ])
    <p align="right"><strong>D'autre part,</strong></p>
    <p align="center">Collectivement appelé(e)s <strong>"LES PARTIES"</strong></p>
</div>
