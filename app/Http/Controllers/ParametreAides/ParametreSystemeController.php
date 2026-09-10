<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParametreAides\StoreReglePrelevementRequest;
use App\Http\Requests\ParametreAides\UpdateReglePrelevementRequest;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\ParametreSysteme;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres système (legacy `PaymentDeductionRuleController@index`,
 * GET/POST `/settings/systeme/general`).
 *
 * Deux blocs : les bascules applicatives clé/valeur et les règles de
 * prélèvement CMU datées. Toutes les écritures passent par des modèles
 * `Auditable`, donc sont tracées dans `journaux_audit`.
 */
class ParametreSystemeController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('voir_parametres_systeme'), 403);

        $regles = ReglePrelevement::query()
            ->with(['sourceFinancement:id,nom', 'typeStage:id,nom', 'creePar:id,nom'])
            ->orderByDesc('actif')
            ->orderByDesc('effet_du')
            ->orderBy('source_financement_id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ParametreAides/ParametresSysteme/Index', [
            'parametres' => ParametreSysteme::catalogue()->map(fn (ParametreSysteme $parametre): array => [
                'id' => $parametre->id,
                'cle' => $parametre->cle,
                'libelle' => $parametre->libelle,
                'description' => $parametre->description,
                'type' => $parametre->type,
                'valeur' => $parametre->valeur_typee,
            ]),
            'regles' => $regles,
            'sources' => SourceFinancement::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'typesStage' => TypeStage::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom']),
            'typesPrelevement' => [ReglePrelevement::TYPE_CMU],
            'typesPaiement' => [ReglePrelevement::PAIEMENT_DEMARRAGE, ReglePrelevement::PAIEMENT_PRESENCE],
            'peutGerer' => $request->user()->can('gerer_parametres_systeme'),
        ]);
    }

    /**
     * Mise à jour des bascules applicatives (legacy `SystemSettingController@update`).
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('gerer_parametres_systeme'), 403);

        $donnees = $request->validate([
            'parametres' => ['required', 'array', 'min:1'],
            'parametres.*.cle' => ['required', 'string', 'exists:parametres_systeme,cle'],
            'parametres.*.valeur' => ['present'],
        ]);

        foreach ($donnees['parametres'] as $saisie) {
            $parametre = ParametreSysteme::query()->where('cle', $saisie['cle'])->firstOrFail();

            $parametre->update([
                'valeur' => $parametre->normaliser($saisie['valeur']),
                'modifie_par' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Paramètres système enregistrés.');
    }

    public function storeRegle(StoreReglePrelevementRequest $request): RedirectResponse
    {
        ReglePrelevement::create($request->donneesPersistables() + [
            'cree_par' => Auth::id(),
            'modifie_par' => Auth::id(),
        ]);

        return back()->with('success', 'Règle de prélèvement créée.');
    }

    public function updateRegle(UpdateReglePrelevementRequest $request, ReglePrelevement $regle): RedirectResponse
    {
        $regle->update($request->donneesPersistables() + ['modifie_par' => Auth::id()]);

        return back()->with('success', 'Règle de prélèvement mise à jour.');
    }

    /**
     * Désactivation plutôt que suppression : les paiements déjà calculés
     * référencent la règle qui les a produits.
     */
    public function destroyRegle(Request $request, ReglePrelevement $regle): RedirectResponse
    {
        abort_unless($request->user()->can('gerer_parametres_systeme'), 403);

        $regle->update([
            'actif' => false,
            'modifie_par' => Auth::id(),
        ]);

        return back()->with('success', 'Règle de prélèvement désactivée.');
    }
}
