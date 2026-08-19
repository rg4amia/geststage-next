<h3 style="margin: 10px 0 6px 0; font-size: 11.5pt;">La structure d'accueil</h3>
<p style="margin: 4px 0; line-height: 1.3; font-size: 11pt;">
    La Société : <strong>{{ $entreprise->libelle_entreprise }}</strong>
    <br>
    Siège social : <strong>{{ $entreprise->ville }}</strong> 
    <span style="margin-left: 20px;">BP</span> <strong>{{ $entreprise->adresse ?? 'NEANT' }}</strong>
    <br>
    N°CC : <strong>{{ $entreprise->compte_contri }}</strong>
    <br>
    N°RCCM : <strong>{{ $entreprise->rccm }}</strong>
    <br>
    N°CNPS : <strong>{{ $entreprise->cnps }}</strong>
    <br>
    Email : <strong>{{ $entreprise->mail }}</strong>
    <br>
    Tél : <strong>{{ $entreprise->contact }}</strong>
    <span style="margin-left: 20px;">Fax :</span> <strong>{{ $entreprise->contact }}</strong>
</p>
<p style="margin: 8px 0; font-size: 11pt;">Ci-après désigné(e) « <strong>le PARTENAIRE</strong> »</p>

<div style="text-align: right; margin: 8px 0; font-size: 11pt;">
  <strong>D'une part,</strong>
</div>
