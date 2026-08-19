<div class="text-justify">
    <div class="declaration-text">
        <h3>Entre les soussigné(e)s : </h3>

        {{-- USEP Section --}}
        @include('stage.contrat.partials_budgetetat.usep-section', ['agence' => $stagiaire->agence])

        {{-- Company Section --}}
        @include('stage.contrat.partials_budgetetat.company-section', [
            'entreprise' => $stagiaire->entreprise,
        ])

        {{-- Trainee Section --}}
        @include('stage.contrat.partials_budgetetat.trainee-section', [
            'stagiaire' => $stagiaire,
            'montant' => $montant,
        ])
        <p align="right"><strong>D'autre part,</strong></p>
        <p align="center">Collectivement appelé(e)s <strong>"LES PARTIES"</strong></p>
        <p style="font-family: 'Cambria' ; font-weight: bold;">Il a été convenu ce qui suit : </p>
    </div>
</div>
