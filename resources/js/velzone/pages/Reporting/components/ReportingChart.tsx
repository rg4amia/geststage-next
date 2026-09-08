import type { ApexOptions } from 'apexcharts';
import React from 'react';
import ReactApexChart from 'react-apexcharts';
import { Card, CardBody, CardHeader, Spinner } from 'reactstrap';

export type ReportingChartData = {
    key: string;
    title: string;
    type: 'bar' | 'line';
    unit: string;
    categories: string[];
    series: Array<{ name: string; data: number[] }>;
};

type Props = {
    chart: ReportingChartData;
    loading?: boolean;
    error?: string | null;
};

const compactNumber = new Intl.NumberFormat('fr-FR', {
    notation: 'compact',
    maximumFractionDigits: 1,
});
const fullNumber = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

const ReportingChart = ({ chart, loading = false, error = null }: Props) => {
    const hasData =
        chart.categories.length > 0 &&
        chart.series.some((serie) =>
            serie.data.some((value) => Number(value) !== 0),
        );
    const isCurrency = chart.unit === 'FCFA';

    const options: ApexOptions = {
        chart: {
            toolbar: { show: false },
            fontFamily: 'inherit',
            animations: { enabled: true, speed: 300 },
        },
        colors: ['#405189', '#0ab39c', '#f7b84b', '#f06548'],
        dataLabels: { enabled: false },
        legend: { show: true, position: 'bottom', horizontalAlign: 'center' },
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: chart.categories.length > 8 ? '70%' : '52%',
                horizontal: chart.key === 'agences',
            },
        },
        stroke: { curve: 'smooth', width: chart.type === 'line' ? 3 : 0 },
        xaxis: {
            categories: chart.categories,
            labels: {
                rotate: chart.categories.length > 6 ? -35 : 0,
                trim: true,
            },
        },
        yaxis: {
            labels: {
                formatter: (value) =>
                    isCurrency
                        ? `${compactNumber.format(value)} F`
                        : compactNumber.format(value),
            },
            title: { text: chart.unit },
        },
        tooltip: {
            y: {
                formatter: (value) =>
                    `${fullNumber.format(value)} ${chart.unit}`,
            },
        },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: { height: 300 },
                    legend: { fontSize: '11px' },
                    xaxis: {
                        labels: { rotate: -45, hideOverlappingLabels: true },
                    },
                },
            },
        ],
        noData: { text: 'Aucune donnée pour les filtres sélectionnés' },
    };

    return (
        <Card className="h-100">
            <CardHeader className="d-flex align-items-center justify-content-between">
                <h4 className="card-title mb-0">{chart.title}</h4>
                <span className="badge bg-light text-body">{chart.unit}</span>
            </CardHeader>
            <CardBody className="d-flex align-items-center justify-content-center">
                {loading ? (
                    <div
                        className="py-5 text-center"
                        role="status"
                        aria-live="polite"
                    >
                        <Spinner color="primary" size="sm" />
                        <div className="mt-2 text-muted">
                            Calcul des agrégats…
                        </div>
                    </div>
                ) : error ? (
                    <div className="alert alert-danger mb-0 w-100" role="alert">
                        {error}
                    </div>
                ) : !hasData ? (
                    <div className="py-5 text-center text-muted">
                        Aucune donnée pour les filtres sélectionnés.
                    </div>
                ) : (
                    <div
                        className="w-100"
                        aria-label={`${chart.title}, unité ${chart.unit}`}
                    >
                        <ReactApexChart
                            type={chart.type}
                            options={options}
                            series={chart.series}
                            height={chart.key === 'agences' ? 420 : 330}
                            className="apex-charts"
                        />
                    </div>
                )}
            </CardBody>
        </Card>
    );
};

export default ReportingChart;
