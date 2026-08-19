<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HistoriqueGeneration;
use App\Models\Internship\Stage;
use Barryvdh\DomPDF\Facade\Pdf;
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
                'entreprise.typeStructure',
                'agence',
                'sourceFinancement',
                'typeStage',
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
        } catch (\Exception $e) {
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

        return [
            'stagiaire' => (object) [
                'nom_stagiaire' => $beneficiaire->nom,
                'prenoms_stagiaire' => $beneficiaire->prenoms,
                'numero_aej' => $beneficiaire->numero_aej,
                'num_piece' => $beneficiaire->numero_piece_identite,
                'nature_piece' => $beneficiaire->typePieceIdentite?->nom,
                'date_naissance' => $beneficiaire->date_naissance?->format('d/m/Y'),
                'lieu_naissance' => $beneficiaire->lieuNaissance,
                'commune_residence' => $beneficiaire->communeResidence?->nom,
                'sous_prefecture_residence' => $beneficiaire->sous_prefecture_residence,
                'contact' => $beneficiaire->contact_telephonique,
                'email' => $beneficiaire->email,
                'intitule_poste_stage' => $fonction ?? $stage->intitule_poste,
                'montant_indemnite' => $montant ?? $stage->montant_indemnite,
                'date_debut' => $stage->date_debut?->format('d/m/Y'),
                'date_fin_prevue' => $stage->date_fin_prevue?->format('d/m/Y'),
                'duree_mois' => $stage->duree_mois,
                'entreprise' => (object) [
                    'libelle_entreprise' => $entreprise->raison_sociale,
                    'adresse' => $entreprise->adresse,
                    'contact' => $entreprise->contact,
                    'email' => $entreprise->email,
                ],
                'agence' => (object) [
                    'libelle_agence' => $stage->agence?->nom,
                    'nom' => $stage->agence?->nom,
                ],
                'typestage' => (object) [
                    'libelle_type_stage' => $stage->typeStage?->nom,
                ],
                'typefinancement' => (object) [
                    'libelle_financement' => $stage->sourceFinancement?->nom,
                ],
                'conseiller' => (object) [
                    'nom_prenoms' => $stage->chefProjet?->nom_complet ?? 'N/A',
                ],
            ],
            'fonction' => $fonction,
            'montant' => $montant,
        ];
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
                'instance_parcours_id' => $stage->instancesParcours()->first()?->id,
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
        } catch (\Exception $e) {
            Log::warning('Impossible de logger la génération du contrat', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
