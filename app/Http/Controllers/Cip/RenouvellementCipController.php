<?php

namespace App\Http\Controllers\Cip;

use App\Domain\Contract\Services\RenouvellementService;
use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use App\Models\Contract\AvenantContrat;
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
 * Corbeilles CIP du renouvellement de contrat (avenant).
 *
 * Portage des écrans legacy `renouvellement/stagiaire-atttente`,
 * `renouvellement/stagiaire-ajourne` et `renouvellement/anticiper/stagiaire-atttente`.
 */
class RenouvellementCipController extends Controller
{
    private const ONGLETS = ['attente', 'anticipe', 'ajourne'];

    public function __construct(private RenouvellementService $renouvellements) {}

    public function index(Request $request): Response
    {
        $filtres = $request->only([
            'agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'recherche',
        ]);

        $onglet = $request->string('onglet')->toString();
        $onglet = in_array($onglet, self::ONGLETS, true) ? $onglet : 'attente';

        $query = match ($onglet) {
            'anticipe' => $this->renouvellements->anticipeQuery($filtres),
            'ajourne' => $this->renouvellements->ajourneQuery($filtres),
            default => $this->renouvellements->attenteQuery($filtres),
        };

        $stages = $query->paginate(25)->withQueryString();
        $stages->through(fn (Stage $stage) => $this->renouvellements->formatLigne($stage));

        $agenceIds = Auth::user()?->perimetresAgences()->pluck('agences.id')->all() ?: [];

        return Inertia::render('Cip/Renouvellements/Index', [
            'onglet' => $onglet,
            'stages' => $stages,
            'compteurs' => $this->renouvellements->compteurs($filtres),
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
     * Propose le renouvellement d'un stage au chef d'agence.
     */
    public function renouveler(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'duree_mois' => ['required', 'integer', 'min:1', 'max:12'],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $stage = Stage::with('contrats')->findOrFail($id);

        try {
            $resultat = $this->renouvellements->renouveler(
                $stage,
                (int) $donnees['duree_mois'],
                $donnees['motif'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            "Avenant {$resultat['numero']} créé, fin de stage portée au {$resultat['nouvelle_date_fin']}. "
            .'En attente de validation du chef d\'agence.'
        );
    }

    /**
     * Renvoie au chef d'agence un renouvellement qu'il avait ajourné.
     */
    public function renvoyer(int $avenantId): RedirectResponse
    {
        $avenant = AvenantContrat::findOrFail($avenantId);

        if ($avenant->statut !== AvenantContrat::STATUT_AJOURNE) {
            return back()->with('error', "Ce renouvellement n'est pas ajourné.");
        }

        $this->renouvellements->renvoyerAuChefAgence($avenant);

        return back()->with('success', "Renouvellement {$avenant->numero} renvoyé au chef d'agence.");
    }
}
