import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Badge, Button, Card, CardBody, CardHeader, Col, Container, Input, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

type Filters = {
    agence_id?: string;
    entreprise_id?: string;
    search?: string;
};

type Props = {
    title: string;
    pageTitle: string;
    mode: 'attente' | 'valides';
    dossiers?: { data?: any[] };
    filters?: Filters;
    sourceFinancement?: any;
    agences?: Array<{ id: number; nom: string }>;
    entreprises?: Array<{ id: number; raison_sociale: string }>;
    stats?: { attente?: number; valides?: number };
};

const DossierListPage = ({
    title,
    pageTitle,
    mode,
    dossiers = { data: [] },
    filters = {},
    sourceFinancement,
    agences = [],
    entreprises = [],
    stats = {},
}: Props) => {
    const [selectedFilters, setSelectedFilters] = useState({
        agence_id: filters.agence_id || '',
        entreprise_id: filters.entreprise_id || '',
        search: filters.search || '',
    });

    const rows = dossiers?.data || [];
    const isAttente = mode === 'attente';
    const endpoint = isAttente ? '/pejedec/attente-validation' : '/pejedec/valides';

    const search = () => {
        router.get(endpoint, selectedFilters, { preserveScroll: true, preserveState: true });
    };

    const validateDossier = (id: number) => {
        if (confirm('Valider ce dossier PEJEDEC ?')) {
            router.post(`/pejedec/dossiers/${id}/valider`, {}, { preserveScroll: true });
        }
    };

    const columns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div>
                    <h5 className="fs-14 mb-1">
                        {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                    </h5>
                    <p className="text-muted mb-0">{cell.row.original.beneficiaire?.matricule || '-'}</p>
                </div>
            ),
        },
        { header: 'Entreprise', accessorKey: 'entreprise.raison_sociale', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Agence', accessorKey: 'agence.nom', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Cohorte', accessorKey: 'cohorte', cell: (cell: any) => <Badge color="info">{cell.getValue()}</Badge> },
        { header: 'Début', accessorKey: 'stage.date_debut', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Contrat', accessorKey: 'contrat.numero', cell: (cell: any) => cell.getValue() || '-' },
        {
            header: 'Statut',
            accessorKey: 'corbeille.label',
            cell: (cell: any) => <Badge color={isAttente ? 'warning' : 'success'}>{cell.getValue() || '-'}</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => isAttente ? (
                <Button color="success" size="sm" outline onClick={() => validateDossier(cell.row.original.id)}>
                    Valider
                </Button>
            ) : (
                <Button color="primary" size="sm" outline onClick={() => router.visit('/cip/pointages/pejedec')}>
                    Pointages
                </Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title={title} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title={pageTitle} pageTitle="PEJEDEC" />

                    <Row className="mb-4">
                        <Col lg={8}>
                            <Card className="border-0 shadow-sm h-100">
                                <CardBody>
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div className="d-flex align-items-center gap-2 mb-2">
                                                <Badge color="primary">PEJEDEC</Badge>
                                                <span className="text-muted">{sourceFinancement?.code || 'PEJEDEC'}</span>
                                            </div>
                                            <h4 className="mb-2">{pageTitle}</h4>
                                            <p className="text-muted mb-0">
                                                Validation des dossiers PEJEDEC avant ouverture du pointage dédié.
                                            </p>
                                        </div>
                                        <div className="text-md-end">
                                            <p className="mb-1 text-muted">File attente</p>
                                            <h4 className="mb-0">{stats.attente || 0}</h4>
                                            <p className="text-muted mb-0">{stats.valides || 0} validé(s)</p>
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
                                    <p className="text-muted mb-0">Cohortes 1-10, 11-20, 21-31</p>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Card className="shadow-sm">
                        <CardHeader className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h4 className="card-title mb-1">{isAttente ? 'Dossiers à traiter' : 'Dossiers validés'}</h4>
                                <p className="text-muted mb-0">{rows.length} dossier(s) affiché(s)</p>
                            </div>
                            <div className="d-flex gap-2">
                                <Button color="light" onClick={() => router.visit(isAttente ? '/pejedec/valides' : '/pejedec/attente-validation')}>
                                    {isAttente ? 'Voir validés' : 'Voir attente'}
                                </Button>
                                <Button color="primary" outline onClick={search}>
                                    Rechercher
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3 mb-3">
                                <Col md={3}>
                                    <label className="form-label">Agence</label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.agence_id}
                                        onChange={(event) => setSelectedFilters((current) => ({ ...current, agence_id: event.target.value }))}
                                    >
                                        <option value="">Toutes</option>
                                        {agences.map((agence) => (
                                            <option key={agence.id} value={agence.id}>{agence.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <label className="form-label">Entreprise</label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.entreprise_id}
                                        onChange={(event) => setSelectedFilters((current) => ({ ...current, entreprise_id: event.target.value }))}
                                    >
                                        <option value="">Toutes</option>
                                        {entreprises.map((entreprise) => (
                                            <option key={entreprise.id} value={entreprise.id}>{entreprise.raison_sociale}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={6}>
                                    <label className="form-label">Recherche</label>
                                    <Input
                                        value={selectedFilters.search}
                                        onChange={(event) => setSelectedFilters((current) => ({ ...current, search: event.target.value }))}
                                        placeholder="Nom, prénoms ou matricule AEJ"
                                    />
                                </Col>
                            </Row>

                            <TableContainerReactTable
                                columns={columns}
                                data={rows}
                                isGlobalFilter={true}
                                customPageSize={10}
                                divClass="table-responsive table-card mb-3"
                                tableClass="align-middle table-nowrap mb-0"
                                theadClass="table-light"
                                SearchPlaceholder="Rechercher..."
                            />
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default DossierListPage;
