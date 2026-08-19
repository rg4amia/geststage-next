<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HistoriqueGeneration;
use App\Models\Internship\Stage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TresorMoneyService
{
    /**
     * Génère un PDF de référence Trésor Money pour un ou plusieurs stages et log l'historique
     */
    public function genererFichierTresorMoney(Collection|array $stages): mixed
    {
        try {
            if (is_array($stages)) {
                $stages = collect($stages);
            }

            // Charger les relations nécessaires
            $stages = $stages->map(function ($stage) {
                if ($stage instanceof Stage) {
                    return $stage->load([
                        'beneficiaire.communeResidence',
                        'beneficiaire.sousPrefectureResidence',
                        'beneficiaire.typePaiement',
                        'entreprise',
                        'agence',
                        'sourceFinancement',
                        'typeStage',
                    ]);
                }

                return $stage;
            });

            // Préparer les données pour la vue
            $data = [
                'stagiaires' => $this->preparerDonneesStagiaires($stages),
                'date_generation' => now()->format('d/m/Y'),
            ];

            // Générer le PDF
            $pdf = Pdf::loadView('stage.cip.tresor_money', $data);

            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $pdf->setPaper('A4', 'landscape');

            // Logger l'historique de génération
            $this->logGeneration($stages);

            return $pdf;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du fichier Trésor Money', [
                'stage_ids' => $stages->pluck('id')->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Prépare les données des stagiaires pour la vue
     */
    private function preparerDonneesStagiaires(Collection $stages): Collection
    {
        return $stages->map(function (Stage $stage) {
            $beneficiaire = $stage->beneficiaire;
            $entreprise = $stage->entreprise;

            return [
                'nom' => $beneficiaire->nom,
                'prenoms' => $beneficiaire->prenoms,
                'fonction' => $stage->intitule_poste,
                'lieu_residence' => $beneficiaire->sousPrefectureResidence?->nom
                    ?? $beneficiaire->communeResidence?->nom
                    ?? 'N/A',
                'numero_tresormoney' => $beneficiaire->numero_tresor_money ?? 'N/A',
                'matricule' => $beneficiaire->numero_aej,
                'num_piece' => $beneficiaire->numero_piece_identite,
                'nature_piece' => $beneficiaire->typePieceIdentite?->nom ?? 'N/A',
                'type_stage' => $stage->typeStage?->nom,
                'entreprise' => $entreprise?->raison_sociale,
                'agence' => $stage->agence?->nom,
                'type_financement' => $stage->sourceFinancement?->nom,
                'montant_indemnite' => $stage->montant_indemnite,
                'date_debut' => $stage->date_debut?->format('d/m/Y'),
                'date_fin_prevue' => $stage->date_fin_prevue?->format('d/m/Y'),
            ];
        });
    }

    /**
     * Génère le nom de fichier pour le fichier Trésor Money
     */
    public function genererNomFichier(?string $mois = null): string
    {
        $mois = $mois ?? now()->format('Y-m');

        return 'TRESOR_MONEY_'.str_replace('-', '_', $mois).'.pdf';
    }

    /**
     * Log la génération d'un fichier Trésor Money dans l'historique
     */
    private function logGeneration(Collection $stages): void
    {
        try {
            // Pour Trésor Money groupé, on peut créer une seule entrée avec le nombre de stagiaires
            // ou une entrée par stagiaire. Ici on choisit une seule entrée groupée
            $firstStage = $stages->first();

            HistoriqueGeneration::create([
                'uuid_public' => Str::uuid(),
                'type_document' => 'TRESOR_MONEY',
                'stage_id' => $firstStage?->id, // Premier stage comme référence
                'user_id' => Auth::id(),
                'nom_fichier' => $this->genererNomFichier(),
                'parametres' => [
                    'stage_ids' => $stages->pluck('id')->toArray(),
                ],
                'nombre_stagiaires' => $stages->count(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Impossible de logger la génération du fichier Trésor Money', [
                'stage_count' => $stages->count(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
