<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
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
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
    /**
     * Corbeilles CIP dans lesquelles un dossier n'a pas encore été (ou plus) transmis
     * au Chef d'Agence : soumission initiale ou retour d'ajournement à corriger.
     */
    private const CORBEILLES_NON_TRANSMISES = [
        CorbeilleEnum::CIP_MES_STAGIAIRES->value,
        CorbeilleEnum::CIP_AJOURNE_CA->value,
        CorbeilleEnum::CIP_AJOURNE_DESSE->value,
        CorbeilleEnum::CIP_AJOURNE_DMG->value,
        CorbeilleEnum::CIP_AJOURNE_AAF->value,
    ];

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

        $user = Auth::user();

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
        ]);

        // Toujours exiger un stage non supprimé logiquement : Stage a désormais le trait
        // SoftDeletes, donc whereHas('stage') exclut déjà les dossiers "deleted_at" côté legacy.
        // Sans ce garde-fou, un utilisateur sans agence_id verrait des lignes avec stage=null.
        $query->whereHas('stage', function ($q) use ($user) {
            if ($user && $user->agence_id) {
                $q->where('agence_id', $user->agence_id);
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
            $search = $filters['search'];
            $query->whereHas('stage.beneficiaire', function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                    ->orWhere('numero_aej', 'ilike', "%{$search}%");
            });
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
        $agences = Cache::remember('filter_agences_mes_stagiaires', 1800, fn () => Agence::orderBy('nom')->pluck('nom', 'id')->toArray());
        $entreprises = Cache::remember(
            'filter_entreprises_mes_stagiaires_'.($user->id ?? '0'),
            1800,
            fn () => Entreprise::when($user && $user->agence_id, function ($q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            })->orderBy('raison_sociale')->pluck('raison_sociale', 'id')->toArray()
        );
        $typesfinancements = Cache::remember('filter_typesfinancements_mes_stagiaires', 1800, fn () => SourceFinancement::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestages = Cache::remember('filter_typestages_mes_stagiaires', 1800, fn () => TypeStage::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestructures = Cache::remember('filter_typestructures_mes_stagiaires', 1800, fn () => TypeStructure::orderBy('nom')->pluck('nom', 'id')->toArray());
        $etapes = Cache::remember('filter_etapes_mes_stagiaires', 1800, fn () => EtapeParcours::orderBy('nom')->pluck('nom', 'id')->toArray());
        $situationstages = Cache::remember('filter_situationstages_mes_stagiaires', 1800, fn () => SituationStage::orderBy('nom')->pluck('nom', 'code')->toArray());

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
     * Corbeille : Pointage Ajourné par DMG
     */
    public function pointageAjourneDmg(Request $request)
    {
        $instances = InstanceParcours::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence'])
            ->where('corbeille_actuelle', CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG)
            ->get();

        return Inertia::render('Cip/Pointages/AjourneDmg', [
            'instances' => $instances,
        ]);
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
     * Générer le contrat de stage
     */
    public function genererContrat(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);
        $fonction = $request->query('fonction');
        $montant = $request->query('montant') ? (float) $request->query('montant') : null;

        $service = app(ContratPaeService::class);

        try {
            $pdf = $service->genererContratPdf($instance->stage, $fonction, $montant);
            $filename = $service->genererNomFichier($instance->stage);

            return $pdf->stream($filename);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération du contrat : '.$e->getMessage());
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
     * Générer la fiche Trésor Money
     */
    public function genererTresorMoney(Request $request, $id)
    {
        $instance = InstanceParcours::with('stage.beneficiaire')->findOrFail($id);

        $service = app(TresorMoneyService::class);

        try {
            $pdf = $service->genererFichierTresorMoney(collect([$instance->stage]));
            $filename = $service->genererNomFichier();

            return $pdf->stream($filename);
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la génération du fichier Trésor Money : '.$e->getMessage());
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

        if (! in_array($instance->corbeille_actuelle, self::CORBEILLES_NON_TRANSMISES, true)) {
            return back()->with('error', 'Ce dossier a déjà été transmis au Chef d\'Agence.');
        }

        $documents = $instance->stage->documents;
        $aContrat = $documents->contains(fn ($d) => $d->typeDocument?->code === self::CODE_DOCUMENT_CONTRAT);

        $requiertTresorMoney = $instance->stage->beneficiaire?->typePaiement?->code === 'TRESOR_MONEY';
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
     * Supprimer un dossier stagiaire
     */
    public function destroy($id)
    {
        $instance = InstanceParcours::findOrFail($id);

        // Note: Selon la logique métier, on supprime l'instance, ou le stage associé
        $instance->delete();

        return back()->with('success', 'Dossier stagiaire supprimé avec succès.');
    }
}
