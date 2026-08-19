<h4 style="margin: 10px 0 4px 0; font-size: 11pt;">et</h4>
<h4 style="margin: 6px 0; font-size: 11pt;">Le/la Stagiaire</h4>
<p style="margin: 4px 0; line-height: 1.3; font-size: 11pt;">
    Monsieur/Mademoiselle/Madame : <strong>{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}</strong>
    <br>
    Né(e) le : <strong>{{ \Carbon\Carbon::parse($stagiaire->date_de_naissance)->isoFormat('DD/MM/Y') }}</strong> à <strong>{{ $stagiaire->lieu_de_naissance }}</strong>
    <br>
    Domicilié(e) à : <strong>{{ $stagiaire->communeresidence?->name }}</strong>
    <br>
    Téléphone : <strong>{{ $stagiaire->contact1 }}</strong>
    <br>
    Admissibilité au Diplôme : 
    @if($stagiaire->diplome !== "AUTRE (A PRECISER)")
        @php
            $diplome = App\Models\Diplome::firstWhere('libelle', $stagiaire->diplome);
        @endphp
        @if($diplome->libelle === "ATTESTATION (FORMATION QUALIFIANTE D'UNE DURÉE INFÉRIEURE OU ÉGALE À 03 MOIS)" || $diplome->libelle === "CERTIFICAT (FORMATION QUALIFIANTE D'UNE DURÉE SUPÉRIEURE À 03 MOIS)")
            <strong>{{ $diplome->abrege }}</strong>
        @else
            <strong>{{ $diplome->libelle }}</strong>
        @endif
    @else
        <strong>{{ $stagiaire->autre_diplome }}</strong>
    @endif
    <br>
    Specialité : <strong>{{ $stagiaire->specialite }}</strong>
    <br>
    Inscrit(e) à l'Agence Emploi Jeunes sous le numéro : <strong>{{ $stagiaire->numero_aej }}</strong> du <strong>{{ \Carbon\Carbon::parse($stagiaire->date_entree)->isoFormat('DD/MM/Y') }}</strong>
    <br>
    Direction/Service d'accueil : <strong>{{ $stagiaire->service_affectation }}</strong>
    <br>
    Période du stage : <strong>{{ \Carbon\Carbon::parse($stagiaire->date_debut)->isoFormat('DD/MM/Y') . ' au ' . \Carbon\Carbon::parse($stagiaire->date_fin)->isoFormat('DD/MM/Y') }}</strong>
</p>
<p style="margin: 8px 0; font-size: 11pt;">Ci-après désigné(e) « <strong>le/la STAGIAIRE</strong> »</p>
