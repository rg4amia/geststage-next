<?php

namespace App\Http\Controllers\Pejedec;

use App\Domain\Payment\Services\PejedecAafService;
use App\Domain\Pejedec\Services\PejedecSourceResolver;
use App\Http\Controllers\Controller;
use App\Models\Attendance\Pointage;
use App\Models\Company\Entreprise;
use App\Models\Payment\DroitPaiement;
use App\Models\Reference\Agence;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use InvalidArgumentException;

class AafController extends Controller
{
    public function __construct(
        private PejedecAafService $pejedecAafService,
        private PejedecSourceResolver $pejedecSourceResolver,
    ) {}

    public function index(Request $request)
    {
        return $this->renderDashboard($request, 'validation', 'Pejedec/Aaf/Index');
    }

    public function attenteValidation(Request $request)
    {
        return $this->renderDashboard($request, 'validation', 'Pejedec/Aaf/AttenteValidation');
    }

    public function paiementsAjournes(Request $request)
    {
        return $this->renderDashboard($request, 'ajournes', 'Pejedec/Aaf/PaiementsAjournes');
    }

    public function correctionsAValider(Request $request)
    {
        return $this->renderDashboard($request, 'corrections', 'Pejedec/Aaf/CorrectionsAValider');
    }

    public function attentePaiement(Request $request)
    {
        return $this->renderDashboard($request, 'paiement', 'Pejedec/Aaf/AttentePaiement');
    }

    public function validerPointage(Request $request, int $id): RedirectResponse
    {
        $pointage = Pointage::with(['stage.sourceFinancement', 'versionCourante'])->findOrFail($id);
        $this->assertPointageDansLePerimetre($pointage);

        try {
            $this->pejedecAafService->validerPointage($pointage, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Validation AAF enregistrée et droit de paiement ouvert.');
    }

    public function validerCorrection(Request $request, int $id): RedirectResponse
    {
        $pointage = Pointage::with(['stage.sourceFinancement', 'versionCourante'])->findOrFail($id);
        $this->assertPointageDansLePerimetre($pointage);

        try {
            $this->pejedecAafService->validerPointage($pointage, $request->user(), depuisCorrection: true);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Correction validée par l’AAF et droit de paiement ouvert.');
    }

    public function genererPaiement(Request $request, int $id): RedirectResponse
    {
        $droitPaiement = DroitPaiement::with(['stage.sourceFinancement'])->findOrFail($id);
        $this->assertDroitDansLePerimetre($droitPaiement);
        $this->assertDroitPejedec($droitPaiement);

        $paiement = $this->pejedecAafService->genererPaiement($droitPaiement);

        $message = $paiement->wasRecentlyCreated
            ? 'Paiement généré et prêt pour la DMG.'
            : 'Le paiement existait déjà pour ce droit.';

        return back()->with('success', $message);
    }

    private function renderDashboard(Request $request, string $focus, string $component)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $agenceId = $request->query('agence_id');
        $entrepriseId = $request->query('entreprise_id');
        $sourceFinancementId = $request->query('source_financement_id');
        $pejedecSource = $this->pejedecSourceResolver->source();

        if ($sourceFinancementId === null || $sourceFinancementId === '') {
            $sourceFinancementId = $pejedecSource?->id ? (string) $pejedecSource->id : null;
        }

        $periode = Periode::where('code', $mois)->first();
        $sourceFinancement = SourceFinancement::find($sourceFinancementId);
        $agencesAutorisees = $this->agencesAutorisees($request);
        $agences = Agence::query()
            ->when($agencesAutorisees !== null, fn ($query) => $query->whereIn('id', $agencesAutorisees))
            ->orderBy('nom')
            ->get(['id', 'nom'])
            ->map(function (Agence $agence) {
            return [
                'id' => $agence->id,
                'label' => $agence->nom,
            ];
        });
        $entreprises = Entreprise::orderBy('raison_sociale')->get(['id', 'raison_sociale'])->map(function (Entreprise $entreprise) {
            return [
                'id' => $entreprise->id,
                'label' => $entreprise->raison_sociale,
            ];
        });
        $sourcesFinancement = SourceFinancement::orderBy('nom')->get(['id', 'nom'])->map(function (SourceFinancement $source) {
            return [
                'id' => $source->id,
                'label' => $source->nom,
            ];
        });

        $attenteValidation = $this->pointageRows(
            $this->queryPointages($mois, ['VALIDE'], $agenceId, $entrepriseId, $sourceFinancementId, aafNonTraite: true, agencesAutorisees: $agencesAutorisees)
        );

        $paiementsAjournes = $this->pointageRows(
            $this->queryPointages($mois, ['AJOURNE_DMG', 'AJOURNE_CA'], $agenceId, $entrepriseId, $sourceFinancementId, agencesAutorisees: $agencesAutorisees)
        );

        $correctionsAValider = $this->pointageRows(
            $this->queryPointages($mois, ['CORRIGE_CIP'], $agenceId, $entrepriseId, $sourceFinancementId, aafNonTraite: true, agencesAutorisees: $agencesAutorisees)
        );

        $attentePaiement = $this->droitRows(
            $this->queryDroitsPaiement($mois, $agenceId, $entrepriseId, $sourceFinancementId, $agencesAutorisees)
        );

        return Inertia::render($component, [
            'attenteValidation' => $attenteValidation,
            'paiementsAjournes' => $paiementsAjournes,
            'correctionsAValider' => $correctionsAValider,
            'attentePaiement' => $attentePaiement,
            'statistiques' => [
                'validation' => $attenteValidation->count(),
                'ajournes' => $paiementsAjournes->count(),
                'corrections' => $correctionsAValider->count(),
                'paiement' => $attentePaiement->count(),
                'total' => $attenteValidation->count() + $paiementsAjournes->count() + $correctionsAValider->count() + $attentePaiement->count(),
            ],
            'moisActuel' => $mois,
            'periode' => $periode ? [
                'id' => $periode->id,
                'code' => $periode->code,
                'nom' => $periode->code,
            ] : null,
            'sourceFinancement' => $sourceFinancement ? [
                'id' => $sourceFinancement->id,
                'code' => $sourceFinancement->code,
                'nom' => $sourceFinancement->nom,
            ] : null,
            'agences' => $agences,
            'entreprises' => $entreprises,
            'sourcesFinancement' => $sourcesFinancement,
            'filters' => [
                'mois' => $mois,
                'agence_id' => $agenceId ? (string) $agenceId : '',
                'entreprise_id' => $entrepriseId ? (string) $entrepriseId : '',
                'source_financement_id' => $sourceFinancementId ? (string) $sourceFinancementId : '',
            ],
            'focus' => $focus,
        ]);
    }

    private function queryPointages(
        string $mois,
        array $statuts,
        ?string $agenceId = null,
        ?string $entrepriseId = null,
        ?string $sourceFinancementId = null,
        bool $aafNonTraite = false,
        ?array $agencesAutorisees = null,
    ): Collection {
        return Pointage::with([
            'stage.beneficiaire',
            'stage.entreprise',
            'stage.agence',
            'stage.sourceFinancement',
            'periode',
            'versionCourante',
        ])
            ->whereIn('statut', $statuts)
            ->whereHas('periode', function ($query) use ($mois) {
                $query->where('code', $mois);
            })
            ->when($agenceId, function ($query) use ($agenceId) {
                $query->whereHas('stage', function ($stageQuery) use ($agenceId) {
                    $stageQuery->where('agence_id', $agenceId);
                });
            })
            ->when($agencesAutorisees !== null, function ($query) use ($agencesAutorisees) {
                $query->whereHas('stage', function ($stageQuery) use ($agencesAutorisees) {
                    $stageQuery->whereIn('agence_id', $agencesAutorisees);
                });
            })
            ->when($entrepriseId, function ($query) use ($entrepriseId) {
                $query->whereHas('stage', function ($stageQuery) use ($entrepriseId) {
                    $stageQuery->where('entreprise_id', $entrepriseId);
                });
            })
            ->when($sourceFinancementId, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            })
            ->when($aafNonTraite, function ($query) {
                $query->whereDoesntHave('decisions', function ($decisionQuery) {
                    $decisionQuery->where('decision', 'VALIDE_AAF');
                })->whereDoesntHave('droitsPaiement', function ($droitQuery) {
                    $droitQuery->whereNull('annule_le');
                });
            })
            ->orderByDesc('id')
            ->get();
    }

    private function queryDroitsPaiement(
        string $mois,
        ?string $agenceId = null,
        ?string $entrepriseId = null,
        ?string $sourceFinancementId = null,
        ?array $agencesAutorisees = null,
    ): Collection {
        return DroitPaiement::with([
            'stage.beneficiaire',
            'stage.entreprise',
            'stage.agence',
            'stage.sourceFinancement',
            'periode',
            'sourceFinancement',
            'paiements',
        ])
            ->where('statut', 'OUVERT')
            ->whereHas('periode', function ($query) use ($mois) {
                $query->where('code', $mois);
            })
            ->when($agenceId, function ($query) use ($agenceId) {
                $query->whereHas('stage', function ($stageQuery) use ($agenceId) {
                    $stageQuery->where('agence_id', $agenceId);
                });
            })
            ->when($agencesAutorisees !== null, function ($query) use ($agencesAutorisees) {
                $query->whereHas('stage', function ($stageQuery) use ($agencesAutorisees) {
                    $stageQuery->whereIn('agence_id', $agencesAutorisees);
                });
            })
            ->when($entrepriseId, function ($query) use ($entrepriseId) {
                $query->whereHas('stage', function ($stageQuery) use ($entrepriseId) {
                    $stageQuery->where('entreprise_id', $entrepriseId);
                });
            })
            ->when($sourceFinancementId, function ($query) use ($sourceFinancementId) {
                $query->whereHas('stage', function ($stageQuery) use ($sourceFinancementId) {
                    $stageQuery->where('source_financement_id', $sourceFinancementId);
                });
            })
            ->whereDoesntHave('paiements')
            ->orderByDesc('id')
            ->get();
    }

    private function agencesAutorisees(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || $user->hasRole('administrateur')) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()->pluck('agences.id')->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    private function assertPointageDansLePerimetre(Pointage $pointage): void
    {
        $agencesAutorisees = $this->agencesAutorisees(request());

        if ($agencesAutorisees !== null && ! in_array($pointage->stage?->agence_id, $agencesAutorisees, true)) {
            abort(403, "Ce pointage ne relève pas de votre périmètre d'agence.");
        }
    }

    private function assertDroitDansLePerimetre(DroitPaiement $droitPaiement): void
    {
        $agencesAutorisees = $this->agencesAutorisees(request());

        if ($agencesAutorisees !== null && ! in_array($droitPaiement->stage?->agence_id, $agencesAutorisees, true)) {
            abort(403, "Ce droit de paiement ne relève pas de votre périmètre d'agence.");
        }
    }

    private function assertDroitPejedec(DroitPaiement $droitPaiement): void
    {
        $source = $droitPaiement->stage?->sourceFinancement;

        if ($source?->code !== 'PEJEDEC' && (int) $source?->ancien_id !== 5) {
            abort(403, "Ce droit de paiement n'appartient pas au financement PEJEDEC.");
        }
    }

    private function pointageRows(Collection $pointages): Collection
    {
        return $pointages->map(function (Pointage $pointage) {
            $stage = $pointage->stage;

            return [
                'id' => $pointage->id,
                'numero' => 'PTG-'.str_pad((string) $pointage->id, 5, '0', STR_PAD_LEFT),
                'stage' => [
                    'id' => $stage?->id,
                    'beneficiaire' => [
                        'nom' => $stage?->beneficiaire?->nom ?? 'Inconnu',
                        'prenoms' => $stage?->beneficiaire?->prenoms ?? '',
                        'matricule' => $stage?->beneficiaire?->numero_aej ?? '',
                    ],
                    'entreprise' => [
                        'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
                    ],
                    'agence' => [
                        'nom' => $stage?->agence?->nom ?? '-',
                    ],
                    'sourceFinancement' => [
                        'id' => $stage?->sourceFinancement?->id,
                        'code' => $stage?->sourceFinancement?->code ?? 'PEJEDEC',
                        'nom' => $stage?->sourceFinancement?->nom ?? 'PEJEDEC',
                    ],
                ],
                'periode' => [
                    'id' => $pointage->periode?->id,
                    'code' => $pointage->periode?->code ?? $pointage->periode?->nom,
                    'nom' => $pointage->periode?->code,
                ],
                'statut' => $pointage->statut,
                'jours_presents' => $pointage->versionCourante?->jours_presents,
                'jours_absents' => $pointage->versionCourante?->jours_absents,
                'observation' => $pointage->versionCourante?->observation,
                'date_creation' => $pointage->created_at?->format('d/m/Y'),
                'date_soumission' => $pointage->updated_at?->format('d/m/Y'),
            ];
        })->values();
    }

    private function droitRows(Collection $droits): Collection
    {
        return $droits->map(function (DroitPaiement $droitPaiement) {
            $stage = $droitPaiement->stage;

            return [
                'id' => $droitPaiement->id,
                'numero' => 'PAY-'.str_pad((string) $droitPaiement->id, 5, '0', STR_PAD_LEFT),
                'stage' => [
                    'id' => $stage?->id,
                    'beneficiaire' => [
                        'nom' => $stage?->beneficiaire?->nom ?? 'Inconnu',
                        'prenoms' => $stage?->beneficiaire?->prenoms ?? '',
                        'matricule' => $stage?->beneficiaire?->numero_aej ?? '',
                    ],
                    'entreprise' => [
                        'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
                    ],
                    'agence' => [
                        'nom' => $stage?->agence?->nom ?? '-',
                    ],
                    'sourceFinancement' => [
                        'id' => $stage?->sourceFinancement?->id,
                        'code' => $stage?->sourceFinancement?->code ?? 'PEJEDEC',
                        'nom' => $stage?->sourceFinancement?->nom ?? 'PEJEDEC',
                    ],
                ],
                'periode' => [
                    'id' => $droitPaiement->periode?->id,
                    'code' => $droitPaiement->periode?->code ?? $droitPaiement->periode?->nom,
                    'nom' => $droitPaiement->periode?->code,
                ],
                'montant' => $droitPaiement->montant,
                'statut' => $droitPaiement->statut,
                'paiements_count' => $droitPaiement->paiements->count(),
                'date_creation' => $droitPaiement->created_at?->format('d/m/Y'),
                'date_mise_a_jour' => $droitPaiement->updated_at?->format('d/m/Y'),
            ];
        })->values();
    }
}
