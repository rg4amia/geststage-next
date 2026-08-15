<?php

namespace App\Http\Controllers\Pejedec;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Pointage;
use App\Models\Payment\DroitPaiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class AafController extends Controller
{
    private const PEJEDEC_SOURCE_FINANCEMENT_ID = 3;

    public function index(Request $request)
    {
        return $this->renderDashboard($request, 'validation');
    }

    public function attenteValidation(Request $request)
    {
        return $this->renderDashboard($request, 'validation');
    }

    public function paiementsAjournes(Request $request)
    {
        return $this->renderDashboard($request, 'ajournes');
    }

    public function correctionsAValider(Request $request)
    {
        return $this->renderDashboard($request, 'corrections');
    }

    public function attentePaiement(Request $request)
    {
        return $this->renderDashboard($request, 'paiement');
    }

    private function renderDashboard(Request $request, string $focus)
    {
        $mois = $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::where('code', $mois)->first();
        $sourceFinancement = SourceFinancement::find(self::PEJEDEC_SOURCE_FINANCEMENT_ID);

        $attenteValidation = $this->pointageRows(
            $this->queryPointages($mois, ['SOUMIS'])
        );

        $paiementsAjournes = $this->pointageRows(
            $this->queryPointages($mois, ['AJOURNE_DMG', 'AJOURNE_CA'])
        );

        $correctionsAValider = $this->pointageRows(
            $this->queryPointages($mois, ['CORRIGE_CIP'])
        );

        $attentePaiement = $this->droitRows(
            $this->queryDroitsPaiement($mois)
        );

        return Inertia::render('Pejedec/Aaf/Index', [
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
                'nom' => $periode->nom,
            ] : null,
            'sourceFinancement' => $sourceFinancement ? [
                'id' => $sourceFinancement->id,
                'code' => $sourceFinancement->code,
                'nom' => $sourceFinancement->nom,
            ] : null,
            'focus' => $focus,
        ]);
    }

    private function queryPointages(string $mois, array $statuts): Collection
    {
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
            ->whereHas('stage', function ($query) {
                $query->where('source_financement_id', self::PEJEDEC_SOURCE_FINANCEMENT_ID);
            })
            ->orderByDesc('id')
            ->get();
    }

    private function queryDroitsPaiement(string $mois): Collection
    {
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
            ->whereHas('stage', function ($query) {
                $query->where('source_financement_id', self::PEJEDEC_SOURCE_FINANCEMENT_ID);
            })
            ->whereDoesntHave('paiements')
            ->orderByDesc('id')
            ->get();
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
                    'nom' => $pointage->periode?->nom ?? $pointage->periode?->code,
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
                    'nom' => $droitPaiement->periode?->nom ?? $droitPaiement->periode?->code,
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
