import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useCallback, useMemo, useRef, useState } from 'react';
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
interface PageProps {
    tab: string;
    data: any;
    filters: Record<string, string>;
    agences: { id: number; nom: string }[];
    entreprises: { id: number; raison_sociale: string }[];
    sourcesFinancement: { id: number; nom: string }[];
    typesStage: { id: number; nom: string }[];
    periodes: { id: number; code: string }[];
    periode: { id: number; code: string } | null;
    counts: { attente: number; effectue: number; ajourne_ca: number; ajourne_dmg: number };
    situationsStage: { id: number; code: string; nom: string }[];
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

const statusBadge = (statut: string) => {
    const map: Record<string, { color: string; label: string }> = {
        SOUMIS: { color: 'info', label: 'Soumis' },
        VALIDE: { color: 'success', label: 'Validé' },
        AJOURNE_CA: { color: 'warning', label: 'Ajourné CA' },
        AJOURNE_DMG: { color: 'danger', label: 'Ajourné DMG' },
        CORRIGE_CIP: { color: 'primary', label: 'Corrigé CIP' },
        BROUILLON: { color: 'secondary', label: 'Brouillon' },
    };
    const s = map[statut] || { color: 'secondary', label: statut || '-' };
    return <span className={`badge bg-${s.color}-subtle text-${s.color}`}>{s.label}</span>;
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const PointagesIndex = (props: PageProps) => {
    const {
        tab,
        data,
        filters,
        agences = [],
        entreprises = [],
        sourcesFinancement = [],
        typesStage = [],
        periodes = [],
        periode,
        counts = { attente: 0, effectue: 0, ajourne_ca: 0, ajourne_dmg: 0 },
        situationsStage = [],
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const [currentTab, setCurrentTab] = useState(tab || 'attente');
    const [selectedFilters, setSelectedFilters] = useState({
        mois: filters?.mois || '',
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        search: filters?.search || '',
    });

    /* ─── Modales ─── */
    const [batchModalOpen, setBatchModalOpen] = useState(false);
    const [abandonModalOpen, setAbandonModalOpen] = useState(false);
    const [annulerModalOpen, setAnnulerModalOpen] = useState(false);
    const [corrigerDmgModalOpen, setCorrigerDmgModalOpen] = useState(false);

    /* ─── État du formulaire batch ─── */
    const [batchObservation, setBatchObservation] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    /* ─── État du formulaire abandon individuel ─── */
    const [abandonData, setAbandonData] = useState({
        stage_id: 0,
        stage_name: '',
        situation_stage_code: '',
        observation: '',
    });
    const justificatifRef = useRef<HTMLInputElement>(null);

    /* ─── État annulation ─── */
    const [annulerTarget, setAnnulerTarget] = useState<{ id: number; nom: string } | null>(null);

    /* ─── État correction DMG ─── */
    const [corrigerTarget, setCorrigerTarget] = useState<any>(null);
    const [corrigerMotif, setCorrigerMotif] = useState('');
    const [corrigerJours, setCorrigerJours] = useState(30);

    /* ─── Navigation / Filtres ─── */
    const applyFilters = useCallback(
        (activeTab: string, currentFilters: typeof selectedFilters) => {
            const params: Record<string, string> = { tab: activeTab };
            Object.entries(currentFilters).forEach(([key, val]) => {
                if (val) params[key] = val;
            });
            router.get('/cip/pointages', params, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [],
    );

    const toggleTab = (t: string) => {
        if (currentTab !== t) {
            setCurrentTab(t);
            applyFilters(t, selectedFilters);
        }
    };

    const handleFilterChange = (field: string, value: string) => {
        const newFilters = { ...selectedFilters, [field]: value };
        setSelectedFilters(newFilters);
        applyFilters(currentTab, newFilters);
    };

    const resetFilters = () => {
        const defaultFilters = {
            mois: '',
            agence_id: '',
            entreprise_id: '',
            source_financement_id: '',
            type_stage_id: '',
            search: '',
        };
        setSelectedFilters(defaultFilters);
        applyFilters(currentTab, defaultFilters);
    };

    /* ─── Pointage Batch ─── */
    const handleBatchSubmit = () => {
        if (!data?.data || data.data.length === 0) return;
        setBatchModalOpen(true);
    };

    const confirmBatchSubmit = () => {
        if (isProcessing) return;
        setIsProcessing(true);

        const stageIds = (data?.data || []).map((item: any) => item.id);
        router.post(
            '/cip/pointages/soumettre-batch',
            {
                periode_id: periode?.id,
                stage_ids: stageIds,
                observation: batchObservation || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setBatchModalOpen(false);
                    setBatchObservation('');
                },
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    /* ─── Pointage Individuel (Abandon/Status) ─── */
    const openAbandonModal = (stage: any) => {
        setAbandonData({
            stage_id: stage.id,
            stage_name: `${stage.beneficiaire?.nom || ''} ${stage.beneficiaire?.prenoms || ''}`,
            situation_stage_code: '',
            observation: '',
        });
        setAbandonModalOpen(true);
    };

    const confirmAbandon = () => {
        if (isProcessing) return;
        setIsProcessing(true);

        const formData = new FormData();
        formData.append('stage_id', String(abandonData.stage_id));
        formData.append('periode_id', String(periode?.id || ''));
        if (abandonData.situation_stage_code) {
            formData.append('situation_stage_code', abandonData.situation_stage_code);
        }
        if (abandonData.observation) {
            formData.append('observation', abandonData.observation);
        }
        if (justificatifRef.current?.files?.[0]) {
            formData.append('justificatif_file', justificatifRef.current.files[0]);
        }

        router.post('/cip/pointages/soumettre-individuel', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setAbandonModalOpen(false);
                setAbandonData({ stage_id: 0, stage_name: '', situation_stage_code: '', observation: '' });
                if (justificatifRef.current) justificatifRef.current.value = '';
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Annuler Pointage ─── */
    const openAnnulerModal = (pointage: any) => {
        const stage = pointage.stage || pointage;
        setAnnulerTarget({
            id: pointage.id,
            nom: `${stage.beneficiaire?.nom || ''} ${stage.beneficiaire?.prenoms || ''}`,
        });
        setAnnulerModalOpen(true);
    };

    const confirmAnnuler = () => {
        if (!annulerTarget || isProcessing) return;
        setIsProcessing(true);
        router.delete(`/cip/pointages/${annulerTarget.id}/annuler`, {
            preserveScroll: true,
            onSuccess: () => {
                setAnnulerModalOpen(false);
                setAnnulerTarget(null);
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Corriger DMG ─── */
    const openCorrigerDmgModal = (pointage: any) => {
        setCorrigerTarget(pointage);
        setCorrigerMotif('');
        setCorrigerJours(pointage.versionCourante?.jours_presents ?? 30);
        setCorrigerDmgModalOpen(true);
    };

    const confirmCorrigerDmg = () => {
        if (!corrigerTarget || isProcessing) return;
        setIsProcessing(true);
        router.post(`/cip/pointages/corriger-ajournement-dmg/${corrigerTarget.id}`, {
            motif: corrigerMotif || undefined,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setCorrigerDmgModalOpen(false);
                setCorrigerTarget(null);
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Colonnes du tableau ─── */
    const getStageData = useCallback(
        (row: any) => (currentTab === 'attente' ? row : row.stage || row),
        [currentTab],
    );

    const columnsAttente = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Agent Saisie',
                cell: () => <span className="text-muted">CIP</span>,
            },
            {
                header: 'Agence',
                cell: (cell: any) => getStageData(cell.row.original)?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => getStageData(cell.row.original)?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'Financement',
                cell: (cell: any) => getStageData(cell.row.original)?.sourceFinancement?.nom || getStageData(cell.row.original)?.source_financement?.nom || '-',
            },
            {
                header: 'Type Stage',
                cell: (cell: any) => getStageData(cell.row.original)?.typeStage?.nom || getStageData(cell.row.original)?.type_stage?.nom || 'Stage de qualification',
            },
            {
                header: 'Date début',
                cell: (cell: any) => formatDateFr(getStageData(cell.row.original)?.date_debut),
            },
            {
                header: 'Date fin',
                cell: (cell: any) => formatDateFr(getStageData(cell.row.original)?.date_fin),
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => getStageData(cell.row.original)?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const stage = getStageData(cell.row.original);
                    const nom = stage?.beneficiaire?.nom || '';
                    const prenoms = stage?.beneficiaire?.prenoms || '';
                    return <span className="fw-medium">{nom} {prenoms}</span>;
                },
            },
            {
                header: 'Date naissance',
                cell: (cell: any) => formatDateFr(getStageData(cell.row.original)?.beneficiaire?.date_naissance),
            },
            {
                header: 'Sexe',
                cell: (cell: any) => getStageData(cell.row.original)?.beneficiaire?.sexe || '-',
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    return (
                        <div className="d-flex gap-1">
                            <Button color="success" size="sm" outline onClick={() => openAbandonModal(row)} title="Pointage individuel / Status">
                                <i className="ri-check-line me-1"></i>Status
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [getStageData],
    );

    const columnsEffectue = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Agent Saisie',
                cell: (cell: any) => {
                    const ver = cell.row.original.versionCourante || cell.row.original.version_courante;
                    return ver?.saisi_par?.name || ver?.saisiPar?.name || 'CIP';
                },
            },
            {
                header: 'Mois',
                cell: (cell: any) => (
                    <span className="badge bg-info text-white">{cell.row.original.periode?.code || '-'}</span>
                ),
            },
            {
                header: 'Agence',
                cell: (cell: any) => getStageData(cell.row.original)?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => getStageData(cell.row.original)?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'Financement',
                cell: (cell: any) => getStageData(cell.row.original)?.sourceFinancement?.nom || '-',
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => getStageData(cell.row.original)?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const stage = getStageData(cell.row.original);
                    return `${stage?.beneficiaire?.nom || ''} ${stage?.beneficiaire?.prenoms || ''}`;
                },
            },
            {
                header: 'Statut',
                cell: (cell: any) => statusBadge(cell.row.original.statut),
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const canCancel = row.statut === 'SOUMIS';
                    return (
                        <div className="d-flex gap-1">
                            {canCancel && (
                                <Button color="warning" size="sm" outline onClick={() => openAnnulerModal(row)} title="Annuler le pointage">
                                    <i className="ri-close-circle-line me-1"></i>Annuler
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ],
        [getStageData],
    );

    const columnsAjourneCA = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Agent Saisie',
                cell: (cell: any) => {
                    const ver = cell.row.original.versionCourante || cell.row.original.version_courante;
                    return ver?.saisi_par?.name || ver?.saisiPar?.name || 'CIP';
                },
            },
            {
                header: 'Mois',
                cell: (cell: any) => (
                    <span className="badge bg-warning text-dark">{cell.row.original.periode?.code || '-'}</span>
                ),
            },
            {
                header: 'Agence',
                cell: (cell: any) => getStageData(cell.row.original)?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => getStageData(cell.row.original)?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => getStageData(cell.row.original)?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const stage = getStageData(cell.row.original);
                    return `${stage?.beneficiaire?.nom || ''} ${stage?.beneficiaire?.prenoms || ''}`;
                },
            },
            {
                header: 'Motif ajournement',
                cell: (cell: any) => {
                    const decisions = cell.row.original.decisions || [];
                    const lastDecision = decisions[decisions.length - 1];
                    return (
                        <span className="text-warning">
                            <i className="ri-error-warning-line me-1"></i>
                            {lastDecision?.motif || 'Ajourné par le Chef d\'Agence'}
                        </span>
                    );
                },
            },
            {
                header: 'Statut',
                cell: (cell: any) => statusBadge(cell.row.original.statut),
            },
        ],
        [getStageData],
    );

    const columnsAjourneDMG = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Date ajournement',
                cell: (cell: any) => {
                    const decisions = cell.row.original.decisions || [];
                    const lastDecision = decisions[decisions.length - 1];
                    return formatDateFr(lastDecision?.decide_le || lastDecision?.created_at);
                },
            },
            {
                header: 'Observation DMG',
                cell: (cell: any) => {
                    const decisions = cell.row.original.decisions || [];
                    const lastDecision = decisions[decisions.length - 1];
                    return (
                        <span className="text-danger">
                            <i className="ri-error-warning-line me-1"></i>
                            {lastDecision?.motif || 'Ajourné par la DMG'}
                        </span>
                    );
                },
            },
            {
                header: 'Agence',
                cell: (cell: any) => getStageData(cell.row.original)?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => getStageData(cell.row.original)?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => getStageData(cell.row.original)?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const stage = getStageData(cell.row.original);
                    return `${stage?.beneficiaire?.nom || ''} ${stage?.beneficiaire?.prenoms || ''}`;
                },
            },
            {
                header: 'Statut',
                cell: (cell: any) => statusBadge(cell.row.original.statut),
            },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <Button color="primary" size="sm" onClick={() => openCorrigerDmgModal(cell.row.original)}>
                        <i className="ri-edit-line me-1"></i>Corriger
                    </Button>
                ),
            },
        ],
        [getStageData],
    );

    const currentColumns = useMemo(() => {
        switch (currentTab) {
            case 'effectue': return columnsEffectue;
            case 'ajourne_ca': return columnsAjourneCA;
            case 'ajourne_dmg': return columnsAjourneDMG;
            default: return columnsAttente;
        }
    }, [currentTab, columnsAttente, columnsEffectue, columnsAjourneCA, columnsAjourneDMG]);

    /* ─── Données du tableau avec détection de la ligne rouge (démarrage manquant) ─── */
    const tableData = useMemo(() => {
        const items = data?.data || [];
        if (currentTab !== 'attente') return items;
        // Marquer visuellement les lignes sans pointage de démarrage
        return items.map((item: any) => ({
            ...item,
            _rowClassName: item.has_pointage_demarrage === false && item.is_demarrage === false
                ? 'table-danger'
                : '',
        }));
    }, [data, currentTab]);

    /* ─── Onglets config ─── */
    const tabs = [
        { key: 'attente', label: 'ATTENTE POINTAGE', count: counts.attente, color: 'warning', icon: 'ri-time-line' },
        { key: 'effectue', label: 'POINTAGE EFFECTUÉ', count: counts.effectue, color: 'success', icon: 'ri-check-double-line' },
        { key: 'ajourne_ca', label: 'AJOURNÉ / CHEF AGENCE', count: counts.ajourne_ca, color: 'danger', icon: 'ri-arrow-go-back-line' },
        { key: 'ajourne_dmg', label: 'AJOURNÉ / DMG', count: counts.ajourne_dmg, color: 'secondary', icon: 'ri-arrow-go-back-fill' },
    ];

    return (
        <React.Fragment>
            <Head title="Espace de pointage de présence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Espace de pointage de présence" pageTitle="Stagiaires" />

                    {/* ─── Flash Messages ─── */}
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
                                    className={classnames('mb-0 shadow-sm border-0 cursor-pointer', {
                                        'ring-2': currentTab === t.key,
                                    })}
                                    onClick={() => toggleTab(t.key)}
                                    style={{
                                        cursor: 'pointer',
                                        borderLeft: currentTab === t.key ? `4px solid var(--vz-${t.color})` : '4px solid transparent',
                                        transition: 'border-left-color 0.2s ease',
                                    }}
                                >
                                    <CardBody className="py-3">
                                        <div className="d-flex align-items-center">
                                            <div className={`avatar-sm flex-shrink-0 me-3`}>
                                                <span className={`avatar-title bg-${t.color}-subtle text-${t.color} rounded-circle fs-20`}>
                                                    <i className={t.icon}></i>
                                                </span>
                                            </div>
                                            <div className="flex-grow-1">
                                                <p className="text-muted text-uppercase fw-medium fs-12 mb-1">{t.label.split('/')[0].trim()}</p>
                                                <h3 className={`mb-0 text-${t.color}`}>{t.count}</h3>
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
                                    <i className="ri-calendar-check-line me-2 text-primary"></i>
                                    Espace de pointage de présence
                                </h4>
                                <Button color="soft-secondary" size="sm" onClick={resetFilters}>
                                    <i className="ri-refresh-line me-1"></i>Réinitialiser les filtres
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody>
                            {/* ─── Filtres ─── */}
                            <Row className="g-3 mb-4">
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.agence_id}
                                        onChange={(e) => handleFilterChange('agence_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {agences.map((a) => (
                                            <option key={a.id} value={a.id}>{a.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Entreprise</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.entreprise_id}
                                        onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {entreprises.map((e) => (
                                            <option key={e.id} value={e.id}>{e.raison_sociale}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Financement</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.source_financement_id}
                                        onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {sourcesFinancement.map((sf) => (
                                            <option key={sf.id} value={sf.id}>{sf.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3} sm={6}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type de stage</Label>
                                    <Input
                                        type="select"
                                        bsSize="sm"
                                        value={selectedFilters.type_stage_id}
                                        onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}
                                    >
                                        <option value="">Tout</option>
                                        {typesStage.map((ts) => (
                                            <option key={ts.id} value={ts.id}>{ts.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                            </Row>

                            {/* ─── Onglets ─── */}
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
                                            <Badge color={t.color} pill className="ms-2">
                                                {t.count}
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={currentTab} className="pt-4">
                                {/* ─── Contenu Attente ─── */}
                                <TabPane tabId="attente">
                                    <Row className="mb-3 align-items-end">
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.mois}
                                                onChange={(e) => handleFilterChange('mois', e.target.value)}
                                            >
                                                <option value="">Sélectionner une période</option>
                                                {periodes.map((p) => (
                                                    <option key={p.id} value={p.code}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Recherche</Label>
                                            <Input
                                                type="text"
                                                placeholder="Nom, prénom, N° AEJ..."
                                                value={selectedFilters.search}
                                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                            />
                                        </Col>
                                        <Col md={6} className="text-md-end">
                                            <Button
                                                color="primary"
                                                className="btn-label waves-effect waves-light"
                                                onClick={handleBatchSubmit}
                                                disabled={!data?.data || data.data.length === 0 || !periode}
                                            >
                                                <i className="ri-check-double-line label-icon align-middle fs-16 me-2"></i>
                                                Effectuer pointage {periode ? `(${periode.code})` : ''}
                                            </Button>
                                        </Col>
                                    </Row>

                                    <Alert color="warning" className="border-0 mb-3">
                                        <div className="d-flex align-items-center">
                                            <div className="d-inline-block me-2" style={{ width: 16, height: 16, backgroundColor: '#f84343', border: '1px solid #999' }}></div>
                                            <div>
                                                <strong>Stagiaires en attente :</strong> Les lignes en rouge indiquent des stagiaires dont le pointage de démarrage n'a pas encore été validé.
                                                Ils ne seront pas pris en compte lors du pointage groupé.
                                            </div>
                                        </div>
                                    </Alert>
                                </TabPane>

                                {/* ─── Contenu Effectué ─── */}
                                <TabPane tabId="effectue">
                                    <Row className="mb-3 align-items-end">
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.mois}
                                                onChange={(e) => handleFilterChange('mois', e.target.value)}
                                            >
                                                <option value="">Sélectionner une période</option>
                                                {periodes.map((p) => (
                                                    <option key={p.id} value={p.code}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Recherche</Label>
                                            <Input
                                                type="text"
                                                placeholder="Nom, prénom, N° AEJ..."
                                                value={selectedFilters.search}
                                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                            />
                                        </Col>
                                    </Row>
                                </TabPane>

                                {/* ─── Contenu Ajourné CA ─── */}
                                <TabPane tabId="ajourne_ca">
                                    <Row className="mb-3 align-items-end">
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.mois}
                                                onChange={(e) => handleFilterChange('mois', e.target.value)}
                                            >
                                                <option value="">Sélectionner une période</option>
                                                {periodes.map((p) => (
                                                    <option key={p.id} value={p.code}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                    </Row>
                                </TabPane>

                                {/* ─── Contenu Ajourné DMG ─── */}
                                <TabPane tabId="ajourne_dmg">
                                    <Row className="mb-3 align-items-end">
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.mois}
                                                onChange={(e) => handleFilterChange('mois', e.target.value)}
                                            >
                                                <option value="">Sélectionner une période</option>
                                                {periodes.map((p) => (
                                                    <option key={p.id} value={p.code}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                    </Row>
                                </TabPane>
                            </TabContent>

                            {/* ─── Tableau (partagé entre tous les onglets) ─── */}
                            <TableContainerReactTable
                                columns={currentColumns}
                                data={tableData}
                                isGlobalFilter={false}
                                customPageSize={20}
                                divClass="table-responsive table-card mt-1 mb-1"
                                tableClass="align-middle table-nowrap table-hover"
                                theadClass={currentTab === 'attente' ? 'table-success' : 'table-light'}
                                trClass={(row: any) => row?.original?._rowClassName || ''}
                                SearchPlaceholder="Recherche..."
                            />

                            {/* ─── Pagination serveur ─── */}
                            {data?.links && data.links.length > 3 && (
                                <div className="d-flex justify-content-between align-items-center mt-3">
                                    <p className="text-muted mb-0 fs-13">
                                        Affiche {data.from || 0} à {data.to || 0} sur {data.total || 0} enregistrements
                                    </p>
                                    <ul className="pagination pagination-sm mb-0">
                                        {data.links.map((link: any, idx: number) => (
                                            <li
                                                key={idx}
                                                className={classnames('page-item', { active: link.active, disabled: !link.url })}
                                            >
                                                <button
                                                    className="page-link"
                                                    disabled={!link.url}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                    onClick={() => {
                                                        if (link.url) {
                                                            router.get(link.url, {}, { preserveState: true, preserveScroll: true });
                                                        }
                                                    }}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════════════════════════════════════════
                MODALE 1 — Pointage Batch (Groupé)
               ═══════════════════════════════════════════ */}
            <Modal isOpen={batchModalOpen} toggle={() => !isProcessing && setBatchModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setBatchModalOpen(false)}>
                    <i className="ri-check-double-line me-2 text-primary"></i>
                    Pointage des stagiaires
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        Vous allez soumettre le pointage de présence pour <strong>{data?.data?.length || 0}</strong> stagiaire(s)
                        de la période <strong>{periode?.code || '-'}</strong>.
                    </p>
                    <div className="mb-0">
                        <Label className="form-label">Commentaire / Observation</Label>
                        <Input
                            type="textarea"
                            rows={3}
                            value={batchObservation}
                            onChange={(e) => setBatchObservation(e.target.value)}
                            placeholder="Observation facultative pour ce pointage groupé..."
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setBatchModalOpen(false)} disabled={isProcessing}>
                        Fermer
                    </Button>
                    <Button color="primary" onClick={confirmBatchSubmit} disabled={isProcessing}>
                        {isProcessing ? (
                            <>
                                <Spinner size="sm" className="me-2" />
                                Traitement en cours...
                            </>
                        ) : (
                            <>
                                <i className="ri-check-line me-1"></i>Valider
                            </>
                        )}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE 2 — Pointage Individuel (Abandon / Status)
               ═══════════════════════════════════════════ */}
            <Modal isOpen={abandonModalOpen} toggle={() => !isProcessing && setAbandonModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setAbandonModalOpen(false)}>
                    <i className="ri-user-settings-line me-2 text-success"></i>
                    Pointage du stagiaire
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">
                        Stagiaire : <strong>{abandonData.stage_name}</strong>
                    </p>

                    <div className="mb-3">
                        <Label className="form-label">Situation du stage <span className="text-danger">*</span></Label>
                        <Input
                            type="select"
                            value={abandonData.situation_stage_code}
                            onChange={(e) => setAbandonData({ ...abandonData, situation_stage_code: e.target.value })}
                            required
                        >
                            <option value="">Sélectionner la situation</option>
                            {situationsStage.map((s) => (
                                <option key={s.id} value={s.code}>{s.nom}</option>
                            ))}
                        </Input>
                    </div>

                    <div className="mb-3">
                        <Label className="form-label">Commentaire</Label>
                        <Input
                            type="textarea"
                            rows={3}
                            value={abandonData.observation}
                            onChange={(e) => setAbandonData({ ...abandonData, observation: e.target.value })}
                            placeholder="Observation ou commentaire..."
                        />
                    </div>

                    <div className="mb-0">
                        <Label className="form-label">Fichier justificatif</Label>
                        <Input
                            type="file"
                            innerRef={justificatifRef}
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        />
                        <small className="text-muted">PDF, Image ou Document (max 5 Mo)</small>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setAbandonModalOpen(false)} disabled={isProcessing}>
                        Fermer
                    </Button>
                    <Button color="success" onClick={confirmAbandon} disabled={isProcessing}>
                        {isProcessing ? (
                            <>
                                <Spinner size="sm" className="me-2" />
                                Enregistrement...
                            </>
                        ) : (
                            <>
                                <i className="ri-save-line me-1"></i>Enregistrer
                            </>
                        )}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE 3 — Annuler un Pointage
               ═══════════════════════════════════════════ */}
            <Modal isOpen={annulerModalOpen} toggle={() => !isProcessing && setAnnulerModalOpen(false)} centered size="sm">
                <ModalHeader toggle={() => !isProcessing && setAnnulerModalOpen(false)} className="bg-danger-subtle">
                    <i className="ri-error-warning-line me-2 text-danger"></i>
                    Confirmer le retrait
                </ModalHeader>
                <ModalBody className="text-center py-4">
                    <div className="mb-3">
                        <i className="ri-delete-bin-line text-danger" style={{ fontSize: 48 }}></i>
                    </div>
                    <h5>Êtes-vous sûr de vouloir retirer ce pointage ?</h5>
                    <p className="text-muted mb-0">
                        {annulerTarget?.nom && <>Stagiaire : <strong>{annulerTarget.nom}</strong></>}
                    </p>
                </ModalBody>
                <ModalFooter className="justify-content-center">
                    <Button color="light" onClick={() => setAnnulerModalOpen(false)} disabled={isProcessing}>
                        Non
                    </Button>
                    <Button color="danger" onClick={confirmAnnuler} disabled={isProcessing}>
                        {isProcessing ? <Spinner size="sm" /> : 'Oui, retirer'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE 4 — Corriger Ajournement DMG
               ═══════════════════════════════════════════ */}
            <Modal isOpen={corrigerDmgModalOpen} toggle={() => !isProcessing && setCorrigerDmgModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setCorrigerDmgModalOpen(false)}>
                    <i className="ri-edit-line me-2 text-primary"></i>
                    Corriger le pointage (DMG)
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        Le pointage retournera au Chef d'Agence avec le statut <strong>CORRIGÉ CIP</strong> pour revalidation.
                    </p>

                    <div className="mb-3">
                        <Label className="form-label">Jours de présence corrigés</Label>
                        <Input
                            type="number"
                            min={0}
                            max={31}
                            value={corrigerJours}
                            onChange={(e) => setCorrigerJours(Number(e.target.value))}
                        />
                    </div>

                    <div className="mb-0">
                        <Label className="form-label">Motif de la correction</Label>
                        <Input
                            type="textarea"
                            rows={3}
                            value={corrigerMotif}
                            onChange={(e) => setCorrigerMotif(e.target.value)}
                            placeholder="Motif de la correction DMG..."
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setCorrigerDmgModalOpen(false)} disabled={isProcessing}>
                        Annuler
                    </Button>
                    <Button color="primary" onClick={confirmCorrigerDmg} disabled={isProcessing}>
                        {isProcessing ? (
                            <>
                                <Spinner size="sm" className="me-2" />
                                Envoi...
                            </>
                        ) : (
                            <>
                                <i className="ri-send-plane-line me-1"></i>Corriger et renvoyer au CA
                            </>
                        )}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default PointagesIndex;
