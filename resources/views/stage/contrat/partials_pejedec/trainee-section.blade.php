<h3>et</h3>
<h3>Le/la Stagiaire</h3>
<p>
    <label style="margin-bottom:1px">Monsieur/Mademoiselle/Madame : </label>
    <span>{{ $stagiaire->nom_stagiaire . ' ' . $stagiaire->prenoms_stagiaire }}</span>
    <br>
    <label>Né(e) le : </label>
    <span>{{ \Carbon\Carbon::parse($stagiaire->date_de_naissance)->isoFormat('DD/MM/Y') }}</span>
    à
    <span>{{ $stagiaire->lieu_de_naissance }}</span>
    <br>
    <label>Domicilié(e) à : </label>
    <span>{{ $stagiaire->communeresidence?->name }}</span>
    <br>
    <label>Téléphone : </label>
    <span>{{ $stagiaire->contact1 }}</span>
    <br>

    <label>Titulaire d'un Diplôme : </label>
    @if($stagiaire->diplome !== "AUTRE (A PRECISER)")
    @php
    $diplome = App\Models\Diplome::firstWhere('libelle', $stagiaire->diplome);
    @endphp
    @if($diplome->libelle === "ATTESTATION (FORMATION QUALIFIANTE D'UNE DURÉE INFÉRIEURE OU ÉGALE À 03 MOIS)" || $diplome->libelle === "CERTIFICAT (FORMATION QUALIFIANTE D'UNE DURÉE SUPÉRIEURE À 03 MOIS)")

    <span>{{ $diplome->abrege }}</span>
    @else
    <span>{{ $diplome->libelle }}</span>
    @endif
    @else
    <span>{{ $stagiaire->autre_diplome }}</span>
    @endif
    <br>
    <label>Specialité : </label>
    <span>{{ $stagiaire->specialite }}</span>
    <br>
    <label>Inscrit(e) à l'Agence Emploi Jeunes sous le numéro : </label>
    <span>{{ $stagiaire->numero_aej }}</span>
    du
    <span>{{ \Carbon\Carbon::parse($stagiaire->date_entree)->isoFormat('DD/MM/Y') }}</span>
    <br>
    <label>Direction/Service d'accueil : </label>
    <span>{{ $stagiaire->service_affectation }}</span>
    <br>
    <label>
        Indemnité de stage mensuelle complémentaire :
    </label>
    <span>{{ $montant . ' F CFA' }}</span>
    <br>
    <label>
        Période du stage :
    </label>
    <span>{{ 'du '.\Carbon\Carbon::parse($stagiaire->date_debut)->isoFormat('DD/MM/Y') . ' au ' . \Carbon\Carbon::parse($stagiaire->date_fin)->isoFormat('DD/MM/Y') }}</span>
</p>
<p>Ci-après désigné(e) « <strong>le/la STAGIAIRE</strong> »</p>
