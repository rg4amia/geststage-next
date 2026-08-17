<?php

namespace App\Domain\Reporting\Services;

use App\Models\Attendance\Pointage;
use App\Models\Audit\JournalAudit;
use App\Models\Internship\Stage;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingDashboardService
{
    public function buildOverview(array $filters = []): array
    {
        $mois = (string) ($filters['mois'] ?? Carbon::now()->format('Y-m'));
        $periode = $this->resolvePeriode($mois);
        $sourceFinancementId = $this->normalizeNullableInteger($filters['source_financement_id'] ?? null);

        $sourcesFinancement = SourceFinancement::query()
            ->orderBy('nom')
            ->get(['id', 'code', 'nom'])
            ->map(fn (SourceFinancement $source) => [
                'id' => $source->id,
                'code' => $source->code,
                'nom' => $source->nom,
            ])
            ->values()
            ->all();

        $stageQuery = Stage::query();
        if ($sourceFinancementId !== null) {
            $stageQuery->where('source_financement_id', $sourceFinancementId);
        }

        $pointageQuery = Pointage::query();
        if ($periode !== null) {
            $pointageQuery->where('periode_id', $periode->id);
        }
        if ($sourceFinancementId !== null) {
            $pointageQuery->whereHas('stage', function ($query) use ($sourceFinancementId) {
                $query->where('source_financement_id', $sourceFinancementId);
            });
        }

        $droitQuery = DroitPaiement::query();
        if ($periode !== null) {
            $droitQuery->where('periode_id', $periode->id);
        }
        if ($sourceFinancementId !== null) {
            $droitQuery->where('source_financement_id', $sourceFinancementId);
        }

        $dossierQuery = DossierPaiement::query();
        if ($periode !== null) {
            $dossierQuery->where('periode_id', $periode->id);
        }
        if ($sourceFinancementId !== null) {
            $dossierQuery->where('source_financement_id', $sourceFinancementId);
        }

        $paiementQuery = Paiement::query();
        if ($periode !== null) {
            $paiementQuery->whereHas('droitPaiement', function ($query) use ($periode) {
                $query->where('periode_id', $periode->id);
            });
        }
        if ($sourceFinancementId !== null) {
            $paiementQuery->whereHas('droitPaiement.stage', function ($query) use ($sourceFinancementId) {
                $query->where('source_financement_id', $sourceFinancementId);
            });
        }

        $pointagesAttente = (clone $pointageQuery)->attenteValidationCA()->count();
        $pointagesValides = (clone $pointageQuery)->valide()->count();
        $pointagesAjournesCA = (clone $pointageQuery)->ajourneParCA()->count();
        $pointagesAjournesDMG = (clone $pointageQuery)->ajourneParDMG()->count();

        $dossiersTransmisAC = (clone $dossierQuery)->transmisAc()->count();
        $dossiersVisesAC = (clone $dossierQuery)->viseAc()->count();
        $dossiersAjournesDMG = (clone $dossierQuery)->ajourneDmg()->count();

        $droitsOuverts = (clone $droitQuery)->where('statut', 'OUVERT')->count();
        $montantDroitsOuverts = (clone $droitQuery)->where('statut', 'OUVERT')->sum('montant');

        $paiementsATraiter = (clone $paiementQuery)->aTraiter()->count();
        $montantPaiementsATraiter = (clone $paiementQuery)->aTraiter()->sum('montant');

        $journalActivite = JournalAudit::query()
            ->with(['user:id,name,email'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (JournalAudit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action,
                'modele' => class_basename($audit->modele_type),
                'modele_id' => $audit->modele_id,
                'utilisateur' => $audit->user?->name ?? 'Système',
                'email' => $audit->user?->email,
                'date' => $audit->created_at?->format('d/m/Y H:i'),
            ])
            ->values()
            ->all();

        $repartitionSourcesFinancement = DroitPaiement::query()
            ->select('source_financement_id', DB::raw('COUNT(*) as total_droits'), DB::raw('COALESCE(SUM(montant), 0) as montant_total'))
            ->with('sourceFinancement:id,code,nom')
            ->when($periode !== null, function ($query) use ($periode) {
                $query->where('periode_id', $periode->id);
            })
            ->groupBy('source_financement_id')
            ->orderByDesc('montant_total')
            ->get()
            ->map(fn (DroitPaiement $droit) => [
                'source' => [
                    'id' => $droit->sourceFinancement?->id,
                    'code' => $droit->sourceFinancement?->code,
                    'nom' => $droit->sourceFinancement?->nom,
                ],
                'total_droits' => (int) ($droit->total_droits ?? 0),
                'montant_total' => (string) ($droit->montant_total ?? '0'),
            ])
            ->values()
            ->all();

        $alertes = $this->buildAlertes(
            $pointagesAttente,
            $pointagesAjournesDMG,
            $dossiersAjournesDMG,
            $paiementsATraiter,
        );

        return [
            'moisActuel' => $mois,
            'periode' => $periode,
            'sourceFinancement' => $sourceFinancementId !== null
                ? SourceFinancement::query()->find($sourceFinancementId, ['id', 'code', 'nom'])
                : null,
            'sourcesFinancement' => $sourcesFinancement,
            'filters' => [
                'mois' => $mois,
                'source_financement_id' => $sourceFinancementId !== null ? (string) $sourceFinancementId : '',
            ],
            'statistiques' => [
                'stages_total' => $stageQuery->count(),
                'pointages_attente' => $pointagesAttente,
                'pointages_valides' => $pointagesValides,
                'pointages_ajournes_ca' => $pointagesAjournesCA,
                'pointages_ajournes_dmg' => $pointagesAjournesDMG,
                'dossiers_transmis_ac' => $dossiersTransmisAC,
                'dossiers_vises_ac' => $dossiersVisesAC,
                'dossiers_ajournes_dmg' => $dossiersAjournesDMG,
                'droits_ouverts' => $droitsOuverts,
                'montant_droits_ouverts' => (string) $montantDroitsOuverts,
                'paiements_a_traiter' => $paiementsATraiter,
                'montant_paiements_a_traiter' => (string) $montantPaiementsATraiter,
                'audits_recents' => count($journalActivite),
            ],
            'repartitionSourcesFinancement' => $repartitionSourcesFinancement,
            'journalActivite' => $journalActivite,
            'alertes' => $alertes,
        ];
    }

    public function exportRows(array $filters = []): array
    {
        $overview = $this->buildOverview($filters);

        return [
            ['Indicateur', 'Valeur'],
            ['Période', $overview['periode']?->code ?? $overview['moisActuel']],
            ['Stages totaux', (string) $overview['statistiques']['stages_total']],
            ['Pointages en attente', (string) $overview['statistiques']['pointages_attente']],
            ['Pointages validés', (string) $overview['statistiques']['pointages_valides']],
            ['Pointages ajournés CA', (string) $overview['statistiques']['pointages_ajournes_ca']],
            ['Pointages ajournés DMG', (string) $overview['statistiques']['pointages_ajournes_dmg']],
            ['Dossiers transmis AC', (string) $overview['statistiques']['dossiers_transmis_ac']],
            ['Dossiers visés AC', (string) $overview['statistiques']['dossiers_vises_ac']],
            ['Dossiers ajournés DMG', (string) $overview['statistiques']['dossiers_ajournes_dmg']],
            ['Droits ouverts', (string) $overview['statistiques']['droits_ouverts']],
            ['Montant droits ouverts', (string) $overview['statistiques']['montant_droits_ouverts']],
            ['Paiements à traiter', (string) $overview['statistiques']['paiements_a_traiter']],
            ['Montant paiements à traiter', (string) $overview['statistiques']['montant_paiements_a_traiter']],
            ['Alertes', (string) count($overview['alertes'])],
        ];
    }

    private function resolvePeriode(string $mois): ?Periode
    {
        $periode = Periode::query()->where('code', $mois)->first();

        return $periode ?? Periode::query()->orderByDesc('date_debut')->first();
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function buildAlertes(
        int $pointagesAttente,
        int $pointagesAjournesDMG,
        int $dossiersAjournesDMG,
        int $paiementsATraiter
    ): array {
        $alertes = [];

        if ($pointagesAttente > 0) {
            $alertes[] = [
                'niveau' => 'info',
                'titre' => 'Pointages en attente',
                'message' => sprintf('%d pointage(s) attendent une validation administrative.', $pointagesAttente),
            ];
        }

        if ($pointagesAjournesDMG > 0) {
            $alertes[] = [
                'niveau' => 'warning',
                'titre' => 'Pointages ajournés DMG',
                'message' => sprintf('%d pointage(s) sont en attente de reprise DMG.', $pointagesAjournesDMG),
            ];
        }

        if ($dossiersAjournesDMG > 0) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'Dossiers ajournés DMG',
                'message' => sprintf('%d dossier(s) paiement sont encore bloqués côté DMG.', $dossiersAjournesDMG),
            ];
        }

        if ($paiementsATraiter > 0) {
            $alertes[] = [
                'niveau' => 'success',
                'titre' => 'Paiements à traiter',
                'message' => sprintf('%d paiement(s) attendent l’ordonnancement.', $paiementsATraiter),
            ];
        }

        return $alertes;
    }
}
