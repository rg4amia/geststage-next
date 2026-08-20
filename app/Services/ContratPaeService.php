<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HistoriqueGeneration;
use App\Models\Internship\Stage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContratPaeService
{
    /**
     * Génère un PDF de contrat PAE pour un stage donné et log l'historique
     */
    public function genererContratPdf(Stage $stage, ?string $fonction = null, ?float $montant = null): mixed
    {
        try {
            $stage->load([
                'beneficiaire.communeResidence',
                'beneficiaire.typePaiement',
                'beneficiaire.diplome',
                'entreprise.typeStructure',
                'entreprise.commune',
                'agence',
                'conseiller',
                'sourceFinancement',
                'typeStage',
                'contrats',
            ]);

            // Déterminer la vue appropriée selon la source de financement et type de stage
            $view = $this->determinerVue($stage);

            if (empty($view)) {
                throw new \RuntimeException('Type de contrat non déterminé pour cette combinaison de financement/stage');
            }

            // Préparer les données pour la vue
            $data = $this->preparerDonnees($stage, $fonction, $montant);

            // Générer le PDF
            $pdf = Pdf::loadView($view, $data);

            $pdf->setOptions([
                'defaultFont' => 'Cambria',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
                'margin_right' => 0,
            ]);

            // Configurer le contexte SSL
            $pdf->getDomPDF()->setHttpContext(
                stream_context_create([
                    'ssl' => [
                        'allow_self_signed' => true,
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ])
            );

            // Ajouter la numérotation des pages
            $pdf->output();
            $canvas = $pdf->get_canvas();
            $canvas->page_text(
                $canvas->get_width() - 100,
                $canvas->get_height() - 20,
                'P. {PAGE_NUM} / {PAGE_COUNT}',
                null,
                10,
                [0, 0, 0]
            );

            $pdf->setPaper('A4', 'portrait');

            // Logger l'historique de génération
            $this->logGeneration($stage, $fonction, $montant);

            return $pdf;
        } catch (\Throwable $e) {
            Log::error('Erreur lors de la génération du contrat PDF', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Détermine la vue Blade à utiliser selon la source de financement et le type de stage
     */
    private function determinerVue(Stage $stage): string
    {
        $sourceFinancementCode = $stage->sourceFinancement?->code;
        $typeStageCode = $stage->typeStage?->code;

        // Mapping selon la logique legacy
        $viewMappings = [
            'BUDGET_ETAT' => [
                'STAGE_ECOLE' => 'stage.contrat.budget-etat-stageecole',
                'default' => 'stage.contrat.budget-etat-pae',
            ],
            'PAPS_GOUV' => [
                'STAGE_ECOLE' => 'stage.contrat.paps-gouv-stageecole',
                'default' => 'stage.contrat.paps-gouv-pae',
            ],
            'C2D' => 'stage.contrat.c2d',
            'PEJEDEC' => 'stage.contrat.pejedec',
        ];

        if (isset($viewMappings[$sourceFinancementCode])) {
            if (is_array($viewMappings[$sourceFinancementCode])) {
                return $viewMappings[$sourceFinancementCode][$typeStageCode]
                    ?? $viewMappings[$sourceFinancementCode]['default'];
            }

            return $viewMappings[$sourceFinancementCode];
        }

        // Vue par défaut si aucune correspondance
        return 'stage.contrat.budget-etat-pae';
    }

    /**
     * Prépare les données à passer à la vue Blade
     */
    private function preparerDonnees(Stage $stage, ?string $fonction, ?float $montant): array
    {
        $beneficiaire = $stage->beneficiaire;
        $entreprise = $stage->entreprise;
        $agence = $stage->agence;
        $contrat = $stage->contrats->sortByDesc('id')->first();
        $dateDebut = $this->formaterDate($stage->date_debut);
        $dateFin = $this->formaterDate($stage->date_fin_prevue);
        $dateNaissance = $this->formaterDate($beneficiaire->date_naissance);
        $dateEntree = $this->formaterDate($stage->date_entree_portefeuille ?? $stage->created_at);
        $montantIndemnite = $montant ?? (float) ($contrat?->prime_mensuelle ?? 0);
        $nbreMoisPrev = $this->calculerDureeMois($stage->date_debut, $stage->date_fin_prevue);
        $communeResidence = $beneficiaire->communeResidence?->nom
            ?? $beneficiaire->sous_prefecture_residence
            ?? 'N/A';
        $villeEntreprise = $entreprise?->commune?->nom
            ?? $stage->commune_stage
            ?? $stage->sous_prefecture_stage
            ?? 'NEANT';

        $stagiaire = new class extends \stdClass
        {
            public function isPrimeMirahEligible(): bool
            {
                return false;
            }
        };

        $stagiaire->nom_stagiaire = $beneficiaire->nom;
        $stagiaire->prenoms_stagiaire = $beneficiaire->prenoms;
        $stagiaire->numero_aej = $beneficiaire->numero_aej;
        $stagiaire->num_piece = $beneficiaire->numero_piece_identite;
        $stagiaire->nature_piece = $beneficiaire->nature_piece_identite;
        $stagiaire->date_naissance = $dateNaissance;
        $stagiaire->date_de_naissance = $dateNaissance;
        $stagiaire->lieu_naissance = $beneficiaire->lieu_naissance;
        $stagiaire->lieu_de_naissance = $beneficiaire->lieu_naissance;
        $stagiaire->commune_residence = $communeResidence;
        $stagiaire->sous_prefecture_residence = $beneficiaire->sous_prefecture_residence;
        $stagiaire->communeresidence = (object) ['name' => $communeResidence];
        $stagiaire->contact = $beneficiaire->telephone_principal;
        $stagiaire->contact1 = $beneficiaire->telephone_principal;
        $stagiaire->email = $beneficiaire->email;
        $stagiaire->intitule_poste_stage = $fonction ?? $stage->intitule_poste;
        $stagiaire->service_affectation = $stage->service_affectation;
        $stagiaire->montant_indemnite = $montantIndemnite;
        $stagiaire->prime_mensuelle = $montantIndemnite;
        $stagiaire->date_debut = $dateDebut;
        $stagiaire->date_fin_prevue = $dateFin;
        $stagiaire->date_fin = $dateFin;
        $stagiaire->date_entree = $dateEntree;
        $stagiaire->nbre_mois_prev = $nbreMoisPrev;
        $stagiaire->duree_mois = $nbreMoisPrev;
        $stagiaire->diplome = $beneficiaire->diplome?->nom;
        $stagiaire->autre_diplome = $beneficiaire->autre_diplome;
        $stagiaire->specialite = $beneficiaire->specialite;
        $stagiaire->entreprise = (object) [
            'libelle_entreprise' => $entreprise?->raison_sociale ?? 'NEANT',
            'ville' => $villeEntreprise,
            'adresse' => $entreprise?->adresse,
            'compte_contri' => $entreprise?->numero_contribuable,
            'rccm' => $entreprise?->registre_commerce,
            'cnps' => null,
            'mail' => $entreprise?->email,
            'contact' => $entreprise?->telephone,
            'dg' => null,
        ];
        $stagiaire->agence = (object) [
            'libelle_agence' => $agence?->nom ?? 'N/A',
            'nom' => $agence?->nom ?? 'N/A',
            'chef_agence' => $agence?->chef_agence ?? 'N/A',
        ];
        $stagiaire->typestage = (object) [
            'libelle_type_stage' => $stage->typeStage?->nom,
        ];
        $stagiaire->typefinancement = (object) [
            'libelle_financement' => $stage->sourceFinancement?->nom,
        ];
        $stagiaire->conseiller = (object) [
            'nom_prenoms' => $stage->conseiller?->nom_complet ?? 'N/A',
        ];

        return [
            'stagiaire' => $stagiaire,
            'fonction' => $fonction,
            'montant' => $montantIndemnite,
            'agence' => $stagiaire->agence,
            'conseiller' => $stage->conseiller?->nom_complet ?? 'N/A',
        ];
    }

    private function formaterDate(mixed $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    private function calculerDureeMois(mixed $dateDebut, mixed $dateFin): int
    {
        if (empty($dateDebut) || empty($dateFin)) {
            return 6;
        }

        $debut = $dateDebut instanceof Carbon ? $dateDebut->copy() : Carbon::parse($dateDebut);
        $fin = $dateFin instanceof Carbon ? $dateFin->copy() : Carbon::parse($dateFin);

        return max(1, (int) ceil($debut->diffInDays($fin) / 30));
    }

    /**
     * Génère le nom de fichier pour le contrat
     */
    public function genererNomFichier(Stage $stage): string
    {
        $beneficiaire = $stage->beneficiaire;

        return str_replace(
            ' ',
            '_',
            'CONTRAT_' . $beneficiaire->nom . '_' . $beneficiaire->prenoms . '_' . $beneficiaire->numero_aej
        ) . '.pdf';
    }

    /**
     * Log la génération d'un contrat dans l'historique
     */
    private function logGeneration(Stage $stage, ?string $fonction, ?float $montant): void
    {
        try {
            HistoriqueGeneration::create([
                'uuid_public' => Str::uuid(),
                'type_document' => 'CONTRAT',
                'stage_id' => $stage->id,
                'instance_parcours_id' => $stage->instanceParcours()->first()?->id,
                'user_id' => Auth::id(),
                'nom_fichier' => $this->genererNomFichier($stage),
                'parametres' => [
                    'fonction' => $fonction,
                    'montant' => $montant,
                ],
                'source_financement' => $stage->sourceFinancement?->nom,
                'type_stage' => $stage->typeStage?->nom,
                'nombre_stagiaires' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Impossible de logger la génération du contrat', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
