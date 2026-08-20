import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import classnames from 'classnames';
import React, { useCallback, useMemo, useState, useEffect } from 'react';
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
interface RowData {
    id: number;
    date: string;
    agence: string;
    entreprise: string;
    source_financement: string;
    type_stage: string;
    type_structure: string;
    numero_aej: string;
    nom_prenoms: string;
    date_naissance: string;
    sexe: string;
    contrat_label: string;
    incidence_financiere: string;
    date_debut: string;
    date_fin: string;
    corbeille_actuelle: string;
}

interface MoisOption {
    value: string;
    label: string;
    count: number;
}

interface Counts {
    demarrage: number;
    demarrageOmis: number;
    retourAjournement: number;
}

interface RefItem {
    id: number;
    nom: string;
    raison_sociale?: string;
}

interface PageProps {
    agences: RefItem[];
    entreprises: RefItem[];
    typesfinancements: RefItem[];
    typestages: RefItem[];
    typestructures: RefItem[];
    filters: Record<string, string>;
}

/* ─── Helpers ─── */
const formatDateFr = (dateStr: string | null | undefined) => {
    if (!dateStr || dateStr === '-') return '-';
    try {
        return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const ValidationDemarrageIndex = (props: PageProps) => {
    const {
        agences = [],
        entreprises = [],
        typesfinancements = [],
        typestages = [],
        typestructures = [],
        filters = {},
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    /* ─── États ─── */
    const [data, setData] = useState<{
        demarrage: RowData[];
        demarrageOmis: RowData[];
        retourAjournement: RowData[];
        counts: Counts;
    }>({
        demarrage: [],
        demarrageOmis: [],
        retourAjournement: [],
        counts: { demarrage: 0, demarrageOmis: 0, retourAjournement: 0 },
    });
    const [isLoading, setIsLoading] = useState(true);
    const [moisOmis, setMoisOmis] = useState<MoisOption[]>([]);
    const [isMoisLoading, setIsMoisLoading] = useState(false);
    const [activeTab, setActiveTab] = useState(filters?.tab || '1');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isProcessing, setIsProcessing] = useState(false);

    /* ─── Validation par filtre (financement + type stage) ─── */
    const hasFilterValidation = Boolean(selectedFilters.typesfinancement_id && selectedFilters.typestage_id);

    /* ─── Modales ─── */
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [modalContratOpen, setModalContratOpen] = useState(false);
    const [previewRow, setPreviewRow] = useState<RowData | null>(null);
    const [motifAjournement, setMotifAjournement] = useState('');
    const [contratRowId, setContratRowId] = useState<number | null>(null);
    const [contratFonction, setContratFonction] = useState('');
    const [contratMontant, setContratMontant] = useState('');

    /* ─── Filtres ─── */
    const [selectedFilters, setSelectedFilters] = useState({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        created_begin: filters?.created_begin || '',
        created_end: filters?.created_end || '',
        mois_debut: filters?.mois_debut || '',
    });

    /* ─── Données onglet courant ─── */
    const currentRows: RowData[] = useMemo(() => {
        if (activeTab === '2') return data.demarrageOmis || [];
        if (activeTab === '3') return data.retourAjournement || [];
        return data.demarrage || [];
    }, [activeTab, data]);

    const currentTabType = useMemo(() => {
        if (activeTab === '2') return 'demarrageOmis';
        if (activeTab === '3') return 'retourAjournement';
        return 'demarrage';
    }, [activeTab]);

    /* ─── Fetch Data ─── */
    const fetchValidations = useCallback(async (params: Record<string, string>) => {
        setIsLoading(true);
        try {
            const response = await axios.get('/chefagence/validations', {
                params,
                headers: { Accept: 'application/json' },
            });
            const raw = response.data !== undefined ? response.data : response || {};
            setData({
                demarrage: Array.isArray(raw?.demarrage) ? raw.demarrage : [],
                demarrageOmis: Array.isArray(raw?.demarrageOmis) ? raw.demarrageOmis : [],
                retourAjournement: Array.isArray(raw?.retourAjournement) ? raw.retourAjournement : [],
                counts: {
                    demarrage: raw?.counts?.demarrage ?? 0,
                    demarrageOmis: raw?.counts?.demarrageOmis ?? 0,
                    retourAjournement: raw?.counts?.retourAjournement ?? 0,
                },
            });
        } catch (error) {
            console.error('Erreur lors du chargement des données', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchMoisOmis = useCallback(async (commonFilters: Record<string, string>) => {
        setIsMoisLoading(true);
        try {
            const response = await axios.get('/chefagence/validations/mois-omis', {
                params: commonFilters,
                headers: { Accept: 'application/json' },
            });
            const raw = response.data !== undefined ? response.data : response;
            setMoisOmis(Array.isArray(raw) ? raw : []);
        } catch {
            setMoisOmis([]);
        } finally {
            setIsMoisLoading(false);
        }
    }, []);

    /* ─── Navigation Filtres ─── */
    const applyFilters = useCallback(
        (newFilters: typeof selectedFilters, tab?: string) => {
            const params: Record<string, string> = { tab: tab ?? activeTab };
            Object.entries(newFilters).forEach(([key, val]) => {
                if (val) params[key] = val;
            });
            const url = new URL(window.location.href);
            url.search = '';
            Object.entries(params).forEach(([key, val]) => {
                if (val) url.searchParams.set(key, val);
            });
            window.history.replaceState({}, '', url);
            fetchValidations(params);

            const commonParams: Record<string, string> = {};
            (['agence_id', 'entreprise_id', 'typesfinancement_id', 'typestage_id', 'type_structure_id'] as const).forEach((k) => {
                if (newFilters[k]) commonParams[k] = newFilters[k];
            });
            fetchMoisOmis(commonParams);
        },
        [activeTab, fetchValidations, fetchMoisOmis],
    );

    useEffect(() => {
        const params: Record<string, string> = { tab: activeTab };
        Object.entries(selectedFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        fetchValidations(params);
        const commonParams: Record<string, string> = {};
        (['agence_id', 'entreprise_id', 'typesfinancement_id', 'typestage_id', 'type_structure_id'] as const).forEach((k) => {
            if (selectedFilters[k]) commonParams[k] = selectedFilters[k];
        });
        fetchMoisOmis(commonParams);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleFilterChange = (field: string, value: string) => {
        const newFilters = { ...selectedFilters, [field]: value };
        setSelectedFilters(newFilters);
        applyFilters(newFilters);
    };

    const resetFilters = () => {
        const defaults = {
            agence_id: '', entreprise_id: '', typesfinancement_id: '', typestage_id: '',
            type_structure_id: '', created_begin: '', created_end: '', mois_debut: '',
        };
        setSelectedFilters(defaults);
        setSelectedIds([]);
        router.visit('/chefagence/validations');
    };

    const toggleTab = (tab: string) => {
        if (tab !== activeTab) {
            setActiveTab(tab);
            setSelectedIds([]);
            applyFilters(selectedFilters, tab);
        }
    };

    /* ─── Sélection ─── */
    const toggleSelectAll = useCallback(() => {
        const allIds = currentRows.map((r) => r.id);
        setSelectedIds((prev) => (prev.length === allIds.length ? [] : allIds));
    }, [currentRows]);

    const toggleSelectOne = useCallback((id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    }, []);

    /* ─── Recharger les données ─── */
    const reloadData = () => {
        const params: Record<string, string> = { tab: activeTab };
        Object.entries(selectedFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        fetchValidations(params);
    };

    /* ─── Validation individuelle ─── */
    const handleValiderIndividual = (instanceId: number) => {
        if (isProcessing) return;
        setIsProcessing(true);
        router.post(`/chefagence/validations/valider-group`, {
            ids: [instanceId],
            type: currentTabType,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setIsProcessing(false);
                reloadData();
            },
        });
    };

    /* ─── Validation groupée ─── */
    const openValiderModal = () => {
        if (selectedIds.length === 0) return;
        setModalValiderOpen(true);
    };

    const confirmValider = () => {
        setIsProcessing(true);
        router.post('/chefagence/validations/valider-group', {
            ids: selectedIds.map(String),
            type: currentTabType,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setModalValiderOpen(false);
                setSelectedIds([]);
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Validation de toute la liste ─── */
    const handleValiderTout = () => {
        if (activeTab === '2' && !selectedFilters.mois_debut) return;
        const allIds = currentRows.map((r) => r.id);
        if (allIds.length === 0) return;
        setIsProcessing(true);
        router.post('/chefagence/validations/valider-group', { ids: allIds.map(String), type: currentTabType }, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIds([]); reloadData(); },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Validation par filtre (financement + type stage) ─── */
    const handleValiderParFiltre = () => {
        if (!hasFilterValidation || currentRows.length === 0 || isProcessing) return;
        setIsProcessing(true);
        router.post('/chefagence/validations/valider-group', {
            ids: currentRows.map((r) => r.id).map(String),
            type: currentTabType,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIds([]); reloadData(); },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Ajournement ─── */
    const openAjournerModal = () => {
        if (selectedIds.length === 0) return;
        setMotifAjournement('');
        setModalAjournerOpen(true);
    };

    const confirmAjourner = () => {
        if (motifAjournement.trim().length < 5) return;
        setIsProcessing(true);
        router.post('/chefagence/validations/ajourner-group', {
            ids: selectedIds.map(String),
            motif: motifAjournement,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setModalAjournerOpen(false);
                setSelectedIds([]);
                setMotifAjournement('');
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Génération ADD ─── */
    const handleGenererAdd = (ids: number[]) => {
        if (ids.length === 0) return;
        setIsProcessing(true);
        router.post('/chefagence/validations/generer-add-group', { ids: ids.map(String), type: currentTabType }, {
            preserveScroll: true,
            onSuccess: () => { setSelectedIds([]); reloadData(); },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Génération Contrat ─── */
    const openContratModal = (rowId: number) => {
        setContratRowId(rowId);
        setContratFonction('');
        setContratMontant('');
        setModalContratOpen(true);
    };

    const handleGenererContrat = () => {
        if (!contratRowId) return;
        const params = new URLSearchParams();
        if (contratFonction.trim()) params.append('fonction', contratFonction.trim());
        if (contratMontant.trim()) params.append('montant', contratMontant.trim());
        window.open(`/chefagence/validations/${contratRowId}/generer-contrat${params.toString() ? '?' + params.toString() : ''}`, '_blank');
        setModalContratOpen(false);
        setContratRowId(null);
    };

    /* ─── Génération Trésor Money ─── */
    const handleGenererTresorMoney = () => {
        if (selectedIds.length === 0) return;
        setIsProcessing(true);
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/chefagence/validations/generer-tresor-money-group';
        form.target = '_blank';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }
        selectedIds.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = String(id);
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        setTimeout(() => setIsProcessing(false), 1000);
    };

    /* ─── Indicateurs de décision ─── */
    const stats = useMemo(() => {
        const withContrat = currentRows.filter((r) => r.contrat_label === 'Avec Contrat').length;
        const withIncidence = currentRows.filter((r) => r.incidence_financiere === 'Oui').length;
        const sansContrat = currentRows.length - withContrat;
        return { withContrat, withIncidence, sansContrat };
    }, [currentRows]);

    /* ─── Onglets ─── */
    const tabs = [
        { key: '1', label: 'DÉMARRAGE', count: data?.counts?.demarrage ?? 0, color: 'primary', icon: 'ri-play-circle-line' },
        { key: '2', label: 'DÉMARRAGE OMIS', count: data?.counts?.demarrageOmis ?? 0, color: 'warning', icon: 'ri-time-line' },
        { key: '3', label: "RETOUR D'AJOURNEMENT", count: data?.counts?.retourAjournement ?? 0, color: 'danger', icon: 'ri-arrow-go-back-line' },
    ];

    /* ─── Colonnes ─── */
    const columns = useMemo(
        () => [
            {
                id: 'select',
                header: () => (
                    <input
                        type="checkbox"
                        className="form-check-input"
                        checked={currentRows.length > 0 && selectedIds.length === currentRows.length}
                        onChange={toggleSelectAll}
                    />
                ),
                cell: (cell: any) => (
                    <input
                        type="checkbox"
                        className="form-check-input"
                        checked={selectedIds.includes(cell.row.original.id)}
                        onChange={() => toggleSelectOne(cell.row.original.id)}
                    />
                ),
                size: 50,
            },
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => (
                    <span className="fw-medium">{cell.row.original.entreprise}</span>
                ),
            },
            {
                header: 'Financement',
                cell: (cell: any) => {
                    const val = cell.row.original.source_financement;
                    const colorMap: Record<string, string> = {
                        'PEJEDEC': 'info', 'BUDGET AEJ': 'primary', 'PAPS-GOUV': 'success', 'C2D': 'warning',
                    };
                    const color = colorMap[val] || 'secondary';
                    return <Badge color={`${color}-subtle`} className={`text-${color}`}>{val}</Badge>;
                },
            },
            {
                header: 'Type Stage',
                cell: (cell: any) => cell.row.original.type_stage,
            },
            {
                header: 'Type Structure',
                cell: (cell: any) => {
                    const val = cell.row.original.type_structure;
                    if (!val || val === '-') return <span className="text-muted">-</span>;
                    return <Badge color="success-subtle" className="text-success">{val}</Badge>;
                },
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => <span className="text-muted">{cell.row.original.numero_aej}</span>,
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => <span className="fw-semibold">{cell.row.original.nom_prenoms}</span>,
            },
            {
                header: 'Contrat',
                cell: (cell: any) => {
                    const has = cell.row.original.contrat_label === 'Avec Contrat';
                    return (
                        <Badge color={has ? 'success-subtle' : 'warning-subtle'} className={`text-${has ? 'success' : 'warning'}`}>
                            <i className={`ri-${has ? 'check' : 'close'}-circle-line me-1`}></i>
                            {cell.row.original.contrat_label}
                        </Badge>
                    );
                },
            },
            {
                header: 'Incidence',
                cell: (cell: any) => {
                    const has = cell.row.original.incidence_financiere === 'Oui';
                    return (
                        <Badge color={has ? 'danger-subtle' : 'secondary-subtle'} className={`text-${has ? 'danger' : 'secondary'}`}>
                            <i className={`ri-money-{has ? 'dollar' : 'none'}-circle-line me-1`}></i>
                            {has ? 'Financière' : 'Aucune'}
                        </Badge>
                    );
                },
            },
            {
                header: 'Date début',
                cell: (cell: any) => cell.row.original.date_debut,
            },
            {
                header: 'Date fin',
                cell: (cell: any) => cell.row.original.date_fin,
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const row = cell.row.original as RowData;
                    return (
                        <div className="d-flex gap-1">
                            <Button color="info" size="sm" outline onClick={() => setPreviewRow(row)} title="Voir le détail">
                                <i className="ri-eye-line"></i>
                            </Button>
                            <Button color="success" size="sm" outline onClick={() => handleValiderIndividual(row.id)} title="Valider" disabled={isProcessing}>
                                <i className="ri-check-line"></i>
                            </Button>
                            <Button color="warning" size="sm" outline onClick={() => openContratModal(row.id)} title="Générer contrat">
                                <i className="ri-file-text-line"></i>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [currentRows, selectedIds, toggleSelectAll, toggleSelectOne, isProcessing],
    );

    /* ════════════════════════════════════════════════════════
       RENDU
       ════════════════════════════════════════════════════════ */
    return (
        <React.Fragment>
            <Head title="Validation Démarrage" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validations des Démarrages" pageTitle="Chef d'Agence" />

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
                        {tabs.map((card) => (
                            <Col lg={4} md={4} sm={12} key={card.key}>
                                <Card
                                    className="mb-0 shadow-sm border-0"
                                    onClick={() => toggleTab(card.key)}
                                    style={{
                                        cursor: 'pointer',
                                        borderLeft: activeTab === card.key
                                            ? `4px solid var(--vz-${card.color})`
                                            : '4px solid transparent',
                                        transition: 'border-left-color 0.2s ease',
                                    }}
                                >
                                    <CardBody className="py-3">
                                        <div className="d-flex align-items-center">
                                            <div className="avatar-sm flex-shrink-0 me-3">
                                                <span className={`avatar-title bg-${card.color}-subtle text-${card.color} rounded-circle fs-20`}>
                                                    <i className={card.icon}></i>
                                                </span>
                                            </div>
                                            <div className="flex-grow-1">
                                                <p className="text-muted text-uppercase fw-medium fs-12 mb-1">{card.label}</p>
                                                <h3 className={`mb-0 text-${card.color}`}>{card.count}</h3>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    {/* ─── Carte principale ─── */}
                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom-0 pb-0">
                            <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                <h4 className="card-title mb-0">
                                    <i className="ri-checkbox-multiple-line me-2 text-success"></i>
                                    Validation &amp; Démarrages
                                    {selectedIds.length > 0 && (
                                        <Badge color="success" className="ms-2 fs-12">
                                            {selectedIds.length} sélectionné(s)
                                        </Badge>
                                    )}
                                </h4>
                                <Button color="soft-secondary" size="sm" onClick={resetFilters}>
                                    <i className="ri-refresh-line me-1"></i>Réinitialiser les filtres
                                </Button>
                            </div>
                        </CardHeader>

                        <CardBody>
                            {/* ── Filtres ── */}
                            <Row className="g-3 mb-4">
                                <Col md={2} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.agence_id}
                                        onChange={(e) => handleFilterChange('agence_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {agences.map((a) => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={2} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Entreprise</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.entreprise_id}
                                        onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {entreprises.map((e) => <option key={e.id} value={e.id}>{e.raison_sociale || e.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={2} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Financement</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.typesfinancement_id}
                                        onChange={(e) => handleFilterChange('typesfinancement_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {typesfinancements.map((sf) => <option key={sf.id} value={sf.id}>{sf.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={2} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type Stage</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.typestage_id}
                                        onChange={(e) => handleFilterChange('typestage_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {typestages.map((ts) => <option key={ts.id} value={ts.id}>{ts.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={2} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type Structure</Label>
                                    <Input type="select" bsSize="sm" value={selectedFilters.type_structure_id}
                                        onChange={(e) => handleFilterChange('type_structure_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {typestructures.map((t) => <option key={t.id} value={t.id}>{t.nom}</option>)}
                                    </Input>
                                </Col>
                                <Col md={1} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Du</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.created_begin}
                                        onChange={(e) => handleFilterChange('created_begin', e.target.value)} />
                                </Col>
                                <Col md={1} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Au</Label>
                                    <Input type="date" bsSize="sm" value={selectedFilters.created_end}
                                        onChange={(e) => handleFilterChange('created_end', e.target.value)} />
                                </Col>
                            </Row>

                            {/* ── Onglets ── */}
                            <Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                {tabs.map((t) => (
                                    <NavItem key={t.key}>
                                        <NavLink
                                            className={classnames({ active: activeTab === t.key }, 'fw-semibold py-3')}
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

                            {/* ── Barre d'actions ── */}
                            <div className="d-flex flex-wrap gap-2 pt-3 pb-2 border-bottom">
                                {/* Actions sur sélection */}
                                {selectedIds.length > 0 && (
                                    <>
                                        <Button color="success" size="sm" onClick={openValiderModal} disabled={isProcessing}>
                                            <i className="ri-check-double-line me-1"></i>Valider ({selectedIds.length})
                                        </Button>
                                        <Button color="danger" size="sm" onClick={openAjournerModal} disabled={isProcessing}>
                                            <i className="ri-close-circle-line me-1"></i>Ajourner ({selectedIds.length})
                                        </Button>
                                        <span className="vr my-1" />
                                        <Button color="info" size="sm" onClick={() => handleGenererAdd(selectedIds)} disabled={isProcessing}>
                                            <i className="ri-file-text-line me-1"></i>ADD (sélection)
                                        </Button>
                                        <Button color="warning" size="sm" onClick={handleGenererTresorMoney} disabled={isProcessing}>
                                            <i className="ri-money-dollar-circle-line me-1"></i>Trésor Money
                                        </Button>
                                    </>
                                )}

                                {/* Actions globales */}
                                <Button color="primary" size="sm" onClick={handleValiderTout} disabled={isProcessing || currentRows.length === 0}
                                    className={selectedIds.length === 0 ? '' : 'ms-auto'}>
                                    <i className="ri-folder-check-line me-1"></i>Valider toute la liste
                                </Button>
                                <Button color="secondary" size="sm" onClick={() => handleGenererAdd(currentRows.map((r) => r.id))}
                                    disabled={isProcessing || currentRows.length === 0}>
                                    <i className="ri-file-list-3-line me-1"></i>ADD (liste)
                                </Button>

                                {/* Indicateurs de décision */}
                                {currentRows.length > 0 && (
                                    <div className="ms-auto d-flex gap-3 align-items-center">
                                        <span className="text-muted fs-12">
                                            <i className="ri-information-line me-1"></i>
                                            {stats.withContrat} avec contrat · {stats.withIncidence} incidence financière
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* ── Contenu onglets ── */}
                            <TabContent activeTab={activeTab} className="pt-3">
                                {isLoading ? (
                                    <div className="d-flex justify-content-center align-items-center py-5">
                                        <Spinner color="success" />
                                    </div>
                                ) : (
                                    <>
                                        {/* Onglet 1 : Démarrage */}
                                        <TabPane tabId="1">
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.demarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mt-1 mb-1"
                                                tableClass="align-middle table-nowrap table-hover"
                                                theadClass="table-light text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>

                                        {/* Onglet 2 : Démarrage Omis */}
                                        <TabPane tabId="2">
                                            <div className="bg-warning-subtle border border-warning-subtle rounded mb-3 p-3">
                                                <div className="d-flex align-items-center gap-2 mb-2">
                                                    <i className="ri-calendar-2-line text-warning fs-15"></i>
                                                    <span className="fw-semibold text-warning fs-13">MOIS DE DÉMARRAGE</span>
                                                    {selectedFilters.mois_debut && (
                                                        <button type="button" className="btn btn-sm btn-link text-muted p-0 ms-1 fs-12"
                                                            onClick={() => handleFilterChange('mois_debut', '')}>
                                                            <i className="ri-close-line me-1"></i>Tout afficher
                                                        </button>
                                                    )}
                                                </div>
                                                {isMoisLoading ? (
                                                    <div className="d-flex align-items-center gap-2 text-muted fs-13">
                                                        <Spinner size="sm" color="warning" />Chargement...
                                                    </div>
                                                ) : moisOmis.length === 0 ? (
                                                    <p className="text-muted fs-13 mb-0">
                                                        <i className="ri-inbox-line me-1"></i>Aucun démarrage omis disponible.
                                                    </p>
                                                ) : (
                                                    <div className="d-flex flex-wrap gap-2">
                                                        {moisOmis.map((mois) => {
                                                            const isActive = selectedFilters.mois_debut === mois.value;
                                                            return (
                                                                <button key={mois.value} type="button"
                                                                    onClick={() => handleFilterChange('mois_debut', isActive ? '' : mois.value)}
                                                                    className={`btn btn-sm d-flex align-items-center gap-2 ${isActive ? 'btn-warning' : 'btn-outline-secondary'}`}
                                                                    style={{ fontWeight: isActive ? 600 : 400 }}>
                                                                    {mois.label}
                                                                    <span className={`badge rounded-pill ${isActive ? 'bg-white text-warning' : 'bg-secondary-subtle text-secondary'}`}>
                                                                        {mois.count.toLocaleString('fr-FR')}
                                                                    </span>
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.demarrageOmis || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mt-1 mb-1"
                                                tableClass="align-middle table-nowrap table-hover"
                                                theadClass="table-light text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>

                                        {/* Onglet 3 : Retour d'Ajournement */}
                                        <TabPane tabId="3">
                                            <Alert color="warning" className="border-0 mb-3">
                                                <i className="ri-information-line me-2"></i>
                                                Ces dossiers ont été ajournés et retournés pour correction.
                                                Vous pouvez valider la correction ou ajourner à nouveau.
                                            </Alert>
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.retourAjournement || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mt-1 mb-1"
                                                tableClass="align-middle table-nowrap table-hover"
                                                theadClass="table-light text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>
                                    </>
                                )}
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════ MODALES ═══════ */}

            {/* Modale — Valider la sélection */}
            <Modal isOpen={modalValiderOpen} toggle={() => !isProcessing && setModalValiderOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setModalValiderOpen(false)} className="bg-success text-white">
                    <i className="ri-check-double-line me-2"></i>Validation de dossiers
                </ModalHeader>
                <ModalBody>
                    <p>Vous êtes sur le point de valider <strong>{selectedIds.length}</strong> dossier(s).</p>
                    <div className="alert alert-secondary border-0 mb-0">
                        <strong>Indicateurs :</strong> {stats.withContrat} avec contrat · {stats.withIncidence} avec incidence financière
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalValiderOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button color="success" onClick={confirmValider} disabled={isProcessing}>
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer la validation'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Modale — Ajourner la sélection */}
            <Modal isOpen={modalAjournerOpen} toggle={() => !isProcessing && setModalAjournerOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setModalAjournerOpen(false)} className="bg-danger text-white">
                    <i className="ri-close-circle-line me-2"></i>Ajourner la sélection
                </ModalHeader>
                <ModalBody>
                    <p>Vous êtes sur le point d'ajourner <strong>{selectedIds.length}</strong> dossier(s).
                        Les dossiers retourneront dans la corbeille CIP pour correction.</p>
                    <div className="mb-0">
                        <Label className="form-label">Motif <span className="text-danger">*</span></Label>
                        <Input type="textarea" rows={3} value={motifAjournement}
                            onChange={(e) => setMotifAjournement(e.target.value)}
                            placeholder="Ex : Contrat illisible, dates incorrectes..." disabled={isProcessing} />
                        {motifAjournement.trim().length > 0 && motifAjournement.trim().length < 5 && (
                            <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                        )}
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjourner} disabled={isProcessing || motifAjournement.trim().length < 5}>
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : "Confirmer l'ajournement"}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Modale — Détail dossier */}
            <Modal isOpen={Boolean(previewRow)} toggle={() => setPreviewRow(null)} size="lg" centered>
                <ModalHeader toggle={() => setPreviewRow(null)} className="bg-light">
                    <i className="ri-file-user-line me-2"></i>Détail du dossier
                </ModalHeader>
                <ModalBody>
                    {previewRow && (
                        <Row>
                            <Col md={6}>
                                <table className="table table-sm align-middle mb-0">
                                    <tbody>
                                        <tr><th className="text-muted fw-medium">Agence</th><td>{previewRow.agence}</td></tr>
                                        <tr><th className="text-muted fw-medium">Entreprise</th><td className="fw-medium">{previewRow.entreprise}</td></tr>
                                        <tr><th className="text-muted fw-medium">Financement</th><td>{previewRow.source_financement}</td></tr>
                                        <tr><th className="text-muted fw-medium">Type Stage</th><td>{previewRow.type_stage}</td></tr>
                                        <tr><th className="text-muted fw-medium">Type Structure</th><td>{previewRow.type_structure !== '-' ? <Badge color="success">{previewRow.type_structure}</Badge> : '-'}</td></tr>
                                    </tbody>
                                </table>
                            </Col>
                            <Col md={6}>
                                <table className="table table-sm align-middle mb-0">
                                    <tbody>
                                        <tr><th className="text-muted fw-medium">N° AEJ</th><td>{previewRow.numero_aej}</td></tr>
                                        <tr><th className="text-muted fw-medium">Nom et prénoms</th><td className="fw-semibold">{previewRow.nom_prenoms}</td></tr>
                                        <tr><th className="text-muted fw-medium">Contrat</th><td><Badge color={previewRow.contrat_label === 'Avec Contrat' ? 'success' : 'warning'}>{previewRow.contrat_label}</Badge></td></tr>
                                        <tr><th className="text-muted fw-medium">Incidence financière</th><td><Badge color={previewRow.incidence_financiere === 'Oui' ? 'danger' : 'secondary'}>{previewRow.incidence_financiere === 'Oui' ? 'Oui — Financière' : 'Non'}</Badge></td></tr>
                                        <tr><th className="text-muted fw-medium">Date début</th><td>{previewRow.date_debut}</td></tr>
                                        <tr><th className="text-muted fw-medium">Date fin</th><td>{previewRow.date_fin}</td></tr>
                                    </tbody>
                                </table>
                            </Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setPreviewRow(null)}>Fermer</Button>
                </ModalFooter>
            </Modal>

            {/* Modale — Génération Contrat */}
            <Modal isOpen={modalContratOpen} toggle={() => setModalContratOpen(false)} centered>
                <ModalHeader toggle={() => setModalContratOpen(false)} className="bg-success text-white">
                    <i className="ri-file-text-line me-2"></i>Génération de Contrat
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">
                        <i className="ri-information-line me-1"></i>Personnalisez les informations avant de générer le contrat (optionnel)
                    </p>
                    <div className="mb-3">
                        <Label className="form-label">Fonction du poste</Label>
                        <Input type="text" placeholder="Ex: Assistant administratif..." value={contratFonction}
                            onChange={(e) => setContratFonction(e.target.value)} />
                        <small className="text-muted">Laissez vide pour utiliser la fonction enregistrée</small>
                    </div>
                    <div className="mb-3">
                        <Label className="form-label">Montant de l'indemnité (FCFA)</Label>
                        <Input type="number" placeholder="Ex: 100000" value={contratMontant}
                            onChange={(e) => setContratMontant(e.target.value)} min="0" step="1000" />
                        <small className="text-muted">Laissez vide pour utiliser le montant enregistré</small>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalContratOpen(false)}>Annuler</Button>
                    <Button color="success" onClick={handleGenererContrat}>
                        <i className="ri-file-download-line me-1"></i>Générer le contrat
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default ValidationDemarrageIndex;
