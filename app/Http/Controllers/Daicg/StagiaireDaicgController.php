<?php

namespace App\Http\Controllers\Daicg;

use App\Domain\Supervision\Services\VisaRegionalService;
use App\Http\Controllers\Controller;
use App\Models\Reference\Agence;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vue globale DAICG des stagiaires (portage regroupé des écrans legacy
 * `daicg/stagiaire-valider-par-chef-agence` et `daicg/stagiaire-valider-par-desse`).
 *
 * Écran de consultation uniquement : aucune décision n'est prise ici. Les deux onglets
 * réutilisent les mêmes requêtes que `/agence-regionale/visas`
 * (`VisaRegionalService::validesArQuery()` / `visesQuery()`) plutôt que de dupliquer un
 * filtrage — la DAICG voit exactement les dossiers déjà validés par le chef d'agence ou
 * par la DESSE sur cet écran-là.
 */
class StagiaireDaicgController extends Controller
{
    private const FILTRES = [
        'agence_id',
        'entreprise_id',
        'source_financement_id',
        'type_stage_id',
        'situation_stage',
        'date_debut',
        'date_fin',
        'date_valid_ar_debut',
        'date_valid_ar_fin',
        'date_valid_desse_debut',
        'date_valid_desse_fin',
        'recherche',
    ];

    private const PAR_PAGE = 25;

    /** Onglets de l'écran → onglet correspondant de VisaRegionalService. */
    private const ONGLETS = [
        'valides_ca' => 'valides_ar',
        'valides_desse' => 'vises_desse',
    ];

    public function __construct(private readonly VisaRegionalService $visas) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only(self::FILTRES);
        $onglet = $this->onglet($request);

        $lignes = $this->visas->queryPourOnglet(self::ONGLETS[$onglet], $filtres)
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        $lignes->through(fn ($stage): array => $this->visas->formatLigne($stage));

        return Inertia::render('Daicg/Stagiaires/Index', [
            'onglet' => $onglet,
            'filters' => $filtres,
            'stages' => $lignes,
            'agences' => Agence::cachedPluck('nom'),
            'typesfinancements' => SourceFinancement::cachedPluck('nom'),
            'typestages' => TypeStage::cachedPluck('nom'),
            'situations' => SituationStage::cachedPluck('nom', 'code'),
        ]);
    }

    private function onglet(Request $request): string
    {
        $onglet = $request->string('onglet')->toString();

        return array_key_exists($onglet, self::ONGLETS) ? $onglet : 'valides_ca';
    }
}
