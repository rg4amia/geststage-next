<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Attendance\Services\PointageService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Payment\Paiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypePaiement;
use App\Models\Reference\TypeStage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
                    ->where('situation_stage', SituationStage::CODE_EN_COURS)
                    ->where('date_debut', '<=', $periode->date_fin)
                    ->where(function ($q) use ($periode) {
                        $q->whereNull('date_fin_prevue')
                            ->orWhere('date_fin_prevue', '>=', $periode->date_debut);
                    })
                    ->whereDoesntHave('pointages', function ($q) use ($periode) {
                        $q->where('periode_id', $periode->id)
                            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
                    })
                    // Le CA doit avoir validé le dossier avant que le CIP ne pointe un mois :
                    // légacy `etat_chef_agence = 2` (WaitCheckedChefAgenceService).
                    ->whereDoesntHave('instanceParcours', function ($q) {
                        $q->whereIn('corbeille_actuelle', CorbeilleEnum::nonValideesParCa());
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
                    ->where('situation_stage', SituationStage::CODE_EN_COURS)
                    ->where('date_debut', '<=', $periode->date_fin)
                    ->where(function ($q) use ($periode) {
                        $q->whereNull('date_fin_prevue')
                            ->orWhere('date_fin_prevue', '>=', $periode->date_debut);
                    })
                    ->whereDoesntHave('pointages', function ($q) use ($periode) {
                        $q->where('periode_id', $periode->id)
                            ->whereIn('statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
                    })
                    // Le CA doit avoir validé le dossier avant que le CIP ne pointe un mois :
                    // légacy `etat_chef_agence = 2` (WaitCheckedChefAgenceService).
                    ->whereDoesntHave('instanceParcours', function ($q) {
                        $q->whereIn('corbeille_actuelle', CorbeilleEnum::nonValideesParCa());
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
                    // Legacy `PointageChefAgenceController::statusPointage()` pose `status_ca = 2`
                    // à l'ajournement : c'est bien l'équivalent de `AJOURNE_CA` (cf. mapStatutPointage()).
                    ->where('statut', 'AJOURNE_CA')
                    // Legacy `PointageService::getPointageAjournerByChefAgence()` restreint la liste
                    // aux stagiaires encore dans le dispositif (`contrats_pae.id_situation_stage = 1`) :
                    // un stagiaire sorti n'a plus de pointage à corriger par le CIP.
                    ->whereHas('stage', fn (Builder $q) => $q->where('situation_stage', SituationStage::CODE_EN_COURS));

                $this->applyPointageFilters($query, $filters);
                $data = $query->paginate(20)->withQueryString();

            } elseif ($tab === 'ajourne_dmg') {
                $query = $this->buildLegacyAjourneDmgQuery($periode, $filters);
                $data = $query->paginate(20)->withQueryString();
                $data->getCollection()->transform(fn (Paiement $paiement) => $this->mapLegacyAjourneDmgRow($paiement));
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

    private function buildLegacyAjourneDmgQuery(Periode $periode, array $filters): Builder
    {
        $user = Auth::user();

        return Paiement::with([
            'decisions.auteur',
            'droitPaiement.pointage.stage.beneficiaire.typePaiement',
            'droitPaiement.pointage.stage.entreprise',
            'droitPaiement.pointage.stage.agence',
            'droitPaiement.pointage.stage.sourceFinancement',
            'droitPaiement.pointage.stage.typeStage',
        ])
            ->where('statut', 'AJOURNE_DMG')
            ->whereHas('droitPaiement', function ($query) use ($periode) {
                $query->where('periode_id', $periode->id)
                    ->whereNotNull('pointage_id')
                    ->whereHas('pointage', fn ($pointage) => $pointage->where('statut', 'VALIDE'));
            })
            ->whereHas('droitPaiement.pointage.stage', function ($query) use ($filters, $user) {

                if ($user?->agence_id) {
                    $query->where('agence_id', $user->agence_id);
                }

                $this->applyStageFilters($query, $filters);
            })
            ->orderByDesc('created_at');
    }

    private function mapLegacyAjourneDmgRow(Paiement $paiement): array
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
            'date_ajournement' => ($decision?->decide_le ?? $paiement->created_at)?->toDateString(),
            'observation_dmg' => $decision?->motif ?: 'Ajourné par la DMG',
            'decisions' => $paiement->decisions->values(),
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
                    'numero_tresor_money' => $beneficiaire?->numero_tresor_money,
                    'numero_wave' => $beneficiaire?->numero_wave,
                ],
                'entreprise' => [
                    'raison_sociale' => $stage?->entreprise?->raison_sociale,
                ],
                'agence' => [
                    'nom' => $stage?->agence?->nom,
                ],
                'sourceFinancement' => [
                    'nom' => $stage?->sourceFinancement?->nom,
                ],
                'typeStage' => [
                    'nom' => $stage?->typeStage?->nom,
                ],
            ],
        ];
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

    public function editStagiaire(Request $request, $id)
    {
        $stage = Stage::with(['beneficiaire.typePaiement', 'agence', 'entreprise', 'typeStage', 'sourceFinancement', 'instanceParcours'])->findOrFail($id);
        $typesPaiement = TypePaiement::orderBy('nom')->get();

        return Inertia::render('Cip/Pointages/EditStagiaire', [
            'stage' => $stage,
            'typesPaiement' => $typesPaiement,
            'returnTo' => [
                'tab' => $request->query('return_tab', 'ajourne_dmg'),
                'mois' => $request->query('mois'),
            ],
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
            'return_tab' => 'nullable|string',
            'mois' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $typePaiement = TypePaiement::findOrFail((int) $validated['type_paiement_id']);
        $beneficiaireUpdates = [
            'telephone_principal' => $validated['telephone_principal'],
            'telephone_secondaire' => $validated['telephone_secondaire'],
            'type_paiement_id' => $validated['type_paiement_id'],
            'numero_tresor_money' => $validated['numero_tresor_money'],
            'numero_wave' => $validated['numero_wave'],
        ];

        if ($typePaiement->code === 'TRESOR_MONEY') {
            $beneficiaireUpdates['numero_wave'] = null;
        }

        if ($typePaiement->code === 'WAVE') {
            $beneficiaireUpdates['numero_tresor_money'] = null;
        }

        $stage->beneficiaire->update($beneficiaireUpdates);

        return redirect()->route('cip.pointages.index', array_filter([
            'tab' => $validated['return_tab'] ?? 'ajourne_dmg',
            'mois' => $validated['mois'] ?? null,
        ]))->with('success', 'Informations du stagiaire mises à jour avec succès.');
    }
}
