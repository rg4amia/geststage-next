<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\Reference\TypeStructure;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MesStagiairesCipController extends Controller
{
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
            'stage.pointages.periode',
            'stage.pointages.versionCourante'
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
        if (!empty($filters['agence_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('agence_id', $filters['agence_id']);
            });
        }
        if (!empty($filters['entreprise_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('entreprise_id', $filters['entreprise_id']);
            });
        }
        if (!empty($filters['typesfinancement_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('source_financement_id', $filters['typesfinancement_id']);
            });
        }
        if (!empty($filters['typestage_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('type_stage_id', $filters['typestage_id']);
            });
        }
        if (!empty($filters['type_structure_id'])) {
            $query->whereHas('stage.entreprise', function ($q) use ($filters) {
                $q->where('type_structure_id', $filters['type_structure_id']);
            });
        }
        if (!empty($filters['etape_id'])) {
            $query->where('etape_courante_id', $filters['etape_id']);
        }
        if (!empty($filters['situationstage_id'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('situation_stage', $filters['situationstage_id']);
            });
        }
        if (!empty($filters['date_debut'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('date_debut', '>=', $filters['date_debut']);
            });
        }
        if (!empty($filters['date_fin'])) {
            $query->whereHas('stage', function ($q) use ($filters) {
                $q->where('date_fin_prevue', '<=', $filters['date_fin']);
            });
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('stage.beneficiaire', function($q) use ($search) {
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
            'daicg_attente_dmg'
        ])->count();

        $instances = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        // Shell Inertia — données de filtres
        $agences = Cache::remember('filter_agences_mes_stagiaires', 1800, fn () => \App\Models\Reference\Agence::orderBy('nom')->pluck('nom', 'id')->toArray());
        $entreprises = Cache::remember('filter_entreprises_mes_stagiaires_' . ($user->id ?? '0'), 1800, fn () =>
            \App\Models\Company\Entreprise::when($user && $user->agence_id, function ($q) use ($user) {
                $q->where('agence_id', $user->agence_id);
            })->orderBy('raison_sociale')->pluck('raison_sociale', 'id')->toArray()
        );
        $typesfinancements = Cache::remember('filter_typesfinancements_mes_stagiaires', 1800, fn () => \App\Models\Reference\SourceFinancement::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestages = Cache::remember('filter_typestages_mes_stagiaires', 1800, fn () => \App\Models\Reference\TypeStage::orderBy('nom')->pluck('nom', 'id')->toArray());
        $typestructures = Cache::remember('filter_typestructures_mes_stagiaires', 1800, fn () => \App\Models\Reference\TypeStructure::orderBy('nom')->pluck('nom', 'id')->toArray());
        $etapes = Cache::remember('filter_etapes_mes_stagiaires', 1800, fn () => \App\Models\Workflow\EtapeParcours::orderBy('nom')->pluck('nom', 'id')->toArray());
        $situationstages = Cache::remember('filter_situationstages_mes_stagiaires', 1800, fn () => \App\Models\Reference\SituationStage::orderBy('nom')->pluck('nom', 'code')->toArray());

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
        $instance = InstanceParcours::findOrFail($id);
        $fonction = $request->query('fonction');
        $montant = $request->query('montant');
        
        // TODO: Logique de génération PDF
        // Retourne un message temporaire pour le frontend
        return back()->with('success', "Le contrat pour {$instance->stage->beneficiaire->nom} a été généré avec succès.");
    }

    /**
     * Transférer le contrat signé (upload)
     */
    public function transfererContrat(Request $request, $id)
    {
        $request->validate([
            'contrat_stage' => 'required|file|mimes:pdf|max:5120', // 5MB max
        ]);

        $instance = InstanceParcours::findOrFail($id);

        if ($request->hasFile('contrat_stage')) {
            $path = $request->file('contrat_stage')->store('contrats_stagiaires', 'public');
            // TODO: Enregistrer le chemin dans le modèle
            // $instance->stage->update(['file_contrat' => $path]);
        }

        return back()->with('success', 'Contrat transféré avec succès.');
    }

    /**
     * Générer la fiche Trésor Money
     */
    public function genererTresorMoney(Request $request, $id)
    {
        $instance = InstanceParcours::findOrFail($id);
        
        // TODO: Logique de génération de la fiche PDF
        return back()->with('success', "Fiche Trésor Money générée avec succès.");
    }

    /**
     * Uploader la fiche Trésor Money scannée
     */
    public function uploadTresorMoney(Request $request, $id)
    {
        $request->validate([
            'tresor_money_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $instance = InstanceParcours::findOrFail($id);

        if ($request->hasFile('tresor_money_file')) {
            $path = $request->file('tresor_money_file')->store('tresor_money_files', 'public');
            // TODO: Enregistrer le chemin dans le modèle
            // $instance->stage->update(['file_tresor_money' => $path]);
        }

        return back()->with('success', 'Fiche Trésor Money enregistrée avec succès.');
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
