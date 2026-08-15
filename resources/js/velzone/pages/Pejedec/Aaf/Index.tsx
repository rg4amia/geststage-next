import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Badge, Button, Card, CardBody, CardHeader, Col, Container, Input, Nav, NavItem, NavLink, Row, TabContent, TabPane } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const PejedecAafIndex = ({
    attenteValidation = [],
    paiementsAjournes = [],
    correctionsAValider = [],
    attentePaiement = [],
    statistiques = {},
    moisActuel,
    periode,
    sourceFinancement,
    focus = 'validation',
}: any) => {
    const [activeTab, setActiveTab] = useState(
        focus === 'ajournes' ? '2' : focus === 'corrections' ? '3' : focus === 'paiement' ? '4' : '1',
    );

    const goToMonth = (value: string) => {
        router.get('/pejedec/af', { mois: value }, { preserveScroll: true, preserveState: true });
    };

    const badgeForStatus = (status?: string) => {
        switch (status) {
            case 'SOUMIS':
                return <Badge color="info">Soumis</Badge>;
            case 'AJOURNE_CA':
                return <Badge color="warning">Ajourné CA</Badge>;
            case 'AJOURNE_DMG':
                return <Badge color="danger">Ajourné DMG</Badge>;
            case 'CORRIGE_CIP':
                return <Badge color="primary">Corrigé CIP</Badge>;
            case 'OUVERT':
                return <Badge color="warning">Ouvert</Badge>;
            case 'PAYE':
                return <Badge color="success">Payé</Badge>;
            default:
                return <Badge color="secondary">{status || 'Inconnu'}</Badge>;
        }
    };

    const openPointage = () => {
        router.visit('/cip/pointages/pejedec');
    };

    const openDmg = () => {
        router.visit('/dmg/paiements');
    };

    const validationColumns = [
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
            header: 'Agence',
            accessorKey: 'stage.agence.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Période',
            accessorKey: 'periode.code',
            cell: (cell: any) => cell.row.original.periode?.code || cell.row.original.periode?.nom || '-',
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => badgeForStatus(cell.getValue()),
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="primary" size="sm" outline onClick={openPointage}>
                    Ouvrir le pointage
                </Button>
            ),
        },
    ];

    const ajournesColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => (
                <div>
                    <h5 className="fs-14 mb-1 text-danger">
                        {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                    </h5>
                    <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                </div>
            ),
        },
        {
            header: 'Motif / Observation',
            accessorKey: 'observation',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Jours',
            cell: (cell: any) => `${cell.row.original.jours_presents ?? 0} P / ${cell.row.original.jours_absents ?? 0} A`,
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => badgeForStatus(cell.getValue()),
        },
        {
            header: 'Date',
            accessorKey: 'date_soumission',
            cell: (cell: any) => cell.getValue() || cell.row.original.date_creation || '-',
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="warning" size="sm" outline onClick={openPointage}>
                    Revoir le dossier
                </Button>
            ),
        },
    ];

    const correctionsColumns = [
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
            header: 'Jours présents',
            accessorKey: 'jours_presents',
            cell: (cell: any) => cell.getValue() ?? '-',
        },
        {
            header: 'Jours absents',
            accessorKey: 'jours_absents',
            cell: (cell: any) => cell.getValue() ?? '-',
        },
        {
            header: 'Observation',
            accessorKey: 'observation',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => badgeForStatus(cell.getValue()),
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="info" size="sm" outline onClick={openPointage}>
                    Vérifier la correction
                </Button>
            ),
        },
    ];

    const attentePaiementColumns = [
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
            header: 'Agence',
            accessorKey: 'stage.agence.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Montant',
            accessorKey: 'montant',
            cell: (cell: any) => `${cell.getValue() || 0} FCFA`,
        },
        {
            header: 'Paiements',
            accessorKey: 'paiements_count',
            cell: (cell: any) => `${cell.getValue() || 0} associé(s)`,
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => badgeForStatus(cell.getValue()),
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="success" size="sm" outline onClick={openDmg}>
                    Aller à la DMG
                </Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="PEJEDEC / AAF" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="PEJEDEC / AAF" pageTitle="Espace métier" />

                    <Row className="mb-4">
                        <Col lg={8}>
                            <Card className="border-0 shadow-sm h-100">
                                <CardBody>
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div className="d-flex align-items-center gap-2 mb-2">
                                                <Badge color="primary">PEJEDEC / AAF</Badge>
                                                <span className="text-muted">Flux financier et corrections</span>
                                            </div>
                                            <h4 className="mb-2">Suivi métier consolidé</h4>
                                            <p className="text-muted mb-0">
                                                Les vues ci-dessous regroupent les états AAF restants du legacy et pointent vers les écrans
                                                de traitement déjà branchés.
                                            </p>
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
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row className="g-3 mb-3">
                        {[
                            { label: 'Attente validation', value: statistiques?.validation || 0, color: 'info' },
                            { label: 'Paiements ajournés', value: statistiques?.ajournes || 0, color: 'danger' },
                            { label: 'Corrections à valider', value: statistiques?.corrections || 0, color: 'primary' },
                            { label: 'Attente paiement', value: statistiques?.paiement || 0, color: 'warning' },
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
                                <h4 className="card-title mb-1">Corbeilles PEJEDEC / AAF</h4>
                                <p className="text-muted mb-0">Lecture unifiée des files métier héritées du legacy.</p>
                            </div>
                            <Input
                                type="month"
                                value={moisActuel}
                                onChange={(event) => goToMonth(event.target.value)}
                                style={{ maxWidth: '180px' }}
                            />
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-primary nav-justified mb-3">
                                {[
                                    { id: '1', label: 'Validation PEJEDEC', count: attenteValidation.length },
                                    { id: '2', label: 'Paiements ajournés', count: paiementsAjournes.length },
                                    { id: '3', label: 'Corrections à valider', count: correctionsAValider.length },
                                    { id: '4', label: 'Attente paiement', count: attentePaiement.length },
                                ].map((tab) => (
                                    <NavItem key={tab.id}>
                                        <NavLink
                                            style={{ cursor: 'pointer' }}
                                            className={classnames({ active: activeTab === tab.id })}
                                            onClick={() => setActiveTab(tab.id)}
                                        >
                                            {tab.label} <Badge color="light" className="ms-1 text-dark">{tab.count}</Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={activeTab}>
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={validationColumns}
                                        data={attenteValidation || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>

                                <TabPane tabId="2">
                                    <TableContainerReactTable
                                        columns={ajournesColumns}
                                        data={paiementsAjournes || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <TableContainerReactTable
                                        columns={correctionsColumns}
                                        data={correctionsAValider || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>

                                <TabPane tabId="4">
                                    <TableContainerReactTable
                                        columns={attentePaiementColumns}
                                        data={attentePaiement || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default PejedecAafIndex;
