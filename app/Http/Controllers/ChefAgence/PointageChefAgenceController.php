<?php

namespace App\Http\Controllers\ChefAgence;

use App\Domain\Attendance\Services\PointageChefAgenceService;
use App\Http\Controllers\Controller;
use App\Services\AttestationPresenceService;
use App\Models\Attendance\Pointage;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Cache, DB};
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PointageChefAgenceController extends Controller
{
    public function __construct(
        private readonly PointageChefAgenceService $pointageCaService,
        private readonly AttestationPresenceService $attestationService,
    ) {}

    /**
     * Page principale de validation des pointages par le Chef d'Agence.
     * Adapté du legacy pointageAttenteValidationByChefAgence avec :
     * - Sélecteur de mois dynamique
     * - Filtres multiples (agence, entreprise, financement, type stage, CIP, dates)
     * - Onglets : Nouveaux pointages / Corrections ADP
     */
    public function pointageAttenteValidationByChefAgence(Request $request): Response
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $tab = $request->query('tab', 'attente');

        $filters = $request->only([
            'agence_id', 'entreprise_id', 'source_financement_id',
            'type_stage_id', 'search', 'date_debut', 'date_fin',
        ]);

        $user = Auth::user();

        // Mois en attente (corbeille dynamique)
        $moisEnAttente = $this->pointageCaService->getMoisEnAttente($user->agence_id ?? null);

        // Si le mois sélectionné n'a pas de pointages, prendre le premier mois disponible
        if ($moisEnAttente->isEmpty()) {
            $mois = null;
        } elseif (!$moisEnAttente->pluck('value')->contains($mois)) {
            $mois = $moisEnAttente->first()['value'];
        }

        $dataSoumis = null;
        $dataCorrige = null;

        if ($mois) {
            // Nouveaux pointages (SOUMIS)
            $querySoumis = $this->pointageCaService->getPointagesEnAttente($mois, $filters);
            $dataSoumis = $querySoumis->paginate(20)->withQueryString();

            // Corrections ADP (CORRIGE_CIP)
            $queryCorrige = $this->pointageCaService->getPointagesCorrigesAdp($mois, $filters);
            $dataCorrige = $queryCorrige->paginate(20)->withQueryString();
        }

        // Références pour les filtres
        $referenceData = [
            'agences' => Cache::remember('ref.agences', 86400, fn () => Agence::query()->orderBy('nom')->pluck('nom', 'id')),
            'entreprises' => Cache::remember('ref.entreprises', 86400, fn () => Entreprise::query()->orderBy('raison_sociale')->pluck('raison_sociale', 'id')),
            'sourcesFinancement' => Cache::remember('ref.sources_financement', 86400, fn () => SourceFinancement::query()->orderBy('nom')->pluck('nom', 'id')),
            'typesStage' => Cache::remember('ref.types_stage', 86400, fn () => TypeStage::query()->orderBy('nom')->pluck('nom', 'id')),
        ];

        return Inertia::render('ChefAgence/Pointages/Index', array_merge($referenceData, [
            'moisEnAttente' => $moisEnAttente,
            'moisActuel' => $mois,
            'activeTab' => $tab,
            'pointagesSoumis' => $dataSoumis,
            'pointagesCorrigeAdp' => $dataCorrige,
            'filters' => $filters,
        ]));
    }

    /**
     * Valide un pointage individuel.
     * POST /chefagence/pointages/valider/{id}
     */
    public function valider(Request $request, int $id): JsonResponse
    {
        $pointage = Pointage::with(['stage'])->findOrFail($id);

        if ($pointage->statut !== 'SOUMIS') {
            return response()->json([
                'success' => false,
                'message' => 'Ce pointage ne peut pas être validé dans son état actuel.',
            ], 422);
        }

        try {
            $this->pointageCaService->validerPointage($pointage, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Pointage validé avec succès. Transmis à la DMG.',
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur validation pointage {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validation groupée de pointages.
     * POST /chefagence/pointages/valider-groupe
     */
    public function validerGroupe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pointage_ids' => ['required', 'array', 'min:1'],
            'pointage_ids.*' => ['integer', 'exists:pointages,id'],
        ]);

        try {
            $results = $this->pointageCaService->validerGroupe(
                $validated['pointage_ids'],
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => "{$results['success']} pointage(s) validé(s) avec succès.",
                'details' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur validation groupée: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la validation groupée : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ajourne un pointage (retour au CIP pour correction).
     * POST /chefagence/pointages/ajourner/{id}
     */
    public function ajourner(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $pointage = Pointage::with(['stage'])->findOrFail($id);

        try {
            $this->pointageCaService->ajournerPointage(
                $pointage,
                $request->user(),
                $validated['motif']
            );

            return response()->json([
                'success' => true,
                'message' => 'Pointage ajourné. Le CIP sera notifié pour correction.',
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur ajournement pointage {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'ajournement : " . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validation groupée avec ajournement.
     * POST /chefagence/pointages/ajourner-groupe
     */
    public function ajournerGroupe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pointage_ids' => ['required', 'array', 'min:1'],
            'pointage_ids.*' => ['integer', 'exists:pointages,id'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $count = 0;
        DB::transaction(function () use ($validated, $request, &$count) {
            foreach ($validated['pointage_ids'] as $id) {
                $pointage = Pointage::with(['stage'])->find($id);
                if ($pointage && $pointage->statut === 'SOUMIS') {
                    $this->pointageCaService->ajournerPointage(
                        $pointage,
                        $request->user(),
                        $validated['motif']
                    );
                    $count++;
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$count} pointage(s) ajourné(s) avec succès.",
        ]);
    }

    /**
     * Valide une correction de pointage ajourné ADP.
     * POST /chefagence/pointages-adp/{id}/valider
     */
    public function validerAjournementAdp(Request $request, int $id): JsonResponse
    {
        $pointage = Pointage::with(['stage'])->findOrFail($id);

        if ($pointage->statut !== 'CORRIGE_CIP') {
            return response()->json([
                'success' => false,
                'message' => "Ce pointage ne peut pas être validé dans son état actuel.",
            ], 422);
        }

        try {
            $this->pointageCaService->validerAjournementAdp($pointage, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Correction acceptée. Le pointage a été resoumis.',
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur validation ADP {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rejette une correction de pointage ajourné ADP.
     * POST /chefagence/pointages-adp/{id}/rejeter
     */
    public function rejeterAjournementAdp(Request $request, int $id): JsonResponse
    {
        $pointage = Pointage::with(['stage'])->findOrFail($id);

        if ($pointage->statut !== 'CORRIGE_CIP') {
            return response()->json([
                'success' => false,
                'message' => "Ce pointage ne peut pas être rejeté dans son état actuel.",
            ], 422);
        }

        try {
            $this->pointageCaService->rejeterAjournementAdp($pointage, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Dossier rejeté. Renvoyé dans la corbeille "Mes Stagiaires" du CIP.',
            ]);
        } catch (\Exception $e) {
            Log::error("Erreur rejet ADP {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retourne les mois disponibles pour le sélecteur dynamique.
     * GET /chefagence/pointages/mois
     */
    public function moisDisponibles(Request $request): JsonResponse
    {
        $user = Auth::user();
        $moisEnAttente = $this->pointageCaService->getMoisEnAttente($user->agence_id ?? null);

        return response()->json([
            'success' => true,
            'mois' => $moisEnAttente,
        ]);
    }

    /**
     * Génère le PDF d'attestation de présence.
     * POST /chefagence/pointages/generer-attestation
     */
    public function genererAttestation(Request $request)
    {
        $validated = $request->validate([
            'mois' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'source_financement_id' => ['nullable', 'integer', 'exists:sources_financement,id'],
            'type_stage_id' => ['nullable', 'integer', 'exists:types_stage,id'],
            'pointage_ids' => ['nullable', 'array'],
            'pointage_ids.*' => ['integer', 'exists:pointages,id'],
            'mode_traitement' => ['nullable', 'integer'],
        ]);

        $filters = array_filter([
            'source_financement_id' => $validated['source_financement_id'] ?? null,
        ]);

        try {
            $pdf = $this->attestationService->genererAttestation(
                moisPointage: $validated['mois'],
                filters: $filters,
                pointageIds: $validated['pointage_ids'] ?? null,
                typeStageId: $validated['type_stage_id'] ?? null,
                modeTraitement: $validated['mode_traitement'] ?? 1,
            );

            $filename = $this->attestationService->genererNomFichier($validated['mois']);

            return $pdf->stream($filename);
        } catch (\Exception $e) {
            Log::error('Erreur génération attestation: ' . $e->getMessage());

            return back()->with('error', 'Erreur lors de la génération de l\'attestation : ' . $e->getMessage());
        }
    }
}
