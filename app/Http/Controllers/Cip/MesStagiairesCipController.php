<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Domain\Workflow\Services\SuiviPointageService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use App\Models\Internship\Stage;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeDocument;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use App\Services\ContratPaeService;
use App\Services\TresorMoneyService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
    private const CODE_DOCUMENT_CONTRAT = 'CONTRAT';

    private const CODE_DOCUMENT_TRESOR_MONEY = 'TRESOR_MONEY';

    /**
     * Corbeille : Mes Stagiaires
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'agence_id',
            'entreprise_id',
            'typesfinancement_id',
            'typestage_id',
            'type_structure_id',
            'date_debut',
            'date_fin',
            'etape_id',
            'situationstage_id',
            'search',
            'page',
        ]);

        $query = InstanceParcours::with([
            'stage.beneficiaire.typePaiement',
            'stage.entreprise.typeStructure',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'stage.contrats',
            'stage.documents.typeDocument',
            'stage.documents.versions',
            'stage.pointages.periode',
            'stage.pointages.versionCourante',
            'etapeCourante',
        ]);

        $agencesAutorisees = $this->agencesAutorisees();

        // Toujours exiger un stage non supprimé logiquement : Stage a désormais le trait
        // SoftDeletes, donc whereHas('stage') exclut déjà les dossiers "deleted_at" côté legacy.
        $query->whereHas('stage', function ($q) use ($agencesAutorisees) {
            if ($agencesAutorisees !== null) {
                $q->whereIn('agence_id', $agencesAutorisees);
            }
        });

        // Apply filters
        if (! empty($filters['agence_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('agence_id', $filters['agence_id']);
            });
        }
        if (! empty($filters['entreprise_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('entreprise_id', $filters['entreprise_id']);
            });
        }
        if (! empty($filters['typesfinancement_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('source_financement_id', $filters['typesfinancement_id']);
            });
        }
        if (! empty($filters['typestage_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('type_stage_id', $filters['typestage_id']);
            });
        }
        if (! empty($filters['type_structure_id'])) {
            $query->whereHas('stage.entreprise', function ($q) use ($filters) {
                $q->where('type_structure_id', $filters['type_structure_id']);
            });
        }
        if (! empty($filters['etape_id'])) {
            $query->where('etape_courante_id', $filters['etape_id']);
        }
        if (! empty($filters['situationstage_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('situation_stage', $filters['situationstage_id']);
            });
        }
        if (! empty($filters['date_debut'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('date_debut', '>=', $filters['date_debut']);
            });
        }
        if (! empty($filters['date_fin'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('date_fin_prevue', '<=', $filters['date_fin']);
            });
        }
        if (! empty($filters['search'])) {
            $this->applyRechercheDossier($query, $filters['search']);
        }

        $total = $query->count();
        $avecContrat = (clone $query)->has('stage.contrats')->count();
        $sansContrat = $total - $avecContrat;
        $enAttente = (clone $query)->whereIn('corbeille_actuelle', [
            'ca_attente_validation_demarrage',
            'ca_attente_validation_omis',
            'dmg_attente_paiement_demarrage',
            'ca_validation_pointages',
            'desse_attente_verification_dmg',
            'desse_attente_ca',
            'daicg_attente_dmg',
        ])->count();

        $instances = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        // Shell Inertia — données de filtres
        $agences = Agence::cachedPluck('nom');
        $entreprises = Entreprise::cached()
            ->when($agencesAutorisees, fn ($c, $ids) => $c->whereIn('agence_id', $ids))
            ->sortBy('raison_sociale')
            ->pluck('raison_sociale', 'id')
            ->all();
        $typesfinancements = SourceFinancement::cachedPluck('nom');
        $typestages = TypeStage::cachedPluck('nom');
        $typestructures = TypeStructure::cachedPluck('nom');
        $etapes = Cache::remember('filter_etapes_mes_stagiaires', 1800, fn () => EtapeParcours::orderBy('nom')->pluck('nom', 'id')->toArray());
        $situationstages = SituationStage::cachedPluck('nom', 'code');

        return Inertia::render('Cip/MesStagiaires/Index', [
            'instances' => $instances,
            'stats' => [
                'total' => $total,
                'avecContrat' => $avecContrat,
                'sansContrat' => $sansContrat,
                'enAttente' => $enAttente,
            ],
            'agences' => $agences,
            'entreprises' => $entreprises,
            'typesfinancements' => $typesfinancements,
            'typestages' => $typestages,
            'typestructures' => $typestructures,
            'etapes' => $etapes,
            'situationstages' => $situationstages,
            'filters' => $filters,
        ]);
    }

    /**
     * Stagiaires ajournés par le Chef d'Agence.
     * Corbeille : cip_ajourne_ca — instance retournée au CIP pour correction.
     *
     * Équivalent legacy : StagiaireController@liste_stagiaires_rejetes
     * Conditions legacy : active_chef_agence=0, etat_chef_agence=1, agent_id=3
     */
    public function ajournesChefAgence(Request $request)
    {
        $filters = $request->only([
            'agence_id', 'entreprise_id', 'typesfinancement_id',
            'typestage_id', 'type_structure_id', 'search', 'page',
        ]);

        $agencesAutorisees = $this->agencesAutorisees();

        $query = InstanceParcours::with([
            'stage.beneficiaire.typePaiement',
            'stage.entreprise.typeStructure',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'stage.contrats',
            'etapeCourante',
            'evenements.acteur',
        ])
            ->where('corbeille_actuelle', CorbeilleEnum::CIP_AJOURNE_CA->value)
            ->whereHas('stage', function ($q) use ($agencesAutorisees) {
                if ($agencesAutorisees !== null) {
                    $q->whereIn('agence_id', $agencesAutorisees);
                }
            });

        if (! empty($filters['agence_id'])) {
            $query->whereHas('stage', fn ($q) => $q->where('agence_id', $filters['agence_id']));
        }
        if (! empty($filters['entreprise_id'])) {
            $query->whereHas('stage', fn ($q) => $q->where('entreprise_id', $filters['entreprise_id']));
        }
        if (! empty($filters['typesfinancement_id'])) {
            $query->whereHas('stage', fn ($q) => $q->where('source_financement_id', $filters['typesfinancement_id']));
        }
        if (! empty($filters['typestage_id'])) {
            $query->whereHas('stage', fn ($q) => $q->where('type_stage_id', $filters['typestage_id']));
        }
        if (! empty($filters['type_structure_id'])) {
            $query->whereHas('stage.entreprise', fn ($q) => $q->where('type_structure_id', $filters['type_structure_id']));
        }
        if (! empty($filters['search'])) {
            $this->applyRechercheDossier($query, $filters['search']);
        }

        $instances = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        $agences = Agence::cachedPluck('nom');
        $entreprises = Entreprise::cached()
            ->when($agencesAutorisees, fn ($c, $ids) => $c->whereIn('agence_id', $ids))
            ->sortBy('raison_sociale')
            ->pluck('raison_sociale', 'id')
            ->all();
        $typesfinancements = SourceFinancement::cachedPluck('nom');
        $typestages = TypeStage::cachedPluck('nom');
        $typestructures = TypeStructure::cachedPluck('nom');

        return Inertia::render('Cip/MesStagiaires/AjournesChefAgence', [
            'instances' => $instances,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'typesfinancements' => $typesfinancements,
            'typestages' => $typestages,
            'typestructures' => $typestructures,
            'filters' => $filters,
        ]);
    }

    /**
     * Corbeille : Pointage Ajourné par DMG
     *
     * Liste paginée des paiements au statut AJOURNE_DMG pour une période donnée,
     * filtrable par agence, entreprise, financement, type de stage et recherche
     * libre (nom / prénoms / N° AEJ).
     *
     * Équivalent legacy : PointageService::getDmgDeferredMonths() +
     * PointageCipController::buildLegacyAjourneDmgQuery()
     */
    public function pointageAjourneDmg(Request $request)
    {
        $agencesAutorisees = $this->agencesAutorisees();

        $filters = $request->only([
            'periode_id', 'agence_id', 'entreprise_id',
            'source_financement_id', 'type_stage_id', 'search', 'page',
        ]);

        $query = Paiement::with([
            'decisions.auteur',
            'droitPaiement.pointage.stage.beneficiaire.typePaiement',
            'droitPaiement.pointage.stage.entreprise',
            'droitPaiement.pointage.stage.agence',
            'droitPaiement.pointage.stage.sourceFinancement',
            'droitPaiement.pointage.stage.typeStage',
            'droitPaiement.periode',
        ])
            ->where('statut', 'AJOURNE_DMG')
            ->whereHas('droitPaiement', function ($q) {
                $q->whereNotNull('pointage_id')
                    ->whereHas('pointage', fn ($p) => $p->where('statut', 'VALIDE'));
            })
            ->whereHas('droitPaiement.pointage.stage', function ($q) use ($agencesAutorisees) {
                if ($agencesAutorisees !== null) {
                    $q->whereIn('agence_id', $agencesAutorisees);
                }
            });

        // ── Filtres ──
        if (! empty($filters['periode_id'])) {
            $query->whereHas('droitPaiement', fn ($q) => $q->where('periode_id', $filters['periode_id']));
        }
        if (! empty($filters['agence_id'])) {
            $query->whereHas('droitPaiement.pointage.stage', fn ($q) => $q->where('agence_id', $filters['agence_id']));
        }
        if (! empty($filters['entreprise_id'])) {
            $query->whereHas('droitPaiement.pointage.stage', fn ($q) => $q->where('entreprise_id', $filters['entreprise_id']));
        }
        if (! empty($filters['source_financement_id'])) {
            $query->whereHas('droitPaiement.pointage.stage', fn ($q) => $q->where('source_financement_id', $filters['source_financement_id']));
        }
        if (! empty($filters['type_stage_id'])) {
            $query->whereHas('droitPaiement.pointage.stage', fn ($q) => $q->where('type_stage_id', $filters['type_stage_id']));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('droitPaiement.pointage.stage.beneficiaire', function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                    ->orWhere('numero_aej', 'ilike', "%{$search}%");
            });
        }

        $paiements = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Mapper en structure plate pour le frontend
        $paiements->getCollection()->transform(fn (Paiement $paiement) => $this->mapAjourneDmgRow($paiement));

        // Données de filtres
        $periodes = Periode::orderByDesc('date_debut')->limit(24)->get(['id', 'code']);
        $agences = Agence::orderBy('nom')->get(['id', 'nom']);
        $entreprises = Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale']);
        $sourcesFinancement = SourceFinancement::orderBy('nom')->get(['id', 'nom']);
        $typesStage = TypeStage::orderBy('nom')->get(['id', 'nom']);

        return Inertia::render('Cip/Pointages/AjourneDmg', [
            'paiements' => $paiements,
            'periodes' => $periodes,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'sourcesFinancement' => $sourcesFinancement,
            'typesStage' => $typesStage,
            'filters' => $filters,
        ]);
    }

    /**
     * Mappe un paiement DMG-ajourné en structure plate pour le frontend.
     */
    private function mapAjourneDmgRow(Paiement $paiement): array
    {
        $stage = $paiement->droitPaiement?->pointage?->stage;
        $beneficiaire = $stage?->beneficiaire;
        $decision = $paiement->decisions
            ->where('decision', 'AJOURNE_DMG')
            ->sortByDesc('decide_le')
            ->first();

        return [
            'id' => $paiement->id,
            'stage_id' => $stage?->id,
            'statut' => 'AJOURNE_DMG',
            'montant' => $paiement->montant,
            'date_ajournement' => ($decision?->decide_le ?? $paiement->created_at)?->toDateString(),
            'observation_dmg' => $decision?->motif ?: 'Ajourné par la DMG',
            'decisions' => $paiement->decisions->values(),
            'periode' => [
                'code' => $paiement->droitPaiement?->periode?->code,
            ],
            'stage' => [
                'id' => $stage?->id,
                'date_debut' => $stage?->date_debut?->toDateString(),
                'date_fin_prevue' => $stage?->date_fin_prevue?->toDateString(),
                'beneficiaire' => [
                    'numero_aej' => $beneficiaire?->numero_aej,
                    'nom' => $beneficiaire?->nom,
                    'prenoms' => $beneficiaire?->prenoms,
                    'telephone_principal' => $beneficiaire?->telephone_principal,
                    'telephone_secondaire' => $beneficiaire?->telephone_secondaire,
                    'type_paiement_id' => $beneficiaire?->type_paiement_id,
                    'typePaiement' => $beneficiaire?->typePaiement,
                    'numero_tresor_money' => $beneficiaire?->numero_tresor_money,
                    'numero_wave' => $beneficiaire?->numero_wave,
                ],
                'entreprise' => [
                    'id' => $stage?->entreprise?->id,
                    'raison_sociale' => $stage?->entreprise?->raison_sociale,
                ],
                'agence' => [
                    'id' => $stage?->agence?->id,
                    'nom' => $stage?->agence?->nom,
                ],
                'sourceFinancement' => [
                    'id' => $stage?->sourceFinancement?->id,
                    'nom' => $stage?->sourceFinancement?->nom,
                ],
                'typeStage' => [
                    'id' => $stage?->typeStage?->id,
                    'nom' => $stage?->typeStage?->nom,
                ],
            ],
        ];
    }

    /**
     * Corbeille : Suivi des cas spécifiques
     */
    public function suivi(Request $request, CorbeilleParcoursQueryService $corbeilles)
    {
        return Inertia::render('Cip/Suivi/Index', [
            'differesAC' => $corbeilles->instanceRows(CorbeilleEnum::CIP_DIFFERE_AC, 'Différé AC'),
            'doublonsDESSE' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_DESSE, 'Doublon DESSE'),
            'renouvellements' => $corbeilles->instanceRows(CorbeilleEnum::CIP_FIN_CONTRAT, 'Renouvellement'),
            'suspensionsAbandons' => $corbeilles->instanceRows(CorbeilleEnum::CIP_AJOURNE_AAF, 'Suspension ou abandon'),
        ]);
    }

    /**
     * Agences sur lesquelles l'utilisateur courant est habilité, ou `null` s'il n'a aucun
     * périmètre défini — auquel cas aucune restriction n'est appliquée, comme dans
     * `SituationStageService` et `SituationStagiaireCipController`. `users` n'a pas de colonne
     * `agence_id` : le périmètre est porté par le pivot `perimetres_agences_utilisateurs`.
     *
     * @return array<int, int>|null
     */
    private function agencesAutorisees(): ?array
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()->pluck('agences.id')->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    /**
     * Garde-fou périmètre pour les actions portant sur un dossier précis (génération/dépôt de
     * documents, transmission, suivi, suppression) : sans ce contrôle, une URL directe permet
     * de contourner le filtrage appliqué à la liste.
     */
    private function assertDansLePerimetre(InstanceParcours $instance): void
    {
        $agencesAutorisees = $this->agencesAutorisees();

        if ($agencesAutorisees !== null && ! in_array($instance->stage->agence_id, $agencesAutorisees, true)) {
            abort(403, "Ce dossier ne relève pas de votre périmètre d'agence.");
        }
    }

    /**
     * Recherche libre couvrant, comme le legacy DataTables : le bénéficiaire (nom, prénoms,
     * numéro AEJ), le numéro et l'état du contrat, l'étape courante du workflow et l'état des
     * pointages.
     */
    private function applyRechercheDossier($query, string $search): void
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%'.addcslashes($search, '%_').'%';

        $query->where(function ($q) use ($operator, $term) {
            $q->whereHas('stage.beneficiaire', function ($bq) use ($operator, $term) {
                $bq->where('nom', $operator, $term)
                    ->orWhere('prenoms', $operator, $term)
                    ->orWhere('numero_aej', $operator, $term);
            })
                ->orWhereHas('stage.contrats', function ($cq) use ($operator, $term) {
                    $cq->where('numero', $operator, $term)
                        ->orWhere('statut', $operator, $term);
                })
                ->orWhereHas('etapeCourante', function ($eq) use ($operator, $term) {
                    $eq->where('nom', $operator, $term);
                })
                ->orWhereHas('stage.pointages', function ($pq) use ($operator, $term) {
                    $pq->where('statut', $operator, $term);
                });
        });
    }

    /**
     * Générer le contrat de stage (stream direct)
     */
    public function genererContrat(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $request->validate([
            'fonction' => 'nullable|string|max:255',
            'montant' => 'nullable|numeric|min:0',
        ]);

        $fonction = $request->query('fonction');
        $montant = $request->query('montant') ? (float) $request->query('montant') : null;

        $service = app(ContratPaeService::class);

        try {
            $pdf = $service->genererContratPdf($instance->stage, $fonction, $montant);
            $filename = $service->genererNomFichier($instance->stage);

            return $pdf->stream($filename);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur lors de la génération du contrat : '.$e->getMessage());
        }
    }

    /**
     * Générer le contrat et retourner l'URL en JSON (pour aperçu iframe)
     */
    public function genererContratJson(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $request->validate([
            'fonction' => 'nullable|string|max:255',
            'montant' => 'nullable|numeric|min:0',
        ]);

        $fonction = $request->query('fonction');
        $montant = $request->query('montant') ? (float) $request->query('montant') : null;

        $service = app(ContratPaeService::class);

        try {
            $pdf = $service->genererContratPdf($instance->stage, $fonction, $montant);
            $filename = $service->genererNomFichier($instance->stage);

            // Stocker temporairement dans storage/app/public/pdf-preview (30 min)
            $tmpKey = Str::uuid();
            $path = "pdf-preview/{$tmpKey}_{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            // Nettoyer les anciens fichiers temporaires (> 30 min)
            $this->nettoyerPdfTemporaires();

            return response()->json([
                'url' => Storage::disk('public')->url($path),
                'filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erreur lors de la génération du contrat : '.$e->getMessage()], 500);
        }
    }

    /**
     * Transférer le contrat signé (upload)
     */
    public function transfererContrat(Request $request, $id)
    {
        $request->validate([
            'contrat_stage' => 'required|file|mimes:pdf|max:5120', // 5MB max
        ]);

        $instance = InstanceParcours::with('stage.contrats')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $this->deposerDocument(
            $instance,
            $request->file('contrat_stage'),
            self::CODE_DOCUMENT_CONTRAT,
            'Contrat',
            'contrats_stagiaires'
        );

        return back()->with('success', 'Contrat transféré avec succès.');
    }

    /**
     * Générer la fiche Trésor Money (stream direct)
     */
    public function genererTresorMoney(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $service = app(TresorMoneyService::class);

        try {
            $pdf = $service->genererFichierTresorMoney(collect([$instance->stage]));
            $filename = $service->genererNomFichier();

            return $pdf->stream($filename);
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur lors de la génération du fichier Trésor Money : '.$e->getMessage());
        }
    }

    /**
     * Générer la fiche Trésor Money et retourner l'URL en JSON (pour aperçu iframe)
     */
    public function genererTresorMoneyJson(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $service = app(TresorMoneyService::class);

        try {
            $pdf = $service->genererFichierTresorMoney(collect([$instance->stage]));
            $filename = $service->genererNomFichier();

            // Stocker temporairement dans storage/app/public/pdf-preview (30 min)
            $tmpKey = Str::uuid();
            $path = "pdf-preview/{$tmpKey}_{$filename}";
            Storage::disk('public')->put($path, $pdf->output());

            // Nettoyer les anciens fichiers temporaires (> 30 min)
            $this->nettoyerPdfTemporaires();

            return response()->json([
                'url' => Storage::disk('public')->url($path),
                'filename' => $filename,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erreur lors de la génération du fichier Trésor Money : '.$e->getMessage()], 500);
        }
    }

    /**
     * Supprime les fichiers PDF temporaires de prévisualisation de plus de 30 minutes
     */
    private function nettoyerPdfTemporaires(): void
    {
        try {
            $disk = Storage::disk('public');
            $files = $disk->files('pdf-preview');
            $limite = now()->subMinutes(30)->timestamp;

            foreach ($files as $file) {
                if ($disk->lastModified($file) < $limite) {
                    $disk->delete($file);
                }
            }
        } catch (\Exception $e) {
            // Silencieux : le nettoyage est best-effort
        }
    }

    /**
     * Uploader la fiche Trésor Money scannée
     */
    public function uploadTresorMoney(Request $request, $id)
    {
        $request->validate([
            'tresor_money_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $instance = InstanceParcours::with('stage.contrats')->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        $this->deposerDocument(
            $instance,
            $request->file('tresor_money_file'),
            self::CODE_DOCUMENT_TRESOR_MONEY,
            'Fiche Trésor Money',
            'tresor_money_files'
        );

        return back()->with('success', 'Fiche Trésor Money enregistrée avec succès.');
    }

    /**
     * Enregistre (ou remplace) un document rattaché au dossier, via le sous-système GED
     * (documents / versions_documents), déjà utilisé par InscriptionStagiaireService::inscrire().
     * Un même Document est réutilisé pour un (stage, type) donné : chaque nouvel upload crée
     * une nouvelle VersionDocument plutôt qu'un nouveau Document, pour conserver l'historique.
     */
    private function deposerDocument(InstanceParcours $instance, UploadedFile $file, string $code, string $nomType, string $dossierStockage): Document
    {
        $stage = $instance->stage;
        $contrat = $stage->contrats->first();
        $user = Auth::user();

        $typeDocument = TypeDocument::firstOrCreate(
            ['code' => $code],
            ['nom' => $nomType, 'actif' => true]
        );

        $document = Document::firstOrNew([
            'stage_id' => $stage->id,
            'type_document_id' => $typeDocument->id,
        ]);
        $document->beneficiaire_id = $stage->beneficiaire_id;
        $document->contrat_id = $contrat?->id;
        $document->cree_par_id = $document->cree_par_id ?? $user?->id;
        $document->nom = $file->getClientOriginalName();
        $document->statut = 'VALIDE';
        $document->prive = true;
        $document->save();

        $path = $file->store($dossierStockage.'/'.$stage->id, 'public');
        $numeroVersion = $document->versions()->max('numero_version') + 1;

        VersionDocument::create([
            'document_id' => $document->id,
            'depose_par_id' => $user?->id,
            'numero_version' => $numeroVersion,
            'disque' => 'public',
            'chemin' => $path,
            'nom_original' => $file->getClientOriginalName(),
            'type_mime' => $file->getMimeType(),
            'taille_octets' => $file->getSize(),
            'empreinte_sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        return $document;
    }

    /**
     * Le CIP transmet le dossier au Chef d'Agence (démarrage ou démarrage omis selon la date
     * de début). N'est autorisé que si le contrat signé et, pour un paiement Trésor Money, la
     * fiche Trésor Money ont déjà été déposés — cf. WorkflowTransitionService::submitToChefAgence.
     */
    public function transmettreChefAgence(Request $request, $id, WorkflowTransitionService $workflow)
    {
        $instance = InstanceParcours::with(['stage.beneficiaire.typePaiement', 'stage.documents.typeDocument'])->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        if (! in_array($instance->corbeille_actuelle, CorbeilleEnum::nonTransmisesChefAgence(), true)) {
            return back()->with('error', 'Ce dossier a déjà été transmis au Chef d\'Agence.');
        }

        $documents = $instance->stage->documents;
        $aContrat = $documents->contains(fn ($d) => $d->typeDocument?->code === self::CODE_DOCUMENT_CONTRAT);

        $requiertTresorMoney = $instance->stage->beneficiaire?->requiert_tresor_money ?? false;
        $aTresorMoney = ! $requiertTresorMoney
            || $documents->contains(fn ($d) => $d->typeDocument?->code === self::CODE_DOCUMENT_TRESOR_MONEY);

        $manquants = [];
        if (! $aContrat) {
            $manquants[] = 'le contrat signé';
        }
        if (! $aTresorMoney) {
            $manquants[] = 'la fiche Trésor Money';
        }

        if (! empty($manquants)) {
            return back()->with('error', 'Transmission impossible : '.implode(' et ', $manquants).' manquant(s).');
        }

        $workflow->submitToChefAgence($instance);

        return back()->with('success', 'Dossier transmis au Chef d\'Agence avec succès.');
    }

    /**
     * JSON : position de chaque pointage du dossier dans le circuit complet
     * (CIP → CA → visa DESSE → DMG → CB → OP → bordereau → AC → paiement).
     *
     * Chargé à l'ouverture de la modale « Analyse du Pointage » plutôt que dans la liste :
     * la chaîne de paiement représente une demi-douzaine de relations par mois, inutiles
     * pour les 50 lignes paginées de l'écran.
     */
    public function suiviPointages($id, SuiviPointageService $suivi)
    {
        $instance = InstanceParcours::with(SuiviPointageService::relationsStage())->findOrFail($id);
        $this->assertDansLePerimetre($instance);

        return response()->json([
            'corbeille_actuelle' => $suivi->corbeille($instance->corbeille_actuelle),
            'pointages' => $suivi->pourStage($instance->stage),
        ]);
    }

    /**
     * Supprimer un dossier stagiaire
     *
     * Réservé au CIP/administrateur et aux dossiers pas encore transmis au Chef d'Agence —
     * cf. InstanceParcoursPolicy::delete().
     */
    public function destroy($id)
    {
        $instance = InstanceParcours::with('stage')->findOrFail($id);
        $this->assertDansLePerimetre($instance);
        $this->authorize('delete', $instance);

        $instance->delete();

        return back()->with('success', 'Dossier stagiaire supprimé avec succès.');
    }
}
