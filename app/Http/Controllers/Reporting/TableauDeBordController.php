<?php

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\ReportingDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableauDeBordController
{
    public function __construct(
        private ReportingDashboardService $reportingService
    ) {}

    public function index(Request $request): Response
    {
        $overview = $this->reportingService->buildOverview($this->filters($request), $request->user());

        return Inertia::render('Reporting/Index', $overview);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->reportingService->exportRows($filters, $request->user());
        $normalized = $this->reportingService->normalizeFilters($filters);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, sprintf('reporting-%s.csv', $normalized['mois']), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'mois' => ['nullable', 'date_format:Y-m'],
            'source_financement_id' => ['nullable', 'integer', 'exists:sources_financement,id'],
            'source' => ['nullable', Rule::in(['global', 'budget-aej', 'paps-gouv', 'pejedec', 'c2d'])],
            'jour_reference' => ['nullable', 'date_format:Y-m-d'],
            'type_stage' => ['nullable', Rule::in(['tous', 'qualification', 'validation'])],
        ]);
    }
}
