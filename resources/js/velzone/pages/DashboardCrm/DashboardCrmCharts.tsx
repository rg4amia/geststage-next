import React from 'react';
import ReactApexChart from "react-apexcharts";
import useChartColors from 'Components/Common/useChartColors';

const SalesForecastCharts = ({ chartId, series }: any) => {
    const chartColors = useChartColors(chartId);

    var options: any = {
        chart: {
            type: 'bar',
            height: 341,
            toolbar: {
                show: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '65%',
            },
        },
        stroke: {
            show: true,
            width: 5,
            colors: ['transparent']
        },
        xaxis: {
            categories: [''],
            axisTicks: {
                show: false,
                borderType: 'solid',
                color: '#78909C',
                height: 6,
                offsetX: 0,
                offsetY: 0
            },
            title: {
                text: 'Total Forecasted Value',
                offsetX: 0,
                offsetY: -30,
                style: {
                    color: '#78909C',
                    fontSize: '12px',
                    fontWeight: 400,
                },
            },
        },
        yaxis: {
            labels: {
                formatter: function (value: any) {
                    return "$" + value + "k";
                }
            },
            tickAmount: 4,
            min: 0
        },
        fill: {
            opacity: 1
        },
        legend: {
            show: true,
            position: 'bottom',
            horizontalAlign: 'center',
            fontWeight: 500,
            offsetX: 0,
            offsetY: -14,
            itemMargin: {
                horizontal: 8,
                vertical: 0
            },
            markers: {
                width: 10,
                height: 10,
            }
        },
        colors: chartColors
    };
    return (
        <React.Fragment>
            <ReactApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="bar"
                height="341"

                data-colors='["--vz-primary", "--vz-success", "--vz-warning"]'
                data-colors-minimal='["--vz-primary-rgb, 0.75", "--vz-primary", "--vz-primary-rgb, 0.55"]'
                data-colors-creative='["--vz-primary", "--vz-secondary", "--vz-info"]'
                data-colors-corporate='["--vz-primary", "--vz-success", "--vz-secondary"]'
                data-colors-galaxy='["--vz-primary", "--vz-secondary", "--vz-info"]'
                data-colors-classic='["--vz-primary", "--vz-warning", "--vz-secondary"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

const DealTypeCharts = ({ chartId, series }: any) => {
    const chartColors = useChartColors(chartId);


    var options: any = {
        chart: {
            height: 341,
            type: 'radar',
            dropShadow: {
                enabled: true,
                blur: 1,
                left: 1,
                top: 1
            },
            toolbar: {
                show: false
            },
        },
        stroke: {
            width: 2
        },
        fill: {
            opacity: 0.2
        },
        legend: {
            show: true,
            fontWeight: 500,
            offsetX: 0,
            offsetY: -8,
            markers: {
                width: 8,
                height: 8,
                radius: 6,
            },
            itemMargin: {
                horizontal: 10,
                vertical: 0
            }
        },
        markers: {
            size: 0
        },
        colors: chartColors,
        xaxis: {
            categories: ['2016', '2017', '2018', '2019', '2020', '2021']
        }
    };
    return (
        <React.Fragment>
            <ReactApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="radar"
                height="341"

                data-colors='["--vz-warning", "--vz-danger", "--vz-success"]'
                data-colors-minimal='["--vz-primary-rgb, 0.15", "--vz-primary-rgb, 0.35", "--vz-primary-rgb, 0.45"]'
                data-colors-modern='["--vz-warning", "--vz-secondary", "--vz-success"]'
                data-colors-interactive='["--vz-warning", "--vz-info", "--vz-primary"]'
                data-colors-corporate='["--vz-secondary", "--vz-info", "--vz-success"]'
                data-colors-classic='["--vz-secondary", "--vz-danger", "--vz-success"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

const BalanceOverviewCharts = ({ chartId, series }: any) => {
    const chartColors = useChartColors(chartId);


    var options: any = {
        chart: {
            height: 290,
            type: 'area',
            toolbar: 'false',
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        },
        yaxis: {
            labels: {
                formatter: function (value: any) {
                    return "$" + value + "k";
                }
            },
            tickAmount: 5,
            min: 0,
            max: 260
        },
        colors: chartColors,
        fill: {
            opacity: 0.06,
            colors: chartColors,
            type: 'solid'
        }
    };
    return (
        <React.Fragment>
            <ReactApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="area"
                height="290"

                data-colors='["--vz-success", "--vz-danger"]'
                data-colors-minimal='["--vz-primary", "--vz-info"]'
                data-colors-interactive='["--vz-info", "--vz-primary"]'
                data-colors-galaxy='["--vz-primary", "--vz-secondary"]'
                data-colors-classic='["--vz-primary", "--vz-secondary"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

export { SalesForecastCharts, DealTypeCharts, BalanceOverviewCharts };