import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input, Row, Col, Label, UncontrolledDropdown, DropdownToggle, DropdownMenu, DropdownItem } from 'reactstrap';
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

    const getStatutBadgeColor = (statut?: string) => {
        switch ((statut || '').toUpperCase()) {
            case 'VALIDE':
            case 'TRAITE':
                return 'success';
            case 'AJOURNE':
            case 'AJOURNE_DMG':
            case 'AJOURNE_CB':
                return 'danger';
            case 'TRANSMIS_CB':
                return 'warning';
            case 'A_TRAITER':
                return 'info';
            default:
                return 'secondary';
        }
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
        { header: 'Nom et prénoms', accessorKey: 'beneficiaire', cell: (cell: any) => { const b = cell.getValue(); return <span className="fw-medium">{b ? `${b.nom} ${b.prenoms}`.trim() : '-'}</span>; } },
        { header: 'Date de naissance', accessorKey: 'beneficiaire.date_naissance', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Debut', accessorKey: 'date_debut', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Fin', accessorKey: 'date_fin', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'N° Trésor Pay', accessorKey: 'tresor_pay', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'État dossier', accessorKey: 'statut', cell: (cell: any) => <Badge color={getStatutBadgeColor(cell.getValue())} className="fs-11">{cell.getValue() || '-'}</Badge> },
        { header: 'Pièce jointe', accessorKey: 'piece_jointe', cell: () => '-' },
        {
            header: 'Action',
            cell: (cell: any) => (
                <button type="button" className="btn btn-soft-info btn-sm" title="Voir le détail" onClick={() => handleAction(cell.row.original)}>
                    <i className="ri-eye-line"></i>
                </button>
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
        { header: 'Nom et prénoms', accessorKey: 'beneficiaire', cell: (cell: any) => { const b = cell.getValue(); return <span className="fw-medium">{b ? `${b.nom} ${b.prenoms}` : '-'}</span>; } },
        { header: 'Date de naissance', accessorKey: 'beneficiaire.date_naissance', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Validation', accessorKey: 'stage.date_validation', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Debut', accessorKey: 'stage.date_debut', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Date Fin', accessorKey: 'stage.date_fin', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'N° Trésor Pay', accessorKey: 'beneficiaire.tresor_pay', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'État dossier', accessorKey: 'statut', cell: (cell: any) => <Badge color={getStatutBadgeColor(cell.getValue())} className="fs-11">{cell.getValue() || '-'}</Badge> },
        { header: 'Pièce jointe', accessorKey: 'piece_jointe', cell: (cell: any) => '-' },
        { header: 'Action', cell: (cell: any) => (
            <button type="button" className="btn btn-soft-info btn-sm" title="Voir le détail" onClick={() => handleAction(cell.row.original)}>
                <i className="ri-eye-line"></i>
            </button>
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
            cell: (cell: any) => <Badge color="warning" className="fs-11">En élaboration</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <button type="button" className="btn btn-soft-success btn-sm">
                    <i className="ri-file-list-3-line me-1"></i>Générer OP
                </button>
            ),
        },
    ];

    const resetFilters = () => {
        router.get(window.location.pathname, {}, { preserveState: false });
    };

    const statCards = [
        { label: 'Attente Démarrage', value: attenteDemarrage.length, icon: 'ri-flag-line', color: 'primary' },
        { label: 'Attente Présence', value: attentePresence.length, icon: 'ri-user-follow-line', color: 'info' },
        { label: 'Gestion Multi-Dossiers', value: dossiers.length, icon: 'ri-folder-2-line', color: 'warning' },
        { label: 'Total à traiter', value: attenteDemarrage.length + attentePresence.length, icon: 'ri-time-line', color: 'success' },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DMG - Préparation des Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Préparation des Paiements" pageTitle="DMG" />

                    <Row className="g-3 mb-3">
                        {statCards.map((s) => (
                            <Col xl={3} md={6} key={s.label}>
                                <Card className="card-animate mb-0 h-100">
                                    <CardBody className="p-3">
                                        <div className="d-flex align-items-center gap-3">
                                            <div className={`avatar-sm flex-shrink-0 bg-${s.color}-subtle rounded`}>
                                                <span className={`avatar-title text-${s.color} rounded fs-3 bg-transparent`}>
                                                    <i className={s.icon}></i>
                                                </span>
                                            </div>
                                            <div>
                                                <p className="text-uppercase fw-medium text-muted mb-0 fs-11">{s.label}</p>
                                                <h4 className="fs-22 fw-bold mb-0">{s.value}</h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <Card className="mb-3">
                        <CardBody className="pb-2">
                            <h5 className="fs-13 fw-medium mb-3 text-muted">
                                <i className="ri-filter-3-line me-1"></i>Traitement des paiements
                            </h5>
                            <Row className="g-2 align-items-end mb-2">
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-calendar-2-line me-1"></i>Periode Pointage
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Tout</option>
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-community-line me-1"></i>Agence
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="ABOBO">
                                        <option value="">Tout</option>
                                        <option value="ABOBO">ABOBO</option>
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-funds-line me-1"></i>Source Financement
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Tout</option>
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-briefcase-line me-1"></i>Type de Stage
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Tout</option>
                                    </Input>
                                </Col>
                            </Row>
                            <Row className="g-2 align-items-end mb-2">
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-building-4-line me-1"></i>Type de structure
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Sélectionner un type de structure</option>
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-attachment-2 me-1"></i>Dossier physique déposé
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Tout</option>
                                    </Input>
                                </Col>
                                <Col xs={12} sm={4} md={6}>
                                    <Label className="form-label fs-12 text-danger fw-medium mb-1">
                                        <i className="ri-error-warning-line me-1"></i>Dossier (Stagiaire ajournée)
                                    </Label>
                                    <Input type="select" bsSize="sm" defaultValue="">
                                        <option value="">Sélectionner un dossier</option>
                                    </Input>
                                </Col>
                            </Row>
                            <Row className="g-2 align-items-end">
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-calendar-line me-1"></i>Date Debut
                                    </Label>
                                    <Input type="date" bsSize="sm" />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-calendar-line me-1"></i>Date Fin
                                    </Label>
                                    <Input type="date" bsSize="sm" />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-calendar-check-line me-1"></i>Date Validation Debut
                                    </Label>
                                    <Input type="date" bsSize="sm" />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">
                                        <i className="ri-calendar-check-line me-1"></i>Date Validation Fin
                                    </Label>
                                    <Input type="date" bsSize="sm" />
                                </Col>
                            </Row>

                            <div className="d-flex align-items-center justify-content-end gap-2 mt-3 pt-2 border-top">
                                <button type="button" className="btn btn-outline-secondary btn-sm" onClick={resetFilters} title="Réinitialiser">
                                    <i className="ri-refresh-line"></i>
                                </button>
                                <Button color="success" size="sm" type="button">
                                    <i className="ri-search-line me-1"></i>Rechercher
                                </Button>
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Constitution des dossiers de paiement</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        <i className="ri-flag-line me-1 align-middle"></i>
                                        Attente Démarrage <Badge color="primary" className="ms-1">{attenteDemarrage.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        <i className="ri-user-follow-line me-1 align-middle"></i>
                                        Attente Présence <Badge color="primary" className="ms-1">{attentePresence.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        <i className="ri-folder-2-line me-1 align-middle"></i>
                                        Gestion Multi-Dossiers <Badge color="info" className="ms-1">{dossiers.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Card className="border shadow-none mb-4">
                                        <CardHeader className="d-flex align-items-center bg-light border-bottom border-light">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">
                                                Traitement des stagiaires sélectionnés
                                                <Badge color="primary" className="ms-2 fs-12">{attenteDemarrage.length}</Badge>
                                            </h5>
                                            <div className="d-flex gap-2">
                                                <button type="button" className="btn btn-soft-secondary btn-sm"><i className="ri-eraser-line me-1"></i>Vider onglet</button>
                                                <button type="button" className="btn btn-soft-danger btn-sm"><i className="ri-delete-bin-line me-1"></i>Vider tout</button>
                                            </div>
                                        </CardHeader>
                                        <CardBody>
                                            <div className="d-flex flex-wrap gap-2">
                                                <button type="button" className="btn btn-soft-info btn-sm">
                                                    <i className="ri-printer-line me-1"></i>Générer Etat Paiement (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                </button>
                                                <button type="button" className="btn btn-soft-success btn-sm">
                                                    <i className="ri-file-excel-2-line me-1"></i>Canvas Bénéficiaires TrésorPay Dépenses
                                                </button>
                                                <button type="button" className="btn btn-soft-primary btn-sm">
                                                    <i className="ri-printer-line me-1"></i>Attestation Démarrage (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                </button>
                                                <button type="button" className="btn btn-primary btn-sm">
                                                    <i className="ri-check-double-line me-1"></i>Fusionner Fiche Trésor Pay
                                                </button>
                                                <button type="button" className="btn btn-soft-danger btn-sm">
                                                    <i className="ri-close-circle-line me-1"></i>Ajourner <i className="ri-arrow-down-s-line ms-1"></i>
                                                </button>
                                                <button type="button" className="btn btn-success btn-sm">
                                                    <i className="ri-check-line me-1"></i>Valider paiement <i className="ri-arrow-down-s-line ms-1"></i>
                                                </button>
                                                <button type="button" className="btn btn-soft-dark btn-sm">
                                                    <i className="ri-folder-fill me-1"></i>Marquer dossiers <i className="ri-arrow-down-s-line ms-1"></i>
                                                </button>
                                            </div>
                                        </CardBody>
                                    </Card>

                                    <Row className="g-3 mb-4">
                                        <Col md={4}>
                                            <div className="alert alert-info border-0 border-start border-4 border-info mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-error-warning-line fs-16"></i>
                                                <span><strong>Retard Cohorte 1 :</strong> date début comprise entre le 1er et le 5, validée par l'agence après le 10 du mois</span>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="alert alert-warning border-0 border-start border-4 border-warning mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-error-warning-line fs-16"></i>
                                                <span><strong>Retard Cohorte 2 :</strong> date début comprise à partir du 10, validée par l'agence après le 20 du mois</span>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="alert alert-danger border-0 border-start border-4 border-danger mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-error-warning-line fs-16"></i>
                                                <span><strong>Retard Cohorte 3 :</strong> date début comprise à partir du 20, validée par l'agence après le mois en cours</span>
                                            </div>
                                        </Col>
                                    </Row>

                                    <Nav tabs className="nav-tabs-custom nav-success mb-3 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'global' })} onClick={() => toggleDemarrageTab('global')}>
                                                Cohorte Global
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte1' })} onClick={() => toggleDemarrageTab('cohorte1')}>
                                                Retard Cohorte 1
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte2' })} onClick={() => toggleDemarrageTab('cohorte2')}>
                                                Retard Cohorte 2
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte3' })} onClick={() => toggleDemarrageTab('cohorte3')}>
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
                                                tableClass="table-striped align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte1">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table-striped align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte2">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table-striped align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                            />
                                        </TabPane>
                                        <TabPane tabId="cohorte3">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table-striped align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                            />
                                        </TabPane>
                                    </TabContent>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Card className="border shadow-none mb-4">
                                        <CardHeader className="d-flex align-items-center bg-light border-bottom border-light">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">
                                                Traitement des stagiaires sélectionnés
                                                <Badge color="primary" className="ms-2 fs-12">{attentePresence.length}</Badge>
                                                {selectedPresenceIds.length > 0 && (
                                                    <span className="text-muted fs-12 fw-normal ms-2">
                                                        <i className="ri-checkbox-multiple-line me-1"></i>{selectedPresenceIds.length} sélectionné(s)
                                                    </span>
                                                )}
                                            </h5>
                                        </CardHeader>
                                        <CardBody>
                                            <div className="d-flex flex-wrap gap-2">
                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-info btn-sm">
                                                        <i className="ri-printer-line me-1"></i> Etat Paiement (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Tous les stagiaires (liste)</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Stagiaires sélectionnés</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-soft-success btn-sm">
                                                    <i className="ri-file-excel-2-line me-1"></i> Canvas Bénéficiaires TrésorPay Dépenses
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-primary btn-sm">
                                                        <i className="ri-printer-line me-1"></i> Attestation Présence (.PDF) <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Stagiaires sélectionnés</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-primary btn-sm">
                                                    <i className="ri-check-double-line me-1"></i> Fusionner Fiche Trésor Pay
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-danger btn-sm">
                                                        <i className="ri-close-circle-line me-1"></i> Ajourner <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Ajourner la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Ajourner sélection</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-success btn-sm">
                                                        <i className="ri-check-line me-1"></i> Valider paiement <i className="ri-arrow-down-s-line ms-1"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem>Valider paiement de la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0}>Sélectionner pour Valider</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-dark btn-sm">
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
                                        tableClass="table-striped align-middle table-nowrap mb-0"
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
                                        tableClass="table-striped align-middle table-nowrap mb-0"
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
