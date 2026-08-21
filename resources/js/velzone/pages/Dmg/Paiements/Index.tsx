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
    UncontrolledDropdown,
    DropdownToggle,
    DropdownMenu,
    DropdownItem,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

/* ─── Types ─── */
interface RefItem {
    id: number;
    nom: string;
    raison_sociale?: string;
}

interface PeriodeOption {
    id: number;
    code: string;
}

interface PaiementRow {
    id: number;
    numero: string;
    beneficiaire: {
        nom: string;
        prenoms: string;
        matricule: string;
        date_naissance: string;
        tresor_pay: string;
    };
    entreprise: { raison_sociale: string };
    agence: { nom: string };
    stage: {
        source_financement: string;
        type_stage: string;
        date_validation: string;
        date_debut: string;
        date_fin: string;
    };
    montant: number;
    statut: string;
    date_creation: string;
    piece_jointe: string | null;
    cohorte: number;
}

interface DossierRow {
    id: number;
    numero: string;
    agence: { nom: string };
    source_financement: { libelle: string };
    nombre_stagiaires: number;
    montant: number;
    montant_total: number;
    statut: string;
    date_creation: string;
}

interface Compteurs {
    global: { demarrage: number; presence: number };
    cohorte1: { demarrage: number; presence: number };
    cohorte2: { demarrage: number; presence: number };
    cohorte3: { demarrage: number; presence: number };
}

interface Filters {
    agence_id?: string;
    entreprise_id?: string;
    source_financement_id?: string;
    type_stage_id?: string;
    type_structure_id?: string;
    date_debut?: string;
    date_fin?: string;
    date_validation_debut?: string;
    date_validation_fin?: string;
    search?: string;
}

interface PageProps {
    attenteDemarrage: PaiementRow[];
    attentePresence: PaiementRow[];
    compteurs: Compteurs;
    dossiers: DossierRow[];
    dossiersTransmis: DossierRow[];
    dossiersAjournes: DossierRow[];
    ops: any[];
    bordereaux: any[];
    moisActuel: string;
    periode: PeriodeOption | null;
    filters: Filters;
    cohorte: string;
    agences: RefItem[];
    entreprises: RefItem[];
    sourcesFinancement: RefItem[];
    typesStage: RefItem[];
    typestructures: RefItem[];
    periodeOptions: PeriodeOption[];
    limiteAffichee: number;
}

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════════════════════ */
const DmgPaiementsIndex = (props: PageProps) => {
    const {
        attenteDemarrage = [],
        attentePresence = [],
        compteurs,
        dossiers = [],
        dossiersTransmis = [],
        dossiersAjournes = [],
        ops = [],
        bordereaux = [],
        moisActuel,
        filters = {},
        cohorte: initialCohorte = 'global',
        agences = [],
        entreprises = [],
        sourcesFinancement = [],
        typesStage = [],
        typestructures = [],
        periodeOptions = [],
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    /* ─── États ─── */
    const [activeTab, setActiveTab] = useState('1');
    const [demarrageTab, setDemarrageTab] = useState(initialCohorte);
    const [processing, setProcessing] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    /* ─── Sélection ─── */
    const [selectedDemarrageIds, setSelectedDemarrageIds] = useState<number[]>([]);
    const [selectedPresenceIds, setSelectedPresenceIds] = useState<number[]>([]);

    /* ─── Filtres ─── */
    const [selectedFilters, setSelectedFilters] = useState<Filters>({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        date_debut: filters?.date_debut || '',
        date_fin: filters?.date_fin || '',
        date_validation_debut: filters?.date_validation_debut || '',
        date_validation_fin: filters?.date_validation_fin || '',
        search: filters?.search || '',
    });
    const [moisSelectionne, setMoisSelectionne] = useState(moisActuel || '');

    /* ─── Modales ─── */
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [modalDetailOpen, setModalDetailOpen] = useState(false);
    const [modalDossierOpen, setModalDossierOpen] = useState(false);
    const [detailRow, setDetailRow] = useState<PaiementRow | null>(null);
    const [motifAjourner, setMotifAjourner] = useState('');
    const [dossierStatus, setDossierStatus] = useState('en_attente');

    /* ─── Données onglet courant ─── */
    const currentDemarrageRows = useMemo(() => attenteDemarrage || [], [attenteDemarrage]);
    const currentPresenceRows = useMemo(() => attentePresence || [], [attentePresence]);

    /* ─── Filtre cohorte côté client ─── */
    const filteredDemarrageRows = useMemo(() => {
        if (demarrageTab === 'global') return currentDemarrageRows;
        const cohortNum = parseInt(demarrageTab.replace('cohorte', ''), 10);
        return currentDemarrageRows.filter((r) => r.cohorte === cohortNum);
    }, [currentDemarrageRows, demarrageTab]);

    /* ─── Navigation filtres ─── */
    const applyFilters = useCallback(() => {
        const params: Record<string, string> = {};
        if (moisSelectionne) params.mois = moisSelectionne;
        Object.entries(selectedFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        params.cohorte = demarrageTab;
        params.tab = activeTab;

        const url = new URL(window.location.href);
        url.search = '';
        Object.entries(params).forEach(([key, val]) => {
            if (val) url.searchParams.set(key, val);
        });
        window.history.replaceState({}, '', url);

        setIsLoading(true);
        router.get('/dmg/paiements', params, {
            preserveState: true,
            onFinish: () => setIsLoading(false),
        });
    }, [moisSelectionne, selectedFilters, demarrageTab, activeTab]);

    const resetFilters = () => {
        setSelectedFilters({
            agence_id: '', entreprise_id: '', source_financement_id: '',
            type_stage_id: '', type_structure_id: '', date_debut: '',
            date_fin: '', date_validation_debut: '', date_validation_fin: '', search: '',
        });
        setMoisSelectionne(moisActuel || '');
        router.visit('/dmg/paiements', { preserveState: false });
    };

    const handleFilterChange = (field: string, value: string) => {
        setSelectedFilters((prev) => ({ ...prev, [field]: value }));
    };

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
            setActiveTab(tab);
            setSelectedDemarrageIds([]);
            setSelectedPresenceIds([]);
        }
    };

    const toggleDemarrageTab = (tab: string) => {
        if (demarrageTab !== tab) {
            setDemarrageTab(tab);
            setSelectedDemarrageIds([]);
        }
    };

    /* ─── Sélection ─── */
    const toggleSelectAllDemarrage = useCallback(() => {
        const allIds = filteredDemarrageRows.map((r) => r.id);
        setSelectedDemarrageIds((prev) => (prev.length === allIds.length ? [] : allIds));
    }, [filteredDemarrageRows]);

    const toggleSelectOneDemarrage = useCallback((id: number) => {
        setSelectedDemarrageIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    }, []);

    const toggleSelectAllPresence = useCallback(() => {
        const allIds = currentPresenceRows.map((r) => r.id);
        setSelectedPresenceIds((prev) => (prev.length === allIds.length ? [] : allIds));
    }, [currentPresenceRows]);

    const toggleSelectOnePresence = useCallback((id: number) => {
        setSelectedPresenceIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    }, []);

    /* ─── Actions backend ─── */
    const handleGenererDossiers = () => {
        const ids = activeTab === '2' ? selectedPresenceIds : selectedDemarrageIds;
        if (!props.periode || ids.length === 0) return;
        setIsLoading(true);
        router.post('/dmg/paiements/generer', { periode_id: props.periode.id, paiement_ids: ids }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDemarrageIds([]);
                setSelectedPresenceIds([]);
            },
            onFinish: () => setIsLoading(false),
        });
    };

    const handleValiderPaiement = (ids: number[], scope: 'liste' | 'selected') => {
        if (ids.length === 0 || !props.periode) return;
        setProcessing(true);
        router.post('/dmg/paiements/generer', { periode_id: props.periode.id, paiement_ids: ids, scope }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDemarrageIds([]);
                setSelectedPresenceIds([]);
                setModalValiderOpen(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleAjournerPaiement = () => {
        const ids = activeTab === '2' ? selectedPresenceIds : selectedDemarrageIds;
        if (motifAjourner.trim().length < 5 || ids.length === 0) return;
        setProcessing(true);
        router.post('/dmg/paiements/ajourner', { paiement_ids: ids, motif: motifAjourner }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDemarrageIds([]);
                setSelectedPresenceIds([]);
                setModalAjournerOpen(false);
                setMotifAjourner('');
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleMarquerDossier = (ids: number[], status: string) => {
        if (ids.length === 0) return;
        setProcessing(true);
        router.post('/dmg/paiements/marquer-dossier-physique', {
            paiement_ids: ids,
            statut: status.toUpperCase(),
        }, {
            preserveScroll: true,
            onSuccess: () => setModalDossierOpen(false),
            onFinish: () => setProcessing(false),
        });
    };

    const handleGenererPdf = (type: 'etat_paiement' | 'attestation_demarrage' | 'attestation_presence' | 'fusion_tresor', scope: 'liste' | 'selected') => {
        const ids = scope === 'selected' ? (activeTab === '2' ? selectedPresenceIds : selectedDemarrageIds) : [];
        const params = new URLSearchParams();
        params.set('mois', moisSelectionne);
        params.set('type', type);
        if (scope === 'selected') ids.forEach((id) => params.append('ids[]', String(id)));
        window.open(`/dmg/paiements/generer-pdf?${params.toString()}`, '_blank');
    };

    /* ─── Badge helpers ─── */
    const getStatutBadge = (statut?: string) => {
        switch ((statut || '').toUpperCase()) {
            case 'VALIDE': case 'TRAITE': return 'success';
            case 'AJOURNE': case 'AJOURNE_DMG': case 'AJOURNE_CB': return 'danger';
            case 'TRANSMIS_CB': return 'warning';
            case 'A_TRAITER': case 'EN_COURS': return 'info';
            default: return 'secondary';
        }
    };

    /* ═══════════════════════════════════════════════════════════════════
       COLONNES TABLEAUX
       ═══════════════════════════════════════════════════════════════════ */
    const selectColumnDemarrage = {
        id: 'select',
        header: () => (
            <Input type="checkbox" className="form-check-input"
                checked={filteredDemarrageRows.length > 0 && selectedDemarrageIds.length === filteredDemarrageRows.length}
                onChange={() => toggleSelectAllDemarrage()} />
        ),
        cell: (cell: any) => (
            <Input type="checkbox" className="form-check-input"
                checked={selectedDemarrageIds.includes(cell.row.original.id)}
                onChange={() => toggleSelectOneDemarrage(cell.row.original.id)} />
        ),
        size: 50,
    };

    const selectColumnPresence = {
        id: 'select',
        header: () => (
            <Input type="checkbox" className="form-check-input"
                checked={currentPresenceRows.length > 0 && selectedPresenceIds.length === currentPresenceRows.length}
                onChange={() => toggleSelectAllPresence()} />
        ),
        cell: (cell: any) => (
            <Input type="checkbox" className="form-check-input"
                checked={selectedPresenceIds.includes(cell.row.original.id)}
                onChange={() => toggleSelectOnePresence(cell.row.original.id)} />
        ),
        size: 50,
    };

    const commonDemarrageColumns = useMemo(() => [
        { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
        { header: 'Agence', cell: (cell: any) => <span className="fw-medium">{cell.row.original.agence?.nom || '-'}</span> },
        { header: 'Entreprise', cell: (cell: any) => cell.row.original.entreprise?.raison_sociale || '-' },
        { header: 'Financement', cell: (cell: any) => {
            const val = cell.row.original.stage?.source_financement || '-';
            const colorMap: Record<string, string> = { 'PEJEDEC': 'info', 'BUDGET AEJ': 'primary', 'PAPS-GOUV': 'success', 'C2D': 'warning' };
            return <Badge color={`${(colorMap[val] || 'secondary')}-subtle`} className={`text-${colorMap[val] || 'secondary'}`}>{val}</Badge>;
        }},
        { header: 'Type Stage', cell: (cell: any) => cell.row.original.stage?.type_stage || '-' },
        { header: 'N° AEJ', cell: (cell: any) => <span className="text-muted">{cell.row.original.beneficiaire?.matricule || '-'}</span> },
        { header: 'Nom et prénoms', cell: (cell: any) => {
            const b = cell.row.original.beneficiaire;
            return <span className="fw-semibold">{b ? `${b.nom} ${b.prenoms}`.trim() : '-'}</span>;
        }},
        { header: 'Date Naiss.', cell: (cell: any) => cell.row.original.beneficiaire?.date_naissance || '-' },
        { header: 'Date Début', cell: (cell: any) => cell.row.original.stage?.date_debut || '-' },
        { header: 'Date Fin', cell: (cell: any) => cell.row.original.stage?.date_fin || '-' },
        { header: 'N° Trésor Pay', cell: (cell: any) => cell.row.original.beneficiaire?.tresor_pay || '-' },
        { header: 'Montant', cell: (cell: any) => {
            const m = cell.row.original.montant;
            return m ? <span className="fw-bold text-success">{Number(m).toLocaleString('fr-FR')} FCFA</span> : '-';
        }},
        { header: 'État', cell: (cell: any) => <Badge color={getStatutBadge(cell.row.original.statut)} className="fs-11">{cell.row.original.statut || '-'}</Badge> },
        { header: 'Actions', cell: (cell: any) => (
            <div className="d-flex gap-1">
                <Button color="info" size="sm" outline onClick={() => { setDetailRow(cell.row.original); setModalDetailOpen(true); }} title="Détail">
                    <i className="ri-eye-line"></i>
                </Button>
            </div>
        )},
    ], []);

    const presenceColumns = useMemo(() => [
        selectColumnPresence,
        ...commonDemarrageColumns,
    ], [selectedPresenceIds, currentPresenceRows]);

    const demarrageColumns = useMemo(() => [
        selectColumnDemarrage,
        ...commonDemarrageColumns,
    ], [selectedDemarrageIds, filteredDemarrageRows]);

    const dossierColumns = useMemo(() => [
        { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
        { header: 'Numéro', cell: (cell: any) => <span className="fw-medium text-primary">{cell.row.original.numero}</span> },
        { header: 'Agence', cell: (cell: any) => cell.row.original.agence?.nom || '-' },
        { header: 'Financement', cell: (cell: any) => cell.row.original.source_financement?.libelle || '-' },
        { header: 'Nb Stagiaires', cell: (cell: any) => <Badge color="info">{cell.row.original.nombre_stagiaires}</Badge> },
        { header: 'Montant', cell: (cell: any) => <span className="fw-bold">{Number(cell.row.original.montant_total || 0).toLocaleString('fr-FR')} FCFA</span> },
        { header: 'Statut', cell: (cell: any) => <Badge color={getStatutBadge(cell.row.original.statut)} className="fs-11">{cell.row.original.statut}</Badge> },
        { header: 'Actions', cell: (cell: any) => (
            <div className="d-flex gap-1">
                {cell.row.original.statut === 'En élaboration' && (
                    <Button color="success" size="sm" outline onClick={() => handleGenererDossiers()} title="Générer OP">
                        <i className="ri-file-list-3-line"></i>
                    </Button>
                )}
                {cell.row.original.statut === 'Transmis CB' && (
                    <Button color="primary" size="sm" outline title="Créer Bordereau">
                        <i className="ri-file-shield-2-line"></i>
                    </Button>
                )}
            </div>
        )},
    ], []);

    /* ─── Données dossiers par statut ─── */
    const [dossierTab, setDossierTab] = useState('brouillon');

    /* ─── Stats ─── */
    const statCards = useMemo(() => [
        { label: 'Attente Démarrage', value: compteurs?.global?.demarrage ?? attenteDemarrage.length, icon: 'ri-flag-line', color: 'primary' },
        { label: 'Attente Présence', value: compteurs?.global?.presence ?? attentePresence.length, icon: 'ri-user-follow-line', color: 'info' },
        { label: 'Dossiers en cours', value: dossiers.length, icon: 'ri-folder-2-line', color: 'warning' },
        { label: 'Total à traiter', value: (compteurs?.global?.demarrage ?? 0) + (compteurs?.global?.presence ?? 0), icon: 'ri-time-line', color: 'success' },
    ], [compteurs, attenteDemarrage, attentePresence, dossiers]);

    /* ─── Cohorte badges ─── */
    const cohortBadge = (cohorteKey: string, type: 'demarrage' | 'presence') => {
        if (!compteurs || !compteurs[cohorteKey as keyof Compteurs]) return null;
        const count = compteurs[cohorteKey as keyof Compteurs]?.[type] ?? 0;
        return <Badge color="secondary" pill className="ms-1 fs-11">{count}</Badge>;
    };

    /* ═══════════════════════════════════════════════════════════════════
       RENDU
       ═══════════════════════════════════════════════════════════════════ */
    return (
        <React.Fragment>
            <Head title="Espace DMG — Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Préparation des Paiements" pageTitle="DMG" />

                    {/* ─── Flash Messages ─── */}
                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show">
                            <i className="ri-check-double-line me-2 align-middle"></i>{flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show">
                            <i className="ri-error-warning-line me-2 align-middle"></i>{flash.error}
                        </Alert>
                    )}

                    {/* ─── Cartes Statistiques ─── */}
                    <Row className="g-3 mb-4">
                        {statCards.map((s) => (
                            <Col xl={3} md={6} key={s.label}>
                                <Card className="card-animate mb-0 h-100 shadow-sm border-0">
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

                    {/* ═══════ FILTRES ═══════ */}
                    <Card className="mb-3 shadow-sm border-0">
                        <CardBody className="pb-2">
                            <h5 className="fs-13 fw-medium mb-3 text-muted">
                                <i className="ri-filter-3-line me-1"></i>Filtres de recherche
                            </h5>
                            <Row className="g-2 align-items-end mb-2">
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Période</Label>
                                    <Input type="select" bsSize="sm" value={moisSelectionne} onChange={(e) => setMoisSelectionne(e.target.value)}>
                                        <option value="">Toutes</option>
                                        {periodeOptions.map((p) => <option key={p.id} value={p.code}>{p.code}</option>)}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Agence</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.agence_id || ''} onChange={(e) => handleFilterChange('agence_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {agences.map((a) => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Entreprise</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.entreprise_id || ''} onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {entreprises.map((e) => <option key={e.id} value={e.id}>{e.raison_sociale || e.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Financement</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.source_financement_id || ''} onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {sourcesFinancement.map((sf) => <option key={sf.id} value={sf.id}>{sf.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Type Stage</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.type_stage_id || ''} onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {typesStage.map((ts) => <option key={ts.id} value={ts.id}>{ts.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <Label className="form-label fs-12 text-muted mb-1">Type Structure</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.type_structure_id || ''} onChange={(e) => handleFilterChange('type_structure_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {typestructures.map((t) => <option key={t.id} value={t.id}>{t.nom}</option>)}
                                    </Input>
                                </Col>
                            </Row>
                            <Row className="g-2 align-items-end">
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">Date Début</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_debut || ''} onChange={(e) => handleFilterChange('date_debut', e.target.value)} />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">Date Fin</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_fin || ''} onChange={(e) => handleFilterChange('date_fin', e.target.value)} />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">Valid. Début</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_validation_debut || ''} onChange={(e) => handleFilterChange('date_validation_debut', e.target.value)} />
                                </Col>
                                <Col xs={6} sm={3}>
                                    <Label className="form-label fs-12 text-muted mb-1">Valid. Fin</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.date_validation_fin || ''} onChange={(e) => handleFilterChange('date_validation_fin', e.target.value)} />
                                </Col>
                            </Row>
                            <div className="d-flex align-items-center justify-content-end gap-2 mt-3 pt-2 border-top">
                                <button type="button" className="btn btn-outline-secondary btn-sm" onClick={resetFilters}>
                                    <i className="ri-refresh-line me-1"></i>Réinitialiser
                                </button>
                                <Button color="success" size="sm" onClick={applyFilters} disabled={isLoading}>
                                    {isLoading ? <><Spinner size="sm" className="me-1" />Chargement...</> : <><i className="ri-search-line me-1"></i>Rechercher</>}
                                </Button>
                            </div>
                        </CardBody>
                    </Card>

                    {/* ═══════ CONTENU PRINCIPAL ═══════ */}
                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom-0 pb-0">
                            <h4 className="card-title mb-0">
                                <i className="ri-wallet-3-line me-2 text-success"></i>
                                Constitution des dossiers de paiement
                            </h4>
                        </CardHeader>
                        <CardBody>
                            {/* ── Onglets Principaux ── */}
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        <i className="ri-flag-line me-1 align-middle"></i>
                                        Attente Démarrage <Badge color="primary" className="ms-1">{compteurs?.global?.demarrage ?? attenteDemarrage.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        <i className="ri-user-follow-line me-1 align-middle"></i>
                                        Attente Présence <Badge color="info" className="ms-1">{compteurs?.global?.presence ?? attentePresence.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        <i className="ri-folder-2-line me-1 align-middle"></i>
                                        Dossiers & OP <Badge color="warning" className="ms-1">{dossiers.length + dossiersTransmis.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                {/* ═══════ ONGLET 1 : ATTENTE DÉMARRAGE ═══════ */}
                                <TabPane tabId="1">
                                    {/* ── Actions globales démarrage ── */}
                                    <Card className="border shadow-none mb-3">
                                        <CardHeader className="bg-light border-bottom border-light d-flex align-items-center">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">
                                                <i className="ri-checkbox-multiple-line me-1"></i>
                                                Traitement démarrage
                                                {selectedDemarrageIds.length > 0 && (
                                                    <Badge color="success" className="ms-2 fs-12">{selectedDemarrageIds.length} sélectionné(s)</Badge>
                                                )}
                                            </h5>
                                        </CardHeader>
                                        <CardBody className="py-2">
                                            <div className="d-flex flex-wrap gap-2">
                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-info btn-sm">
                                                        <i className="ri-printer-line me-1"></i>État Paiement (PDF) <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleGenererPdf('etat_paiement', 'liste')}>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedDemarrageIds.length === 0} onClick={() => handleGenererPdf('etat_paiement', 'selected')}>Sélection ({selectedDemarrageIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-soft-success btn-sm" onClick={() => handleGenererPdf('etat_paiement' as any, 'liste')}>
                                                    <i className="ri-file-excel-2-line me-1"></i>Canvas TrésorPay
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-primary btn-sm">
                                                        <i className="ri-printer-line me-1"></i>Attestation Démarrage (PDF) <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleGenererPdf('attestation_demarrage', 'liste')}>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedDemarrageIds.length === 0} onClick={() => handleGenererPdf('attestation_demarrage', 'selected')}>Sélection ({selectedDemarrageIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-primary btn-sm" onClick={() => handleGenererPdf('fusion_tresor' as any, 'liste')}>
                                                    <i className="ri-check-double-line me-1"></i>Fusionner Trésor Pay
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-danger btn-sm">
                                                        <i className="ri-close-circle-line me-1"></i>Ajourner <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => { setModalAjournerOpen(true); }}>Ajourner la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedDemarrageIds.length === 0} onClick={() => { setModalAjournerOpen(true); }}>Ajourner sélection ({selectedDemarrageIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-success btn-sm">
                                                        <i className="ri-check-line me-1"></i>Valider paiement <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleValiderPaiement(filteredDemarrageRows.map((r) => r.id), 'liste')}>Valider toute la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedDemarrageIds.length === 0} onClick={() => handleValiderPaiement(selectedDemarrageIds, 'selected')}>Valider sélection ({selectedDemarrageIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-dark btn-sm">
                                                        <i className="ri-folder-fill me-1"></i>Marquer dossier <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => { setModalDossierOpen(true); }}>Marquer la liste</DropdownItem>
                                                        {selectedDemarrageIds.length > 0 && (
                                                            <DropdownItem onClick={() => { setModalDossierOpen(true); }}>Marquer sélection ({selectedDemarrageIds.length})</DropdownItem>
                                                        )}
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>
                                            </div>
                                        </CardBody>
                                    </Card>

                                    {/* ── Info Cohortes ── */}
                                    <Row className="g-3 mb-3">
                                        <Col md={4}>
                                            <div className="alert alert-info border-0 border-start border-4 border-info mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-information-line fs-16"></i>
                                                <span><strong>Cohorte 1 :</strong> date début 1er–5 du mois. Badge <Badge color="info" className="ms-1">{compteurs?.cohorte1?.demarrage ?? 0}</Badge></span>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="alert alert-warning border-0 border-start border-4 border-warning mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-information-line fs-16"></i>
                                                <span><strong>Cohorte 2 :</strong> date début 6–19 du mois. Badge <Badge color="warning" className="ms-1">{compteurs?.cohorte2?.demarrage ?? 0}</Badge></span>
                                            </div>
                                        </Col>
                                        <Col md={4}>
                                            <div className="alert alert-danger border-0 border-start border-4 border-danger mb-0 h-100 d-flex align-items-center gap-2 fs-13">
                                                <i className="ri-information-line fs-16"></i>
                                                <span><strong>Cohorte 3 :</strong> date début 20+ du mois. Badge <Badge color="danger" className="ms-1">{compteurs?.cohorte3?.demarrage ?? 0}</Badge></span>
                                            </div>
                                        </Col>
                                    </Row>

                                    {/* ── Sous-onglets Cohorte ── */}
                                    <Nav tabs className="nav-tabs-custom nav-success mb-3 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'global' })} onClick={() => toggleDemarrageTab('global')}>
                                                Cohorte Global {cohortBadge('global', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte1' })} onClick={() => toggleDemarrageTab('cohorte1')}>
                                                Cohorte 1 {cohortBadge('cohorte1', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte2' })} onClick={() => toggleDemarrageTab('cohorte2')}>
                                                Cohorte 2 {cohortBadge('cohorte2', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte3' })} onClick={() => toggleDemarrageTab('cohorte3')}>
                                                Cohorte 3 {cohortBadge('cohorte3', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                    </Nav>

                                    {/* ── Tableau Démarrage ── */}
                                    {isLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="success" /></div>
                                    ) : (
                                        <TableContainerReactTable
                                            columns={demarrageColumns}
                                            data={filteredDemarrageRows}
                                            isGlobalFilter={true}
                                            customPageSize={10}
                                            divClass="table-responsive table-card mb-3"
                                            tableClass="table-striped align-middle table-nowrap mb-0"
                                            theadClass="table-light text-uppercase fw-semibold fs-11"
                                            SearchPlaceholder="Rechercher..."
                                        />
                                    )}
                                </TabPane>

                                {/* ═══════ ONGLET 2 : ATTENTE PRÉSENCE ═══════ */}
                                <TabPane tabId="2">
                                    {/* ── Actions globales présence ── */}
                                    <Card className="border shadow-none mb-3">
                                        <CardHeader className="bg-light border-bottom border-light d-flex align-items-center">
                                            <h5 className="card-title mb-0 flex-grow-1 fs-14">
                                                <i className="ri-checkbox-multiple-line me-1"></i>
                                                Traitement présence
                                                {selectedPresenceIds.length > 0 && (
                                                    <Badge color="success" className="ms-2 fs-12">{selectedPresenceIds.length} sélectionné(s)</Badge>
                                                )}
                                            </h5>
                                        </CardHeader>
                                        <CardBody className="py-2">
                                            <div className="d-flex flex-wrap gap-2">
                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-info btn-sm">
                                                        <i className="ri-printer-line me-1"></i>État Paiement (PDF) <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleGenererPdf('etat_paiement', 'liste')}>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0} onClick={() => handleGenererPdf('etat_paiement', 'selected')}>Sélection ({selectedPresenceIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-soft-success btn-sm">
                                                    <i className="ri-file-excel-2-line me-1"></i>Canvas TrésorPay
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-primary btn-sm">
                                                        <i className="ri-printer-line me-1"></i>Attestation Présence (PDF) <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleGenererPdf('attestation_presence', 'liste')}>Tous les stagiaires</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0} onClick={() => handleGenererPdf('attestation_presence', 'selected')}>Sélection ({selectedPresenceIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <button type="button" className="btn btn-primary btn-sm">
                                                    <i className="ri-check-double-line me-1"></i>Fusionner Trésor Pay
                                                </button>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-soft-danger btn-sm">
                                                        <i className="ri-close-circle-line me-1"></i>Ajourner <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => { setModalAjournerOpen(true); }}>Ajourner la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0} onClick={() => { setModalAjournerOpen(true); }}>Ajourner sélection ({selectedPresenceIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>

                                                <UncontrolledDropdown>
                                                    <DropdownToggle tag="button" className="btn btn-success btn-sm">
                                                        <i className="ri-check-line me-1"></i>Valider paiement <i className="ri-arrow-down-s-line"></i>
                                                    </DropdownToggle>
                                                    <DropdownMenu>
                                                        <DropdownItem onClick={() => handleValiderPaiement(currentPresenceRows.map((r) => r.id), 'liste')}>Valider toute la liste</DropdownItem>
                                                        <DropdownItem disabled={selectedPresenceIds.length === 0} onClick={() => handleValiderPaiement(selectedPresenceIds, 'selected')}>Valider sélection ({selectedPresenceIds.length})</DropdownItem>
                                                    </DropdownMenu>
                                                </UncontrolledDropdown>
                                            </div>
                                        </CardBody>
                                    </Card>

                                    {/* ── Tableau Présence ── */}
                                    {isLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="success" /></div>
                                    ) : (
                                        <TableContainerReactTable
                                            columns={presenceColumns}
                                            data={currentPresenceRows}
                                            isGlobalFilter={true}
                                            customPageSize={10}
                                            divClass="table-responsive table-card mb-3"
                                            tableClass="table-striped align-middle table-nowrap mb-0"
                                            theadClass="table-light text-uppercase fw-semibold fs-11"
                                            SearchPlaceholder="Rechercher..."
                                        />
                                    )}
                                </TabPane>

                                {/* ═══════ ONGLET 3 : DOSSIERS & OP ═══════ */}
                                <TabPane tabId="3">
                                    {/* ── Sous-onglets dossiers ── */}
                                    <Nav tabs className="nav-tabs-custom nav-success mb-3 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'brouillon' })} onClick={() => setDossierTab('brouillon')}>
                                                <i className="ri-draft-line me-1"></i>En élaboration <Badge color="warning" className="ms-1">{dossiers.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'transmis' })} onClick={() => setDossierTab('transmis')}>
                                                <i className="ri-send-plane-line me-1"></i>Transmis CB <Badge color="info" className="ms-1">{dossiersTransmis.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'ajournes' })} onClick={() => setDossierTab('ajournes')}>
                                                <i className="ri-close-circle-line me-1"></i>Ajournés <Badge color="danger" className="ms-1">{dossiersAjournes.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'ops' })} onClick={() => setDossierTab('ops')}>
                                                <i className="ri-file-list-3-line me-1"></i>Ordres de Paiement <Badge color="primary" className="ms-1">{ops.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'bordereaux' })} onClick={() => setDossierTab('bordereaux')}>
                                                <i className="ri-file-shield-2-line me-1"></i>Bordereaux <Badge color="success" className="ms-1">{bordereaux.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                    </Nav>

                                    <div className="d-flex gap-2 mb-3">
                                            <Button color="success" size="sm" onClick={handleGenererDossiers} disabled={!props.periode || processing || (selectedDemarrageIds.length + selectedPresenceIds.length === 0)}>
                                            {processing ? <><Spinner size="sm" className="me-1" />Génération...</> : <><i className="ri-folder-add-line me-1"></i>Générer les dossiers pour la période</>}
                                        </Button>
                                    </div>

                                    <TabContent activeTab={dossierTab}>
                                        <TabPane tabId="brouillon">
                                            <TableContainerReactTable columns={dossierColumns} data={dossiers} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="transmis">
                                            <TableContainerReactTable columns={dossierColumns} data={dossiersTransmis} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="ajournes">
                                            <TableContainerReactTable columns={dossierColumns} data={dossiersAjournes} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="ops">
                                            {ops.length === 0 ? (
                                                <p className="text-muted text-center py-4"><i className="ri-inbox-line me-1"></i>Aucun ordre de paiement pour cette période.</p>
                                            ) : (
                                                <TableContainerReactTable
                                                    columns={[
                                                        { header: 'Numéro', cell: (c: any) => <span className="fw-medium text-primary">{c.row.original.numero}</span> },
                                                        { header: 'Montant', cell: (c: any) => <span className="fw-bold">{Number(c.row.original.montant_total || 0).toLocaleString('fr-FR')} FCFA</span> },
                                                        { header: 'Statut', cell: (c: any) => <Badge color={getStatutBadge(c.row.original.statut)} className="fs-11">{c.row.original.statut}</Badge> },
                                                    ]}
                                                    data={ops} isGlobalFilter={true} customPageSize={10}
                                                    divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                            )}
                                        </TabPane>
                                        <TabPane tabId="bordereaux">
                                            {bordereaux.length === 0 ? (
                                                <p className="text-muted text-center py-4"><i className="ri-inbox-line me-1"></i>Aucun bordereau pour cette période.</p>
                                            ) : (
                                                <TableContainerReactTable
                                                    columns={[
                                                        { header: 'Numéro', cell: (c: any) => <span className="fw-medium text-primary">{c.row.original.numero}</span> },
                                                        { header: 'Montant', cell: (c: any) => <span className="fw-bold">{Number(c.row.original.montant_total || 0).toLocaleString('fr-FR')} FCFA</span> },
                                                        { header: 'Statut', cell: (c: any) => <Badge color={getStatutBadge(c.row.original.statut)} className="fs-11">{c.row.original.statut}</Badge> },
                                                    ]}
                                                    data={bordereaux} isGlobalFilter={true} customPageSize={10}
                                                    divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                            )}
                                        </TabPane>
                                    </TabContent>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════ MODALES ═══════ */}

            {/* Modale — Détail paiement */}
            <Modal isOpen={modalDetailOpen} toggle={() => setModalDetailOpen(false)} size="lg" centered>
                <ModalHeader toggle={() => setModalDetailOpen(false)} className="bg-light">
                    <i className="ri-file-user-line me-2"></i>Détail du paiement
                </ModalHeader>
                <ModalBody>
                    {detailRow && (
                        <Row>
                            <Col md={6}>
                                <table className="table table-sm align-middle mb-0">
                                    <tbody>
                                        <tr><th className="text-muted fw-medium">Agence</th><td>{detailRow.agence?.nom}</td></tr>
                                        <tr><th className="text-muted fw-medium">Entreprise</th><td className="fw-medium">{detailRow.entreprise?.raison_sociale}</td></tr>
                                        <tr><th className="text-muted fw-medium">Financement</th><td>{detailRow.stage?.source_financement}</td></tr>
                                        <tr><th className="text-muted fw-medium">Type Stage</th><td>{detailRow.stage?.type_stage}</td></tr>
                                    </tbody>
                                </table>
                            </Col>
                            <Col md={6}>
                                <table className="table table-sm align-middle mb-0">
                                    <tbody>
                                        <tr><th className="text-muted fw-medium">N° AEJ</th><td>{detailRow.beneficiaire?.matricule}</td></tr>
                                        <tr><th className="text-muted fw-medium">Nom et prénoms</th><td className="fw-semibold">{detailRow.beneficiaire?.nom} {detailRow.beneficiaire?.prenoms}</td></tr>
                                        <tr><th className="text-muted fw-medium">N° Trésor Pay</th><td>{detailRow.beneficiaire?.tresor_pay}</td></tr>
                                        <tr><th className="text-muted fw-medium">Montant</th><td className="fw-bold text-success">{Number(detailRow.montant || 0).toLocaleString('fr-FR')} FCFA</td></tr>
                                        <tr><th className="text-muted fw-medium">État</th><td><Badge color={getStatutBadge(detailRow.statut)}>{detailRow.statut}</Badge></td></tr>
                                    </tbody>
                                </table>
                            </Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalDetailOpen(false)}>Fermer</Button>
                </ModalFooter>
            </Modal>

            {/* Modale — Ajourner */}
            <Modal isOpen={modalAjournerOpen} toggle={() => !processing && setModalAjournerOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalAjournerOpen(false)} className="bg-danger text-white">
                    <i className="ri-close-circle-line me-2"></i>Ajourner le paiement
                </ModalHeader>
                <ModalBody>
                    <p>Les dossiers sélectionnés seront ajournés et retournés au Chef d'Agence pour correction.</p>
                    <div>
                        <Label className="form-label">Motif <span className="text-danger">*</span></Label>
                        <Input type="textarea" rows={3} value={motifAjourner} onChange={(e) => setMotifAjourner(e.target.value)}
                            placeholder="Ex : Dossier physique non conforme, montant erroné..." disabled={processing} />
                        {motifAjourner.trim().length > 0 && motifAjourner.trim().length < 5 && (
                            <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                        )}
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="danger" onClick={handleAjournerPaiement} disabled={processing || motifAjourner.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : "Confirmer l'ajournement"}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Modale — Marquer dossier physique */}
            <Modal isOpen={modalDossierOpen} toggle={() => !processing && setModalDossierOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalDossierOpen(false)} className="bg-dark text-white">
                    <i className="ri-folder-fill me-2"></i>Marquer le dossier physique
                </ModalHeader>
                <ModalBody>
                    <p>Indiquez le statut du dossier physique pour les stagiaires sélectionnés.</p>
                    <div>
                        <Label className="form-label">Statut</Label>
                        <Input type="select" value={dossierStatus} onChange={(e) => setDossierStatus(e.target.value)}>
                            <option value="en_attente">En attente</option>
                            <option value="recu">Reçu</option>
                            <option value="conforme">Conforme</option>
                        </Input>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalDossierOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="dark" onClick={() => handleMarquerDossier(activeTab === '2' ? selectedPresenceIds : selectedDemarrageIds, dossierStatus)} disabled={processing}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DmgPaiementsIndex;
