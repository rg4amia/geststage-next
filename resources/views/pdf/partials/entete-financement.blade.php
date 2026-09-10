{{--
    En-tête institutionnel legacy (`print.etat_financier_papsgouv/budgetaej`,
    `print.attestation_presence.dmg.{paps-gouv,budget-aej,c2d,pejedec}`), reconstitué à
    l'identique (logos + intitulés) selon la source de financement.

    Props :
    - $financement : code SourceFinancement (PA_PS_GOUV, BUDGET_AEJ, C2D, PEJEDEC, ...)
    - $titre : HTML du titre centré (ex. "ETAT DE PAIEMENT DES PRIMES DE STAGE DE ...")
--}}
<div class="entete-financement">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            @if ($financement === 'PA_PS_GOUV')
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <img src="{{ public_path('print-assets/paps-gouv/logo_pa-psgouv.png') }}" width="90">
                    <img src="{{ public_path('print-assets/paps-gouv/bad.png') }}" width="90"><br>
                    <strong>CABINET DU PREMIER MINISTRE, CHEF DU GOUVERNEMENT</strong><br>
                    <strong>PA PSGOUV USEP EMPLOI DES JEUNES ET ENTREPRENEURIAT</strong>
                </td>
                <td style="width: 34%; text-align: center; vertical-align: bottom;">
                    <img src="{{ public_path('print-assets/logo_ministere.jpeg') }}" width="90">
                    <img src="{{ public_path('print-assets/image003.png') }}">
                </td>
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <img src="{{ public_path('print-assets/paps-gouv/amoirie.png') }}" width="90"><br>
                    <strong>REPUBLIQUE DE COTE D'IVOIRE</strong><br>
                    <strong>Union-Discipline-Travail</strong>
                </td>
            @elseif ($financement === 'C2D')
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <strong>MINISTERE DE LA PROMOTION DE LA JEUNESSE, DE L'INSERTION PROFESSIONNELLE ET DU
                        SERVICE CIVIQUE</strong><br>
                    -------------
                </td>
                <td style="width: 34%; text-align: center; vertical-align: bottom;">
                    <img src="{{ public_path('print-assets/logo_ministere.jpeg') }}" width="90">
                    <img src="{{ public_path('print-assets/image003.png') }}">
                    <img src="{{ public_path('print-assets/logoc2d.png') }}" width="90">
                </td>
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <strong>REPUBLIQUE DE COTE D'IVOIRE</strong><br>
                    <strong>Union-Discipline-Travail</strong><br>
                    -------------
                </td>
            @else
                {{-- Budget AEJ / PEJEDEC : même habillage que le legacy `etat_financier_budgetaej`. --}}
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <strong>MINISTERE DE LA PROMOTION DE LA JEUNESSE, DE L'INSERTION PROFESSIONNELLE ET DU
                        SERVICE CIVIQUE</strong><br>
                    -------------
                </td>
                <td style="width: 34%; text-align: center; vertical-align: bottom;">
                    <img src="{{ public_path('print-assets/logo_ministere.jpeg') }}" width="90">
                    <img src="{{ public_path('print-assets/image003.png') }}">
                </td>
                <td style="width: 33%; text-align: center; vertical-align: bottom;">
                    <strong>REPUBLIQUE DE COTE D'IVOIRE</strong><br>
                    <strong>Union-Discipline-Travail</strong><br>
                    -------------
                </td>
            @endif
        </tr>
    </table>
    <p style="margin: 4px 0 0; text-align: center;">
        <strong style="font-size: 18px;">{!! $titre !!}</strong>
    </p>
</div>
