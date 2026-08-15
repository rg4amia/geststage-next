import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const MesStagiaires = ({ stagiaires }: any) => {
    const data = stagiaires || [];

    const columns = useMemo(
        () => [
            {
                header: 'Bénéficiaire',
                accessorKey: 'stage.beneficiaire.nom',
                cell: (cell: any) => (
                    <div className="d-flex align-items-center">
                        <div className="flex-grow-1">
                            <h5 className="fs-14 mb-1">
                                {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                            </h5>
                            <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                        </div>
                    </div>
                ),
            },
            {
                header: 'Entreprise',
                accessorKey: 'stage.entreprise.raison_sociale',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Période',
                accessorKey: 'stage.date_debut',
                cell: (cell: any) => {
                    const stage = cell.row.original.stage;
                    if (!stage) return '-';
                    return `${stage.date_debut || '?'} au ${stage.date_fin || '?'}`;
                }
            },
            {
                header: 'Corbeille Actuelle',
                accessorKey: 'corbeille_actuelle',
                cell: (cell: any) => (
                    <span className="badge bg-primary-subtle text-primary">
                        {cell.getValue()}
                    </span>
                ),
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    return (
                        <div className="d-flex gap-2">
                            <Button color="success" size="sm" className="btn-icon" title="Générer Contrat">
                                <i className="ri-file-text-line"></i>
                            </Button>
                            <Button color="info" size="sm" className="btn-icon" title="Uploader Fiche TM">
                                <i className="ri-upload-cloud-2-line"></i>
                            </Button>
                            <Button color="primary" size="sm" className="btn-icon" title="Transmettre CA">
                                <i className="ri-send-plane-line"></i>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        []
    );

    return (
        <React.Fragment>
            <Head title="Mes Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Mes Stagiaires" pageTitle="Espace CIP" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">Stagiaires en cours d'inscription</h4>
                                </CardHeader>
                                <CardBody>
                                    <TableContainerReactTable
                                        columns={columns}
                                        data={data}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher un stagiaire..."
                                    />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default MesStagiaires;
