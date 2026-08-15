import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input, Row, Col, Label, Form } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DmgPaiementsIndex = ({
    attenteDemarrage = [],
    attentePresence = [],
    dossiers = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const handleAction = (stagiaire: any) => {
        setSelectedStagiaire(stagiaire);
        setModalOpen(true);
    };

    const confirmAction = () => {
        setProcessing(true);
        // Api call simulation
        setTimeout(() => {
            setProcessing(false);
            setModalOpen(false);
        }, 500);
    };

    const commonColumns = [
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
            header: 'Montant (FCFA)',
            accessorKey: 'montant',
            cell: (cell: any) => <span className="fw-bold">{cell.getValue() || '0'} FCFA</span>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="primary" size="sm" onClick={() => handleAction(cell.row.original)}>
                    Inclure dans un dossier
                </Button>
            ),
        },
    ];

    const dossierColumns = [
        {
            header: 'Numéro Dossier',
            accessorKey: 'numero',
            cell: (cell: any) => <span className="fw-medium text-primary">{cell.getValue()}</span>,
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => <Badge color="warning">En élaboration</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="success" size="sm">Générer OP</Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DMG - Préparation des Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Préparation des Paiements" pageTitle="DMG" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Constitution des dossiers de paiement</h4>
                        </CardHeader>
                        <CardBody>
                            <Form className="mb-4 bg-light p-3 border rounded">
                                <h5 className="fs-13 fw-medium mb-3">Traitement des paiements</h5>
                                <Row className="g-3 mb-3">
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Periode Pointage</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Tout</option>
                                        </Input>
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Agence</Label>
                                        <Input type="select" defaultValue="ABOBO">
                                            <option value="">Tout</option>
                                            <option value="ABOBO">ABOBO</option>
                                        </Input>
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Source Financement</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Tout</option>
                                        </Input>
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Type de Stage</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Tout</option>
                                        </Input>
                                    </Col>
                                </Row>
                                <Row className="g-3 mb-3">
                                    <Col md={4}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Type de structure</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Sélectionner un type de structure</option>
                                        </Input>
                                    </Col>
                                    <Col md={4}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Dossier physique déposé</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Tout</option>
                                        </Input>
                                    </Col>
                                    <Col md={4}>
                                        <Label className="form-label text-uppercase fs-11 fw-bold text-danger">Dossier (Stagiaire ajournée)</Label>
                                        <Input type="select" defaultValue="">
                                            <option value="">Sélectionner un dossier</option>
                                        </Input>
                                    </Col>
                                </Row>
                                <Row className="g-3 mb-3">
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Date Debut</Label>
                                        <Input type="date" placeholder="dd / mm / yyyy" />
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Date Fin</Label>
                                        <Input type="date" placeholder="dd / mm / yyyy" />
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Date Validation Debut</Label>
                                        <Input type="date" placeholder="dd / mm / yyyy" />
                                    </Col>
                                    <Col md={3}>
                                        <Label className="form-label text-uppercase fs-11 text-muted fw-bold">Date Validation Fin</Label>
                                        <Input type="date" placeholder="dd / mm / yyyy" />
                                    </Col>
                                </Row>
                                <Row>
                                    <Col>
                                        <Button color="success" type="button">
                                            Rechercher
                                        </Button>
                                    </Col>
                                </Row>
                            </Form>

                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente Démarrage <Badge color="primary" className="ms-1">{attenteDemarrage.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Attente Présence <Badge color="primary" className="ms-1">{attentePresence.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Gestion Multi-Dossiers <Badge color="info" className="ms-1">{dossiers.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={attenteDemarrage || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="2">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={attentePresence || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <TableContainerReactTable
                                        columns={dossierColumns}
                                        data={dossiers || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Création / Ajout au dossier</ModalHeader>
                <ModalBody>
                    <p>Choisissez le dossier dans lequel inclure ce paiement.</p>
                    <div className="mb-3">
                        <label className="form-label">Dossier existant ou Nouveau</label>
                        <Input type="select">
                            <option>-- Nouveau Dossier --</option>
                            <option>Dossier #2023-01</option>
                        </Input>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="primary" onClick={confirmAction} disabled={processing}>Confirmer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DmgPaiementsIndex;
