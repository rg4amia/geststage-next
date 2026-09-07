<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use App\Models\Audit\JournalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Journaux d'activité (legacy `ActivityLogController@index` / `export`).
 *
 * Le legacy s'appuyait sur `spatie/laravel-activitylog` ; la trace équivalente
 * est ici produite par le trait `Auditable` dans `journaux_audit`. Les filtres
 * du legacy — utilisateur, action, modèle, plage de dates — sont conservés.
 */
class JournalAuditController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('voir_journaux_audit'), 403);

        $journaux = $this->requeteFiltree($request)
            ->with('user:id,nom,email')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $journaux->getCollection()->transform(fn (JournalAudit $journal): array => [
            'id' => $journal->id,
            'action' => $journal->action,
            'modele' => class_basename($journal->modele_type),
            'modele_type' => $journal->modele_type,
            'modele_id' => $journal->modele_id,
            'utilisateur' => $journal->user?->only(['id', 'nom', 'email']),
            'adresse_ip' => $journal->adresse_ip,
            'anciennes_donnees' => $journal->anciennes_donnees,
            'nouvelles_donnees' => $journal->nouvelles_donnees,
            'created_at' => $journal->created_at?->toIso8601String(),
        ]);

        return Inertia::render('ParametreAides/Journaux/Index', [
            'journaux' => $journaux,
            'actions' => JournalAudit::query()->distinct()->orderBy('action')->pluck('action'),
            'modeles' => JournalAudit::query()->distinct()->orderBy('modele_type')->pluck('modele_type')
                ->map(fn (string $type): array => ['valeur' => $type, 'libelle' => class_basename($type)]),
            'utilisateurs' => User::query()
                ->whereIn('id', JournalAudit::query()->whereNotNull('user_id')->distinct()->select('user_id'))
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'filters' => $request->only(['search', 'action', 'modele_type', 'user_id', 'du', 'au']),
        ]);
    }

    /**
     * Export CSV en flux : le journal grossit vite, il n'est jamais chargé
     * intégralement en mémoire.
     */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('voir_journaux_audit'), 403);

        $requete = $this->requeteFiltree($request)->with('user:id,nom,email')->latest('id');

        return response()->streamDownload(function () use ($requete): void {
            $sortie = fopen('php://output', 'wb');

            fputcsv($sortie, ['Date', 'Utilisateur', 'Action', 'Modèle', 'Identifiant', 'Adresse IP']);

            $requete->chunkById(500, function ($journaux) use ($sortie): void {
                foreach ($journaux as $journal) {
                    fputcsv($sortie, [
                        $journal->created_at?->format('Y-m-d H:i:s'),
                        $journal->user?->nom ?? 'Système',
                        $journal->action,
                        class_basename($journal->modele_type),
                        $journal->modele_id,
                        $journal->adresse_ip,
                    ]);
                }
            });

            fclose($sortie);
        }, 'journaux-activite-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<JournalAudit>
     */
    private function requeteFiltree(Request $request)
    {
        return JournalAudit::query()
            ->when($request->string('action')->toString(), fn ($query, string $action) => $query->where('action', $action))
            ->when($request->string('modele_type')->toString(), fn ($query, string $type) => $query->where('modele_type', $type))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('du'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('du')))
            ->when($request->filled('au'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('au')))
            ->when($request->string('search')->toString(), function ($query, string $recherche): void {
                // Recherche sur le modèle ciblé : nom court saisi par l'utilisateur
                // (« Agence »), identifiant, ou nom de classe complet.
                $query->where(function ($sousRequete) use ($recherche): void {
                    $sousRequete->where('modele_type', 'ilike', "%{$recherche}%")
                        ->orWhere('action', 'ilike', "%{$recherche}%");

                    if (ctype_digit(trim($recherche))) {
                        $sousRequete->orWhere('modele_id', (int) $recherche);
                    }
                });
            });
    }
}
