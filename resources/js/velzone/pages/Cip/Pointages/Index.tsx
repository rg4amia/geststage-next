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
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

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
    counts: { attente: number; attente_pejedec: number; effectue: number; ajourne_ca: number; ajourne_dmg: number };
    situationsStage: { id: number; code: string; nom: string }[];
}

/* ─── Constantes onglets ─── */
const ONGLETS = [
    { id: 'attente', label: 'ATTENTE POINTAGE', compteur: 'attente' as const, color: 'warning', icon: 'ri-time-line' },
    { id: 'attente_pejedec', label: 'ATTENTE PEJEDEC', compteur: 'attente_pejedec' as const, color: 'info', icon: 'ri-file-text-line' },
    { id: 'effectue', label: 'POINTAGE EFFECTUÉ', compteur: 'effectue' as const, color: 'success', icon: 'ri-check-double-line' },
    { id: 'ajourne_ca', label: 'AJOURNÉ / CHEF AGENCE', compteur: 'ajourne_ca' as const, color: 'danger', icon: 'ri-arrow-go-back-line' },
    { id: 'ajourne_dmg', label: 'AJOURNÉ / DMG', compteur: 'ajourne_dmg' as const, color: 'secondary', icon: 'ri-arrow-go-back-fill' },
];

/* ─── Helpers ─── */
const formatDateFr = (dateStr: string | null | undefined) => {
    if (!dateStr) {
        return '-';
    }

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

const MESSAGES_ONGLET: Record<string, { color: string; icon: string; message: string }> = {
    attente: { color: 'warning', icon: 'ri-time-line', message: 'Stagiaires en attente de pointage de présence pour la période sélectionnée.' },
    attente_pejedec: { color: 'info', icon: 'ri-file-text-line', message: 'Stagiaires PEJEDEC en attente de pointage de présence.' },
    effectue: { color: 'success', icon: 'ri-check-double-line', message: 'Pointages de présence déjà soumis pour la période sélectionnée.' },
    ajourne_ca: { color: 'danger', icon: 'ri-arrow-go-back-line', message: 'Pointages retournés par le Chef d\'Agence pour correction.' },
    ajourne_dmg: { color: 'secondary', icon: 'ri-arrow-go-back-fill', message: 'Pointages ajournés par la DMG pour correction.' },
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
        counts = { attente: 0, attente_pejedec: 0, effectue: 0, ajourne_ca: 0, ajourne_dmg: 0 },
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
    const naviguer = useCallback(
        (activeTab: string, currentFilters: typeof selectedFilters) => {
            const params: Record<string, string> = { tab: activeTab };
            Object.entries(currentFilters).forEach(([key, val]) => {
                if (val) {
                    params[key] = val;
                }
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
            naviguer(t, selectedFilters);
        }
    };

    const handleFilterChange = (field: string, value: string) => {
        setSelectedFilters((prev) => ({ ...prev, [field]: value }));
    };

    const appliquerFiltres = () => {
        naviguer(currentTab, selectedFilters);
    };

    const reinitialiser = () => {
        const defaultFilters = {
            mois: '',
            agence_id: '',
            entreprise_id: '',
            source_financement_id: '',
            type_stage_id: '',
            search: '',
        };
        setSelectedFilters(defaultFilters);
        naviguer(currentTab, defaultFilters);
    };

    /* ─── Pointage Batch ─── */
    const handleBatchSubmit = () => {
        if (!data?.data || data.data.length === 0) {
            return;
        }

        setBatchModalOpen(true);
    };

    const confirmBatchSubmit = () => {
        if (isProcessing) {
            return;
        }

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
        if (isProcessing) {
            return;
        }

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

                if (justificatifRef.current) {
                    justificatifRef.current.value = '';
                }
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
        if (!annulerTarget || isProcessing) {
            return;
        }

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
        if (!corrigerTarget || isProcessing) {
            return;
        }

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

    /* ─── Données helper ─── */
    const getStageData = useCallback(
        (row: any) => ((currentTab === 'attente' || currentTab === 'attente_pejedec') ? row : row.stage || row),
        [currentTab],
    );

    /* ─── Données du tableau avec détection de la ligne rouge (démarrage manquant) ─── */
    const tableData = useMemo(() => {
        const items = data?.data || [];

        if (currentTab !== 'attente') {
            return items;
        }

        return items.map((item: any) => ({
            ...item,
            _rowClassName: item.has_pointage_demarrage === false && item.is_demarrage === false
                ? 'table-danger'
                : '',
        }));
    }, [data, currentTab]);

    const estOngletPaiement = currentTab === 'ajourne_dmg';

    return (
        <React.Fragment>
            <Head title="Espace de pointage de présence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Espace de pointage de présence" pageTitle="Stagiaires" />

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

                    <Card>
                        {/* ─── Onglets dans le CardHeader (style Visas) ─── */}
                        <CardHeader className="border-0 pb-0">
                            <Nav tabs className="nav-tabs-custom nav-success flex-wrap">
                                {ONGLETS.map((item) => (
                                    <NavItem key={item.id}>
                                        <NavLink
                                            href="#"
                                            className={classnames({ active: currentTab === item.id }, 'cursor-pointer')}
                                            onClick={(e) => {
                                                e.preventDefault();
                                                toggleTab(item.id);
                                            }}
                                        >
                                            <i className={`${item.icon} me-1`}></i>
                                            {item.label}
                                            <Badge color="light" className="text-body ms-1">
                                                {counts[item.compteur] ?? 0}
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>
                        </CardHeader>

                        {/* ─── Filtres (CardBody dédié, style Visas) ─── */}
                        <CardBody className="border-bottom">
                            <Row className="g-2">
                                <Col md={3}>
                                    <Label className="form-label">Agence</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.agence_id}
                                        onChange={(e) => handleFilterChange('agence_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {agences.map((a) => (
                                            <option key={a.id} value={a.id}>{a.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Entreprise</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.entreprise_id}
                                        onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {entreprises.map((e) => (
                                            <option key={e.id} value={e.id}>{e.raison_sociale}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Financement</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.source_financement_id}
                                        onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {sourcesFinancement.map((sf) => (
                                            <option key={sf.id} value={sf.id}>{sf.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Type de stage</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.type_stage_id}
                                        onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {typesStage.map((ts) => (
                                            <option key={ts.id} value={ts.id}>{ts.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Période</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.mois}
                                        onChange={(e) => handleFilterChange('mois', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {periodes.map((p) => (
                                            <option key={p.id} value={p.code}>{p.code}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Recherche</Label>
                                    <Input
                                        type="text"
                                        placeholder="Nom, prénom, N° AEJ…"
                                        value={selectedFilters.search}
                                        onChange={(e) => handleFilterChange('search', e.target.value)}
                                    />
                                </Col>
                                <Col md={6} className="d-flex align-items-end gap-2">
                                    <Button color="primary" onClick={appliquerFiltres}>
                                        <i className="ri-search-line align-bottom me-1" /> Filtrer
                                    </Button>
                                    <Button color="light" onClick={reinitialiser}>Réinitialiser</Button>
                                </Col>
                            </Row>
                        </CardBody>

                        {/* ─── Tableau (CardBody dédié, style Visas) ─── */}
                        <CardBody>
                            {currentTab === 'attente' && (
                                <Alert color="warning" className="border-0 mb-3">
                                    <div className="d-flex align-items-center">
                                        <div className="d-inline-block me-2" style={{ width: 16, height: 16, backgroundColor: '#f84343', border: '1px solid #999' }}></div>
                                        <div>
                                            <strong>Stagiaires en attente :</strong> Les lignes en rouge indiquent des stagiaires dont le pointage de démarrage n'a pas encore été validé.
                                            Ils ne seront pas pris en compte lors du pointage groupé.
                                        </div>
                                    </div>
                                </Alert>
                            )}

                            {(currentTab === 'attente' || currentTab === 'attente_pejedec') && (
                                <div className="d-flex justify-content-end mb-3">
                                    <Button
                                        color="primary"
                                        className="btn-label waves-effect waves-light"
                                        onClick={handleBatchSubmit}
                                        disabled={!data?.data || data.data.length === 0 || !periode}
                                    >
                                        <i className="ri-check-double-line label-icon align-middle fs-16 me-2"></i>
                                        Effectuer pointage {periode ? `(${periode.code})` : ''}
                                    </Button>
                                </div>
                            )}

                            <div className="table-responsive">
                                <Table className="table-sm align-middle table-nowrap mb-0">
                                    <thead className={currentTab === 'attente' ? 'table-success' : 'table-light'}>
                                        {currentTab === 'ajourne_dmg' ? (
                                            <tr>
                                                <th>#</th>
                                                <th>Date ajournement</th>
                                                <th>Observation DMG</th>
                                                <th>Agence</th>
                                                <th>Entreprise</th>
                                                <th>N° AEJ</th>
                                                <th>Nom et prénoms</th>
                                                <th>Statut</th>
                                                <th className="text-end">Actions</th>
                                            </tr>
                                        ) : currentTab === 'ajourne_ca' ? (
                                            <tr>
                                                <th>#</th>
                                                <th>Agent Saisie</th>
                                                <th>Mois</th>
                                                <th>Agence</th>
                                                <th>Entreprise</th>
                                                <th>N° AEJ</th>
                                                <th>Nom et prénoms</th>
                                                <th>Motif ajournement</th>
                                                <th>Statut</th>
                                            </tr>
                                        ) : currentTab === 'effectue' ? (
                                            <tr>
                                                <th>#</th>
                                                <th>Agent Saisie</th>
                                                <th>Mois</th>
                                                <th>Agence</th>
                                                <th>Entreprise</th>
                                                <th>Financement</th>
                                                <th>Type de stage</th>
                                                <th>N° AEJ</th>
                                                <th>Nom et prénoms</th>
                                                <th>Statut</th>
                                                <th className="text-end">Actions</th>
                                            </tr>
                                        ) : (
                                            <tr>
                                                <th>#</th>
                                                <th>Agent Saisie</th>
                                                <th>Agence</th>
                                                <th>Entreprise</th>
                                                <th>Financement</th>
                                                <th>Type Stage</th>
                                                <th>Date début</th>
                                                <th>Date fin</th>
                                                <th>N° AEJ</th>
                                                <th>Nom et prénoms</th>
                                                <th>Date naissance</th>
                                                <th>Sexe</th>
                                                <th className="text-end">Actions</th>
                                            </tr>
                                        )}
                                    </thead>
                                    <tbody>
                                        {tableData.length === 0 ? (
                                            <tr>
                                                <td colSpan={currentTab === 'ajourne_dmg' ? 9 : currentTab === 'ajourne_ca' ? 9 : currentTab === 'effectue' ? 11 : 13} className="text-center">
                                                    Aucun dossier dans cette corbeille.
                                                </td>
                                            </tr>
                                        ) : (
                                            tableData.map((row: any, index: number) => (
                                                <tr key={row.id} className={row._rowClassName || ''}>
                                                    {/* ─── Lignes ATTENTE / ATTENTE_PEJEDEC ─── */}
                                                    {(currentTab === 'attente' || currentTab === 'attente_pejedec') && (
                                                        <>
                                                            <td>{index + 1}</td>
                                                            <td><span className="text-muted">CIP</span></td>
                                                            <td>{getStageData(row)?.agence?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.entreprise?.raison_sociale || '-'}</td>
                                                            <td>{getStageData(row)?.sourceFinancement?.nom || getStageData(row)?.source_financement?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.typeStage?.nom || getStageData(row)?.type_stage?.nom || 'Stage de qualification'}</td>
                                                            <td className="text-nowrap">{formatDateFr(getStageData(row)?.date_debut)}</td>
                                                            <td className="text-nowrap">{formatDateFr(getStageData(row)?.date_fin_prevue)}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.numero_aej || '-'}</td>
                                                            <td>
                                                                <span className="fw-medium">
                                                                    {getStageData(row)?.beneficiaire?.nom} {getStageData(row)?.beneficiaire?.prenoms}
                                                                </span>
                                                            </td>
                                                            <td>{formatDateFr(getStageData(row)?.beneficiaire?.date_naissance)}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.sexe || '-'}</td>
                                                            <td className="text-end">
                                                                <Button color="success" size="sm" outline onClick={() => openAbandonModal(row)} title="Pointage individuel / Status">
                                                                    <i className="ri-check-line me-1"></i>Status
                                                                </Button>
                                                            </td>
                                                        </>
                                                    )}

                                                    {/* ─── Lignes EFFECTUE ─── */}
                                                    {currentTab === 'effectue' && (
                                                        <>
                                                            <td>{index + 1}</td>
                                                            <td>{(row.versionCourante || row.version_courante)?.saisi_par?.name || (row.versionCourante || row.version_courante)?.saisiPar?.name || '-'}</td>
                                                            <td><Badge color="info" className="text-white">{row.periode?.code || '-'}</Badge></td>
                                                            <td>{getStageData(row)?.agence?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.entreprise?.raison_sociale || '-'}</td>
                                                            <td>{getStageData(row)?.sourceFinancement?.nom || getStageData(row)?.source_financement?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.typeStage?.nom || getStageData(row)?.type_stage?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.numero_aej || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.nom} {getStageData(row)?.beneficiaire?.prenoms}</td>
                                                            <td>{statusBadge(row.statut)}</td>
                                                            <td className="text-end">
                                                                {row.statut === 'SOUMIS' && (
                                                                    <Button color="warning" size="sm" outline onClick={() => openAnnulerModal(row)} title="Annuler le pointage">
                                                                        <i className="ri-close-circle-line me-1"></i>Annuler
                                                                    </Button>
                                                                )}
                                                            </td>
                                                        </>
                                                    )}

                                                    {/* ─── Lignes AJOURNE_CA ─── */}
                                                    {currentTab === 'ajourne_ca' && (
                                                        <>
                                                            <td>{index + 1}</td>
                                                            <td>{(row.versionCourante || row.version_courante)?.saisi_par?.name || (row.versionCourante || row.version_courante)?.saisiPar?.name || 'CIP'}</td>
                                                            <td><Badge color="warning" className="text-dark">{row.periode?.code || '-'}</Badge></td>
                                                            <td>{getStageData(row)?.agence?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.entreprise?.raison_sociale || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.numero_aej || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.nom} {getStageData(row)?.beneficiaire?.prenoms}</td>
                                                            <td>
                                                                <span className="text-warning">
                                                                    <i className="ri-error-warning-line me-1"></i>
                                                                    {(row.decisions || [])[(row.decisions || []).length - 1]?.motif || "Ajourné par le Chef d'Agence"}
                                                                </span>
                                                            </td>
                                                            <td>{statusBadge(row.statut)}</td>
                                                        </>
                                                    )}

                                                    {/* ─── Lignes AJOURNE_DMG ─── */}
                                                    {currentTab === 'ajourne_dmg' && (
                                                        <>
                                                            <td>{index + 1}</td>
                                                            <td className="text-nowrap">
                                                                {row.date_ajournement
                                                                    ? formatDateFr(row.date_ajournement)
                                                                    : formatDateFr((row.decisions || [])[(row.decisions || []).length - 1]?.decide_le || (row.decisions || [])[(row.decisions || []).length - 1]?.created_at)}
                                                            </td>
                                                            <td>
                                                                <span className="text-danger">
                                                                    <i className="ri-error-warning-line me-1"></i>
                                                                    {row.observation_dmg || (row.decisions || [])[(row.decisions || []).length - 1]?.motif || 'Ajourné par la DMG'}
                                                                </span>
                                                            </td>
                                                            <td>{getStageData(row)?.agence?.nom || '-'}</td>
                                                            <td>{getStageData(row)?.entreprise?.raison_sociale || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.numero_aej || '-'}</td>
                                                            <td>{getStageData(row)?.beneficiaire?.nom} {getStageData(row)?.beneficiaire?.prenoms}</td>
                                                            <td>{statusBadge(row.statut)}</td>
                                                            <td className="text-end">
                                                                <div className="d-flex gap-1 justify-content-end">
                                                                    <Button color="dark" size="sm" href={`/cip/pointages/edit-stagiaire/${row.stage_id || getStageData(row).id}?return_tab=ajourne_dmg&mois=${encodeURIComponent(selectedFilters.mois || '')}`}>
                                                                        <i className="ri-user-settings-line me-1"></i>Traiter
                                                                    </Button>
                                                                    {!row.stage_id && (
                                                                        <Button color="primary" size="sm" onClick={() => openCorrigerDmgModal(row)}>
                                                                            <i className="ri-edit-line me-1"></i>Corriger
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        </>
                                                    )}
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </Table>
                            </div>

                            {data && (
                                <ServerPagination
                                    pagination={normalizePagination(data)}
                                    itemLabel="dossiers"
                                    onPageChange={(page) => {
                                        const params: Record<string, string> = { tab: currentTab, page: String(page) };
                                        Object.entries(selectedFilters).forEach(([k, v]) => {
                                            if (v) {
                                                params[k] = v;
                                            }
                                        });
                                        router.get('/cip/pointages', params, { preserveState: true, preserveScroll: true });
                                    }}
                                />
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
