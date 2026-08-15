import React, { useEffect, useState } from 'react';
import useChartColors from 'Components/Common/useChartColors';

const ApexChart = ({ height, className, ...props }: any) => {
    const [ChartComponent, setChartComponent] = useState<any>(null);

    useEffect(() => {
        let isMounted = true;

        import('react-apexcharts').then((module) => {
            if (isMounted) {
                setChartComponent(() => (module as any).default ?? module);
            }
        });

        return () => {
            isMounted = false;
        };
    }, []);

    const domProps = Object.fromEntries(
        Object.entries(props).filter(([key]) => key === 'id' || key === 'dir' || key.startsWith('data-')),
    );

    const cloneForApex = (value: any): any => {
        if (Array.isArray(value)) {
            return value.map(cloneForApex);
        }

        if (value && typeof value === 'object') {
            return Object.fromEntries(
                Object.entries(value).map(([key, nestedValue]) => [key, cloneForApex(nestedValue)]),
            );
        }

        return value;
    };

    if (!ChartComponent) {
        return <div {...domProps} className={className} style={{ minHeight: height }} />;
    }

    return (
        <ChartComponent
            {...props}
            options={cloneForApex(props.options)}
            series={cloneForApex(props.series)}
            height={height}
            className={className}
        />
    );
};

const AudiencesCharts = ({ chartId, series }: any) => {
    const chartAudienceColumnChartsColors = useChartColors(chartId);

    var options: any = {
        chart: {
            type: 'bar',
            height: 309,
            stacked: true,
            toolbar: {
                show: false,
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '20%',
                borderRadius: 6,
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: true,
            position: 'bottom',
            horizontalAlign: 'center',
            fontWeight: 400,
            fontSize: '8px',
            offsetX: 0,
            offsetY: 0,
            markers: {
                width: 9,
                height: 9,
                radius: 4,
            },
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        grid: {
            show: false,
        },
        colors: chartAudienceColumnChartsColors,
        xaxis: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            axisTicks: {
                show: false,
            },
            axisBorder: {
                show: true,
                strokeDashArray: 1,
                height: 1,
                width: '100%',
                offsetX: 0,
                offsetY: 0
            },
        },
        yaxis: {
            show: false
        },
        fill: {
            opacity: 1
        }
    };
    return (
        <React.Fragment>
            <ApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="bar"
                height="309"

                data-colors='["--vz-success", "--vz-light"]'
                data-colors-minimal='["--vz-primary", "--vz-light"]'
                data-colors-modern='["--vz-primary", "--vz-light"]'
                data-colors-interactive='["--vz-primary", "--vz-light"]'
                data-colors-creative='["--vz-secondary", "--vz-light"]'
                data-colors-corporate='["--vz-primary", "--vz-light"]'
                data-colors-galaxy='["--vz-primary", "--vz-light"]'
                data-colors-classic='["--vz-primary", "--vz-secondary"]'
                data-colors-vintage='["--vz-primary", "--vz-success-rgb, 0.5"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

const AudiencesSessionsCharts = ({ chartId, series }: any) => {
    const chartHeatMapBasicColors = useChartColors(chartId);
    const heatMapRangeColors = [
        chartHeatMapBasicColors[0] ?? '#0ab39c',
        chartHeatMapBasicColors[1] ?? '#299cdb',
    ];


    var options: any = {
        chart: {
            height: 400,
            type: 'heatmap',
            offsetX: 0,
            offsetY: -8,
            toolbar: {
                show: false
            }
        },
        plotOptions: {
            heatmap: {
                colorScale: {
                    ranges: [{
                        from: 0,
                        to: 50,
                            color: heatMapRangeColors[0]
                    },
                    {
                        from: 51,
                        to: 100,
                            color: heatMapRangeColors[1]
                    },
                    ],
                },

            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: true,
            horizontalAlign: 'center',
            offsetX: 0,
            offsetY: 20,
            markers: {
                width: 20,
                height: 6,
                radius: 2,
            },
            itemMargin: {
                horizontal: 12,
                vertical: 0
            },
        },
        colors: chartHeatMapBasicColors,
        tooltip: {
            y: [{
                formatter: function (y: any) {
                    if (typeof y !== "undefined") {
                        return y.toFixed(0) + "k";
                    }
                    return y;
                }
            }]
        }
    };
    return (
        <React.Fragment>
            <ApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="heatmap"
                height="400"

                data-colors='["--vz-success", "--vz-info"]'
                data-colors-minimal='["--vz-info", "--vz-primary"]'
                data-colors-modern='["--vz-success", "--vz-secondary"]'
                data-colors-interactive='["--vz-info", "--vz-primary"]'
                data-colors-creative='["--vz-primary", "--vz-success"]'
                data-colors-corporate='["--vz-secondary", "--vz-primary"]'
                data-colors-galaxy='["--vz-primary", "--vz-secondary"]'
                data-colors-classic='["--vz-primary", "--vz-danger"]'
                data-colors-vintage='["--vz-success", "--vz-secondary"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

const CountriesCharts = ({ chartId, series }: any) => {
    const barchartCountriesColors = useChartColors(chartId);

    var options: any = {
        chart: {
            type: 'bar',
            height: 436,
            toolbar: {
                show: false,
            }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
                distributed: true,
                dataLabels: {
                    position: 'top',
                },
            }
        },
        colors: barchartCountriesColors,
        dataLabels: {
            enabled: true,
            offsetX: 32,
            style: {
                fontSize: '12px',
                fontWeight: 400,
                colors: ['#adb5bd']
                // colors:['#878a99']
            }
        },

        legend: {
            show: false,
        },
        grid: {
            show: false,
        },
        xaxis: {
            categories: ['India', 'United States', 'China', 'Indonesia', 'Russia', 'Bangladesh', 'Canada', 'Brazil', 'Vietnam', 'UK'],
        },
    };
    return (
        <React.Fragment>
            <ApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="bar"
                height="436"

                data-colors='["--vz-info", "--vz-info", "--vz-info", "--vz-info", "--vz-danger", "--vz-info", "--vz-info", "--vz-info", "--vz-info", "--vz-info"]'
                data-colors-minimal='["--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary-rgb, 0.45", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary"]'
                data-colors-material='["--vz-primary", "--vz-primary", "--vz-info", "--vz-info", "--vz-danger", "--vz-primary", "--vz-primary", "--vz-warning", "--vz-primary", "--vz-primary"]'
                data-colors-galaxy='["--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4", "--vz-primary-rgb, 0.4"]'
                data-colors-classic='["--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary-rgb, 0.45", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary", "--vz-primary"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};

const UsersByDeviceCharts = ({ chartId, series }: any) => {
    const dountchartUserDeviceColors = useChartColors(chartId);

    const options: any = {
        labels: ["Desktop", "Mobile", "Tablet"],
        chart: {
            type: "donut",
            height: 219,
        },
        plotOptions: {
            pie: {
                size: 100,
                donut: {
                    size: "76%",
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        legend: {
            show: false,
            position: 'bottom',
            horizontalAlign: 'center',
            offsetX: 0,
            offsetY: 0,
            markers: {
                width: 20,
                height: 6,
                radius: 2,
            },
            itemMargin: {
                horizontal: 12,
                vertical: 0
            },
        },
        stroke: {
            width: 0
        },
        yaxis: {
            labels: {
                formatter: function (value: any) {
                    return value + 'k Users';
                }
            },
            tickAmount: 4,
            min: 0
        },
        colors: dountchartUserDeviceColors,
    };
    return (
        <React.Fragment>
            <ApexChart dir="ltr"
                options={options}
                series={series}
                id={chartId}
                type="donut"
                height="219"

                data-colors='["--vz-primary", "--vz-warning", "--vz-info"]'
                data-colors-minimal='["--vz-primary", "--vz-primary-rgb, 0.60", "--vz-primary-rgb, 0.75"]'
                data-colors-galaxy='["--vz-primary", "--vz-primary-rgb, .75", "--vz-primary-rgb, 0.60"]'
                className="apex-charts"
            />
        </React.Fragment>
    );
};


export { AudiencesCharts, AudiencesSessionsCharts, CountriesCharts, UsersByDeviceCharts };
