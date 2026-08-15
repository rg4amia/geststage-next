import React from 'react';
import { Head } from '@inertiajs/react';
import { Grid } from 'gridjs-react';
import { html } from 'gridjs';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';

import BreadCrumb from '../../../Components/Common/BreadCrumb';
import 'gridjs/dist/theme/mermaid.css';

const tableData = [
    [
        '01',
        'Jonathan',
        html('<a href="mailto:jonathan@example.com">jonathan@example.com</a>'),
        'Senior Implementation Architect',
        'Hauck Inc',
        'Holy See',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '02',
        'Harold',
        html('<a href="mailto:harold@example.com">harold@example.com</a>'),
        'Forward Creative Coordinator',
        'Metz Inc',
        'Iran',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '03',
        'Shannon',
        html('<a href="mailto:shannon@example.com">shannon@example.com</a>'),
        'Legacy Functionality Associate',
        'Zemlak Group',
        'South Georgia',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '04',
        'Robert',
        html('<a href="mailto:robert@example.com">robert@example.com</a>'),
        'Product Accounts Technician',
        'Hoeger',
        'San Marino',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '05',
        'Noel',
        html('<a href="mailto:noel@example.com">noel@example.com</a>'),
        'Customer Data Director',
        'Howell - Rippin',
        'Germany',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '06',
        'Traci',
        html('<a href="mailto:traci@example.com">traci@example.com</a>'),
        'Corporate Markets Consultant',
        'Donnelly LLC',
        'Canada',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '07',
        'Kerry',
        html('<a href="mailto:kerry@example.com">kerry@example.com</a>'),
        'Lead Integration Manager',
        'Rutherford Group',
        'France',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '08',
        'Perry',
        html('<a href="mailto:perry@example.com">perry@example.com</a>'),
        'Dynamic Brand Strategist',
        'Schinner PLC',
        'Italy',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '09',
        'Estelle',
        html('<a href="mailto:estelle@example.com">estelle@example.com</a>'),
        'National Data Liaison',
        'Leannon Group',
        'Spain',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
    [
        '10',
        'Noemi',
        html('<a href="mailto:noemi@example.com">noemi@example.com</a>'),
        'Chief Directives Officer',
        'Heller - Green',
        'Belgium',
        html('<a href="#" class="text-decoration-underline text-body">Details</a>'),
    ],
];

const columns = [
    { id: 'id', name: 'ID', width: '90px' },
    { id: 'name', name: 'Name' },
    { id: 'email', name: 'Email' },
    { id: 'position', name: 'Position' },
    { id: 'company', name: 'Company' },
    { id: 'country', name: 'Country' },
    { id: 'actions', name: 'Actions' },
];

const gridLanguage = {
    search: {
        placeholder: 'Type a keyword...',
    },
    pagination: {
        previous: 'Previous',
        next: 'Next',
        showing: 'Showing',
        results: () => 'results',
        to: 'to',
        of: 'of',
    },
};

const GridJs = () => {
    return (
        <React.Fragment>
            <Head title="Grid Js" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Grid Js" pageTitle="Tables" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">
                                        Base Example
                                    </h4>
                                </CardHeader>
                                <CardBody>
                                    <div id="table-gridjs">
                                        <Grid
                                            columns={columns}
                                            data={tableData}
                                            search
                                            sort
                                            pagination={{
                                                enabled: true,
                                                limit: 5,
                                                summary: true,
                                            }}
                                            language={gridLanguage}
                                        />
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">
                                        Card Table
                                    </h4>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <div className="table-card">
                                        <Grid
                                            columns={columns.slice(1, 6)}
                                            data={tableData.map((row) =>
                                                row.slice(1, 6),
                                            )}
                                            sort
                                            pagination={false}
                                        />
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default GridJs;
