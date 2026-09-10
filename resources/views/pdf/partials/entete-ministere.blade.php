{{--
    En-tête institutionnel générique (équivalent legacy `print.attestation_demarrage`),
    utilisé pour les documents non différenciés par source de financement.

    Props :
    - $titre : HTML du titre centré
--}}
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width: 33%; text-align: center; vertical-align: bottom;">
            <strong>MINISTERE DE LA PROMOTION DE LA JEUNESSE, DE L'INSERTION PROFESSIONNELLE ET DU
                SERVICE CIVIQUE</strong><br>
            -------------
        </td>
        <td style="width: 34%; text-align: center; vertical-align: bottom;">
            <img src="{{ public_path('print-assets/image001.png') }}" width="50">
            <img src="{{ public_path('print-assets/image003.png') }}">
        </td>
        <td style="width: 33%; text-align: center; vertical-align: bottom;">
            <strong>REPUBLIQUE DE COTE D'IVOIRE</strong><br>
            <strong>Union-Discipline-Travail</strong><br>
            -------------
        </td>
    </tr>
</table>
<p style="margin: 4px 0 0; text-align: center;">
    <strong style="font-size: 18px;">{!! $titre !!}</strong>
</p>
