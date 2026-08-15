import React from 'react';
import { Head, router } from '@inertiajs/react';
import { Badge, Button, Card, CardBody, CardHeader, Col, Container, Input, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

type FilterState = {
    mois: string;
    agence_id: string;
    entreprise_id: string;
    source_financement_id: string;
};

type OptionItem = {
    id: number | string;
    label: string;
};

type SummaryCard = {
    label: string;
    value: number;
    color: string;
};

type AafSectionPageProps = {
    title: string;
    pageTitle: string;
    badge: string;
    heroTitle: string;
    heroText: string;
    summaryCards: SummaryCard[];
    filters: FilterState;
    setFilters: React.Dispatch<React.SetStateAction<FilterState>>;
    onSearch: () => void;
    data: any[];
    columns: any[];
    moisActuel: string;
    periode?: any;
    sourceFinancement?: any;
    agences: OptionItem[];
    entreprises: OptionItem[];
    sourcesFinancement: OptionItem[];
    backLink: string;
    backLabel: string;
    searchPlaceholder?: string;
};

const AafSectionPage = ({
    title,
    pageTitle,
    badge,
    heroTitle,
    heroText,
    summaryCards,
    filters,
    setFilters,
    onSearch,
    data,
    columns,
    moisActuel,
    periode,
    sourceFinancement,
    agences,
    entreprises,
    sourcesFinancement,
    backLink,
    backLabel,
    searchPlaceholder = 'Rechercher...',
}: AafSectionPageProps) => {
    return (
        <React.Fragment>
            <Head title={title} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title={pageTitle} pageTitle="PEJEDEC / AAF" />

                    <Row className="mb-4">
                        <Col lg={8}>
                            <Card className="border-0 shadow-sm h-100">
                                <CardBody>
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div className="d-flex align-items-center gap-2 mb-2">
                                                <Badge color="primary">{badge}</Badge>
                                                <span className="text-muted">{heroText}</span>
                                            </div>
                                            <h4 className="mb-2">{heroTitle}</h4>
                                            <p className="text-muted mb-0">{heroText}</p>
                                        </div>
                                        <div className="text-md-end">
                                            <p className="mb-1 text-muted">Mois actif</p>
                                            <h5 className="mb-0">{moisActuel}</h5>
                                            <p className="text-muted mb-0">{periode?.code || periode?.nom || 'Période non résolue'}</p>
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                        <Col lg={4}>
                            <Card className="border-0 shadow-sm h-100 bg-light">
                                <CardBody>
                                    <p className="text-muted mb-2">Source de financement</p>
                                    <h4 className="mb-1">{sourceFinancement?.nom || 'PEJEDEC'}</h4>
                                    <p className="text-muted mb-0">{sourceFinancement?.code || 'PEJEDEC'}</p>
                                    <div className="d-flex gap-2 mt-3">
                                        <Button color="light" onClick={() => router.visit(backLink)}>
                                            {backLabel}
                                        </Button>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row className="g-3 mb-3">
                        {summaryCards.map((item) => (
                            <Col lg={3} md={6} key={item.label}>
                                <Card className="mb-0 shadow-sm">
                                    <CardBody>
                                        <p className="text-muted mb-1">{item.label}</p>
                                        <h3 className={`text-${item.color} mb-0`}>{item.value}</h3>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <Card className="shadow-sm">
                        <CardHeader className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h4 className="card-title mb-1">{pageTitle}</h4>
                                <p className="text-muted mb-0">Lecture ciblée, plus proche des écrans legacy.</p>
                            </div>
                            <Button color="primary" outline onClick={onSearch}>
                                Rechercher
                            </Button>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3 mb-3">
                                <Col md={3}>
                                    <label className="form-label">Période</label>
                                    <Input
                                        type="month"
                                        value={filters.mois}
                                        onChange={(event) =>
                                            setFilters((current) => ({ ...current, mois: event.target.value }))
                                        }
                                    />
                                </Col>
                                <Col md={3}>
                                    <label className="form-label">Agence</label>
                                    <Input
                                        type="select"
                                        value={filters.agence_id}
                                        onChange={(event) =>
                                            setFilters((current) => ({ ...current, agence_id: event.target.value }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {agences.map((agence) => (
                                            <option key={agence.id} value={agence.id}>
                                                {agence.label}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <label className="form-label">Entreprise</label>
                                    <Input
                                        type="select"
                                        value={filters.entreprise_id}
                                        onChange={(event) =>
                                            setFilters((current) => ({ ...current, entreprise_id: event.target.value }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {entreprises.map((entreprise) => (
                                            <option key={entreprise.id} value={entreprise.id}>
                                                {entreprise.label}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <label className="form-label">Source financement</label>
                                    <Input
                                        type="select"
                                        value={filters.source_financement_id}
                                        onChange={(event) =>
                                            setFilters((current) => ({
                                                ...current,
                                                source_financement_id: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {sourcesFinancement.map((source) => (
                                            <option key={source.id} value={source.id}>
                                                {source.label}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                            </Row>

                            <TableContainerReactTable
                                columns={columns}
                                data={data || []}
                                isGlobalFilter={true}
                                customPageSize={10}
                                divClass="table-responsive table-card mb-3"
                                tableClass="align-middle table-nowrap mb-0"
                                theadClass="table-light"
                                SearchPlaceholder={searchPlaceholder}
                            />
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default AafSectionPage;
