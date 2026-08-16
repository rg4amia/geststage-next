import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input, Row, Col, Label, Form, UncontrolledDropdown, DropdownToggle, DropdownMenu, DropdownItem } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DmgPaiementsIndex = ({
    attenteDemarrage = [],
    attentePresence = [],
    dossiers = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [demarrageTab, setDemarrageTab] = useState('global');
    const [modalOpen, setModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);
    const [selectedPresenceIds, setSelectedPresenceIds] = useState<Array<string | number>>([]);

    const togglePresenceSelectAll = (checked: boolean) => {
        setSelectedPresenceIds(checked ? attentePresence.map((p: any) => p.id) : []);
    };

    const togglePresenceRow = (id: string | number, checked: boolean) => {
        setSelectedPresenceIds((prev) => (checked ? [...prev, id] : prev.filter((rowId) => rowId !== id)));
    };

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const toggleDemarrageTab = (tab: string) => {
        if (demarrageTab !== tab) setDemarrageTab(tab);
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

    const presenceColumns = [
        {
            header: () => (
                <Input
                    type="checkbox"
                    className="form-check-input"
                    checked={attentePresence.length > 0 && selectedPresenceIds.length === attentePresence.length}
                    onChange={(e: any) => togglePresenceSelectAll(e.target.checked)}
                />
            ),
            accessorKey: 'select',
            cell: (cell: any) => (
                <Input
                    type="checkbox"
                    className="form-check-input"
                    checked={selectedPresenceIds.includes(cell.row.original.id)}
                    onChange={(e: any) => togglePresenceRow(cell.row.original.id, e.target.checked)}
                />
            ),
            enableSorting: false,
        },
        { header: 'Date Création', accessorKey: 'date_creation', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Agence', accessorKey: 'agence.nom', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Entreprise', accessorKey: 'entreprise.raison_sociale', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Source de financement', accessorKey: 'source_financement', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Type de stage', accessorKey: 'type_stage', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Type de structure', accessorKey: 'type_structure', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Numéro AEJ', accessorKey: 'beneficiaire.matricule', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Nom et prénoms', accessorKey: 'beneficiaire', cell: (cell: any) => { const b = cell.getValue(); return b ? `${b.nom} ${b.prenoms}`.trim() : '-'; } },
        { header: 'Date de naissance', accessorKey: 'beneficiaire.date_naissance', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Debut', accessorKey: 'date_debut', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Fin', accessorKey: 'date_fin', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'N° Trésor Pay', accessorKey: 'tresor_pay', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'État dossier', accessorKey: 'statut', cell: (cell: any) => <Badge color="info">{cell.getValue() || '-'}</Badge> },
        { header: 'Pièce jointe', accessorKey: 'piece_jointe', cell: () => '-' },
        {
            header: 'Action',
            cell: (cell: any) => (
                <Button color="warning" size="sm" onClick={() => handleAction(cell.row.original)}>
                    <i className="ri-eye-line"></i>
                </Button>
            ),
        },
    ];

    const demarrageColumns = [
        {
            header: () => <Input type="checkbox" className="form-check-input" />,
            accessorKey: 'select',
            cell: () => <Input type="checkbox" className="form-check-input" />,
            enableSorting: false,
        },
        { header: 'Date Création', accessorKey: 'date_creation', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Agence', accessorKey: 'agence.nom', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Entreprise', accessorKey: 'entreprise.raison_sociale', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Source de financement', accessorKey: 'stage.source_financement', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Type de stagiaire', accessorKey: 'stage.type_stage', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Numéro AEJ', accessorKey: 'beneficiaire.matricule', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Nom et prénoms', accessorKey: 'beneficiaire', cell: (cell: any) => { const b = cell.getValue(); return b ? `${b.nom} ${b.prenoms}` : '-'; } },
        { header: 'Date de naissance', accessorKey: 'beneficiaire.date_naissance', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Validation', accessorKey: 'stage.date_validation', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Debut', accessorKey: 'stage.date_debut', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Fin', accessorKey: 'stage.date_fin', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'N° Trésor Pay', accessorKey: 'beneficiaire.tresor_pay', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'État dossier', accessorKey: 'statut', cell: (cell: any) => <Badge color="info">{cell.getValue() || '-'}</Badge> },
        { header: 'Pièce jointe', accessorKey: 'piece_jointe', cell: (cell: any) => '-' },
        { header: 'Action', cell: (cell: any) => (
            <Button color="primary" size="sm" onClick={() => handleAction(cell.row.original)}>
                Action
            </Button>
        ) },
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
                                    <Card className="border shadow-none mb-4">
                                        <CardHeader className="d-flex align-items-center bg-light border-bottom border-light">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">Traiment des stagiaires selectionnée</h5>
                                            <div className="d-flex gap-2">
                                                <Button color="light" size="sm" className="border shadow-none text-muted fw-medium"><i className="ri-eraser-line me-1"></i> Vider onglet</Button>
                                                <Button color="danger" outline size="sm" className="shadow-none fw-medium"><i className="ri-delete-bin-line me-1"></i> Vider tout</Button>
                                            </div>
                                        </CardHeader>
                                        <CardBody>
                                            <div className="d-flex flex-wrap gap-3 mb-2">
                                                <Button color="light" outline className="border-info text-secondary fw-medium shadow-none">
                                                    <i className="ri-printer-line me-1 text-info"></i> Générer Etat Paiement (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                </Button>
                                                <Button color="light" outline className="border-success text-dark fw-bold shadow-none" style={{ backgroundColor: '#fff' }}>
                                                    <i className="ri-printer-line me-1 text-success"></i> Canvas Beneficiaires TresorPay Depenses
                                                </Button>
                                                <Button color="light" outline className="border-info text-secondary fw-medium shadow-none">
                                                    <i className="ri-printer-line me-1 text-info"></i> Attestation Demarrage(.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                </Button>
                                                <Button color="primary" className="shadow-none" style={{ backgroundColor: '#5c6bc0', borderColor: '#5c6bc0' }}>
                                                    <i className="ri-check-double-line me-1"></i> Fusionner Fiche Trésor Pay
                                                </Button>
                                                <Button color="danger" className="shadow-none" style={{ backgroundColor: '#e57373', borderColor: '#e57373' }}>
                                                    <i className="ri-close-circle-line me-1"></i> Ajourner <i className="ri-arrow-down-s-line ms-1"></i>
                                                </Button>
                                                <Button color="success" className="shadow-none" style={{ backgroundColor: '#81c784', borderColor: '#81c784' }}>
                                                    <i className="ri-check-line me-1"></i> Valider paiement <i className="ri-arrow-down-s-line ms-1"></i>
                                                </Button>
                                                <Button color="info" className="shadow-none text-white" style={{ backgroundColor: '#00acc1', borderColor: '#00acc1' }}>
                                                    <i className="ri-folder-fill me-1"></i> Marquer dossiers <i className="ri-arrow-down-s-line ms-1"></i>
                                                </Button>
                                            </div>
                                        </CardBody>
                                    </Card>

                                    <Row className="g-3 mb-4 mt-2">
                                        <Col md={4}>
                                            <div className="p-3 rounded text-white text-center h-100 d-flex align-items-center justify-content-center" style={{ backgroundColor: '#4396df', fontSize: '13px' }}>
                                                Retard Cohorte 1: Date début est compris entre 1 à 5 qui a été validé par l'agence après le 10 du mois
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="p-3 rounded text-white text-center h-100 d-flex align-items-center justify-content-center" style={{ backgroundColor: '#f6cc58', fontSize: '13px' }}>
                                                Retard Cohorte 2: Date début est compris entre 10 qui a été validé par l'agence après le 20 du mois
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="p-3 rounded text-white text-center h-100 d-flex align-items-center justify-content-center" style={{ backgroundColor: '#f18271', fontSize: '13px' }}>
                                                Retard Cohorte 3: Date début est compris entre 20 qui a été validé par l'agence après le mois en cours
                                            </div>
                                        </Col>
                                    </Row>

                                    <Nav tabs className="nav-tabs-custom nav-success mb-3 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer', color: demarrageTab === 'global' ? '#405189' : '#878a99' }} className={classnames({ active: demarrageTab === 'global' })} onClick={() => toggleDemarrageTab('global')}>
                                                Cohorte Global
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer', color: demarrageTab === 'cohorte1' ? '#4396df' : '#878a99' }} className={classnames({ active: demarrageTab === 'cohorte1' })} onClick={() => toggleDemarrageTab('cohorte1')}>
                                                Retard Cohorte 1
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer', color: demarrageTab === 'cohorte2' ? '#f6cc58' : '#878a99' }} className={classnames({ active: demarrageTab === 'cohorte2' })} onClick={() => toggleDemarrageTab('cohorte2')}>
                                                Retard Cohorte 2
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer', color: demarrageTab === 'cohorte3' ? '#f18271' : '#878a99' }} className={classnames({ active: demarrageTab === 'cohorte3' })} onClick={() => toggleDemarrageTab('cohorte3')}>
                                                Retard Cohorte 3
                                            </NavLink>
                                        </NavItem>
                                    </Nav>

                                    <TabContent activeTab={demarrageTab}>
                                        <TabPane tabId="global">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte1">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte2">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte3">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white"
                                            />
                                        </TabPane>
                                    </TabContent>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Card className="border shadow-none mb-4">
                                        <CardHeader className="d-flex align-items-center bg-light border-bottom border-light">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">Traitement des stagiaires sélectionnés</h5>
                                        </CardHeader>
                                        <CardBody>
                                            <div className="d-flex flex-wrap gap-2">
                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-light border-info text-secondary fw-medium shadow-none">
                                                        <i className="ri-printer-line me-1 text-info"></i> Etat Paiement (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Tous les stagiaires (liste)</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Stagiaires sélectionnés</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <Button color="light" outline className="border-success text-dark fw-bold shadow-none" style={{ backgroundColor: '#fff' }}>
                                                    <i className="ri-file-excel-2-line me-1 text-success"></i> Canvas Bénéficiaires TrésorPay Dépenses
                                                </Button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-light border-info text-secondary fw-medium shadow-none">
                                                        <i className="ri-printer-line me-1 text-info"></i> Attestation Présence (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Stagiaires sélectionnés</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <Button color="primary" className="shadow-none" style={{ backgroundColor: '#5c6bc0', borderColor: '#5c6bc0' }}>
                                                    <i className="ri-check-double-line me-1"></i> Fusionner Fiche Trésor Pay
                                                </Button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-danger shadow-none" style={{ backgroundColor: '#e57373', borderColor: '#e57373' }}>
                                                        <i className="ri-close-circle-line me-1"></i> Ajourner <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Ajourner la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Ajourner sélection</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-success shadow-none" style={{ backgroundColor: '#81c784', borderColor: '#81c784' }}>
                                                        <i className="ri-check-line me-1"></i> Valider paiement <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Valider paiement de la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Sélectionner pour Valider</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-info shadow-none text-white" style={{ backgroundColor: '#00acc1', borderColor: '#00acc1' }}>
                                                        <i className="ri-folder-fill me-1"></i> Marquer dossiers <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Marquer la liste</DropdownItem>
                                                        {selectedPresenceIds.length > 0 && (
                                                            <DropdownItem>
                                                                Marquer sélection <Badge color="primary" className="ms-1">{selectedPresenceIds.length}</Badge>
                                                            </DropdownItem>
                                                        )}
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>
                                            </div>
                                        </CardBody>
                                    </Card>

                                    <TableContainerReactTable
                                        columns={presenceColumns}
                                        data={attentePresence || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="bg-success text-white"
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
