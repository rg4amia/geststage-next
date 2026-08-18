import { Head, router, usePage, useForm } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useCallback, useMemo, useState, useEffect } from 'react';
import axios from 'axios';
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
type RowData = {
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
};

interface Counts {
    demarrage: number;
    demarrageOmis: number;
    retourAjournement: number;
}

interface PageProps {
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    typestructures: Record<string, string>;
    periodes: Record<string, string>;
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
        agences = {},
        entreprises = {},
        typesfinancements = {},
        typestages = {},
        typestructures = {},
        periodes = {},
        filters = {},
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    /* ─── États pour le chargement asynchrone (JSON) ─── */
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

    /* ─── Onglet actif ─── */
    const [activeTab, setActiveTab] = useState(filters?.tab || '1');

    /* ─── Sélection de lignes ─── */
    const [selectedRows, setSelectedRows] = useState<string[]>([]);

    /* ─── État de traitement (modales) ─── */
    const [isProcessing, setIsProcessing] = useState(false);

    /* ─── Modales ─── */
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [previewRow, setPreviewRow] = useState<RowData | null>(null);
    const [motifAjournement, setMotifAjournement] = useState('');

    /* ─── Filtres ─── */
    const [selectedFilters, setSelectedFilters] = useState({
        agence_id:           filters?.agence_id || '',
        entreprise_id:       filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id:        filters?.typestage_id || '',
        type_structure_id:   filters?.type_structure_id || '',
        created_begin:       filters?.created_begin || '',
        created_end:         filters?.created_end || '',
        periode_id:          filters?.periode_id || '',
    });

    /* ─── Formulaire pour les actions groupées ─── */
    const {
        data: actionData,
        setData: setActionData,
        post,
        reset: resetAction,
    } = useForm({
        ids:   [] as string[],
        motif: '',
        type:  'demarrage',
    });

    /* ─── Données de l'onglet courant ─── */
    const currentRows: RowData[] = useMemo(() => {
        if (activeTab === '2') return data.demarrageOmis || [];
        if (activeTab === '3') return data.retourAjournement || [];
        return data.demarrage || [];
    }, [activeTab, data]);

    const currentTabLabel = useMemo(() => {
        if (activeTab === '2') return 'Liste des démarrages omis';
        if (activeTab === '3') return "Liste des retours d'ajournement";
        return 'Liste des démarrages';
    }, [activeTab]);

    const currentTabType = useMemo(() => {
        if (activeTab === '2') return 'demarrageOmis';
        if (activeTab === '3') return 'retourAjournement';
        return 'demarrage';
    }, [activeTab]);

    /* ─── Fetch Data (Axios JSON) ─── */
    const fetchValidations = useCallback(
        async (params: Record<string, string>) => {
            setIsLoading(true);
            try {
                const response = await axios.get('/chefagence/validations', {
                    params,
                    headers: { Accept: 'application/json' },
                });
                // Handle Velzone's global axios interceptor which may return response.data directly
                const raw = response.data !== undefined ? response.data : response || {};
                
                setData({
                    demarrage:         Array.isArray(raw?.demarrage) ? raw.demarrage : [],
                    demarrageOmis:     Array.isArray(raw?.demarrageOmis) ? raw.demarrageOmis : [],
                    retourAjournement: Array.isArray(raw?.retourAjournement) ? raw.retourAjournement : [],
                    counts: {
                        demarrage:         raw?.counts?.demarrage ?? 0,
                        demarrageOmis:     raw?.counts?.demarrageOmis ?? 0,
                        retourAjournement: raw?.counts?.retourAjournement ?? 0,
                    },
                });
            } catch (error) {
                console.error("Erreur lors du chargement des données", error);
            } finally {
                setIsLoading(false);
            }
        },
        []
    );

    /* ─── Navigation Filtres (temps réel fluide) ─── */
    const applyFilters = useCallback(
        (newFilters: typeof selectedFilters, tab?: string) => {
            const params: Record<string, string> = { tab: tab ?? activeTab };
            Object.entries(newFilters).forEach(([key, val]) => {
                if (val) params[key] = val;
            });
            // Update URL without page reload
            const url = new URL(window.location.href);
            url.search = '';
            Object.entries(params).forEach(([key, val]) => {
                if (val) url.searchParams.set(key, val);
            });
            window.history.replaceState({}, '', url);

            // Fetch json data
            fetchValidations(params);
        },
        [activeTab, fetchValidations],
    );

    // Initial load
    useEffect(() => {
        const params: Record<string, string> = { tab: activeTab };
        Object.entries(selectedFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        fetchValidations(params);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleFilterChange = (field: string, value: string) => {
        const newFilters = { ...selectedFilters, [field]: value };
        setSelectedFilters(newFilters);
        applyFilters(newFilters);
    };

    const resetFilters = () => {
        const defaultFilters = {
            agence_id:           '',
            entreprise_id:       '',
            typesfinancement_id: '',
            typestage_id:        '',
            type_structure_id:   '',
            created_begin:       '',
            created_end:         '',
            periode_id:          '',
        };
        setSelectedFilters(defaultFilters);
        setSelectedRows([]);
        router.visit('/chefagence/validations');
    };

    const toggleTab = (tab: string) => {
        if (tab !== activeTab) {
            setActiveTab(tab);
            setSelectedRows([]);
            applyFilters(selectedFilters, tab);
        }
    };

    /* ─── Sélection ─── */
    const handleRowSelect = useCallback((id: string) => {
        setSelectedRows((current) =>
            current.includes(id) ? current.filter((rowId) => rowId !== id) : [...current, id],
        );
    }, []);

    const handleSelectAll = useCallback(() => {
        const allIds = currentRows.map((row) => row.id.toString());
        setSelectedRows((current) => (current.length === allIds.length ? [] : allIds));
    }, [currentRows]);

    /* ─── Ouverture des modales ─── */
    const openValiderModal = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');
            return;
        }
        setActionData('ids', selectedRows);
        setActionData('type', currentTabType);
        setModalValiderOpen(true);
    };

    const openAjournerModal = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');
            return;
        }
        setActionData('ids', selectedRows);
        setMotifAjournement('');
        setModalAjournerOpen(true);
    };

    /* ─── Helper pour recharger les données après une action ─── */
    const reloadData = () => {
        const params: Record<string, string> = { tab: activeTab };
        Object.entries(selectedFilters).forEach(([key, val]) => {
            if (val) params[key] = val;
        });
        fetchValidations(params);
    };

    /* ─── Confirmation ─── */
    const confirmValider = () => {
        setIsProcessing(true);
        router.post('/chefagence/validations/valider-group', actionData, {
            preserveScroll: true,
            onSuccess: () => {
                setModalValiderOpen(false);
                setSelectedRows([]);
                resetAction();
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    const confirmAjourner = () => {
        if (!motifAjournement.trim() || motifAjournement.trim().length < 5) {
            alert('Le motif doit contenir au moins 5 caractères.');
            return;
        }
        setIsProcessing(true);
        router.post('/chefagence/validations/ajourner-group', {
            ids: actionData.ids,
            motif: motifAjournement,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setModalAjournerOpen(false);
                setSelectedRows([]);
                setMotifAjournement('');
                resetAction();
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Actions globales (toute la liste) ─── */
    const handleValidationListeEntiere = () => {
        if (activeTab === '2' && !selectedFilters.periode_id) {
            alert('Veuillez sélectionner une période pour le démarrage omis.');
            return;
        }
        const allIds = currentRows.map((row) => row.id.toString());
        if (allIds.length === 0) {
            alert('Aucun dossier à traiter dans cet onglet.');
            return;
        }
        setIsProcessing(true);
        router.post('/chefagence/validations/valider-group', { ids: allIds, type: currentTabType }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    const handleGenererAddSelection = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');
            return;
        }
        setIsProcessing(true);
        router.post('/chefagence/validations/generer-add-group', { ids: selectedRows, type: currentTabType }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    const handleGenererAddGlobal = () => {
        if (activeTab === '2' && !selectedFilters.periode_id) {
            alert('Veuillez sélectionner une période pour le démarrage omis.');
            return;
        }
        const allIds = currentRows.map((row) => row.id.toString());
        if (allIds.length === 0) {
            alert('Aucun dossier à traiter dans cet onglet.');
            return;
        }
        setIsProcessing(true);
        router.post('/chefagence/validations/generer-add-group', { ids: allIds, type: currentTabType }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                reloadData();
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Cartes statistiques ─── */
    const statCards = [
        {
            key:   '1',
            label: 'DÉMARRAGE',
            count: data?.counts?.demarrage ?? 0,
            color: 'primary',
            icon:  'ri-play-circle-line',
        },
        {
            key:   '2',
            label: 'DÉMARRAGE OMIS',
            count: data?.counts?.demarrageOmis ?? 0,
            color: 'warning',
            icon:  'ri-time-line',
        },
        {
            key:   '3',
            label: "RETOUR D'AJOURNEMENT",
            count: data?.counts?.retourAjournement ?? 0,
            color: 'danger',
            icon:  'ri-arrow-go-back-line',
        },
    ];

    /* ─── Colonnes ─── */
    const columns = useMemo(
        () => [
            {
                header: (
                    <div className="form-check">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            checked={currentRows.length > 0 && selectedRows.length === currentRows.length}
                            onChange={handleSelectAll}
                        />
                    </div>
                ),
                accessorKey: 'select',
                enableSorting: false,
                cell: (cell: any) => (
                    <div className="form-check">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            checked={selectedRows.includes(cell.row.original.id.toString())}
                            onChange={() => handleRowSelect(cell.row.original.id.toString())}
                        />
                    </div>
                ),
            },
            { header: 'Date', accessorKey: 'date' },
            { header: 'Agence', accessorKey: 'agence' },
            { header: 'Entreprise', accessorKey: 'entreprise' },
            { header: 'Source de financement', accessorKey: 'source_financement' },
            { header: 'Type de stage', accessorKey: 'type_stage' },
            {
                header: 'Type de structure',
                accessorKey: 'type_structure',
                cell: (cell: any) => {
                    const value = cell.getValue();
                    if (!value || value === '-') return '-';
                    return <Badge color="success">{value}</Badge>;
                },
            },
            { header: 'Numéro AEJ', accessorKey: 'numero_aej' },
            {
                header: 'Nom et prénoms',
                accessorKey: 'nom_prenoms',
                cell: (cell: any) => <span className="fw-medium">{cell.getValue()}</span>,
            },
            {
                header: 'Date de naissance',
                accessorKey: 'date_naissance',
                cell: (cell: any) => formatDateFr(cell.getValue()),
            },
            { header: 'Sexe', accessorKey: 'sexe' },
            {
                header: 'Contrat',
                accessorKey: 'contrat_label',
                cell: (cell: any) => {
                    const value = cell.getValue();
                    return value === 'Avec Contrat' ? (
                        <Badge color="success">{value}</Badge>
                    ) : (
                        <Badge color="warning">{value}</Badge>
                    );
                },
            },
            {
                header: 'Incidence financière',
                accessorKey: 'incidence_financiere',
                cell: (cell: any) => {
                    const value = cell.getValue();
                    return value === 'Oui' ? (
                        <Badge color="success">{value}</Badge>
                    ) : (
                        <Badge color="danger">{value}</Badge>
                    );
                },
            },
            { header: 'Date début', accessorKey: 'date_debut' },
            { header: 'Date de fin', accessorKey: 'date_fin' },
            {
                header: 'Action',
                accessorKey: 'actions',
                enableSorting: false,
                cell: (cell: any) => {
                    const row = cell.row.original as RowData;
                    return (
                        <Button
                            color="info"
                            size="sm"
                            className="btn-icon"
                            onClick={() => setPreviewRow(row)}
                            title="Voir le détail"
                        >
                            <i className="ri-eye-line" />
                        </Button>
                    );
                },
            },
        ],
        [currentRows, selectedRows, handleRowSelect, handleSelectAll],
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

                    {/* ─── Flash Messages ─── */}
                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-check-double-line me-2 align-middle" />
                            {flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-error-warning-line me-2 align-middle" />
                            {flash.error}
                        </Alert>
                    )}

                    {/* ─── Cartes Statistiques ─── */}
                    <Row className="g-3 mb-4">
                        {statCards.map((card) => (
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
                                                    <i className={card.icon} />
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

                    {/* ─── Filtres ─── */}
                    <Card className="shadow-sm border-0 mb-3">
                        <CardHeader className="bg-transparent border-bottom-0 pb-0">
                            <div className="d-flex justify-content-between align-items-center">
                                <h5 className="card-title mb-0">
                                    <i className="ri-filter-3-line me-2 text-muted" />
                                    Filtres
                                </h5>
                                <Button color="soft-secondary" size="sm" onClick={resetFilters}>
                                    <i className="ri-refresh-line me-1" />
                                    Réinitialiser
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody className="pb-2 pt-3">
                            <Row className="g-3">
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Agence</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.agence_id}
                                        onChange={(e) => handleFilterChange('agence_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {Object.entries(agences || {}).map(([id, label]) => (
                                            <option key={id} value={id}>{String(label)}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Entreprise</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.entreprise_id}
                                        onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {Object.entries(entreprises || {}).map(([id, label]) => (
                                            <option key={id} value={id}>{String(label)}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Financement</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.typesfinancement_id}
                                        onChange={(e) => handleFilterChange('typesfinancement_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {Object.entries(typesfinancements || {}).map(([id, label]) => (
                                            <option key={id} value={id}>{String(label)}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Type de stage</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.typestage_id}
                                        onChange={(e) => handleFilterChange('typestage_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {Object.entries(typestages || {}).map(([id, label]) => (
                                            <option key={id} value={id}>{String(label)}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Type de structure</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.type_structure_id}
                                        onChange={(e) => handleFilterChange('type_structure_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {Object.entries(typestructures || {}).map(([id, label]) => (
                                            <option key={id} value={id}>{String(label)}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Date début</Label>
                                    <Input
                                        type="date"
                                        bsSize="sm"
                                        value={selectedFilters.created_begin}
                                        onChange={(e) => handleFilterChange('created_begin', e.target.value)}
                                    />
                                </Col>
                                <Col xs={6} sm={4} md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold mb-1">Date fin</Label>
                                    <Input
                                        type="date"
                                        bsSize="sm"
                                        value={selectedFilters.created_end}
                                        onChange={(e) => handleFilterChange('created_end', e.target.value)}
                                    />
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {/* ─── Boutons d'actions groupées ─── */}
                    <div className="d-flex my-3 flex-wrap gap-2">
                        <Button color="primary" className="btn-label" onClick={openValiderModal} disabled={isProcessing}>
                            <i className="ri-check-double-line label-icon fs-16 me-2 align-middle" />
                            Sélectionner et valider
                        </Button>
                        <Button color="info" className="btn-label" onClick={handleValidationListeEntiere} disabled={isProcessing}>
                            <i className="ri-folder-line label-icon fs-16 me-2 align-middle" />
                            Validation de la liste
                        </Button>
                        <Button color="secondary" className="btn-label" onClick={handleGenererAddGlobal} disabled={isProcessing}>
                            <i className="ri-file-text-line label-icon fs-16 me-2 align-middle" />
                            Générer ADD
                        </Button>
                        <Button color="success" className="btn-label" onClick={handleGenererAddSelection} disabled={isProcessing}>
                            <i className="ri-file-list-3-line label-icon fs-16 me-2 align-middle" />
                            Sélectionner Générer ADD
                        </Button>
                        <Button color="danger" className="btn-label" onClick={openAjournerModal} disabled={isProcessing}>
                            <i className="ri-close-circle-line label-icon fs-16 me-2 align-middle" />
                            Sélectionner et ajourner
                        </Button>
                    </div>

                    {/* ─── Bandeau info ─── */}
                    <Alert color="info" className="rounded-3 d-flex mb-4 border-0">
                        <i className="ri-information-line fs-18 me-3 mt-1" />
                        <div className="w-100">
                            Chaque action [Validation Groupé &amp; Générer Attestation Présence] s'applique à l'onglet actif.
                            Pour l'onglet Présence, sélectionner la période d'attestation de démarrage.
                        </div>
                    </Alert>

                    {/* ─── Tableau principal ─── */}
                    <Card className="border-0 shadow-sm">
                        <CardHeader className="bg-success">
                            <div className="d-flex justify-content-between align-items-center">
                                <h5 className="card-title mb-0 text-white">
                                    {currentTabLabel}
                                    <span
                                        className="badge fs-12 ms-2"
                                        style={{ background: '#e8f0fe', color: '#405189' }}
                                    >
                                        {currentRows.length}
                                    </span>
                                </h5>
                                {selectedRows.length > 0 && (
                                    <Badge color="light" className="text-dark fs-12">
                                        {selectedRows.length} sélectionné(s)
                                    </Badge>
                                )}
                            </div>
                        </CardHeader>
                        <CardBody className="p-0">
                            <Nav tabs className="nav-tabs-custom nav-success border-bottom-0 ms-3 mt-3 mb-0">
                                <NavItem>
                                    <NavLink
                                        className={classnames({ active: activeTab === '1' })}
                                        onClick={() => toggleTab('1')}
                                        style={{ cursor: 'pointer' }}
                                    >
                                        DEMARRAGE
                                        <Badge color="primary" pill className="ms-2">{data?.counts?.demarrage ?? 0}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink
                                        className={classnames({ active: activeTab === '2' })}
                                        onClick={() => toggleTab('2')}
                                        style={{ cursor: 'pointer' }}
                                    >
                                        DEMARRAGE OMIS
                                        <Badge color="warning" pill className="ms-2">{data?.counts?.demarrageOmis ?? 0}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink
                                        className={classnames({ active: activeTab === '3' })}
                                        onClick={() => toggleTab('3')}
                                        style={{ cursor: 'pointer' }}
                                    >
                                        RETOUR D'AJOURNEMENT
                                        <Badge color="danger" pill className="ms-2">{data?.counts?.retourAjournement ?? 0}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab}>
                                {isLoading ? (
                                    <div className="d-flex justify-content-center align-items-center py-5">
                                        <Spinner color="success" />
                                    </div>
                                ) : (
                                    <>
                                        {/* ─── Onglet 1 : Démarrage ─── */}
                                        <TabPane tabId="1">
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.demarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>

                                        {/* ─── Onglet 2 : Démarrage Omis ─── */}
                                        <TabPane tabId="2">
                                            {/* Filtre Période uniquement visible dans cet onglet */}
                                            <div className="bg-light border-bottom p-3">
                                                <Row>
                                                    <Col md={4}>
                                                        <Label className="form-label text-danger mb-2 fw-medium">
                                                            PÉRIODE *
                                                        </Label>
                                                        <Input
                                                            type="select"
                                                            bsSize="sm"
                                                            value={selectedFilters.periode_id}
                                                            onChange={(e) => handleFilterChange('periode_id', e.target.value)}
                                                        >
                                                            <option value="">Sélectionner une période</option>
                                                            {Object.entries(periodes || {}).map(([id, label]) => (
                                                                <option key={id} value={id}>{String(label)}</option>
                                                            ))}
                                                        </Input>
                                                    </Col>
                                                </Row>
                                            </div>
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.demarrageOmis || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>

                                        {/* ─── Onglet 3 : Retour d'Ajournement ─── */}
                                        <TabPane tabId="3">
                                            <TableContainerReactTable
                                                columns={columns}
                                                data={data.retourAjournement || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
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

            {/* ═══════════════════════════════════════════
                MODALE — Ajourner la sélection
               ═══════════════════════════════════════════ */}
            <Modal isOpen={modalAjournerOpen} toggle={() => !isProcessing && setModalAjournerOpen(false)} centered>
                <ModalHeader
                    toggle={() => !isProcessing && setModalAjournerOpen(false)}
                    className="bg-danger text-white"
                >
                    Ajourner la sélection
                </ModalHeader>
                <ModalBody>
                    <p>
                        Vous êtes sur le point d'ajourner <strong>{selectedRows.length}</strong> dossier(s).
                        Les dossiers retourneront dans la corbeille CIP pour correction.
                    </p>
                    <div className="mb-3">
                        <Label htmlFor="motif" className="form-label">
                            Motif d'ajournement <span className="text-danger">*</span>
                        </Label>
                        <Input
                            type="textarea"
                            id="motif"
                            rows={3}
                            placeholder="Ex : Contrat illisible, dates incorrectes, pièce manquante..."
                            value={motifAjournement}
                            onChange={(e) => setMotifAjournement(e.target.value)}
                            disabled={isProcessing}
                        />
                        {motifAjournement.trim().length > 0 && motifAjournement.trim().length < 5 && (
                            <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                        )}
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerOpen(false)} disabled={isProcessing}>
                        Annuler
                    </Button>
                    <Button
                        color="danger"
                        onClick={confirmAjourner}
                        disabled={isProcessing || motifAjournement.trim().length < 5}
                    >
                        {isProcessing ? (
                            <><Spinner size="sm" className="me-2" />Traitement...</>
                        ) : (
                            "Confirmer l'ajournement"
                        )}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Valider la sélection
               ═══════════════════════════════════════════ */}
            <Modal isOpen={modalValiderOpen} toggle={() => !isProcessing && setModalValiderOpen(false)} centered>
                <ModalHeader
                    toggle={() => !isProcessing && setModalValiderOpen(false)}
                    className="bg-primary text-white"
                >
                    Validation de stagiaires
                </ModalHeader>
                <ModalBody>
                    <p>
                        Vous êtes sur le point de valider <strong>{selectedRows.length}</strong> dossier(s).
                    </p>
                    <div className="alert alert-secondary border-0">
                        Onglet actif : <strong>{currentTabLabel}</strong>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalValiderOpen(false)} disabled={isProcessing}>
                        Annuler
                    </Button>
                    <Button color="primary" onClick={confirmValider} disabled={isProcessing}>
                        {isProcessing ? (
                            <><Spinner size="sm" className="me-2" />Traitement...</>
                        ) : (
                            'Confirmer la validation'
                        )}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Détail d'un dossier
               ═══════════════════════════════════════════ */}
            <Modal isOpen={Boolean(previewRow)} toggle={() => setPreviewRow(null)} size="lg" centered>
                <ModalHeader toggle={() => setPreviewRow(null)} className="bg-light">
                    Détail du dossier
                </ModalHeader>
                <ModalBody>
                    {previewRow && (
                        <Row>
                            <Col md={6}>
                                <table className="table table-sm align-middle">
                                    <tbody>
                                        <tr>
                                            <th className="text-muted fw-medium">Date</th>
                                            <td>{previewRow.date}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Agence</th>
                                            <td>{previewRow.agence}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Entreprise</th>
                                            <td>{previewRow.entreprise}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Type de stage</th>
                                            <td>{previewRow.type_stage}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Financement</th>
                                            <td>{previewRow.source_financement}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Type structure</th>
                                            <td>
                                                {previewRow.type_structure !== '-' ? (
                                                    <Badge color="success">{previewRow.type_structure}</Badge>
                                                ) : '-'}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </Col>
                            <Col md={6}>
                                <table className="table table-sm align-middle">
                                    <tbody>
                                        <tr>
                                            <th className="text-muted fw-medium">N° AEJ</th>
                                            <td>{previewRow.numero_aej}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Nom et prénoms</th>
                                            <td className="fw-medium">{previewRow.nom_prenoms}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Date naissance</th>
                                            <td>{previewRow.date_naissance}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Sexe</th>
                                            <td>{previewRow.sexe}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Contrat</th>
                                            <td>
                                                <Badge color={previewRow.contrat_label === 'Avec Contrat' ? 'success' : 'warning'}>
                                                    {previewRow.contrat_label}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Incidence financière</th>
                                            <td>
                                                <Badge color={previewRow.incidence_financiere === 'Oui' ? 'success' : 'danger'}>
                                                    {previewRow.incidence_financiere}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Date début</th>
                                            <td>{previewRow.date_debut}</td>
                                        </tr>
                                        <tr>
                                            <th className="text-muted fw-medium">Date de fin</th>
                                            <td>{previewRow.date_fin}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setPreviewRow(null)}>
                        Fermer
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default ValidationDemarrageIndex;
