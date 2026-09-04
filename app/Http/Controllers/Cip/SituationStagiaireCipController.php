<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Attendance\Services\SituationStageService;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Corbeilles CIP « Situation du stagiaire » : abandons et suspensions.
 *
 * Portage des écrans legacy `cip/situation-stagiaire/{abandon,suspension}`.
 */
class SituationStagiaireCipController extends Controller
{
    public function __construct(private SituationStageService $situations) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only([
            'agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'recherche',
        ]);

        $onglet = $request->string('onglet')->toString() ?: 'suspension';

        $query = $onglet === 'abandon'
            ? $this->situations->abandonsQuery($filtres)
            : $this->situations->suspensionsQuery($filtres);

        $stages = $query->paginate(25)->withQueryString();
        $stages->through(fn (Stage $stage) => $this->situations->formatLigne($stage));

        $agenceIds = Auth::user()?->perimetresAgences()->pluck('agences.id')->all() ?: [];

        return Inertia::render('Cip/Situations/Index', [
            'onglet' => $onglet,
            'stages' => $stages,
            'compteurs' => $this->situations->compteurs($filtres),
            'filters' => $filtres,
            'agences' => Agence::cachedPluck('nom'),
            'entreprises' => Entreprise::cached()
                ->when($agenceIds !== [], fn ($c) => $c->whereIn('agence_id', $agenceIds))
                ->sortBy('raison_sociale')
                ->pluck('raison_sociale', 'id')
                ->all(),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
        ]);
    }

    /**
     * Réactive un stage suspendu et repousse sa date de fin des mois suspendus.
     */
    public function reactiver(int $id): RedirectResponse
    {
        $stage = Stage::with('beneficiaire')->findOrFail($id);

        if ($stage->situation_stage !== SituationStage::CODE_SUSPENSION) {
            return back()->with('error', "Ce stage n'est pas en suspension.");
        }

        $resultat = $this->situations->reactiverSuspension($stage, Auth::id());

        return back()->with(
            'success',
            "{$resultat['nom']} réactivé : {$resultat['mois_suspendus']} mois suspendu(s), "
            ."fin de stage repoussée au {$resultat['nouvelle_date_fin']}."
        );
    }
}
