<?php

namespace App\Http\Controllers\Desse;

use App\Domain\Supervision\Services\VisaDesseService;
use App\Enums\VisaDesseEnum;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visa DESSE des dossiers validés par le chef d'agence.
 *
 * Portage des écrans legacy `Validation_Stagiaire_Desse`,
 * `Liste_Stagiaires_Rejetes_Desse` et `stagiaire_passe_etape_desse`.
 */
class VisaDesseController extends Controller
{
    private const ONGLETS = ['attente', 'rejetes', 'vises'];

    private const FILTRES = [
        'agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'recherche',
    ];

    public function __construct(private VisaDesseService $visas) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only(self::FILTRES);

        $onglet = $request->string('onglet')->toString();
        $onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'attente';

        $query = match ($onglet) {
            'rejetes' => $this->visas->rejetesQuery($filtres),
            'vises' => $this->visas->visesQuery($filtres),
            default => $this->visas->attenteQuery($filtres),
        };

        $stages = $query->paginate(25)->withQueryString();
        $stages->through(fn (Stage $stage) => $this->visas->formatLigne($stage));

        return Inertia::render('Desse/Visas/Index', [
            'onglet' => $onglet,
            'stages' => $stages,
            'compteurs' => $this->visas->compteurs($filtres),
            'filters' => $filtres,
            'visaLabels' => VisaDesseEnum::labels(),
            'agences' => Agence::cachedPluck('nom'),
            'entreprises' => Entreprise::cached()
                ->sortBy('raison_sociale')
                ->pluck('raison_sociale', 'id')
                ->all(),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
        ]);
    }

    /**
     * Accorde le visa DESSE.
     */
    public function viser(int $id): RedirectResponse
    {
        $stage = Stage::with('beneficiaire')->findOrFail($id);

        if ($stage->visa_desse !== VisaDesseEnum::EN_ATTENTE) {
            return back()->with('error', "Ce dossier n'attend pas de visa.");
        }

        $this->visas->viser($stage, Auth::id());

        return back()->with('success', $this->nom($stage).' : visa accordé.');
    }

    /**
     * Refuse le visa DESSE, motif obligatoire.
     */
    public function rejeter(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $stage = Stage::with('beneficiaire')->findOrFail($id);

        if ($stage->visa_desse !== VisaDesseEnum::EN_ATTENTE) {
            return back()->with('error', "Ce dossier n'attend pas de visa.");
        }

        $this->visas->rejeter($stage, $donnees['motif'], Auth::id());

        return back()->with('success', $this->nom($stage).' : visa refusé.');
    }

    /**
     * Remet un dossier rejeté en attente de visa, une fois le CIP passé dessus.
     */
    public function remettreEnAttente(int $id): RedirectResponse
    {
        $stage = Stage::with('beneficiaire')->findOrFail($id);

        if ($stage->visa_desse !== VisaDesseEnum::REJETE) {
            return back()->with('error', "Ce dossier n'a pas été rejeté.");
        }

        $this->visas->remettreEnAttente($stage);

        return back()->with('success', $this->nom($stage).' : dossier remis en attente de visa.');
    }

    private function nom(Stage $stage): string
    {
        $beneficiaire = $stage->beneficiaire;

        return trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')) ?: 'Stagiaire';
    }
}
