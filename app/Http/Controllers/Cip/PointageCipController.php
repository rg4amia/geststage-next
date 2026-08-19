<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Attendance\Services\PointageService;
use App\Http\Controllers\Controller;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PointageCipController extends Controller
{
    public function __construct(private PointageService $pointageService) {}

    public function stagiaireAttentePointage(Request $request)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $mois)) {
            abort(400, 'Format du mois invalide. Format attendu : YYYY-MM');
        }
        $tab = $request->query('tab', 'attente');

        $filters = $request->only(['mois', 'agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'search']);
        $filters['mois'] = $mois;

        $periode = Periode::where('code', $mois)->first();

        // Références pour les filtres
        $agences = Agence::orderBy('nom')->get(['id', 'nom']);
        $entreprises = Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale']);
        $sourcesFinancement = SourceFinancement::orderBy('nom')->get(['id', 'nom']);
        $typesStage = TypeStage::orderBy('nom')->get(['id', 'nom']);
        $periodes = Periode::orderByDesc('date_debut')->limit(24)->get(['id', 'code']);

        $counts = $this->pointageService->getCountsByTab($periode?->id, $filters);
        $situationsStage = DB::table('situations_stage')->orderBy('nom')->get(['id', 'code', 'nom']);

        // Construction de la requête selon l'onglet actif
        $data = null;

        if ($periode) {
            if ($tab === 'attente') {
                $query = Stage::with(['beneficiaire', 'entreprise', 'agence', 'sourceFinancement'])
                    ->withExists(['pointages as has_pointage_demarrage' => function ($q) {
                        $q->where('nature', 'DEMARRAGE')->where('statut', 'VALIDE');
                    }])
                    ->where('situation_stage', 'EN_COURS')
                    ->where('date_debut', '<=', $periode->date_fin)
                    ->where(function ($q) use ($periode) {
                        $q->whereNull('date_fin_prevue')
                            ->orWhere('date_fin_prevue', '>=', $periode->date_debut);
                    })
                    ->whereDoesntHave('pointages', function ($q) use ($periode) {
                        $q->where('periode_id', $periode->id)
                            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
                    })
                    ->where('source_financement_id', '!=', 4); // Exclure PEJEDEC

                $this->applyStageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();

                $data->getCollection()->transform(function ($stage) use ($periode) {
                    $stage->is_demarrage = Carbon::parse($stage->date_debut)->format('Y-m') === $periode->code;

                    return $stage;
                });

            } elseif ($tab === 'attente_pejedec') {
                $query = Stage::with(['beneficiaire', 'entreprise', 'agence', 'sourceFinancement'])
                    ->withExists(['pointages as has_pointage_demarrage' => function ($q) {
                        $q->where('nature', 'DEMARRAGE')->where('statut', 'VALIDE');
                    }])
                    ->where('situation_stage', 'EN_COURS')
                    ->where('date_debut', '<=', $periode->date_fin)
                    ->where(function ($q) use ($periode) {
                        $q->whereNull('date_fin_prevue')
                            ->orWhere('date_fin_prevue', '>=', $periode->date_debut);
                    })
                    ->whereDoesntHave('pointages', function ($q) use ($periode) {
                        $q->where('periode_id', $periode->id)
                            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
                    })
                    ->where('source_financement_id', 4); // Seulement PEJEDEC

                $this->applyStageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();

                $data->getCollection()->transform(function ($stage) use ($periode) {
                    $stage->is_demarrage = Carbon::parse($stage->date_debut)->format('Y-m') === $periode->code;

                    return $stage;
                });

            } elseif ($tab === 'effectue') {
                $query = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante.saisiPar'])
                    ->where('periode_id', $periode->id)
                    ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP']);

                $this->applyPointageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();

            } elseif ($tab === 'ajourne_ca') {
                $query = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante', 'decisions.auteur'])
                    ->where('periode_id', $periode->id)
                    ->where('statut', 'AJOURNE_CA');

                $this->applyPointageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();

            } elseif ($tab === 'ajourne_dmg') {
                $query = Pointage::with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'stage.sourceFinancement', 'versionCourante', 'decisions.auteur'])
                    ->where('periode_id', $periode->id)
                    ->where('statut', 'AJOURNE_DMG');

                $this->applyPointageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();
            }
        }

        // dd($data);

        return Inertia::render('Cip/Pointages/Index', [
            'tab' => $tab,
            'counts' => $counts,
            'situationsStage' => $situationsStage,
            'data' => $data,
            'filters' => $filters,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'sourcesFinancement' => $sourcesFinancement,
            'typesStage' => $typesStage,
            'periodes' => $periodes,
            'periode' => $periode,
        ]);
    }

    private function applyStageFilters($query, $filters)
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('beneficiaire', function ($q) use ($search) {
                $q->where('nom', 'ilike', "%{$search}%")
                    ->orWhere('prenoms', 'ilike', "%{$search}%")
                    ->orWhere('numero_aej', 'ilike', "%{$search}%");
            });
        }
        if (! empty($filters['agence_id'])) {
            $query->where('agence_id', $filters['agence_id']);
        }
        if (! empty($filters['entreprise_id'])) {
            $query->where('entreprise_id', $filters['entreprise_id']);
        }
        if (! empty($filters['source_financement_id'])) {
            $query->where('source_financement_id', $filters['source_financement_id']);
        }
        if (! empty($filters['type_stage_id'])) {
            $query->where('type_stage_id', $filters['type_stage_id']);
        }
    }

    private function applyPointageFilters($query, $filters)
    {
        $query->whereHas('stage', function ($q) use ($filters) {
            $this->applyStageFilters($q, $filters);
        });
    }

    public function soumettreBatch(Request $request)
    {
        $request->validate([
            'periode_id' => 'required|exists:periodes,id',
            'stage_ids' => 'required|array',
            'stage_ids.*' => 'exists:stages,id',
            'observation' => 'nullable|string',
        ]);

        $periode = Periode::findOrFail($request->periode_id);
        $user = Auth::user();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $periode, $user, &$created, &$skipped) {
            $stages = Stage::whereIn('id', $request->stage_ids)->get();
            foreach ($stages as $stage) {
                $is_demarrage = Carbon::parse($stage->date_debut)->format('Y-m') === $periode->code;
                if (! $is_demarrage) {
                    $has_demarrage = Pointage::where('stage_id', $stage->id)
                        ->where('nature', 'DEMARRAGE')
                        ->where('statut', 'VALIDE')
                        ->exists();
                    if (! $has_demarrage) {
                        $skipped++;

                        continue;
                    }
                }

                $this->pointageService->soumettreMensuel(
                    $stage,
                    $periode,
                    30, // jours_presents par défaut
                    0,  // jours_absents par défaut
                    $user,
                    $request->observation
                );
                $created++;
            }
        });

        return redirect()->back()->with('success', "{$created} pointage(s) soumis avec succès.".($skipped > 0 ? " {$skipped} ignoré(s) (démarrage non validé)." : ''));
    }

    public function soumettreIndividuel(Request $request)
    {
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'periode_id' => 'required|exists:periodes,id',
            'situation_stage_code' => 'nullable|string',
            'observation' => 'nullable|string|max:1000',
            'justificatif_file' => 'nullable|file|max:5120',
        ]);

        $stage = Stage::findOrFail($request->stage_id);
        $periode = Periode::findOrFail($request->periode_id);

        $justificatifPath = null;
        if ($request->hasFile('justificatif_file')) {
            $justificatifPath = $request->file('justificatif_file')->store('fichierpointage', 'public');
        }

        $this->pointageService->soumettreIndividuel(
            $stage,
            $periode,
            $request->user(),
            $request->situation_stage_code,
            $request->observation,
            $justificatifPath
        );

        return back()->with('success', 'Pointage soumis avec succès.');
    }

    public function annulerPointage($id)
    {
        $pointage = Pointage::findOrFail($id);
        if ($pointage->statut !== 'SOUMIS') {
            return back()->with('error', 'Seuls les pointages au statut SOUMIS peuvent être annulés.');
        }
        $pointage->delete();

        return back()->with('success', 'Pointage annulé avec succès.');
    }

    public function corrigerAjournementDmg(Request $request, $id)
    {
        $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $pointage = Pointage::with('versionCourante')->findOrFail($id);

        if ($pointage->statut !== 'AJOURNE_DMG') {
            abort(409, 'Le pointage ne peut pas être corrigé dans cet état.');
        }

        if (! $pointage->versionCourante) {
            abort(422, 'La version courante du pointage est introuvable.');
        }

        // Simuler WorkflowTransitionService cipCorrigeAjournementDmg
        // TODO: utiliser l'injection si nécessaire
        $pointage->update(['statut' => 'CORRIGE_CIP']);

        DecisionPointage::create([
            'pointage_id' => $pointage->id,
            'version_pointage_id' => $pointage->versionCourante->id,
            'auteur_id' => $request->user()->id,
            'decision' => 'CORRIGE_CIP',
            'motif' => $request->input('motif'),
        ]);

        return redirect()->back()->with('success', 'Pointage corrigé et renvoyé au CA.');
    }

    public function editStagiaire($id)
    {
        $stage = Stage::with('beneficiaire')->findOrFail($id);
        $typesPaiement = TypePaiement::all();

        return Inertia::render('Cip/Pointages/EditStagiaire', [
            'stage' => $stage,
            'typesPaiement' => $typesPaiement,
        ]);
    }

    public function updateStagiaire(Request $request, $id)
    {
        $stage = Stage::with('beneficiaire')->findOrFail($id);

        $validated = $request->validate([
            'telephone_principal' => 'required|string|max:20',
            'telephone_secondaire' => 'nullable|string|max:20',
            'type_paiement_id' => 'required|exists:type_paiements,id',
            'numero_tresor_money' => 'nullable|string|max:50',
            'numero_wave' => 'nullable|string|max:50',
        ]);

        $stage->beneficiaire->update($validated);

        return redirect()->route('cip.pointages.index', ['tab' => 'ajourne_dmg'])->with('success', 'Informations du stagiaire mises à jour avec succès.');
    }
}
