<?php

namespace App\Domain\Workflow\Services;

use App\Models\Workflow\InstanceParcours;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cœur de requête unique de l'écran CIP « Mes Stagiaires » : la liste paginée, l'export
 * CSV synchrone et le job d'export en arrière-plan partagent exactement la même
 * construction de requête (périmètre d'agences, filtres, recherche libre), afin qu'un
 * export ne puisse jamais contenir une ligne invisible dans la liste.
 */
class ListeStagiairesCipService
{
    /**
     * Requête complète (eager loads + périmètre + filtres + recherche) d'une instance de
     * parcours de l'écran. `page` n'est pas un filtre : la pagination reste à l'appelant.
     *
     * @param  array<string, mixed>  $filtres
     * @param  array<int, int>|null  $agencesAutorisees  null = aucune restriction (vue nationale)
     */
    public function query(array $filtres, ?array $agencesAutorisees): Builder
    {
        $query = InstanceParcours::with([
            'stage.beneficiaire.typePaiement',
            'stage.entreprise.typeStructure',
            'stage.agence',
            'stage.sourceFinancement',
            'stage.typeStage',
            'stage.contrats',
            'stage.documents.typeDocument',
            'stage.documents.versions',
            'stage.pointages.periode',
            'stage.pointages.versionCourante',
            'etapeCourante',
        ]);

        // Toujours exiger un stage non supprimé logiquement : Stage a le trait SoftDeletes,
        // donc whereHas('stage') exclut déjà les dossiers "deleted_at" côté legacy.
        $query->whereHas('stage', function ($q) use ($agencesAutorisees) {
            if ($agencesAutorisees !== null) {
                $q->whereIn('agence_id', $agencesAutorisees);
            }
        });

        if (! empty($filtres['agence_id'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('agence_id', $filtres['agence_id']);
            });
        }
        if (! empty($filtres['entreprise_id'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('entreprise_id', $filtres['entreprise_id']);
            });
        }
        if (! empty($filtres['typesfinancement_id'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('source_financement_id', $filtres['typesfinancement_id']);
            });
        }
        if (! empty($filtres['typestage_id'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('type_stage_id', $filtres['typestage_id']);
            });
        }
        if (! empty($filtres['type_structure_id'])) {
            $query->whereHas('stage.entreprise', function ($q) use ($filtres) {
                $q->where('type_structure_id', $filtres['type_structure_id']);
            });
        }
        if (! empty($filtres['etape_id'])) {
            $query->where('etape_courante_id', $filtres['etape_id']);
        }
        if (! empty($filtres['situationstage_id'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('situation_stage', $filtres['situationstage_id']);
            });
        }
        if (! empty($filtres['date_debut'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('date_debut', '>=', $filtres['date_debut']);
            });
        }
        if (! empty($filtres['date_fin'])) {
            $query->whereHas('stage', function ($q) use ($filtres) {
                $q->where('date_fin_prevue', '<=', $filtres['date_fin']);
            });
        }
        if (! empty($filtres['search'])) {
            $this->appliquerRecherche($query, $filtres['search']);
        }

        return $query;
    }

    /**
     * Agences sur lesquelles l'utilisateur est habilité, ou `null` s'il n'a aucun périmètre
     * défini — auquel cas aucune restriction n'est appliquée, comme dans `SituationStageService`
     * et `VisaRegionalService`. L'administrateur a toujours une vue nationale, même s'il
     * possède par ailleurs un périmètre.
     *
     * @return array<int, int>|null
     */
    public function agencesAutorisees(?User $user = null): ?array
    {
        $user ??= Auth::user();

        if (! $user) {
            return null;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('administrateur')) {
            return null;
        }

        $agenceIds = $user->perimetresAgences()->pluck('agences.id')->all();

        return $agenceIds === [] ? null : $agenceIds;
    }

    /**
     * Recherche libre couvrant, comme le legacy DataTables : le bénéficiaire (nom, prénoms,
     * numéro AEJ), le numéro et l'état du contrat, l'étape courante du workflow et l'état
     * des pointages.
     */
    public function appliquerRecherche(Builder $query, string $search): void
    {
        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%'.addcslashes($search, '%_').'%';

        $query->where(function ($q) use ($operator, $term) {
            $q->whereHas('stage.beneficiaire', function ($bq) use ($operator, $term) {
                $bq->where('nom', $operator, $term)
                    ->orWhere('prenoms', $operator, $term)
                    ->orWhere('numero_aej', $operator, $term);
            })
                ->orWhereHas('stage.contrats', function ($cq) use ($operator, $term) {
                    $cq->where('numero', $operator, $term)
                        ->orWhere('statut', $operator, $term);
                })
                ->orWhereHas('etapeCourante', function ($eq) use ($operator, $term) {
                    $eq->where('nom', $operator, $term);
                })
                ->orWhereHas('stage.pointages', function ($pq) use ($operator, $term) {
                    $pq->where('statut', $operator, $term);
                });
        });
    }

    /**
     * En-têtes + lignes CSV de l'export « Mes Stagiaires » (mêmes colonnes que la liste).
     * Streaming par `cursor()` : un périmètre national entier ne charge jamais la mémoire.
     *
     * @return \Generator<int, array<int, string|null>>
     */
    public function lignesExport(Builder $query): \Generator
    {
        yield ['N° AEJ', 'Nom', 'Prénoms', 'Sexe', 'Date de naissance', 'Téléphone', 'Agence', 'Entreprise', 'Type de structure', 'Financement', 'Type de stage', 'Situation du stage', 'Début', 'Fin prévue', 'Type de paiement', 'N° Trésor Money', 'N° Wave', 'Contrat', 'Étape workflow', 'Enregistré le'];

        foreach ($query->cursor() as $instance) {
            $stage = $instance->stage;
            $beneficiaire = $stage?->beneficiaire;
            $contrat = $stage?->contrats->first();

            yield [
                $beneficiaire?->numero_aej,
                $beneficiaire?->nom,
                $beneficiaire?->prenoms,
                $beneficiaire?->sexe,
                $beneficiaire?->date_naissance?->format('d/m/Y'),
                $beneficiaire?->telephone_principal,
                $stage?->agence?->nom,
                $stage?->entreprise?->raison_sociale,
                $stage?->entreprise?->typeStructure?->nom,
                $stage?->sourceFinancement?->nom,
                $stage?->typeStage?->nom,
                $stage?->situation_stage,
                $stage?->date_debut?->format('d/m/Y'),
                $stage?->date_fin_prevue?->format('d/m/Y'),
                $stage?->beneficiaire?->typePaiement?->nom,
                $beneficiaire?->numero_tresor_money,
                $beneficiaire?->numero_wave,
                $contrat?->numero,
                $instance->corbeille_actuelle,
                $instance->created_at?->format('d/m/Y H:i'),
            ];
        }
    }
}
