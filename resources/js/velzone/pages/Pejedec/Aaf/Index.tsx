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
    agences = [],
    entreprises = [],
    sourcesFinancement = [],
    filters = {},
    focus = 'validation',
}: any) => {
    const [activeTab, setActiveTab] = useState(
        focus === 'ajournes' ? '2' : focus === 'corrections' ? '3' : focus === 'paiement' ? '4' : '1',
    );
    const [selectedFilters, setSelectedFilters] = useState({
        mois: filters?.mois || moisActuel || '',
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || sourceFinancement?.id?.toString() || '3',
    });

    const search = () => {
        router.get(
            '/pejedec/af',
            {
                mois: selectedFilters.mois,
                agence_id: selectedFilters.agence_id,
                entreprise_id: selectedFilters.entreprise_id,
                source_financement_id: selectedFilters.source_financement_id,
            },
            { preserveScroll: true, preserveState: true },
        );
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

    const validerPointage = (id: number) => {
        if (confirm('Valider ce pointage PEJEDEC ?')) {
            router.post(`/pejedec/af/pointages/${id}/valider`, {}, {
                preserveScroll: true,
            });
        }
    };

    const validerCorrection = (id: number) => {
        if (confirm('Valider cette correction PEJEDEC ?')) {
            router.post(`/pejedec/af/pointages/${id}/valider-correction`, {}, {
                preserveScroll: true,
            });
        }
    };

    const genererPaiement = (id: number) => {
        if (confirm('Générer le paiement pour ce droit ?')) {
            router.post(`/pejedec/af/droits-paiement/${id}/generer`, {}, {
                preserveScroll: true,
            });
        }
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
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="success" size="sm" outline onClick={() => validerPointage(cell.row.original.id)}>
                        Valider
                    </Button>
                    <Button color="primary" size="sm" outline onClick={openPointage}>
                        Ouvrir le pointage
                    </Button>
                </div>
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
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="warning" size="sm" outline onClick={() => openPointage()}>
                        Revoir le dossier
                    </Button>
                    <Button color="info" size="sm" outline onClick={() => validerPointage(cell.row.original.id)}>
                        Revalider
                    </Button>
                </div>
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
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="info" size="sm" outline onClick={() => validerCorrection(cell.row.original.id)}>
                        Valider la correction
                    </Button>
                    <Button color="secondary" size="sm" outline onClick={openPointage}>
                        Vérifier la correction
                    </Button>
                </div>
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
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="success" size="sm" outline onClick={() => genererPaiement(cell.row.original.id)}>
                        Générer le paiement
                    </Button>
                    <Button color="secondary" size="sm" outline onClick={openDmg}>
                        Aller à la DMG
                    </Button>
                </div>
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

                    <Card className="shadow-sm mb-3">
                        <CardHeader className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h4 className="card-title mb-1">Corbeilles PEJEDEC / AAF</h4>
                                <p className="text-muted mb-0">Lecture unifiée des files métier héritées du legacy.</p>
                            </div>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3 mb-3">
                                <Col md={3}>
                                    <label className="form-label">Période</label>
                                    <Input
                                        type="month"
                                        value={selectedFilters.mois}
                                        onChange={(event) =>
                                            setSelectedFilters((current) => ({ ...current, mois: event.target.value }))
                                        }
                                    />
                                </Col>
                                <Col md={3}>
                                    <label className="form-label">Agence</label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.agence_id}
                                        onChange={(event) =>
                                            setSelectedFilters((current) => ({ ...current, agence_id: event.target.value }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {agences.map((agence: any) => (
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
                                        value={selectedFilters.entreprise_id}
                                        onChange={(event) =>
                                            setSelectedFilters((current) => ({
                                                ...current,
                                                entreprise_id: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {entreprises.map((entreprise: any) => (
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
                                        value={selectedFilters.source_financement_id}
                                        onChange={(event) =>
                                            setSelectedFilters((current) => ({
                                                ...current,
                                                source_financement_id: event.target.value,
                                            }))
                                        }
                                    >
                                        <option value="">Toutes</option>
                                        {sourcesFinancement.map((source: any) => (
                                            <option key={source.id} value={source.id}>
                                                {source.label}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                            </Row>
                            <div className="d-flex justify-content-end">
                                <Button color="primary" onClick={search}>
                                    Rechercher
                                </Button>
                            </div>

                            <hr className="my-4" />

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
