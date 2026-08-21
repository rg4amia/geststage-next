<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Workflow\InstanceParcours;
use App\Models\Workflow\TacheParcours;
use Carbon\Carbon;
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
            ->map(fn (Paiement $paiement) => $this->formatPaiementRow($paiement, $paiement->statut))
            ->values();
    }

    public function paiementsFor(CorbeilleEnum $corbeille): Collection
    {
        return Paiement::query()
            ->with(['droitPaiement.stage.beneficiaire', 'droitPaiement.stage.entreprise', 'droitPaiement.stage.agence'])
            ->where('corbeille_actuelle', $corbeille->value)
            ->orderBy('created_at')
            ->get();
    }

    public function paiementRowsFor(CorbeilleEnum $corbeille, string $statut): Collection
    {
        return $this->paiementsFor($corbeille)
            ->map(fn (Paiement $paiement) => $this->formatPaiementRow($paiement, $statut))
            ->values();
    }

    private function formatPaiementRow(Paiement $paiement, string $statut): array
    {
        $stage = $paiement->droitPaiement?->stage;
        $beneficiaire = $stage?->beneficiaire;
        $jourDebut = $stage?->date_debut?->day;
        $cohorte = match (true) {
            $jourDebut >= 1 && $jourDebut <= 5 => 1,
            $jourDebut === 10 => 2,
            $jourDebut === 20 => 3,
            default => 0,
        };

        return [
            'id' => $paiement->id,
            'numero' => 'PAY-'.str_pad((string) $paiement->id, 5, '0', STR_PAD_LEFT),
            'beneficiaire' => [
                'nom' => $beneficiaire?->nom ?? 'Inconnu',
                'prenoms' => $beneficiaire?->prenoms ?? '',
                'matricule' => $beneficiaire?->numero_aej ?? '',
                'date_naissance' => $beneficiaire?->date_naissance ? Carbon::parse($beneficiaire->date_naissance)->format('d/m/Y') : '-',
                'tresor_pay' => $beneficiaire?->numero_tresor_pay ?? '-',
            ],
            'entreprise' => [
                'raison_sociale' => $stage?->entreprise?->raison_sociale ?? '-',
            ],
            'agence' => [
                'nom' => $stage?->agence?->nom ?? '-',
            ],
            'stage' => [
                'source_financement' => $stage?->sourceFinancement?->nom ?? '-',
                'type_stage' => $stage?->typeStage?->nom ?? '-',
                'date_validation' => '-',
                'date_debut' => $stage?->date_debut ? Carbon::parse($stage->date_debut)->format('d/m/Y') : '-',
                'date_fin' => $stage?->date_fin_prevue ? Carbon::parse($stage->date_fin_prevue)->format('d/m/Y') : '-',
            ],
            'montant' => $paiement->montant,
            'statut' => $statut,
            'date_creation' => $paiement->created_at?->format('d/m/Y'),
            'piece_jointe' => $paiement->statut_dossier_physique,
            'cohorte' => $cohorte,
        ];
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
                'statut_code' => $dossier->statut,
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
