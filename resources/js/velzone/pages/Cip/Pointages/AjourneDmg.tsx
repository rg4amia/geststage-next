import { Head } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const AjourneDmgIndex = ({ instances }: any) => {
    const data = instances || [];
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedInstance, setSelectedInstance] = useState<any>(null);

    const handleEdit = (instance: any) => {
        setSelectedInstance(instance);
        setModalOpen(true);
    };

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
                header: 'Motif Ajournement (DMG)',
                accessorKey: 'dernier_ajournement',
                cell: (cell: any) => {
                    const motif = "Erreur sur les jours de présence (Ajourné par DMG)";

                    return <span className="text-danger"><i className="ri-error-warning-line me-1"></i>{motif}</span>;
                },
            },
            {
                header: 'Statut du Pointage',
                accessorKey: 'pointage_statut',
                cell: (cell: any) => {
                    return <span className="badge bg-danger-subtle text-danger">AJOURNE DMG</span>;
                },
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    return (
                        <div className="d-flex gap-2">
                            <Button color="primary" size="sm" onClick={() => handleEdit(cell.row.original)} title="Corriger">
                                <i className="ri-edit-line"></i> Corriger
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
            <Head title="Pointages Ajournés (DMG)" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages Ajournés" pageTitle="Espace CIP" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0 text-danger">Pointages rejetés par la DMG</h4>
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
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            {/* Modal de correction */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Corriger le Pointage</ModalHeader>
                <ModalBody>
                    <p>Veuillez corriger le nombre de jours de présence pour ce stagiaire, ou mettre à jour la documentation (ex: Rib, Fiche).</p>
                    <div className="mb-3">
                        <label className="form-label">Jours de présence corrigés</label>
                        <Input type="number" defaultValue={30} />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="primary" onClick={() => setModalOpen(!modalOpen)}>Enregistrer & Soumettre au CA</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default AjourneDmgIndex;
