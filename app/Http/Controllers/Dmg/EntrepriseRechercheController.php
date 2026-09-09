<?php

namespace App\Http\Controllers\Dmg;

use App\Http\Controllers\Controller;
use App\Models\Company\Entreprise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche d'entreprises pour les listes déroulantes async (react-select).
 *
 * S'appuie sur Entreprise::cached() — le cache de référence invalidé par le trait
 * CachesReferenceData à chaque save/delete/restore — plutôt que sur un LIKE SQL :
 * la liste tient en mémoire, la recherche ne coûte aucune requête et un grand
 * référentiel n'alourdit pas la base à chaque frappe.
 */
class EntrepriseRechercheController extends Controller
{
    private const LIMITE = 50;

    public function __invoke(Request $request): JsonResponse
    {
        $terme = mb_strtolower(trim($request->query('q', '')));
        // Débounce côté client, mais garde-fou ici : en dessous de 2 caractères on ne
        // renvoie rien plutôt qu'un tiers du référentiel.
        if (mb_strlen($terme) < 2) {
            return response()->json(['data' => []]);
        }

        $resultats = Entreprise::cached()
            ->filter(fn (Entreprise $entreprise): bool => str_contains(
                mb_strtolower($entreprise->raison_sociale ?? ''),
                $terme,
            ))
            ->sortBy('raison_sociale', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(self::LIMITE)
            ->map(fn (Entreprise $entreprise): array => [
                'id' => $entreprise->getKey(),
                'raison_sociale' => $entreprise->raison_sociale,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $resultats]);
    }
}
