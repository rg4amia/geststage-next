import React, { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const PointagesIndex = ({ instances, moisActuel, periode }: any) => {
    const data = instances || [];

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
                header: 'Statut du Pointage',
                accessorKey: 'pointage_statut',
                cell: (cell: any) => {
                    const pointages = cell.row.original.stage?.pointages || [];
                    const pointageMois = pointages.find((p: any) => p.periode_id === periode?.id);
                    
                    if (!pointageMois) {
                        return <span className="badge bg-warning-subtle text-warning">À Saisir</span>;
                    }
                    if (pointageMois.statut === 'SOUMIS') {
                        return <span className="badge bg-info-subtle text-info">Soumis au CA</span>;
                    }
                    if (pointageMois.statut === 'VALIDE') {
                        return <span className="badge bg-success-subtle text-success">Validé</span>;
                    }
                    return <span className="badge bg-secondary-subtle text-secondary">{pointageMois.statut}</span>;
                },
            },
            {
                header: 'Jours Présence',
                cell: (cell: any) => {
                    const pointages = cell.row.original.stage?.pointages || [];
                    const pointageMois = pointages.find((p: any) => p.periode_id === periode?.id);
                    
                    if (pointageMois && pointageMois.statut !== 'AJOURNE_CA') {
                        return <span>{pointageMois.jours_presence}</span>;
                    }
                    return (
                        <div className="d-flex align-items-center">
                            <Input 
                                type="number" 
                                defaultValue={pointageMois ? pointageMois.jours_presence : 30} 
                                style={{ width: '80px' }} 
                            />
                        </div>
                    );
                },
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const pointages = cell.row.original.stage?.pointages || [];
                    const pointageMois = pointages.find((p: any) => p.periode_id === periode?.id);

                    if (pointageMois && pointageMois.statut !== 'AJOURNE_CA') {
                         return (
                            <Button color="light" size="sm" className="btn-icon" disabled title="Déjà soumis">
                                <i className="ri-check-line"></i>
                            </Button>
                         );
                    }

                    return (
                        <div className="d-flex gap-2">
                            <Button color="success" size="sm" title="Soumettre au CA">
                                Soumettre
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [periode]
    );

    return (
        <React.Fragment>
            <Head title="Pointages CIP" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages Mensuels" pageTitle="Espace CIP" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex justify-content-between align-items-center">
                                    <h4 className="card-title mb-0">Saisie des Présences</h4>
                                    <div className="d-flex align-items-center gap-2">
                                        <label className="mb-0">Mois :</label>
                                        <Input type="month" defaultValue={moisActuel} />
                                    </div>
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

export default PointagesIndex;
