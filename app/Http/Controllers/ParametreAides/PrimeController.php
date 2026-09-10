<?php

namespace App\Http\Controllers\ParametreAides;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeCalculationException;
use App\Domain\Payment\Services\Prime\PrimeCalculatorService;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use App\Http\Controllers\Controller;
use App\Models\Internship\Stage;
use App\Models\Payment\PrimeConfigurationOverride;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Barème des primes (Paramètre & Aides).
 *
 * L'écran expose la grille effectivement appliquée par le moteur de calcul :
 * montant plein, prorata des mois de bord par plage de jour de démarrage, et
 * dates d'entrée en vigueur des grilles Budget AEJ. Les règles restent portées
 * par le code (`PrimeCalculatorService`) ; seules leurs valeurs sont
 * paramétrables ici.
 *
 * @see PrimeCalculatorService
 */
class PrimeController extends Controller
{
    public function __construct(
        private PrimeConfigurationService $configuration,
        private PrimeCalculatorService $calculator,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('voir_parametres_systeme'), 403);

        $override = PrimeConfigurationOverride::query()->with('modifiePar:id,nom')->find(1);

        return Inertia::render('ParametreAides/Primes/Index', [
            'configuration' => $this->configuration->all(),
            'defauts' => $this->configuration->defaults(),
            'strategies' => $this->calculator->catalogue(),
            'personnalise' => $override !== null,
            'derniereModification' => $override ? [
                'le' => $override->updated_at?->toIso8601String(),
                'par' => $override->modifiePar?->nom,
            ] : null,
            'peutGerer' => $request->user()->can('gerer_parametres_systeme'),
        ]);
    }

    /**
     * Enregistre le barème. La validation métier (sections obligatoires, format
     * des dates, montants positifs) est portée par le service de configuration,
     * qui est aussi le seul point de lecture du barème.
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('gerer_parametres_systeme'), 403);

        $donnees = $request->validate([
            'configuration' => ['required', 'array'],
        ]);

        $this->configuration->save($donnees['configuration'], Auth::id());

        return back()->with('success', 'Barème des primes enregistré.');
    }

    /**
     * Rétablit les valeurs livrées avec le code.
     */
    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('gerer_parametres_systeme'), 403);

        $this->configuration->reset(Auth::id());

        return back()->with('success', 'Barème des primes réinitialisé.');
    }

    /**
     * Simulateur : rejoue le barème sur une situation saisie à la main, sans
     * rien écrire. Permet de vérifier une grille avant de l'enregistrer.
     *
     * Réponse JSON et non redirection Inertia : l'écran interroge le barème
     * plusieurs fois de suite, sans recharger la page ni toucher au formulaire
     * d'édition en cours.
     */
    /**
     * Recherche de stagiaires pour préremplir le simulateur : évite d'avoir à
     * ressaisir à la main des dates et des identifiants de référentiel qu'on
     * peut simplement relire sur un dossier réel.
     */
    public function rechercherStagiaires(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('voir_parametres_systeme'), 403);

        $terme = trim((string) $request->query('q', ''));

        if (mb_strlen($terme) < 2) {
            return response()->json(['data' => []]);
        }

        $stages = Stage::query()
            ->with(['beneficiaire', 'entreprise.typeStructure', 'typeStage', 'sourceFinancement'])
            ->whereHas('beneficiaire', function ($requete) use ($terme): void {
                $requete->where('numero_aej', 'like', "%{$terme}%")
                    ->orWhere('nom', 'like', "%{$terme}%")
                    ->orWhere('prenoms', 'like', "%{$terme}%")
                    ->orWhereRaw("nom || ' ' || prenoms like ?", ["%{$terme}%"]);
            })
            ->latest('date_debut')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $stages->map(fn (Stage $stage): array => [
                'id' => $stage->id,
                'label' => trim(sprintf(
                    '%s %s — %s (%s)',
                    $stage->beneficiaire?->nom,
                    $stage->beneficiaire?->prenoms,
                    $stage->beneficiaire?->numero_aej ?? 'sans n° AEJ',
                    $stage->entreprise?->raison_sociale ?? 'entreprise inconnue',
                )),
                'type_stage_legacy_id' => $stage->typeStage?->ancien_id,
                'source_financement_legacy_id' => $stage->sourceFinancement?->ancien_id,
                'type_structure_legacy_id' => $stage->entreprise?->typeStructure?->ancien_id,
                'date_debut' => $stage->date_debut?->format('Y-m-d'),
                'date_fin' => $stage->date_fin_prevue?->format('Y-m-d'),
                'nom_entreprise' => $stage->entreprise?->raison_sociale,
            ])->values(),
        ]);
    }

    public function simuler(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('voir_parametres_systeme'), 403);

        $donnees = $request->validate([
            'type_stage_legacy_id' => ['required', 'integer', 'in:1,2'],
            'source_financement_legacy_id' => ['required', 'integer', 'in:1,2,3,4,5'],
            'type_structure_legacy_id' => ['nullable', 'integer', 'in:1,2,3'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'mois' => ['required', 'date_format:Y-m'],
            'nom_entreprise' => ['nullable', 'string', 'max:255'],
        ]);

        $dateDebut = Carbon::parse($donnees['date_debut']);
        $dateFin = Carbon::parse($donnees['date_fin']);

        $contexte = new ContexteCalculPrime(
            stageId: null,
            typeStageLegacyId: $donnees['type_stage_legacy_id'],
            sourceFinancementLegacyId: $donnees['source_financement_legacy_id'],
            typeStructureLegacyId: $donnees['type_structure_legacy_id'] ?? null,
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            dureeMois: ContexteCalculPrime::dureeEnMois($dateDebut, $dateFin),
            nomEntreprise: $donnees['nom_entreprise'] ?? null,
        );

        try {
            $montant = $this->calculator->calculer($contexte, $donnees['mois']);
        } catch (PrimeCalculationException $e) {
            return response()->json(['erreur' => $e->getMessage()], 422);
        }

        return response()->json([
            'montant' => $montant,
            'duree_mois' => $contexte->dureeMois,
            'mois' => $donnees['mois'],
            'strategie' => $this->calculator->libelleStrategie($contexte),
        ]);
    }
}
