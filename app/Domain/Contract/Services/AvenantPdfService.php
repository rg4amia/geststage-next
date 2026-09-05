<?php

declare(strict_types=1);

namespace App\Domain\Contract\Services;

use App\Models\Contract\AvenantContrat;
use App\Models\HistoriqueGeneration;
use App\Models\Internship\Stage;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AvenantPdfService
{
    /**
     * Génère un PDF d'avenant de renouvellement pour un stage donné et logue la génération.
     */
    public function genererPdf(
        Stage $stage,
        ?string $dateDebutAvenant = null,
        ?string $dateFinAvenant = null,
        ?float $prime = null,
        ?AvenantContrat $avenant = null
    ): DomPdfWrapper {
        $stage->loadMissing([
            'beneficiaire.communeResidence',
            'beneficiaire.diplome',
            'entreprise.typeStructure',
            'entreprise.commune',
            'agence',
            'conseiller',
            'sourceFinancement',
            'typeStage',
            'contrats.avenants',
        ]);

        $contrat = $stage->contrats->sortByDesc('id')->first();
        $avenant = $avenant ?? $contrat?->avenants->sortByDesc('id')->first();

        $dateDebut = $dateDebutAvenant
            ?? ($avenant?->date_effet?->format('Y-m-d'))
            ?? ($stage->date_fin_prevue ? Carbon::parse($stage->date_fin_prevue)->addDay()->format('Y-m-d') : now()->format('Y-m-d'));

        $dateFin = $dateFinAvenant
            ?? ($avenant?->nouvelle_date_fin?->format('Y-m-d'))
            ?? ($stage->date_fin_prevue ? Carbon::parse($dateDebut)->addMonthsNoOverflow(6)->format('Y-m-d') : now()->addMonths(6)->format('Y-m-d'));

        $montantPrime = $prime
            ?? (float) ($avenant?->nouvelle_prime_mensuelle ?? $contrat?->prime_mensuelle ?? 0);

        $fileName = $this->genererNomFichier($stage);
        $view = $this->determinerVue($stage);
        $data = $this->preparerDonnees($stage, $dateDebut, $dateFin, $montantPrime, $fileName, $contrat, $avenant);

        $pdf = Pdf::loadView($view, $data)->setPaper('A4', 'portrait');

        $this->logGeneration($stage, $fileName, $dateDebut, $dateFin, $montantPrime);

        return $pdf;
    }

    /**
     * Détermine la vue Blade de l'avenant selon la source de financement.
     */
    public function determinerVue(Stage $stage): string
    {
        $code = strtoupper((string) ($stage->sourceFinancement?->code ?? ''));

        return match (true) {
            str_contains($code, 'PAPS') => 'stage.contrat.avenants.paps-gouv.body',
            str_contains($code, 'BUDGET') => 'stage.contrat.avenants.budget-aej.body',
            str_contains($code, 'C2D') => 'stage.contrat.avenants.c2d.body',
            str_contains($code, 'PEJEDEC') => 'stage.contrat.avenants.pejedec.body',
            default => 'stage.contrat.avenants.paps-gouv.body',
        };
    }

    /**
     * Génère le nom de fichier normalisé : AVENANT_{FINANCEMENT}_{NOM}_{PRENOMS}_{NUMERO_AEJ}.pdf
     */
    public function genererNomFichier(Stage $stage): string
    {
        $code = strtoupper((string) ($stage->sourceFinancement?->code ?? 'FINANCEMENT'));

        $prefix = match (true) {
            str_contains($code, 'PAPS') => 'PAPS_GOUV',
            str_contains($code, 'BUDGET') => 'BUDGET_AEJ',
            str_contains($code, 'C2D') => 'C2D',
            str_contains($code, 'PEJEDEC') => 'PEJEDEC',
            default => str_replace('-', '_', $code),
        };

        $beneficiaire = $stage->beneficiaire;
        $nom = $this->nettoyerChaine((string) ($beneficiaire?->nom ?? 'NOM'));
        $prenoms = $this->nettoyerChaine((string) ($beneficiaire?->prenoms ?? 'PRENOMS'));
        $matricule = $this->nettoyerChaine((string) ($beneficiaire?->numero_aej ?? 'AEJ'));

        return "AVENANT_{$prefix}_{$nom}_{$prenoms}_{$matricule}.pdf";
    }

    private function nettoyerChaine(string $valeur): string
    {
        $valeur = preg_replace('/[^\p{L}\p{N}]+/u', '_', trim($valeur)) ?? '';

        return strtoupper(trim($valeur, '_'));
    }

    /**
     * Prépare les données pour la vue Blade d'avenant.
     *
     * @return array<string, mixed>
     */
    private function preparerDonnees(
        Stage $stage,
        string $dateDebut,
        string $dateFin,
        float $prime,
        string $fileName,
        mixed $contrat,
        mixed $avenant
    ): array {
        $beneficiaire = $stage->beneficiaire;
        $entreprise = $stage->entreprise;
        $agence = $stage->agence;

        $communeResidence = $beneficiaire?->communeResidence?->nom
            ?? $beneficiaire?->sous_prefecture_residence
            ?? 'N/A';

        $villeEntreprise = $entreprise?->commune?->nom
            ?? $stage->commune_stage
            ?? 'Abidjan';

        $stagiaire = (object) [
            'nom_stagiaire' => $beneficiaire?->nom ?? 'Inconnu',
            'prenoms_stagiaire' => $beneficiaire?->prenoms ?? '',
            'numero_aej' => $beneficiaire?->numero_aej ?? '',
            'date_de_naissance' => $beneficiaire?->date_naissance?->format('Y-m-d'),
            'date_naissance' => $beneficiaire?->date_naissance?->format('Y-m-d'),
            'lieu_de_naissance' => $beneficiaire?->lieu_naissance ?? 'N/A',
            'sexe' => $beneficiaire?->sexe ?? 'HOMME',
            'contact1' => $beneficiaire?->telephone_principal ?? 'N/A',
            'contact' => $beneficiaire?->telephone_principal ?? 'N/A',
            'email' => $beneficiaire?->email ?? '',
            'communeresidence' => (object) ['name' => $communeResidence],
            'diplome' => $beneficiaire?->diplome?->nom ?? $beneficiaire?->diplome ?? null,
            'autre_diplome' => $beneficiaire?->autre_diplome ?? null,
            'specialite' => $beneficiaire?->specialite ?? null,
            'option_diplome' => $beneficiaire?->specialite ?? null,
            'date_debut' => $stage->date_debut?->format('d/m/Y') ?? 'N/A',
            'date_fin' => $stage->date_fin_prevue?->format('d/m/Y') ?? 'N/A',
            'date_debut_original' => $stage->date_debut?->format('Y-m-d'),
            'date_fin_original' => $stage->date_fin_prevue?->format('Y-m-d'),
            'entreprise' => (object) [
                'libelle_entreprise' => $entreprise?->raison_sociale ?? 'N/A',
                'secteur' => $entreprise?->domaine_activite ?? 'N/A',
                'ville' => $villeEntreprise,
                'adresse' => $entreprise?->adresse ?? 'N/A',
                'compte_contri' => $entreprise?->numero_contribuable ?? 'N/A',
                'rccm' => $entreprise?->registre_commerce ?? 'N/A',
                'cnps' => null,
                'contact' => $entreprise?->telephone ?? 'N/A',
                'dg' => $entreprise?->nom_representant ?? null,
                'fonction' => $entreprise?->fonction_representant ?? 'Directeur Général',
            ],
            'agence' => (object) [
                'libelle_agence' => $agence?->nom ?? 'N/A',
                'chef_agence' => $agence?->chef_agence ?? 'N/A',
            ],
            'conseiller' => (object) [
                'nom_prenoms' => $stage->conseiller?->nom_complet ?? 'N/A',
            ],
        ];

        $agenceObj = (object) [
            'libelle_agence' => $agence?->nom ?? 'N/A',
            'chef_agence' => $agence?->chef_agence ?? 'N/A',
        ];

        $conseillerObj = (object) [
            'nom_prenoms' => $stage->conseiller?->nom_complet ?? 'N/A',
        ];

        return [
            'stagiaire' => $stagiaire,
            'agence' => $agenceObj,
            'conseiller' => $conseillerObj,
            'fileName' => $fileName,
            'prime' => $prime,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'fonction' => $stage->intitule_poste ?? 'Stagiaire',
        ];
    }

    private function logGeneration(
        Stage $stage,
        string $fileName,
        string $dateDebut,
        string $dateFin,
        float $prime
    ): void {
        try {
            HistoriqueGeneration::create([
                'uuid_public' => (string) Str::uuid(),
                'type_document' => 'AVENANT',
                'stage_id' => $stage->id,
                'instance_parcours_id' => $stage->instanceParcours()->first()?->id,
                'user_id' => Auth::id(),
                'nom_fichier' => $fileName,
                'parametres' => [
                    'date_debut_avenant' => $dateDebut,
                    'date_fin_avenant' => $dateFin,
                    'prime' => $prime,
                ],
                'source_financement' => $stage->sourceFinancement?->nom,
                'type_stage' => $stage->typeStage?->nom,
                'nombre_stagiaires' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impossible de logger la génération de l\'avenant', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
