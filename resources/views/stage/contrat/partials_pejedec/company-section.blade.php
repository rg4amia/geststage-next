<h3>La structure d'accueil</h3>
<p>
    <label>La Société : </label>
    <span>{{ $entreprise->libelle_entreprise }}</span>
    </br>

    <label>Siège social : </label>
    <span>{{ $entreprise->ville }}</span>
    <br>
    <label style="margin: 5pt">BP</label>
    <span>{{ $entreprise->adresse ?? 'NEANT' }}</span>
    <br>

    <label>N°CC : </label>
    <span>{{ $entreprise->compte_contri }}</span>
    <br>

    <label>N°RCCM : </label>
    <span>{{ $entreprise->rccm }}</span>
    <br>

    <label>N°CNPS : </label>
    <span>{{ $entreprise->cnps }}</span>
    <br>

    <label>Email : </label>
    <span>{{ $entreprise->mail }}</span>
    <br>

    <label>Tél : </label>
    <span>{{ $entreprise->contact }}</span>
    <br>
    <label style="margin: 5pt">Fax : </label>
    <span>{{ $entreprise->contact }}</span>
</p>
<div style="margin-top: -10px;">
  Ci-après désigné(e) « <strong>le PARTENAIRE</strong> »
</div>

<div style="text-align: right;">
  <strong>D'une part,</strong>
</div>
{{-- <p style="margin-top: -10px;">Ci-après désigné(e) « <strong>le PARTENAIRE</strong> »</p>
<p style="text-align: right;"><strong>D'une part,</strong></p>
 --}}
