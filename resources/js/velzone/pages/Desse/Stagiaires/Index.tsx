import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useCallback, useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    Input,
    Label,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Nav,
    NavItem,
    NavLink,
    Row,
    Spinner,
    TabContent,
    TabPane,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

/* ─── Types ─── */
interface RefOption {
    id: number;
    nom?: string;
    raison_sociale?: string;
}

interface DoublonTypeOption {
    value: string;
    label: string;
}

interface PageProps {
    tab: string;
    typeDoublon: string;
    data: any;
    filters: Record<string, string>;
    counts: { attente: number; doublons: number; retour_chefagence: number; doublons_traites: number };
    doublonCounts: Record<string, number>;
    doublonTypes: DoublonTypeOption[];
    agences: RefOption[];
    entreprises: RefOption[];
    sourcesFinancement: RefOption[];
    typesStage: RefOption[];
    typesStructure: RefOption[];
}

/* ─── Helpers ─── */
const formatDateFr = (dateStr: string | null | undefined) => {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

const decisionBadge = (decision: string) => {
    if (decision === 'avere') {
        return <span className="badge bg-danger-subtle text-danger">Avéré</span>;
    }
    return <span className="badge bg-success-subtle text-success">Non avéré</span>;
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const DesseStagiairesIndex = (props: PageProps) => {
    const {
        tab,
        typeDoublon,
        data,
        filters = {},
        counts = { attente: 0, doublons: 0, retour_chefagence: 0, doublons_traites: 0 },
        doublonCounts = {},
        doublonTypes = [],
        agences = [],
        entreprises = [],
        sourcesFinancement = [],
        typesStage = [],
        typesStructure = [],
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const [currentTab, setCurrentTab] = useState(tab || 'attente');
    const [currentTypeDoublon, setCurrentTypeDoublon] = useState(typeDoublon || 'piece_identite');
    const [selectedFilters, setSelectedFilters] = useState({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        date_debut: filters?.date_debut || '',
        date_fin: filters?.date_fin || '',
        search: filters?.search || '',
    });

    const [isProcessing, setIsProcessing] = useState(false);

    /* ─── Modale Rejet (Attente Validation) ─── */
    const [rejetModalOpen, setRejetModalOpen] = useState(false);
    const [selectedInstance, setSelectedInstance] = useState<any>(null);
    const [motifRejet, setMotifRejet] = useState('');

    /* ─── Modale Traiter Doublon ─── */
    const [doublonModalOpen, setDoublonModalOpen] = useState(false);
    const [decisionDoublon, setDecisionDoublon] = useState('non_avere');
    const [motifDoublon, setMotifDoublon] = useState('');

    /* ─── Navigation / Filtres ─── */
    const applyNav = useCallback(
        (activeTab: string, activeTypeDoublon: string, currentFilters: typeof selectedFilters) => {
            const params: Record<string, string> = { tab: activeTab };
            if (activeTab === 'doublons') {
                params.type_doublon = activeTypeDoublon;
            }
            Object.entries(currentFilters).forEach(([key, val]) => {
                if (val) params[key] = val;
            });
            router.get('/desse/stagiaires', params, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [],
    );

    const toggleTab = (t: string) => {
        if (currentTab !== t) {
            setCurrentTab(t);
            applyNav(t, currentTypeDoublon, selectedFilters);
        }
    };

    const toggleTypeDoublon = (t: string) => {
        if (currentTypeDoublon !== t) {
            setCurrentTypeDoublon(t);
            applyNav('doublons', t, selectedFilters);
        }
    };

    const handleFilterChange = (field: string, value: string) => {
        const newFilters = { ...selectedFilters, [field]: value };
        setSelectedFilters(newFilters);
        applyNav(currentTab, currentTypeDoublon, newFilters);
    };

    const resetFilters = () => {
        const defaultFilters = {
            agence_id: '',
            entreprise_id: '',
            source_financement_id: '',
            type_stage_id: '',
            type_structure_id: '',
            date_debut: '',
            date_fin: '',
            search: '',
        };
        setSelectedFilters(defaultFilters);
        applyNav(currentTab, currentTypeDoublon, defaultFilters);
    };

    /* ─── Actions : Attente Validation ─── */
    const validerInstance = (id: number) => {
        if (confirm('Valider ce dossier et le transmettre à la DAICG ?')) {
            router.post(`/desse/stagiaires/valider/${id}`, {}, { preserveScroll: true });
        }
    };

    const openRejetModal = (row: any) => {
        setSelectedInstance(row);
        setMotifRejet('');
        setRejetModalOpen(true);
    };

    const confirmRejet = () => {
        if (!selectedInstance || isProcessing) return;
        setIsProcessing(true);
        router.post(`/desse/stagiaires/ajourner/${selectedInstance.id}`, { motif: motifRejet }, {
            preserveScroll: true,
            onSuccess: () => {
                setRejetModalOpen(false);
                setSelectedInstance(null);
                setMotifRejet('');
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Actions : Retour Chef d'Agence ─── */
    const renvoyerDossier = (id: number) => {
        if (confirm("Renvoyer ce dossier et le transmettre à la DAICG ?")) {
            router.post(`/desse/stagiaires/valider/${id}`, {}, { preserveScroll: true });
        }
    };

    /* ─── Actions : Doublons à Traiter ─── */
    const openDoublonModal = (row: any) => {
        setSelectedInstance(row);
        setDecisionDoublon('non_avere');
        setMotifDoublon('');
        setDoublonModalOpen(true);
    };

    const confirmTraiterDoublon = () => {
        if (!selectedInstance || isProcessing) return;
        setIsProcessing(true);
        router.post(`/desse/stagiaires/doublons/${selectedInstance.id}/traiter`, {
            type_doublon: currentTypeDoublon,
            decision: decisionDoublon,
            motif: motifDoublon,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setDoublonModalOpen(false);
                setSelectedInstance(null);
                setMotifDoublon('');
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Colonnes ─── */
    const columnsAttente = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Bénéficiaire',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return (
                        <div>
                            <h5 className="fs-14 mb-1">{b?.nom} {b?.prenoms}</h5>
                            <p className="text-muted mb-0">{b?.numero_aej || '-'}</p>
                        </div>
                    );
                },
            },
            { header: 'Entreprise', cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-' },
            { header: 'Agence', cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-' },
            { header: 'Type de stage', cell: (cell: any) => cell.row.original.stage?.type_stage?.nom || '-' },
            { header: 'Financement', cell: (cell: any) => cell.row.original.stage?.source_financement?.nom || '-' },
            { header: 'Date début', cell: (cell: any) => formatDateFr(cell.row.original.stage?.date_debut) },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <div className="d-flex gap-2">
                        <Button color="success" size="sm" onClick={() => validerInstance(cell.row.original.id)} disabled={isProcessing}>
                            <i className="ri-check-line align-bottom me-1"></i> Valider
                        </Button>
                        <Button color="danger" size="sm" outline onClick={() => openRejetModal(cell.row.original)}>
                            <i className="ri-close-line align-bottom me-1"></i> Rejeter
                        </Button>
                    </div>
                ),
            },
        ],
        [isProcessing],
    );

    const columnsDoublons = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Bénéficiaire',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return (
                        <div>
                            <h5 className="fs-14 mb-1 text-danger">{b?.nom} {b?.prenoms}</h5>
                            <p className="text-muted mb-0">{formatDateFr(b?.date_naissance)}</p>
                        </div>
                    );
                },
            },
            { header: 'N° AEJ', cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_aej || '-' },
            { header: "N° Pièce d'Identité", cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_piece_identite || '-' },
            { header: 'Téléphone', cell: (cell: any) => cell.row.original.stage?.beneficiaire?.telephone_principal || '-' },
            {
                header: 'Trésor Money / Wave',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return b?.numero_tresor_money || b?.numero_wave || '-';
                },
            },
            { header: 'Entreprise', cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-' },
            { header: 'Agence', cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-' },
            { header: 'Type de stage', cell: (cell: any) => cell.row.original.stage?.type_stage?.nom || '-' },
            {
                header: 'Statut',
                cell: () => <Badge color="warning">Doublon Suspecté</Badge>,
            },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <Button color="primary" size="sm" onClick={() => openDoublonModal(cell.row.original)} disabled={isProcessing}>
                        <i className="ri-settings-3-line align-bottom me-1"></i> Traiter le doublon
                    </Button>
                ),
            },
        ],
        [isProcessing],
    );

    const columnsRetourChefAgence = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Bénéficiaire',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return <span className="fw-medium">{b?.nom} {b?.prenoms}</span>;
                },
            },
            { header: 'Entreprise', cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-' },
            { header: 'Agence', cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-' },
            { header: 'Type de stage', cell: (cell: any) => cell.row.original.stage?.type_stage?.nom || '-' },
            { header: 'Date ajournement', cell: (cell: any) => formatDateFr(cell.row.original.updated_at) },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <Button color="success" size="sm" onClick={() => renvoyerDossier(cell.row.original.id)} disabled={isProcessing}>
                        <i className="ri-send-plane-line align-bottom me-1"></i> Renvoyer / Valider
                    </Button>
                ),
            },
        ],
        [isProcessing],
    );

    const columnsDoublonsTraites = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Bénéficiaire',
                cell: (cell: any) => {
                    const b = cell.row.original.instance?.stage?.beneficiaire;
                    return <span>{b?.nom} {b?.prenoms}</span>;
                },
            },
            {
                header: 'Type de doublon',
                cell: (cell: any) => {
                    const found = doublonTypes.find((d) => d.value === cell.row.original.type_doublon);
                    return found?.label || cell.row.original.type_doublon;
                },
            },
            { header: 'Décision', cell: (cell: any) => decisionBadge(cell.row.original.decision) },
            { header: 'Motif', cell: (cell: any) => <span className="text-muted">{cell.row.original.motif}</span> },
            { header: 'Traité par', cell: (cell: any) => cell.row.original.decide_par?.name || '-' },
            { header: 'Date traitement', cell: (cell: any) => formatDateFr(cell.row.original.decide_le) },
        ],
        [doublonTypes],
    );

    const currentColumns = useMemo(() => {
        switch (currentTab) {
            case 'doublons': return columnsDoublons;
            case 'retour_chefagence': return columnsRetourChefAgence;
            case 'doublons_traites': return columnsDoublonsTraites;
            default: return columnsAttente;
        }
    }, [currentTab, columnsAttente, columnsDoublons, columnsRetourChefAgence, columnsDoublonsTraites]);

    const tabs = [
        { key: 'attente', label: 'ATTENTE VALIDATION', count: counts.attente, color: 'primary', icon: 'ri-time-line' },
        { key: 'doublons', label: 'DOUBLONS À TRAITER', count: counts.doublons, color: 'danger', icon: 'ri-file-copy-2-line' },
        { key: 'retour_chefagence', label: "RETOUR CHEF D'AGENCE", count: counts.retour_chefagence, color: 'warning', icon: 'ri-arrow-go-back-line' },
        { key: 'doublons_traites', label: 'DOUBLONS TRAITÉS', count: counts.doublons_traites, color: 'success', icon: 'ri-check-double-line' },
    ];

    const selectedBeneficiaire = selectedInstance?.stage?.beneficiaire;
    const selectedStage = selectedInstance?.stage;

    return (
        <React.Fragment>
            <Head title="Espace DESSE - Suivi des Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Suivi des Stagiaires" pageTitle="Direction DESSE" />

                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-check-double-line me-2 align-middle"></i>{flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-error-warning-line me-2 align-middle"></i>{flash.error}
                        </Alert>
                    )}

                    {/* ─── Cartes Statistiques ─── */}
                    <Row className="g-3 mb-4">
                        {tabs.map((t) => (
                            <Col lg={3} md={6} key={t.key}>
                                <Card
                                    className="mb-0 shadow-sm border-0"
                                    onClick={() => toggleTab(t.key)}
                                    style={{
                                        cursor: 'pointer',
                                        borderLeft: currentTab === t.key ? `4px solid var(--vz-${t.color})` : '4px solid transparent',
                                        transition: 'border-left-color 0.2s ease',
                                    }}
                                >
                                    <CardBody className="py-3">
                                        <div className="d-flex align-items-center">
                                            <div className="avatar-sm flex-shrink-0 me-3">
                                                <span className={`avatar-title bg-${t.color}-subtle text-${t.color} rounded-circle fs-20`}>
                                                    <i className={t.icon}></i>
                                                </span>
                                            </div>
                                            <div className="flex-grow-1">
                                                <p className="text-muted text-uppercase fw-medium fs-12 mb-1">{t.label}</p>
                                                <h3 className={`mb-0 text-${t.color}`}>{t.count}</h3>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom-0 pb-0">
                            <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                <h4 className="card-title mb-0">
                                    <i className="ri-file-copy-2-line me-2 text-primary"></i>
                                    Validation & Doublons DESSE
                                </h4>
                                <Button color="soft-secondary" size="sm" onClick={resetFilters}>
                                    <i className="ri-refresh-line me-1"></i>Réinitialiser les filtres
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody>
                            {/* ─── Filtres communs ─── */}
                            <Row className="g-3 mb-4">
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.agence_id} onChange={(e) => handleFilterChange('agence_id', e.target.value)}>
                                        <option value="">Tout</option>
                                        {agences.map((a) => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Entreprise</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.entreprise_id} onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}>
                                        <option value="">Tout</option>
                                        {entreprises.map((e) => <option key={e.id} value={e.id}>{e.raison_sociale}</option>)}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Financement</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.source_financement_id} onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}>
                                        <option value="">Tout</option>
                                        {sourcesFinancement.map((sf) => <option key={sf.id} value={sf.id}>{sf.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type de stage</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.type_stage_id} onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}>
                                        <option value="">Tout</option>
                                        {typesStage.map((ts) => <option key={ts.id} value={ts.id}>{ts.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type de structure</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.type_structure_id} onChange={(e) => handleFilterChange('type_structure_id', e.target.value)}>
                                        <option value="">Tout</option>
                                        {typesStructure.map((ts) => <option key={ts.id} value={ts.id}>{ts.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Date début</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_debut} onChange={(e) => handleFilterChange('date_debut', e.target.value)} />
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Date fin</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_fin} onChange={(e) => handleFilterChange('date_fin', e.target.value)} />
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Recherche</Label>
                                    <Input type="text" bsSize="sm" placeholder="Nom, prénom, N° AEJ..." value={selectedFilters.search} onChange={(e) => handleFilterChange('search', e.target.value)} />
                                </Col>
                            </Row>

                            {/* ─── Onglets principaux ─── */}
                            <Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                {tabs.map((t) => (
                                    <NavItem key={t.key}>
                                        <NavLink
                                            className={classnames({ active: currentTab === t.key }, 'fw-semibold py-3')}
                                            style={{ cursor: 'pointer' }}
                                            onClick={() => toggleTab(t.key)}
                                        >
                                            <i className={`${t.icon} me-1`}></i>
                                            {t.label}
                                            <Badge color={t.color} pill className="ms-2">{t.count}</Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={currentTab} className="pt-3">
                                <TabPane tabId="doublons">
                                    {/* ─── Sous-onglets : types de doublon ─── */}
                                    <div className="d-flex flex-wrap gap-2 mb-3">
                                        {doublonTypes.map((d) => {
                                            const isActive = currentTypeDoublon === d.value;
                                            const count = doublonCounts[d.value] ?? 0;
                                            return (
                                                <button
                                                    key={d.value}
                                                    type="button"
                                                    onClick={() => toggleTypeDoublon(d.value)}
                                                    className={`btn btn-sm d-flex align-items-center gap-2 ${isActive
                                                        ? 'btn-primary'
                                                        : 'btn-outline-secondary'
                                                        }`}
                                                    style={{ fontWeight: isActive ? 600 : 400 }}
                                                >
                                                    {d.label}
                                                    <span className={`badge rounded-pill ${isActive ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary'
                                                        }`}>
                                                        {count.toLocaleString('fr-FR')}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {currentTypeDoublon === 'aej' && (
                                        <Alert color="info" className="border-0">
                                            <i className="ri-information-line me-2 align-middle"></i>
                                            Ce type de doublon ne peut structurellement pas survenir : le numéro AEJ est unique en base.
                                        </Alert>
                                    )}
                                </TabPane>
                            </TabContent>

                            {/* ─── Tableau (partagé entre tous les onglets) ─── */}
                            <TableContainerReactTable
                                columns={currentColumns}
                                data={data?.data || []}
                                isGlobalFilter={false}
                                customPageSize={data?.data?.length || 20}
                                divClass="table-responsive table-card mt-1 mb-1"
                                tableClass="align-middle table-nowrap table-hover"
                                theadClass="table-light"
                                SearchPlaceholder="Recherche..."
                                isServerPagination={true}
                                serverPagination={data}
                                onPageChange={(page) => {
                                    const params: Record<string, string> = { tab: currentTab, page: String(page) };
                                    if (currentTab === 'doublons') params.type_doublon = currentTypeDoublon;
                                    Object.entries(selectedFilters).forEach(([k, v]) => { if (v) params[k] = v; });
                                    router.get('/desse/stagiaires', params, { preserveState: true, preserveScroll: true });
                                }}
                            />

                            {/* ─── Pagination serveur ─── */}
                            {/* (gérée directement dans TableContainerReactTable via isServerPagination) */}
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════════════════════════════════════════
                MODALE — Rejeter (Attente Validation)
               ═══════════════════════════════════════════ */}
            <Modal isOpen={rejetModalOpen} toggle={() => !isProcessing && setRejetModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setRejetModalOpen(false)}>Rejeter le dossier</ModalHeader>
                <ModalBody>
                    <p className="text-muted">En rejetant ce dossier, il retournera dans la corbeille "Retour Agence" du Chef d'Agence.</p>
                    {selectedBeneficiaire && (
                        <p className="fw-medium mb-2">Dossier : {selectedBeneficiaire.nom} {selectedBeneficiaire.prenoms}</p>
                    )}
                    <div className="mb-0">
                        <Label className="form-label">Motif du rejet</Label>
                        <Input type="textarea" rows={3} placeholder="Ex: Informations incomplètes..." value={motifRejet} onChange={(e) => setMotifRejet(e.target.value)} />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setRejetModalOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button color="danger" onClick={confirmRejet} disabled={isProcessing}>
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer le Rejet'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Traiter le doublon
               ═══════════════════════════════════════════ */}
            <Modal isOpen={doublonModalOpen} toggle={() => !isProcessing && setDoublonModalOpen(false)} centered size="lg">
                <ModalHeader toggle={() => !isProcessing && setDoublonModalOpen(false)}>
                    <i className="ri-settings-3-line me-2 text-primary"></i>
                    Traiter le doublon
                </ModalHeader>
                <ModalBody>
                    {selectedInstance && (
                        <div className="border rounded p-3 mb-3 bg-light-subtle">
                            <Row>
                                <Col md={6}>
                                    <p className="mb-1"><strong>Bénéficiaire :</strong> {selectedBeneficiaire?.nom} {selectedBeneficiaire?.prenoms}</p>
                                    <p className="mb-1"><strong>N° AEJ :</strong> {selectedBeneficiaire?.numero_aej || '-'}</p>
                                    <p className="mb-1"><strong>N° Pièce :</strong> {selectedBeneficiaire?.numero_piece_identite || '-'}</p>
                                </Col>
                                <Col md={6}>
                                    <p className="mb-1"><strong>Agence :</strong> {selectedStage?.agence?.nom || '-'}</p>
                                    <p className="mb-1"><strong>Entreprise :</strong> {selectedStage?.entreprise?.raison_sociale || '-'}</p>
                                    <p className="mb-1"><strong>Type de stage :</strong> {selectedStage?.type_stage?.nom || '-'}</p>
                                </Col>
                            </Row>
                        </div>
                    )}

                    <p className="text-muted">
                        Cette décision s'appliquera à toutes les lignes partageant le même critère de doublon
                        ({doublonTypes.find((d) => d.value === currentTypeDoublon)?.label}).
                    </p>

                    <div className="mb-3">
                        <Label className="form-label">Décision <span className="text-danger">*</span></Label>
                        <Input type="select" value={decisionDoublon} onChange={(e) => setDecisionDoublon(e.target.value)}>
                            <option value="non_avere">Doublon non avéré — valider et transmettre à la DAICG</option>
                            <option value="avere">Doublon avéré — ajourner et retourner à l'agence</option>
                        </Input>
                    </div>

                    <div className="mb-0">
                        <Label className="form-label">Motif <span className="text-danger">*</span></Label>
                        <Input type="textarea" rows={3} placeholder="Justification de la décision..." value={motifDoublon} onChange={(e) => setMotifDoublon(e.target.value)} />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setDoublonModalOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button color="primary" onClick={confirmTraiterDoublon} disabled={isProcessing || motifDoublon.trim().length < 5}>
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer la décision'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DesseStagiairesIndex;
