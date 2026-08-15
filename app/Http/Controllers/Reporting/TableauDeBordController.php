<?php

namespace App\Http\Controllers\Reporting;

use App\Domain\Reporting\Services\ReportingDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableauDeBordController
{
    public function __construct(
        private ReportingDashboardService $reportingService
    ) {}

    public function index(Request $request)
    {
        $overview = $this->reportingService->buildOverview($request->only([
            'mois',
            'source_financement_id',
        ]));

        return Inertia::render('Reporting/Index', $overview);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $rows = $this->reportingService->exportRows($request->only([
            'mois',
            'source_financement_id',
        ]));
        $mois = (string) ($request->query('mois', now()->format('Y-m')));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, sprintf('reporting-%s.csv', $mois), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
