import React from 'react';
import { Col, Row } from 'reactstrap';
import ReportingChart, { type ReportingChartData } from './ReportingChart';

type Props = {
    charts: ReportingChartData[];
    loading?: boolean;
    error?: string | null;
};

const ReportingChartsGrid = ({
    charts,
    loading = false,
    error = null,
}: Props) => (
    <Row className="g-3">
        {charts.map((chart) => (
            <Col
                xxl={
                    chart.key === 'agences' ||
                    chart.key === 'evolution-mensuelle'
                        ? 12
                        : 6
                }
                key={chart.key}
            >
                <ReportingChart chart={chart} loading={loading} error={error} />
            </Col>
        ))}
    </Row>
);

export default ReportingChartsGrid;
