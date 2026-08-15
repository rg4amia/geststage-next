import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DesseStagiairesIndex = ({
    attenteValidation = [],
    doublons = [],
    statistiques = {}
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [processing, setProcessing] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);
    const [motif, setMotif] = useState('');

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const handleAjourner = (stagiaire: any) => {
        setSelectedStagiaire(stagiaire);
        setMotif('');
        setModalOpen(true);
    };

    const confirmAjournement = () => {
        if (!selectedStagiaire) {
            return;
        }

        setProcessing(true);
        router.post(`/desse/stagiaires/ajourner/${selectedStagiaire.id}`, { motif }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setModalOpen(false);
                setSelectedStagiaire(null);
                setMotif('');
            },
        });
    };

    const validerStagiaire = (id: number) => {
        if (confirm('Valider ce stagiaire ?')) {
            router.post(`/desse/stagiaires/valider/${id}`, {}, {
                preserveScroll: true,
            });
        }
    };

    const traiterDoublon = (id: number) => {
        if (confirm('Marquer ce doublon comme traité ?')) {
            setProcessing(true);
            router.post(`/desse/stagiaires/doublons/${id}/traiter`, {}, {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            });
        }
    };

    const validationColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1">
                            {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{cell.row.original.beneficiaire?.matricule}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Entreprise',
            accessorKey: 'entreprise.raison_sociale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Agence Régionale',
            accessorKey: 'agence.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Actions',
            cell: (cell: any) => {
                return (
                    <div className="d-flex gap-2">
                        <Button color="success" size="sm" onClick={() => validerStagiaire(cell.row.original.id)} disabled={processing}>
                            <i className="ri-check-line align-bottom me-1"></i> Valider
                        </Button>
                        <Button color="danger" size="sm" onClick={() => handleAjourner(cell.row.original)}>
                            <i className="ri-close-line align-bottom me-1"></i> Rejeter
                        </Button>
                    </div>
                );
            },
        },
    ];

    const doublonsColumns = [
        {
            header: 'Bénéficiaire (Doublon)',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1 text-danger">
                            {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{cell.row.original.beneficiaire?.matricule}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Conflit Avec',
            accessorKey: 'conflit_id',
            cell: (cell: any) => `Dossier #${cell.getValue()}`,
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => <Badge color="warning">Doublon Suspecté</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="primary" size="sm" onClick={() => traiterDoublon(cell.row.original.id)} disabled={processing}>
                    <i className="ri-settings-3-line align-bottom me-1"></i> Traiter le doublon
                </Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DESSE - Validation Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Suivi des Stagiaires" pageTitle="Direction DESSE" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Validation & Anomalies</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente Validation <Badge color="primary" className="ms-1">{attenteValidation.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Doublons Suspectés <Badge color="danger" className="ms-1">{doublons.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Statistiques Globales
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
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
                                        columns={doublonsColumns}
                                        data={doublons || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <Row className="text-center p-4">
                                        <Col md={4}>
                                            <div className="border p-3 rounded">
                                                <h5 className="text-primary mb-1">{statistiques?.attente_validation || 0}</h5>
                                                <p className="text-muted mb-0">En attente de vérification</p>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="border p-3 rounded">
                                                <h5 className="text-warning mb-1">{statistiques?.doublons || 0}</h5>
                                                <p className="text-muted mb-0">Doublons suspects</p>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="border p-3 rounded">
                                                <h5 className="text-success mb-1">{statistiques?.total || 0}</h5>
                                                <p className="text-muted mb-0">Total des cas suivis</p>
                                            </div>
                                        </Col>
                                    </Row>
                                </TabPane>

                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* Modal Rejet DESSE */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Rejeter le dossier</ModalHeader>
                <ModalBody>
                    <p>En rejetant ce dossier, il retournera dans la corbeille "Rejetés DESSE" du Chef d'Agence.</p>
                    {selectedStagiaire && (
                        <p className="fw-medium mb-2">
                            Dossier : {selectedStagiaire.beneficiaire?.nom} {selectedStagiaire.beneficiaire?.prenoms}
                        </p>
                    )}
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif du rejet</label>
                        <Input
                            type="textarea"
                            id="motif"
                            rows={3}
                            placeholder="Ex: Informations incomplètes..."
                            value={motif}
                            onChange={(event) => setMotif(event.target.value)}
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjournement} disabled={processing}>Confirmer le Rejet</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DesseStagiairesIndex;
