import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Badge, Card, CardBody, CardHeader, Col, Container, Input, Nav, NavItem, NavLink, Row, TabContent, TabPane } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const PointagesPejedec = ({
    attente = [],
    effectues = [],
    ajournesCA = [],
    ajournesDMG = [],
    moisActuel,
    periode,
    sourceFinancement,
}: any) => {
    const [activeTab, setActiveTab] = useState('1');

    const columns = useMemo(
        () => [
            {
                header: 'Bénéficiaire',
                accessorKey: 'stage.beneficiaire.nom',
                cell: (cell: any) => (
                    <div>
                        <h5 className="fs-14 mb-1">
                            {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                    </div>
                ),
            },
            {
                header: 'Entreprise',
                accessorKey: 'stage.entreprise.raison_sociale',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Financement',
                cell: (cell: any) => (
                    <Badge color="primary" className="text-uppercase">
                        {cell.row.original.stage?.sourceFinancement?.nom || sourceFinancement?.nom || 'PEJEDEC'}
                    </Badge>
                ),
            },
            {
                header: 'Statut',
                accessorKey: 'pointage_statut',
                cell: (cell: any) => {
                    const pointages = cell.row.original.stage?.pointages || [];
                    const pointageMois = pointages.find((p: any) => p.periode_id === periode?.id);

                    if (!pointageMois) {
                        return <span className="badge bg-warning-subtle text-warning">À Saisir</span>;
                    }
                    if (pointageMois.statut === 'SOUMIS') {
                        return <span className="badge bg-info-subtle text-info">Soumis</span>;
                    }
                    if (pointageMois.statut === 'VALIDE') {
                        return <span className="badge bg-success-subtle text-success">Validé</span>;
                    }
                    if (pointageMois.statut === 'AJOURNE_CA') {
                        return <span className="badge bg-danger-subtle text-danger">Ajourné CA</span>;
                    }
                    return <span className="badge bg-secondary-subtle text-secondary">{pointageMois.statut}</span>;
                },
            },
            {
                header: 'Jours',
                cell: (cell: any) => {
                    const pointages = cell.row.original.stage?.pointages || [];
                    const pointageMois = pointages.find((p: any) => p.periode_id === periode?.id);

                    return pointageMois?.jours_presence ?? '-';
                },
            },
        ],
        [periode, sourceFinancement],
    );

    const tabs = [
        { id: '1', label: 'A saisir', data: attente },
        { id: '2', label: 'Soumis', data: effectues },
        { id: '3', label: 'Ajournés CA', data: ajournesCA },
        { id: '4', label: 'Ajournés DMG', data: ajournesDMG },
    ];

    return (
        <React.Fragment>
            <Head title="PEJEDEC - Pointages CIP" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages PEJEDEC" pageTitle="Espace CIP" />

                    <Row className="mb-4">
                        <Col lg={8}>
                            <Card className="border-0 shadow-sm h-100">
                                <CardBody>
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div className="d-flex align-items-center gap-2 mb-2">
                                                <Badge color="primary">PEJEDEC</Badge>
                                                <span className="text-muted">Financement verrouillé</span>
                                            </div>
                                            <h4 className="mb-2">Suivi des pointages PEJEDEC</h4>
                                            <p className="text-muted mb-0">
                                                Le filtre serveur conserve uniquement les stagiaires PEJEDEC et réutilise
                                                le même moteur de pointage que le flux standard.
                                            </p>
                                        </div>
                                        <div className="text-md-end">
                                            <p className="mb-1 text-muted">Mois actif</p>
                                            <h5 className="mb-0">{moisActuel}</h5>
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
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row className="g-3 mb-3">
                        {[
                            { label: 'A saisir', value: attente.length, color: 'warning' },
                            { label: 'Soumis', value: effectues.length, color: 'info' },
                            { label: 'Ajournés CA', value: ajournesCA.length, color: 'danger' },
                            { label: 'Ajournés DMG', value: ajournesDMG.length, color: 'secondary' },
                        ].map((item) => (
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
                                <h4 className="card-title mb-1">Files PEJEDEC</h4>
                                <p className="text-muted mb-0">Validation, soumission et correction des pointages du programme.</p>
                            </div>
                            <Input type="month" defaultValue={moisActuel} style={{ maxWidth: '180px' }} />
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-primary nav-justified mb-3">
                                {tabs.map((tab) => (
                                    <NavItem key={tab.id}>
                                        <NavLink
                                            style={{ cursor: 'pointer' }}
                                            className={classnames({ active: activeTab === tab.id })}
                                            onClick={() => setActiveTab(tab.id)}
                                        >
                                            {tab.label} <Badge color="light" className="ms-1 text-dark">{tab.data.length}</Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={activeTab}>
                                {tabs.map((tab) => (
                                    <TabPane key={tab.id} tabId={tab.id}>
                                        <TableContainerReactTable
                                            columns={columns}
                                            data={tab.data || []}
                                            isGlobalFilter={true}
                                            customPageSize={10}
                                            divClass="table-responsive table-card mb-3"
                                            tableClass="align-middle table-nowrap mb-0"
                                            theadClass="table-light"
                                            SearchPlaceholder="Rechercher..."
                                        />
                                    </TabPane>
                                ))}
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default PointagesPejedec;
