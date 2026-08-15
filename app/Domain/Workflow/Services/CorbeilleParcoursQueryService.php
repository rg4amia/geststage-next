<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use Illuminate\Support\Collection;

class CorbeilleParcoursQueryService
{
    public function instancesFor(CorbeilleEnum $corbeille): Collection
    {
        $instances = TacheParcours::query()
            ->with([
                'instance.stage.beneficiaire',
                'instance.stage.entreprise',
                'instance.stage.agence',
                'instance.etapeCourante',
            ])
            ->where('code_corbeille', $corbeille->value)
            ->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])
            ->orderByDesc('priorite')
            ->orderBy('ouverte_le')
            ->get()
            ->pluck('instance')
            ->filter();

        $instanceIds = $instances->pluck('id')->all();

        $fallback = InstanceParcours::query()
            ->with(['stage.beneficiaire', 'stage.entreprise', 'stage.agence', 'etapeCourante'])
            ->where('corbeille_actuelle', $corbeille->value)
            ->whereNull('terminee_le')
            ->when($instanceIds !== [], fn ($query) => $query->whereNotIn('id', $instanceIds))
            ->orderBy('created_at')
            ->get();

        return $instances->merge($fallback)->values();
    }

    public function instanceRows(CorbeilleEnum $corbeille, string $statut): Collection
    {
        return $this->instancesFor($corbeille)
            ->map(fn (InstanceParcours $instance) => $this->formatInstanceRow($instance, $statut))
            ->values();
    }

    public function paiementRows(Collection $paiements): Collection
    {
        return $paiements
            ->map(function (Paiement $paiement) {
                $stage = $paiement->droitPaiement?->stage;
                $beneficiaire = $stage?->beneficiaire;

                return [
                    'id' => $paiement->id,
                    'numero' => 'PAY-'.str_pad((string) $paiement->id, 5, '0', STR_PAD_LEFT),
                    'beneficiaire' => [
                        'nom' => $beneficiaire?->nom ?? 'Inconnu',
                        'prenoms' => $beneficiaire?->prenoms ?? '',
                        'matricule' => $beneficiaire?->numero_aej ?? '',
                    ],
                    'entreprise' => [
                        'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
                    ],
                    'agence' => [
                        'nom' => $stage?->agence?->nom ?? '-',
                    ],
                    'montant' => $paiement->montant,
                    'statut' => $paiement->statut,
                    'date_creation' => $paiement->created_at?->format('d/m/Y'),
                ];
            })
            ->values();
    }

    public function dossierRows(Collection $dossiers, string $statut): Collection
    {
        return $dossiers
            ->map(fn (DossierPaiement $dossier) => [
                'id' => $dossier->id,
                'numero' => $dossier->numero,
                'numero_dossier' => $dossier->numero,
                'agence' => [
                    'nom' => $dossier->agence?->nom ?? '-',
                ],
                'source_financement' => [
                    'libelle' => $dossier->sourceFinancement?->nom ?? '-',
                ],
                'nombre_stagiaires' => $dossier->paiements_count ?? $dossier->paiements->count(),
                'montant' => $dossier->montant_total,
                'montant_total' => $dossier->montant_total,
                'date_creation' => $dossier->created_at?->format('d/m/Y'),
                'date_transmission' => $dossier->updated_at?->format('d/m/Y'),
                'date_validation' => $dossier->updated_at?->format('d/m/Y'),
                'date_ajournement' => $dossier->updated_at?->format('d/m/Y'),
                'motif_ajournement' => $statut,
                'motif_rejet' => $statut,
                'statut' => $statut,
            ])
            ->values();
    }

    private function formatInstanceRow(InstanceParcours $instance, string $statut): array
    {
        $stage = $instance->stage;
        $beneficiaire = $stage?->beneficiaire;
        $numero = 'DOS-'.str_pad((string) $instance->id, 5, '0', STR_PAD_LEFT);

        return [
            'id' => $instance->id,
            'numero' => $numero,
            'numero_dossier' => $numero,
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
            ],
            'entreprise' => [
                'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
            ],
            'agence' => [
                'nom' => $stage?->agence?->nom ?? '-',
            ],
            'nombre_stagiaires' => 1,
            'montant' => 0,
            'montant_total' => 0,
            'motif_ajournement' => $statut,
            'motif_rejet' => $statut,
            'date_creation' => $instance->created_at?->format('d/m/Y'),
            'date_ajournement' => $instance->updated_at?->format('d/m/Y'),
            'date_rejet' => $instance->updated_at?->format('d/m/Y'),
            'statut' => $statut,
        ];
    }
}
