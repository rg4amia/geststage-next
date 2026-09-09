<?php

namespace App\Domain\Reporting\Services;

use App\Enums\CorbeilleEnum;
use App\Enums\VisaDesseEnum;
use App\Models\Attendance\Pointage;
use App\Models\Audit\JournalAudit;
use App\Models\Contract\AvenantContrat;
use App\Models\Internship\Stage;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportingDashboardService
{
    private const SOURCE_VARIANTS = [
        'global' => null,
        'budget-aej' => 'BUDGET_AEJ',
        'paps-gouv' => 'PA_PS_GOUV',
        'pejedec' => 'PEJEDEC',
        'c2d' => 'C2D',
    ];

    private const PAYMENT_IN_PROGRESS = ['A_TRAITER', 'EN_DOSSIER', 'EN_OP', 'VALIDE_AC'];

    private const PAYMENT_REJECTED = ['AJOURNE_DMG', 'REJETE_AC', 'REJETE_AC_DEFINITIF', 'NON_PAYE'];

    private const PAYMENT_VALIDATED_DMG = ['EN_DOSSIER', 'EN_OP', 'VALIDE_AC', 'PAYE', 'NON_PAYE', 'REJETE_AC', 'REJETE_AC_DEFINITIF'];

    private const NATIONAL_ROLES = ['administrateur', 'desse', 'daicg', 'dmg', 'cb', 'agent_comptable', 'pejedec', 'aaf'];

    /** @param array<string, mixed> $filters */
    public function buildOverview(array $filters = [], ?User $user = null): array
    {
        $normalized = $this->normalizeFilters($filters);
        $agencyIds = $this->authorizedAgencyIds($user);
        $cacheKey = sprintf(
            'reporting.overview.%s.%s.%s.%s.%s',
            $normalized['mois'],
            $normalized['source_financement_id'] ?? 'all',
            $normalized['jour_reference'],
            $normalized['type_stage'],
            $agencyIds === null ? 'national' : sha1(implode(',', $agencyIds)),
        );
        $compute = fn (): array => $this->computeOverview($normalized, $agencyIds);

        return app()->environment('testing')
            ? $compute()
            : Cache::remember($cacheKey, now()->addMinutes(3), $compute);
    }

    /** @param array<string, mixed> $filters */
    public function normalizeFilters(array $filters): array
    {
        $mois = (string) ($filters['mois'] ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
            $mois = now()->format('Y-m');
        }

        $sourceId = $this->normalizeNullableInteger($filters['source_financement_id'] ?? null);
        $sourceVariant = strtolower(trim((string) ($filters['source'] ?? 'global')));
        if ($sourceId === null && array_key_exists($sourceVariant, self::SOURCE_VARIANTS)) {
            $code = self::SOURCE_VARIANTS[$sourceVariant];
            $sourceId = $code === null ? null : SourceFinancement::cached()->firstWhere('code', $code)?->id;
        }

        $month = Carbon::createFromFormat('!Y-m', $mois);

        return [
            'mois' => $mois,
            'source_financement_id' => $sourceId,
            'jour_reference' => $this->normalizeReferenceDay($filters['jour_reference'] ?? null, $month),
            'type_stage' => in_array(($filters['type_stage'] ?? null), ['qualification', 'validation'], true)
                ? (string) $filters['type_stage']
                : 'tous',
        ];
    }

    /** @param array<string, mixed> $filters @param array<int, int>|null $agencyIds */
    private function computeOverview(array $filters, ?array $agencyIds): array
    {
        $month = Carbon::createFromFormat('!Y-m', $filters['mois']);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $period = Periode::query()->where('code', $filters['mois'])->first();
        $sourceId = $filters['source_financement_id'];
        $sources = SourceFinancement::cached()->sortBy('nom')->values();
        $selectedSource = $sourceId === null ? null : $sources->firstWhere('id', $sourceId);

        $stages = $this->stageAggregate($start, $end, $sourceId, $agencyIds);
        $pointages = $this->pointageStatuses($period?->id, $sourceId, $agencyIds);
        $payments = $this->paymentStatuses($period?->id, $sourceId, $agencyIds);
        $dossiers = $this->dossierStatuses($period?->id, $sourceId, $agencyIds);
        $droits = $this->droitAggregate($period?->id, $sourceId, $agencyIds);
        $renewals = $this->renewalAggregate($start, $end, $sourceId, $agencyIds);
        $waitingPointages = $this->countWaitingPointages($period?->id, $start, $end, $sourceId, $agencyIds);
        $duplicates = $this->duplicateSummary($start, $end, $sourceId, $agencyIds);

        $statistics = [
            'stages_total' => (int) ($stages->stages_total ?? 0),
            'pointages_attente' => $this->statusCount($pointages, 'SOUMIS'),
            'pointages_valides' => $this->statusCount($pointages, 'VALIDE'),
            'pointages_ajournes_ca' => $this->statusCount($pointages, 'AJOURNE_CA'),
            // L'ajournement DMG est porté par le paiement, pas par le pointage source.
            'pointages_ajournes_dmg' => $this->statusCount($payments, 'AJOURNE_DMG'),
            'dossiers_transmis_ac' => $this->sumStatusCounts($dossiers, ['TRANSMIS_AC', 'EN_OP']),
            'dossiers_vises_ac' => $this->statusCount($dossiers, 'VISE_AC'),
            'dossiers_ajournes_dmg' => $this->statusCount($dossiers, 'AJOURNE_DMG'),
            'droits_ouverts' => (int) ($droits->total ?? 0),
            'montant_droits_ouverts' => (string) ($droits->montant ?? '0'),
            'paiements_a_traiter' => $this->statusCount($payments, 'A_TRAITER'),
            'montant_paiements_a_traiter' => $this->statusAmount($payments, 'A_TRAITER'),
            'audits_recents' => 0,
        ];

        $biValues = [
            'beneficiaires_saisis' => (int) ($stages->beneficiaires_saisis ?? 0),
            'valides_ar' => (int) ($stages->valides_ar ?? 0),
            'attente_car' => (int) ($stages->attente_car ?? 0),
            'bdd_desse' => (int) ($stages->bdd_desse ?? 0),
            'rejetes_desse' => (int) ($stages->rejetes_desse ?? 0),
            'doublons_desse' => $duplicates['non_traites'],
            'attente_pointage' => $waitingPointages,
            'pointes_agent' => $this->sumStatusCounts($pointages, ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA']),
            'pointage_attente_car' => $this->statusCount($pointages, 'SOUMIS'),
            'pointage_valide_car' => $this->statusCount($pointages, 'VALIDE'),
            'valides_dmg' => $this->sumStatusCounts($payments, self::PAYMENT_VALIDATED_DMG),
            'attente_dmg' => $this->statusCount($payments, 'A_TRAITER'),
            'rejetes_dmg' => $this->statusCount($payments, 'AJOURNE_DMG'),
            'payes_ac' => $this->statusCount($payments, 'PAYE'),
            'fins_contrat' => (int) ($stages->fins_contrat ?? 0),
            'rejetes_ac' => $this->sumStatusCounts($payments, ['REJETE_AC', 'REJETE_AC_DEFINITIF']),
            'captes_annee' => $this->countCapturedYearToDate($start, $end, $sourceId, $agencyIds),
            'attente_renouvellement' => $renewals['attente'],
        ];

        $journal = $this->recentAudit($start, $end, $sourceId, $agencyIds);
        $statistics['audits_recents'] = count($journal);
        $paymentSummary = $this->paymentSummary($payments);

        return [
            'moisActuel' => $filters['mois'],
            'periode' => $period === null ? null : [
                'id' => $period->id,
                'code' => $period->code,
                'date_debut' => $period->date_debut?->format('Y-m-d'),
                'date_fin' => $period->date_fin?->format('Y-m-d'),
            ],
            'sourceFinancement' => $selectedSource === null ? null : [
                'id' => $selectedSource->id,
                'code' => $selectedSource->code,
                'nom' => $selectedSource->nom,
            ],
            'sourcesFinancement' => $sources->map(fn (SourceFinancement $source) => [
                'id' => $source->id,
                'code' => $source->code,
                'nom' => $source->nom,
            ])->all(),
            'sourceVariants' => $this->sourceVariants($sources->keyBy('code')->all()),
            'filters' => [
                'mois' => $filters['mois'],
                'source_financement_id' => $sourceId === null ? '' : (string) $sourceId,
                'jour_reference' => $filters['jour_reference'],
                'type_stage' => $filters['type_stage'],
            ],
            'scope' => [
                'national' => $agencyIds === null,
                'agence_ids' => $agencyIds ?? [],
                'label' => $agencyIds === null ? 'Périmètre national' : 'Périmètre agence ('.count($agencyIds).')',
            ],
            'statistiques' => $statistics,
            'indicateursBi' => $this->biIndicators($biValues, $filters, $agencyIds, $selectedSource?->code),
            'recapPaiements' => $this->paymentRecap($filters, $period?->id, $sourceId, $agencyIds, $payments, $paymentSummary),
            'graphiques' => $this->charts($start, $end, $sourceId, $agencyIds, $stages, $pointages, $payments, $biValues, $renewals, $duplicates),
            'repartitionSourcesFinancement' => $this->fundingDistribution($period?->id, $sourceId, $agencyIds),
            'journalActivite' => $journal,
            'alertes' => $this->buildAlerts($stages, $renewals, $dossiers, $payments, $waitingPointages, $duplicates),
            'chartsError' => null,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportRows(array $filters = [], ?User $user = null): array
    {
        $overview = $this->buildOverview($filters, $user);
        $source = $overview['sourceFinancement']['nom'] ?? 'Toutes les sources';
        $rows = [
            ['Section', 'Indicateur', 'Valeur', 'Unité', 'Période', 'Financement', 'Définition / statut'],
            ['Filtres', 'Période', $overview['moisActuel'], '', $overview['moisActuel'], $source, ''],
            ['Filtres', 'Source de financement', $source, '', $overview['moisActuel'], $source, ''],
        ];
        $operational = [
            'stages_total' => ['Stages totaux', 'dossiers'],
            'pointages_attente' => ['Pointages en attente', 'pointages'],
            'pointages_valides' => ['Pointages validés', 'pointages'],
            'pointages_ajournes_ca' => ['Pointages ajournés CA', 'pointages'],
            'pointages_ajournes_dmg' => ['Paiements issus de pointages ajournés DMG', 'paiements'],
            'dossiers_transmis_ac' => ['Dossiers transmis AC', 'dossiers'],
            'dossiers_vises_ac' => ['Dossiers visés AC', 'dossiers'],
            'dossiers_ajournes_dmg' => ['Dossiers ajournés DMG', 'dossiers'],
            'droits_ouverts' => ['Droits ouverts', 'droits'],
            'montant_droits_ouverts' => ['Montant des droits ouverts', 'FCFA'],
            'paiements_a_traiter' => ['Paiements à traiter', 'paiements'],
            'montant_paiements_a_traiter' => ['Montant des paiements à traiter', 'FCFA'],
        ];
        foreach ($operational as $key => [$label, $unit]) {
            $rows[] = ['Opérationnel', $label, (string) $overview['statistiques'][$key], $unit, $overview['moisActuel'], $source, ''];
        }
        foreach ($overview['indicateursBi'] as $indicator) {
            $rows[] = ['BI', $indicator['label'], (string) $indicator['valeur'], $indicator['unite'], $overview['moisActuel'], $source, $indicator['definition'].' | '.$indicator['statut']];
        }
        foreach ($overview['recapPaiements']['resume'] as $key => $value) {
            $rows[] = ['Paiements', $value['label'], (string) $value['valeur'], str_starts_with($key, 'montant_') ? 'FCFA' : 'paiements', $overview['moisActuel'], $source, implode(', ', $value['statuts'])];
        }

        return $rows;
    }

    private function stageAggregate(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): object
    {
        $waitingCar = [
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE->value,
            CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS->value,
            CorbeilleEnum::CA_RETOUR_AJOURNEMENT->value,
        ];
        $placeholders = implode(',', array_fill(0, count($waitingCar), '?'));

        return $this->stageQuery($sourceId, $agencyIds)
            ->leftJoin('instances_parcours as workflow', function ($join): void {
                $join->on('workflow.stage_id', '=', 's.id')
                    ->whereNull('workflow.pointage_id')
                    ->whereNull('workflow.terminee_le');
            })
            ->whereDate('s.date_debut', '<=', $end->toDateString())
            ->whereDate('s.date_fin_prevue', '>=', $start->toDateString())
            ->selectRaw(
                "COUNT(DISTINCT s.id) AS stages_total,
                COUNT(DISTINCT CASE WHEN s.date_debut BETWEEN ? AND ? THEN s.id END) AS beneficiaires_saisis,
                COUNT(DISTINCT CASE WHEN s.date_validation_ar BETWEEN ? AND ? THEN s.id END) AS valides_ar,
                COUNT(DISTINCT CASE WHEN workflow.corbeille_actuelle IN ({$placeholders}) THEN s.id END) AS attente_car,
                COUNT(DISTINCT CASE WHEN s.visa_desse IS NOT NULL THEN s.id END) AS bdd_desse,
                COUNT(DISTINCT CASE WHEN s.visa_desse = ? THEN s.id END) AS rejetes_desse,
                COUNT(DISTINCT CASE WHEN s.date_fin_prevue BETWEEN ? AND ? THEN s.id END) AS fins_contrat",
                [$start, $end, $start, $end, ...$waitingCar, VisaDesseEnum::REJETE->value, $start, $end],
            )->first() ?? (object) [];
    }

    /** @return array<string, array{total: int, montant: string}> */
    private function pointageStatuses(?int $periodId, ?int $sourceId, ?array $agencyIds): array
    {
        if ($periodId === null) {
            return [];
        }
        $query = DB::table('pointages as p')->join('stages as s', 's.id', '=', 'p.stage_id')
            ->whereNull('p.deleted_at')->whereNull('s.deleted_at')->where('p.periode_id', $periodId);
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);

        return $query->selectRaw('p.statut, COUNT(*) AS total')->groupBy('p.statut')->get()
            ->mapWithKeys(fn ($row) => [(string) $row->statut => ['total' => (int) $row->total, 'montant' => '0']])->all();
    }

    /** @return array<string, array{total: int, montant: string}> */
    private function paymentStatuses(?int $periodId, ?int $sourceId, ?array $agencyIds): array
    {
        if ($periodId === null) {
            return [];
        }

        return $this->paymentQuery($periodId, $sourceId, $agencyIds)
            ->selectRaw('p.statut, COUNT(*) AS total, COALESCE(SUM(p.montant), 0) AS montant')
            ->groupBy('p.statut')->get()
            ->mapWithKeys(fn ($row) => [(string) $row->statut => ['total' => (int) $row->total, 'montant' => (string) $row->montant]])->all();
    }

    /** @return array<string, array{total: int, montant: string}> */
    private function dossierStatuses(?int $periodId, ?int $sourceId, ?array $agencyIds): array
    {
        if ($periodId === null) {
            return [];
        }
        $query = DB::table('dossiers_paiement as dossier')->where('dossier.periode_id', $periodId)
            ->when($sourceId !== null, fn (Builder $q) => $q->where('dossier.source_financement_id', $sourceId));
        if ($agencyIds !== null) {
            $query->join('lignes_dossiers_paiement as ligne', fn ($join) => $join->on('ligne.dossier_paiement_id', '=', 'dossier.id')->whereNull('ligne.retire_le'))
                ->join('paiements as paiement_dossier', 'paiement_dossier.id', '=', 'ligne.paiement_id')
                ->join('droits_paiement as droit_dossier', 'droit_dossier.id', '=', 'paiement_dossier.droit_paiement_id')
                ->join('stages as stage_dossier', 'stage_dossier.id', '=', 'droit_dossier.stage_id')
                ->whereNull('stage_dossier.deleted_at')->whereIn('stage_dossier.agence_id', $agencyIds);
        }

        return $query->selectRaw('dossier.statut, COUNT(DISTINCT dossier.id) AS total, COALESCE(SUM(DISTINCT dossier.montant_total), 0) AS montant')
            ->groupBy('dossier.statut')->get()
            ->mapWithKeys(fn ($row) => [(string) $row->statut => ['total' => (int) $row->total, 'montant' => (string) $row->montant]])->all();
    }

    private function droitAggregate(?int $periodId, ?int $sourceId, ?array $agencyIds): object
    {
        if ($periodId === null) {
            return (object) ['total' => 0, 'montant' => '0'];
        }
        $query = DB::table('droits_paiement as droit')->join('stages as s', 's.id', '=', 'droit.stage_id')
            ->whereNull('s.deleted_at')->where('droit.periode_id', $periodId)->where('droit.statut', 'OUVERT');
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);

        return $query->whereNull('droit.annule_le')
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(droit.montant), 0) AS montant')->first()
            ?? (object) ['total' => 0, 'montant' => '0'];
    }

    /** @return array{attente: int, valides: int, ajournes: int} */
    private function renewalAggregate(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        $typeIds = TypeStage::idsPourCodes([TypeStage::CODE_QUALIFICATION, TypeStage::CODE_QUALIFICATION_HERITE]);
        $waiting = $this->stageQuery($sourceId, $agencyIds)
            ->whereBetween('s.date_fin_prevue', [$start->toDateString(), $end->toDateString()])
            ->when($typeIds !== [], fn (Builder $q) => $q->whereIn('s.type_stage_id', $typeIds))
            ->whereIn('s.situation_stage', [SituationStage::CODE_EN_COURS, SituationStage::CODE_FIN_DE_STAGE])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('contrats as rc')
                    ->join('avenants_contrats as ra', 'ra.contrat_id', '=', 'rc.id')
                    ->whereColumn('rc.stage_id', 's.id')
                    ->whereIn('ra.statut', [AvenantContrat::STATUT_ATTENTE_CA, AvenantContrat::STATUT_AJOURNE]);
            })->count();
        $query = DB::table('avenants_contrats as avenant')->join('contrats as contrat', 'contrat.id', '=', 'avenant.contrat_id')
            ->join('stages as s', 's.id', '=', 'contrat.stage_id')->whereNull('s.deleted_at')
            ->whereBetween('s.date_fin_prevue', [$start->toDateString(), $end->toDateString()]);
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);
        $statuses = $query->selectRaw('avenant.statut, COUNT(DISTINCT avenant.id) AS total')->groupBy('avenant.statut')->pluck('total', 'statut');

        return [
            'attente' => (int) $waiting + (int) ($statuses[AvenantContrat::STATUT_ATTENTE_CA] ?? 0),
            'valides' => (int) ($statuses[AvenantContrat::STATUT_VALIDE] ?? 0),
            'ajournes' => (int) ($statuses[AvenantContrat::STATUT_AJOURNE] ?? 0),
        ];
    }

    private function countWaitingPointages(?int $periodId, Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): int
    {
        if ($periodId === null) {
            return 0;
        }

        return $this->stageQuery($sourceId, $agencyIds)
            ->where('s.situation_stage', SituationStage::CODE_EN_COURS)
            ->whereDate('s.date_debut', '<=', $end->toDateString())->whereDate('s.date_fin_prevue', '>=', $start->toDateString())
            ->whereNotExists(function (Builder $query) use ($periodId): void {
                $query->selectRaw('1')->from('pointages as wp')->whereColumn('wp.stage_id', 's.id')
                    ->where('wp.periode_id', $periodId)->whereNull('wp.deleted_at')
                    ->whereIn('wp.statut', ['SOUMIS', 'VALIDE', 'CORRIGE_CIP', 'AJOURNE_CA', 'AJOURNE_DMG']);
            })
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')->from('instances_parcours as wi')->whereColumn('wi.stage_id', 's.id')
                    ->whereNull('wi.pointage_id')->whereNull('wi.terminee_le')
                    ->whereIn('wi.corbeille_actuelle', CorbeilleEnum::nonValideesParCa());
            })->count();
    }

    /** @return array{non_traites: int, traites: int} */
    private function duplicateSummary(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        $pending = DB::table('instances_parcours as duplicate_workflow')->join('stages as s', 's.id', '=', 'duplicate_workflow.stage_id')
            ->whereNull('duplicate_workflow.terminee_le')->whereNull('s.deleted_at')
            ->where('duplicate_workflow.corbeille_actuelle', CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER->value)
            ->whereDate('s.date_debut', '<=', $end->toDateString())->whereDate('s.date_fin_prevue', '>=', $start->toDateString());
        $this->applyStageScope($pending, 's', $sourceId, $agencyIds);
        $treated = DB::table('desse_doublon_decisions as dd')->join('instances_parcours as di', 'di.id', '=', 'dd.instance_parcours_id')
            ->join('stages as s', 's.id', '=', 'di.stage_id')->whereNull('s.deleted_at')->whereBetween('dd.decide_le', [$start, $end]);
        $this->applyStageScope($treated, 's', $sourceId, $agencyIds);

        return [
            'non_traites' => $pending->distinct()->count('duplicate_workflow.id'),
            'traites' => $treated->distinct()->count('dd.instance_parcours_id'),
        ];
    }

    /** @param array<string, array{total: int, montant: string}> $statuses */
    private function paymentSummary(array $statuses): array
    {
        return [
            'paiements_effectues' => ['label' => 'Paiements effectués', 'valeur' => $this->statusCount($statuses, 'PAYE'), 'statuts' => ['PAYE']],
            'montant_effectue' => ['label' => 'Montant payé', 'valeur' => $this->statusAmount($statuses, 'PAYE'), 'statuts' => ['PAYE']],
            'paiements_rejetes' => ['label' => 'Paiements rejetés / non payés', 'valeur' => $this->sumStatusCounts($statuses, self::PAYMENT_REJECTED), 'statuts' => self::PAYMENT_REJECTED],
            'montant_rejete' => ['label' => 'Montant rejeté / non payé', 'valeur' => $this->sumStatusAmounts($statuses, self::PAYMENT_REJECTED), 'statuts' => self::PAYMENT_REJECTED],
            'paiements_en_cours' => ['label' => 'Paiements en cours', 'valeur' => $this->sumStatusCounts($statuses, self::PAYMENT_IN_PROGRESS), 'statuts' => self::PAYMENT_IN_PROGRESS],
            'montant_en_cours' => ['label' => 'Montant en cours', 'valeur' => $this->sumStatusAmounts($statuses, self::PAYMENT_IN_PROGRESS), 'statuts' => self::PAYMENT_IN_PROGRESS],
        ];
    }

    /** @param array<string, mixed> $filters @param array<int, int>|null $agencyIds */
    private function paymentRecap(array $filters, ?int $periodId, ?int $sourceId, ?array $agencyIds, array $statuses, array $summary): array
    {
        $daicg = [];
        $reasons = [];
        if ($periodId !== null) {
            $rejectedSql = $this->quotedStatuses(self::PAYMENT_REJECTED);
            $progressSql = $this->quotedStatuses(self::PAYMENT_IN_PROGRESS);
            $paymentTotals = DB::table('paiements as paiement_recap')
                ->select('paiement_recap.droit_paiement_id')
                ->where(function (Builder $query) use ($filters): void {
                    $query->whereNull('paiement_recap.paye_le')
                        ->orWhereDate('paiement_recap.paye_le', '<=', $filters['jour_reference']);
                })
                ->selectRaw("COALESCE(SUM(CASE WHEN paiement_recap.statut = 'PAYE' THEN paiement_recap.montant ELSE 0 END), 0) AS montant_paye")
                ->selectRaw("COALESCE(SUM(CASE WHEN paiement_recap.statut IN ({$rejectedSql}) THEN paiement_recap.montant ELSE 0 END), 0) AS montant_rejete")
                ->selectRaw("COALESCE(SUM(CASE WHEN paiement_recap.statut IN ({$progressSql}) THEN paiement_recap.montant ELSE 0 END), 0) AS montant_en_cours")
                ->groupBy('paiement_recap.droit_paiement_id');

            $query = DB::table('droits_paiement as droit')->join('stages as s', 's.id', '=', 'droit.stage_id')
                ->join('agences as agence', 'agence.id', '=', 's.agence_id')->leftJoinSub($paymentTotals, 'paiement', function ($join): void {
                    $join->on('paiement.droit_paiement_id', '=', 'droit.id');
                })
                ->whereNull('s.deleted_at')->whereNull('droit.annule_le')->where('droit.periode_id', $periodId)
                ->when($this->typeStageIds($filters['type_stage']), fn (Builder $q, array $ids) => $q->whereIn('s.type_stage_id', $ids));
            $this->applyStageScope($query, 's', $sourceId, $agencyIds);
            $daicg = $query->groupBy('agence.id', 'agence.nom')->orderBy('agence.nom')->selectRaw(
                'agence.id AS agence_id, agence.nom AS agence, COUNT(DISTINCT s.id) AS beneficiaires,
                COUNT(DISTINCT droit.id) AS droits, COALESCE(SUM(droit.montant), 0) AS montant_du,
                COALESCE(SUM(paiement.montant_paye), 0) AS montant_paye,
                COALESCE(SUM(paiement.montant_rejete), 0) AS montant_rejete,
                COALESCE(SUM(paiement.montant_en_cours), 0) AS montant_en_cours'
            )->get()->map(fn ($row) => [
                'agence_id' => (int) $row->agence_id, 'agence' => $row->agence,
                'beneficiaires' => (int) $row->beneficiaires, 'droits' => (int) $row->droits,
                'montant_du' => (string) $row->montant_du, 'montant_paye' => (string) $row->montant_paye,
                'montant_rejete' => (string) $row->montant_rejete, 'montant_en_cours' => (string) $row->montant_en_cours,
            ])->all();

            $reasonQuery = DB::table('decisions_paiements as decision')->join('paiements as paiement', 'paiement.id', '=', 'decision.paiement_id')
                ->join('droits_paiement as droit', 'droit.id', '=', 'paiement.droit_paiement_id')->join('stages as s', 's.id', '=', 'droit.stage_id')
                ->whereNull('s.deleted_at')->where('droit.periode_id', $periodId)->whereNotNull('decision.motif')->where('decision.motif', '<>', '')
                ->whereIn('decision.decision', ['AJOURNE_DMG', 'REJET_OP_AC', 'DIFFERE_OP_AC', 'DIFFERE_STAGIAIRE_AC']);
            $this->applyStageScope($reasonQuery, 's', $sourceId, $agencyIds);
            $reasons = $reasonQuery->selectRaw('decision.motif, COUNT(*) AS total')->groupBy('decision.motif')->orderByDesc('total')->limit(10)->get()
                ->map(fn ($row) => ['motif' => $row->motif, 'total' => (int) $row->total])->all();
        }

        return [
            'metadata' => [
                'annee' => (int) substr($filters['mois'], 0, 4), 'mois' => $filters['mois'],
                'jour_reference' => $filters['jour_reference'], 'type_stage' => $filters['type_stage'],
                'qualification_validation' => match ($filters['type_stage']) {
                    'qualification' => 'Qualification', 'validation' => 'Validation / stage école', default => 'Qualification et validation',
                },
            ],
            'resume' => $summary,
            'statuts' => collect($statuses)->map(fn ($value, $status) => ['statut' => $status, ...$value])->values()->all(),
            'motifs_rejet' => $reasons,
            'detail_daicg' => $daicg,
        ];
    }

    /** @param array<string, int> $values @param array<string, mixed> $filters @param array<int, int>|null $agencyIds */
    private function biIndicators(array $values, array $filters, ?array $agencyIds, ?string $sourceCode): array
    {
        $all = ['administrateur', 'cip', 'chef_agence', 'desse', 'daicg', 'dmg', 'cb', 'agent_comptable', 'pejedec', 'aaf'];
        $rows = [
            ['beneficiaires_saisis', 'Bénéficiaires saisis', 'Stages dont la date de début appartient au mois sélectionné.', 'Stage', 'stages', 'date_debut dans le mois', ['administrateur', 'cip', 'chef_agence', 'desse', 'daicg'], '/cip/mes-stagiaires'],
            ['valides_ar', 'Validés par l’Agence Régionale', 'Stages dont la validation Agence Régionale est horodatée pendant le mois.', 'Stage', 'stages', 'date_validation_ar dans le mois', ['administrateur', 'chef_agence', 'desse', 'daicg'], '/agence-regionale/visas?tab=valides_ar'],
            ['attente_car', 'Attente CAR', 'Stages dont le parcours attend une validation ou un retour du Chef d’Agence.', 'InstanceParcours + Stage', 'instances_parcours, stages', 'CA attente démarrage / omis / retour', ['administrateur', 'chef_agence', 'daicg'], '/agence-regionale/visas'],
            ['bdd_desse', 'Dossiers BDD DESSE', 'Stages entrés dans le suivi DESSE, matérialisés par un visa initialisé.', 'Stage', 'stages', 'visa_desse non nul', ['administrateur', 'desse', 'daicg'], '/desse/stagiaires'],
            ['rejetes_desse', 'Rejetés DESSE', 'Stages dont le visa DESSE est rejeté.', 'Stage', 'stages', 'visa_desse = REJETE', ['administrateur', 'desse', 'daicg', 'cip'], '/desse/stagiaires?tab=rejetes_desse'],
            ['doublons_desse', 'Doublons DESSE', 'Dossiers présents dans la corbeille de doublons DESSE non traités.', 'InstanceParcours', 'instances_parcours, desse_doublon_decisions', 'desse_doublons_a_traiter', ['administrateur', 'desse'], '/desse/stagiaires?tab=doublons'],
            ['attente_pointage', 'Attente pointage', 'Stages actifs validés par le Chef d’Agence sans pointage pour la période.', 'Stage + Pointage', 'stages, pointages', 'aucun pointage actif', ['administrateur', 'cip', 'chef_agence'], '/cip/pointages'],
            ['pointes_agent', 'Pointés par agent', 'Pointages saisis ou déjà traités pour la période.', 'Pointage', 'pointages', 'SOUMIS / VALIDE / CORRIGE_CIP / AJOURNE_CA', ['administrateur', 'cip', 'chef_agence'], '/cip/pointages'],
            ['pointage_attente_car', 'Pointage attente CAR', 'Pointages soumis et non encore tranchés par le Chef d’Agence.', 'Pointage', 'pointages', 'SOUMIS', ['administrateur', 'chef_agence'], '/cip/pointages'],
            ['pointage_valide_car', 'Pointage validé CAR', 'Pointages validés par le Chef d’Agence.', 'Pointage', 'pointages', 'VALIDE', ['administrateur', 'cip', 'chef_agence', 'dmg'], '/cip/pointages'],
            ['valides_dmg', 'Validés DMG', 'Paiements ayant dépassé la file A_TRAITER DMG.', 'Paiement', 'paiements, droits_paiement', implode(' / ', self::PAYMENT_VALIDATED_DMG), ['administrateur', 'dmg', 'cb', 'agent_comptable', 'daicg'], '/dmg/paiements'],
            ['attente_dmg', 'Attente DMG', 'Paiements ouverts en attente du traitement DMG.', 'Paiement', 'paiements, droits_paiement', 'A_TRAITER', ['administrateur', 'dmg', 'daicg'], '/dmg/paiements'],
            ['rejetes_dmg', 'Rejetés DMG', 'Paiements ajournés par la DMG ; le pointage source reste distinct.', 'Paiement', 'paiements, droits_paiement', 'AJOURNE_DMG', ['administrateur', 'dmg', 'cip', 'daicg'], '/dmg/paiements'],
            ['payes_ac', 'Payés AC', 'Paiements confirmés comme effectivement payés par l’Agent Comptable.', 'Paiement', 'paiements, droits_paiement', 'PAYE', ['administrateur', 'agent_comptable', 'daicg'], '/agent-comptable/paiements'],
            ['fins_contrat', 'Fins de contrat', 'Stages dont la date de fin prévue appartient au mois.', 'Stage', 'stages', 'date_fin_prevue dans le mois', ['administrateur', 'cip', 'chef_agence', 'daicg'], '/cip/mes-stagiaires'],
            ['rejetes_ac', 'Rejetés AC', 'Paiements rejetés par l’Agent Comptable, hors simples non-paiements.', 'Paiement', 'paiements, droits_paiement', 'REJETE_AC / REJETE_AC_DEFINITIF', ['administrateur', 'agent_comptable', 'dmg', 'daicg'], '/agent-comptable/paiements'],
            ['captes_annee', 'Captés par année', 'Stages démarrés depuis le 1er janvier jusqu’à la fin du mois.', 'Stage', 'stages', 'date_debut dans l’année à date', $all, '/daicg/stagiaires'],
            ['attente_renouvellement', 'Attente renouvellement', 'Stages arrivant à terme sans renouvellement actif, plus avenants en attente CA.', 'Stage + Contrat + AvenantContrat', 'stages, contrats, avenants_contrats', 'sans avenant actif / ATTENTE_CA', ['administrateur', 'cip', 'chef_agence'], '/cip/renouvellements'],
        ];

        return array_map(fn (array $row) => [
            'key' => $row[0], 'label' => $row[1], 'valeur' => $values[$row[0]] ?? 0,
            'definition' => $row[2], 'modele' => $row[3], 'table' => $row[4], 'statut' => $row[5],
            'roles' => $row[6], 'lien' => $row[7], 'periode' => $filters['mois'],
            'source_financement' => $sourceCode ?? 'Toutes les sources',
            'perimetre_agence' => $agencyIds === null ? 'National' : implode(', ', $agencyIds),
            'export' => true, 'disponible' => true, 'unite' => 'dossiers',
        ], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function charts(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds, object $stage, array $pointages, array $payments, array $bi, array $renewals, array $duplicates): array
    {
        $sources = $this->stagesBySource($start, $end, $sourceId, $agencyIds);
        $agencies = $this->stagesByAgency($start, $end, $sourceId, $agencyIds);
        $monthly = $this->monthlyEvolution($end, $sourceId, $agencyIds);

        return [
            $this->chart('stages-source', 'Stages par source', 'bar', 'stages', array_column($sources, 'label'), [['name' => 'Stages', 'data' => array_column($sources, 'value')]]),
            $this->chart('validations-etape', 'Validations par étape', 'bar', 'dossiers', ['Saisis', 'Validés AR', 'BDD DESSE', 'Validés DMG', 'Payés AC'], [['name' => 'Dossiers', 'data' => [$bi['beneficiaires_saisis'], $bi['valides_ar'], $bi['bdd_desse'], $bi['valides_dmg'], $bi['payes_ac']]]]),
            $this->statusChart('pointages-statut', 'Pointages par statut', 'pointages', $pointages),
            $this->statusChart('paiements-statut', 'Paiements par statut', 'paiements', $payments),
            $this->chart('montants-paiement', 'Montants payés, rejetés et en cours', 'bar', 'FCFA', ['Payés', 'Rejetés / non payés', 'En cours'], [['name' => 'Montant', 'data' => [(float) $this->statusAmount($payments, 'PAYE'), (float) $this->sumStatusAmounts($payments, self::PAYMENT_REJECTED), (float) $this->sumStatusAmounts($payments, self::PAYMENT_IN_PROGRESS)]]]),
            $this->chart('doublons', 'Doublons DESSE', 'bar', 'dossiers', ['Non traités', 'Traités dans le mois'], [['name' => 'Dossiers', 'data' => [$duplicates['non_traites'], $duplicates['traites']]]]),
            $this->chart('contrats', 'Fins de contrat et renouvellements', 'bar', 'dossiers', ['Fins de contrat', 'Attente renouvellement', 'Renouvellements validés'], [['name' => 'Dossiers', 'data' => [(int) ($stage->fins_contrat ?? 0), $renewals['attente'], $renewals['valides']]]]),
            $this->chart('agences', 'Comparaison agences', 'bar', 'stages', array_column($agencies, 'label'), [['name' => 'Stages actifs', 'data' => array_column($agencies, 'value')]]),
            $this->chart('evolution-mensuelle', 'Évolution mensuelle des paiements', 'line', 'FCFA', array_column($monthly, 'label'), [
                ['name' => 'Payé', 'data' => array_column($monthly, 'paid')],
                ['name' => 'Rejeté / non payé', 'data' => array_column($monthly, 'rejected')],
                ['name' => 'En cours', 'data' => array_column($monthly, 'in_progress')],
            ]),
        ];
    }

    private function stagesBySource(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        return $this->stageQuery($sourceId, $agencyIds)->join('sources_financement as source', 'source.id', '=', 's.source_financement_id')
            ->whereDate('s.date_debut', '<=', $end->toDateString())->whereDate('s.date_fin_prevue', '>=', $start->toDateString())
            ->groupBy('source.id', 'source.nom')->orderBy('source.nom')->selectRaw('source.nom AS label, COUNT(*) AS value')->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])->all();
    }

    private function stagesByAgency(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        return $this->stageQuery($sourceId, $agencyIds)->join('agences as agence', 'agence.id', '=', 's.agence_id')
            ->whereDate('s.date_debut', '<=', $end->toDateString())->whereDate('s.date_fin_prevue', '>=', $start->toDateString())
            ->groupBy('agence.id', 'agence.nom')->orderByDesc('value')->limit(15)->selectRaw('agence.nom AS label, COUNT(*) AS value')->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->value])->all();
    }

    private function monthlyEvolution(Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        $query = DB::table('periodes as periode')->leftJoin('droits_paiement as droit', 'droit.periode_id', '=', 'periode.id')
            ->leftJoin('stages as s', 's.id', '=', 'droit.stage_id')->leftJoin('paiements as paiement', 'paiement.droit_paiement_id', '=', 'droit.id')
            ->whereNull('droit.annule_le')->whereNull('s.deleted_at')
            ->whereYear('periode.date_debut', $end->year)->whereDate('periode.date_debut', '<=', $end->toDateString())
            ->when($sourceId !== null, fn (Builder $q) => $q->where('s.source_financement_id', $sourceId))
            ->when($agencyIds !== null, fn (Builder $q) => $q->whereIn('s.agence_id', $agencyIds));
        $rejected = $this->quotedStatuses(self::PAYMENT_REJECTED);
        $progress = $this->quotedStatuses(self::PAYMENT_IN_PROGRESS);

        return $query->groupBy('periode.id', 'periode.code', 'periode.date_debut')->orderBy('periode.date_debut')->selectRaw(
            "periode.code AS label,
            COALESCE(SUM(CASE WHEN paiement.statut = 'PAYE' THEN paiement.montant ELSE 0 END), 0) AS paid,
            COALESCE(SUM(CASE WHEN paiement.statut IN ({$rejected}) THEN paiement.montant ELSE 0 END), 0) AS rejected,
            COALESCE(SUM(CASE WHEN paiement.statut IN ({$progress}) THEN paiement.montant ELSE 0 END), 0) AS in_progress"
        )->get()->map(fn ($row) => ['label' => $row->label, 'paid' => (float) $row->paid, 'rejected' => (float) $row->rejected, 'in_progress' => (float) $row->in_progress])->all();
    }

    private function fundingDistribution(?int $periodId, ?int $sourceId, ?array $agencyIds): array
    {
        if ($periodId === null) {
            return [];
        }
        $query = DB::table('droits_paiement as droit')->join('stages as s', 's.id', '=', 'droit.stage_id')
            ->join('sources_financement as source', 'source.id', '=', 'droit.source_financement_id')
            ->whereNull('s.deleted_at')->where('droit.periode_id', $periodId);
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);

        return $query->whereNull('droit.annule_le')->groupBy('source.id', 'source.code', 'source.nom')->orderByDesc('montant_total')
            ->selectRaw('source.id, source.code, source.nom, COUNT(*) AS total_droits, COALESCE(SUM(droit.montant), 0) AS montant_total')->get()
            ->map(fn ($row) => ['source' => ['id' => (int) $row->id, 'code' => $row->code, 'nom' => $row->nom], 'total_droits' => (int) $row->total_droits, 'montant_total' => (string) $row->montant_total])->all();
    }

    private function recentAudit(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): array
    {
        $query = JournalAudit::query()->with(['user:id,nom,email'])->whereBetween('created_at', [$start, $end]);
        if ($sourceId !== null || $agencyIds !== null) {
            $stageIds = $this->stageQuery($sourceId, $agencyIds)->select('s.id');
            $query->where('modele_type', Stage::class)->whereIn('modele_id', $stageIds);
        }

        return $query->latest()->limit(10)->get()->map(fn (JournalAudit $audit) => [
            'id' => $audit->id, 'action' => $audit->action, 'modele' => class_basename($audit->modele_type),
            'modele_id' => $audit->modele_id, 'utilisateur' => $audit->user?->name ?? 'Système',
            'email' => $audit->user?->email, 'date' => $audit->created_at?->format('d/m/Y H:i'),
        ])->all();
    }

    private function buildAlerts(object $stage, array $renewals, array $dossiers, array $payments, int $waitingPointages, array $duplicates): array
    {
        $alerts = [
            ['niveau' => 'warning', 'titre' => 'Stages arrivés à terme', 'compteur' => (int) ($stage->fins_contrat ?? 0), 'lien' => '/cip/renouvellements?tab=attente', 'message' => 'Contrats dont le terme appartient au mois filtré.'],
            ['niveau' => 'warning', 'titre' => 'Contrats à renouveler', 'compteur' => $renewals['attente'], 'lien' => '/cip/renouvellements', 'message' => 'Stages sans renouvellement actif ou avenants en attente CA.'],
            ['niveau' => 'danger', 'titre' => 'Dossiers ajournés CB', 'compteur' => $this->statusCount($dossiers, 'AJOURNE_CB'), 'lien' => '/dmg/paiements', 'message' => 'Dossiers renvoyés par le Contrôleur Budgétaire.'],
            ['niveau' => 'danger', 'titre' => 'Paiements bloqués', 'compteur' => $this->sumStatusCounts($payments, ['AJOURNE_DMG', 'REJETE_AC', 'REJETE_AC_DEFINITIF']), 'lien' => '/dmg/paiements', 'message' => 'Paiements ajournés DMG ou rejetés par l’Agent Comptable.'],
            ['niveau' => 'info', 'titre' => 'Pointages non traités', 'compteur' => $waitingPointages, 'lien' => '/cip/pointages', 'message' => 'Stages éligibles sans pointage sur la période.'],
            ['niveau' => 'info', 'titre' => 'Dossiers DMG en attente', 'compteur' => $this->statusCount($payments, 'A_TRAITER'), 'lien' => '/dmg/paiements', 'message' => 'Paiements présents dans la file de traitement DMG.'],
            ['niveau' => 'danger', 'titre' => 'Doublons DESSE non traités', 'compteur' => $duplicates['non_traites'], 'lien' => '/desse/stagiaires?tab=doublons', 'message' => 'Dossiers bloqués dans la corbeille de doublons DESSE.'],
        ];

        return array_values(array_filter($alerts, fn (array $alert) => $alert['compteur'] > 0));
    }

    private function stageQuery(?int $sourceId, ?array $agencyIds): Builder
    {
        $query = DB::table('stages as s')->whereNull('s.deleted_at');
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);

        return $query;
    }

    private function paymentQuery(?int $periodId, ?int $sourceId, ?array $agencyIds): Builder
    {
        $query = DB::table('paiements as p')->join('droits_paiement as d', 'd.id', '=', 'p.droit_paiement_id')
            ->join('stages as s', 's.id', '=', 'd.stage_id')->whereNull('s.deleted_at')->whereNull('d.annule_le')
            ->when($periodId !== null, fn (Builder $q) => $q->where('d.periode_id', $periodId));
        $this->applyStageScope($query, 's', $sourceId, $agencyIds);

        return $query;
    }

    private function applyStageScope(Builder $query, string $alias, ?int $sourceId, ?array $agencyIds): void
    {
        if ($sourceId !== null) {
            $query->where("{$alias}.source_financement_id", $sourceId);
        }
        if ($agencyIds !== null) {
            $query->whereIn("{$alias}.agence_id", $agencyIds);
        }
    }

    /** @return array<int, int>|null */
    private function authorizedAgencyIds(?User $user): ?array
    {
        if ($user === null || $user->hasAnyRole(self::NATIONAL_ROLES)) {
            return null;
        }
        $ids = $user->perimetresAgences()->where(fn ($q) => $q->whereNull('valide_au')->orWhere('valide_au', '>=', now()))
            ->pluck('agences.id')->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        return $ids === [] ? [-1] : $ids;
    }

    private function countCapturedYearToDate(Carbon $start, Carbon $end, ?int $sourceId, ?array $agencyIds): int
    {
        return $this->stageQuery($sourceId, $agencyIds)
            ->whereBetween('s.date_debut', [$start->copy()->startOfYear()->toDateString(), $end->toDateString()])->count();
    }

    private function statusCount(array $statuses, string $key): int
    {
        return (int) ($statuses[$key]['total'] ?? 0);
    }

    private function statusAmount(array $statuses, string $key): string
    {
        return (string) ($statuses[$key]['montant'] ?? '0');
    }

    private function sumStatusCounts(array $statuses, array $keys): int
    {
        return array_sum(array_map(fn (string $key) => $this->statusCount($statuses, $key), $keys));
    }

    private function sumStatusAmounts(array $statuses, array $keys): string
    {
        return (string) array_sum(array_map(fn (string $key) => (float) $this->statusAmount($statuses, $key), $keys));
    }

    private function typeStageIds(string $type): ?array
    {
        return match ($type) {
            'qualification' => TypeStage::idsPourCodes([TypeStage::CODE_QUALIFICATION, TypeStage::CODE_QUALIFICATION_HERITE]),
            'validation' => TypeStage::idsPourCodes([TypeStage::CODE_ECOLE]),
            default => null,
        };
    }

    private function normalizeReferenceDay(mixed $value, Carbon $month): string
    {
        if (is_string($value)) {
            try {
                $day = Carbon::createFromFormat('!Y-m-d', $value);
                if ($day->format('Y-m') === $month->format('Y-m')) {
                    return $day->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        return $month->copy()->endOfMonth()->format('Y-m-d');
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function sourceVariants(array $sourcesByCode): array
    {
        return collect(self::SOURCE_VARIANTS)->map(function (?string $code, string $slug) use ($sourcesByCode): array {
            $source = $code === null ? null : ($sourcesByCode[$code] ?? null);

            return ['slug' => $slug, 'code' => $code, 'source_financement_id' => $source?->id, 'label' => $source?->nom ?? 'Vue globale'];
        })->values()->all();
    }

    private function chart(string $key, string $title, string $type, string $unit, array $categories, array $series): array
    {
        return compact('key', 'title', 'type', 'unit', 'categories', 'series');
    }

    private function statusChart(string $key, string $title, string $unit, array $statuses): array
    {
        return $this->chart($key, $title, 'bar', $unit, array_keys($statuses), [[
            'name' => 'Total', 'data' => array_map(fn (array $value) => (int) $value['total'], array_values($statuses)),
        ]]);
    }

    private function quotedStatuses(array $statuses): string
    {
        return implode(',', array_map(fn (string $status) => DB::getPdo()->quote($status), $statuses));
    }
}
