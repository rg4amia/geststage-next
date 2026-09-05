import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Select from 'react-select';
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

/* ─── Types ─── */
interface RefItem {
    id: number;
    nom: string;
    code?: string;
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
    statut_code: string;
    date_creation: string;
    date_transmission?: string;
    date_ajournement?: string;
    motif_ajournement?: string;
}

interface StagiaireRow {
    paiement_id: number;
    created_at: string;
    agence: string;
    entreprise: string;
    source_financement: string;
    type_stage: string;
    numero_aej: string;
    nom: string;
    prenoms: string;
    date_naissance: string;
    date_debut: string;
    date_fin: string;
    tresor_pay: string;
    montant: number;
    statut: string;
    stage_id?: number;
    dossier_identifiant?: string;
}

interface DocumentItem {
    id: number;
    nom: string;
    type_code: string;
    type_nom: string;
    chemin: string;
    nom_original: string;
    type_mime: string;
    taille_octets: number;
}

interface PageProps {
    dossiersControle: DossierRow[];
    etatsAjournes: DossierRow[];
    moisActuel: string;
    periode: { id: number; code: string } | null;
    periodeOptions: RefItem[];
}

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════════════════════ */
const CbPaiementsIndex = (props: PageProps) => {
    const {
        dossiersControle = [],
        etatsAjournes = [],
        moisActuel = '',
        periodeOptions = [],
    } = props;

    const { flash, errors } = usePage<{
        flash: { success?: string; error?: string };
        errors?: { dossier?: string; motif?: string; statut?: string };
    }>().props;

    /* ─── États ─── */
    const [activeTab, setActiveTab] = useState('1');
    const [processing, setProcessing] = useState(false);
    const [selectedMois, setSelectedMois] = useState(moisActuel);

    /* ─── Sélection dossier ─── */
    const [selectedDossierId, setSelectedDossierId] = useState<string>('');
    const [dossierOptions, setDossierOptions] = useState<{ value: string; label: string; dossier: any }[]>([]);
    const [isLoadingDossiers, setIsLoadingDossiers] = useState(false);

    /* ─── Stagiaires ─── */
    const [stagiaires, setStagiaires] = useState<StagiaireRow[]>([]);
    const [stagiaireTotal, setStagiaireTotal] = useState(0);
    const [stagiairePage, setStagiairePage] = useState(1);
    const [stagiaireSearch, setStagiaireSearch] = useState('');
    const [stagiaireLoading, setStagiaireLoading] = useState(false);
    const [selectedStagiaireIds, setSelectedStagiaireIds] = useState<number[]>([]);

    /* ─── Pagination dossiers ─── */
    const DOSSIERS_PER_PAGE = 5;
    const [dossierPage, setDossierPage] = useState(1);

    /* ─── Refs ─── */
    const stagiairesRef = useRef<HTMLDivElement>(null);

    /* ─── Modales ─── */
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [motifAjourner, setMotifAjourner] = useState('');
    const [dossierToAction, setDossierToAction] = useState<DossierRow | null>(null);

    /* ─── Prévisualisation fichiers ─── */
    const [modalPreviewOpen, setModalPreviewOpen] = useState(false);
    const [previewStagiaire, setPreviewStagiaire] = useState<StagiaireRow | null>(null);
    const [previewTab, setPreviewTab] = useState('cni');
    const [previewDocuments, setPreviewDocuments] = useState<DocumentItem[]>([]);
    const [previewLoading, setPreviewLoading] = useState(false);

    /* ═══════════════ CHARGEMENT DOSSIERS ═══════════════ */
    const loadDossiers = useCallback(() => {
        if (!selectedMois) {
            setDossierOptions([]);

            return;
        }

        setIsLoadingDossiers(true);
        fetch(`/cb/paiements/dossiers?mois=${selectedMois}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.json())
            .then((data: any[]) => {
                setDossierOptions(
                    data.map((d) => ({
                        value: String(d.id),
                        label: `${d.identifiant} — ${d.agence} (${d.nombre_stagiaires} stagi., ${Number(d.montant_total || 0).toLocaleString('fr-FR')} FCFA)`,
                        dossier: d,
                    }))
                );
                setSelectedDossierId('');
                setStagiaires([]);
                setStagiaireTotal(0);
            })
            .catch(() => setDossierOptions([]))
            .finally(() => setIsLoadingDossiers(false));
    }, [selectedMois]);

    useEffect(() => {
        queueMicrotask(loadDossiers);
    }, [loadDossiers]);

    /* ═══════════════ CHARGEMENT STAGIAIRES ═══════════════ */
    const loadStagiaires = useCallback(() => {
        if (!selectedDossierId) {
            setStagiaires([]);
            setStagiaireTotal(0);

            return;
        }

        setStagiaireLoading(true);
        const params = new URLSearchParams();
        params.set('dossier_id', selectedDossierId);
        params.set('start', String((stagiairePage - 1) * 10));
        params.set('length', '10');
        params.set('search', stagiaireSearch);
        params.set('draw', String(Date.now()));

        fetch('/cb/paiements/stagiaires', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
            body: params.toString(),
        })
            .then((r) => r.json())
            .then((res) => {
                setStagiaires(res.data || []);
                setStagiaireTotal(res.recordsFiltered || 0);
                setSelectedStagiaireIds([]);
            })
            .catch(() => {
                setStagiaires([]);
                setStagiaireTotal(0);
            })
            .finally(() => setStagiaireLoading(false));
    }, [selectedDossierId, stagiairePage, stagiaireSearch]);

    useEffect(() => {
        queueMicrotask(loadStagiaires);
    }, [loadStagiaires]);

    /* ═══════════════ SÉLECTION ═══════════════ */
    const toggleAllStagiaires = () => {
        setSelectedStagiaireIds((prev) =>
            prev.length === stagiaires.length ? [] : stagiaires.map((s) => s.paiement_id)
        );
    };

    const toggleStagiaireSelection = (id: number) => {
        setSelectedStagiaireIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    /* ═══════════════ ACTIONS ═══════════════ */
    const handleValiderDossier = (dossier: DossierRow) => {
        setDossierToAction(dossier);
        setModalValiderOpen(true);
    };

    const confirmValider = () => {
        if (!dossierToAction) {
            return;
        }

        setProcessing(true);
        router.post(`/cb/paiements/valider/${dossierToAction.id}`, {}, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setModalValiderOpen(false);
                setDossierToAction(null);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleAjournerDossier = (dossier: DossierRow) => {
        setDossierToAction(dossier);
        setMotifAjourner('');
        setModalAjournerOpen(true);
    };

    const confirmAjourner = () => {
        if (!dossierToAction || motifAjourner.trim().length < 5) {
            return;
        }

        setProcessing(true);
        router.post(`/cb/paiements/ajourner/${dossierToAction.id}`, { motif: motifAjourner }, {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setModalAjournerOpen(false);
                setDossierToAction(null);
                setMotifAjourner('');
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    /* ═══════════════ PRÉVISUALISATION FICHIERS ═══════════════ */
    const handlePreview = (stagiaire: StagiaireRow) => {
        setPreviewStagiaire(stagiaire);
        setPreviewTab('cni');
        setPreviewDocuments([]);
        setModalPreviewOpen(true);

        if (stagiaire.stage_id) {
            setPreviewLoading(true);
            fetch(`/cb/paiements/documents?stage_id=${stagiaire.stage_id}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => r.json())
                .then((res) => setPreviewDocuments(res.data || []))
                .catch(() => setPreviewDocuments([]))
                .finally(() => setPreviewLoading(false));
        }
    };

    const getFileUrl = (chemin: string): string => {
        if (!chemin) {
            return '';
        }

        return `/storage/${chemin}`;
    };

    const getDocumentByType = (code: string): DocumentItem | undefined => {
        return previewDocuments.find((d) => d.type_code?.toUpperCase() === code.toUpperCase());
    };

    /* ─── Badge helpers ─── */
    const getStatutBadge = (statut?: string) => {
        switch ((statut || '').toUpperCase()) {
            case 'VALIDE': case 'VALIDE_CB': return 'success';
            case 'AJOURNE': case 'AJOURNE_DMG': case 'AJOURNE_CB': return 'danger';
            case 'TRANSMIS_CB': return 'warning';
            case 'BROUILLON': return 'info';
            default: return 'secondary';
        }
    };

    /* ─── Période totale montant ─── */
    const totalMontant = useMemo(
        () => stagiaires.reduce((sum, s) => sum + Number(s.montant || 0), 0),
        [stagiaires]
    );

    /* ═══════════════ RENDU ═══════════════ */
    return (
        <React.Fragment>
            <Head title="Espace CB — Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Contrôle des Dossiers de Paiement" pageTitle="Chef de Bureau (CB)" />

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
                    {(errors?.dossier || errors?.statut) && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show">
                            <i className="ri-error-warning-line me-2 align-middle"></i>
                            {errors.dossier || errors.statut}
                        </Alert>
                    )}

                    {/* ─── Cartes Statistiques ─── */}
                    {(() => {
                        const totalDossiers = dossiersControle.length;
                        const totalMontantDossiers = dossiersControle.reduce((sum, d) => sum + Number(d.montant_total || 0), 0);
                        const totalStagiaires = dossiersControle.reduce((sum, d) => sum + (d.nombre_stagiaires || 0), 0);
                        const totalAjournes = etatsAjournes.length;
                        const stats = [
                            { label: 'Dossiers en attente', value: totalDossiers, icon: 'ri-folder-check-line', color: 'primary' },
                            { label: 'Montant total', value: `${totalMontantDossiers.toLocaleString('fr-FR')} FCFA`, icon: 'ri-money-dollar-circle-line', color: 'success' },
                            { label: 'Stagiaires concernés', value: totalStagiaires, icon: 'ri-user-follow-line', color: 'info' },
                            { label: 'États ajournés', value: totalAjournes, icon: 'ri-close-circle-line', color: 'danger' },
                        ];

                        return (
                            <Row className="g-3 mb-4">
                                {stats.map((s) => (
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
                        );
                    })()}

                    {/* ═══════ SÉLECTEUR MOIS + DOSSIER ═══════ */}
                    <Card className="mb-3 shadow-sm border-0">
                        <CardBody className="py-3">
                            <Row className="g-3 align-items-end">
                                <Col md={3}>
                                    <Label className="form-label fw-semibold">
                                        <i className="ri-calendar-line me-1 text-primary"></i>Période
                                    </Label>
                                    <Input type="select" value={selectedMois}
                                        onChange={(e) => {
                                            setSelectedMois(e.target.value);
                                            setSelectedDossierId('');
                                            setDossierPage(1);
                                            setStagiaires([]);
                                            setStagiaireTotal(0);
                                        }}>
                                        <option value="">Toutes les périodes</option>
                                        {periodeOptions.map((p) => (
                                            <option key={p.id} value={p.code}>{p.code}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={6}>
                                    <Label className="form-label fw-semibold">
                                        <i className="ri-folder-2-line me-1 text-warning"></i>Dossier État de Paiement
                                        {isLoadingDossiers && <Spinner size="sm" className="ms-2" color="warning" />}
                                    </Label>
                                    <Select
                                        options={dossierOptions}
                                        value={dossierOptions.find((o) => o.value === selectedDossierId) || null}
                                        onChange={(selected: any) => {
                                            setSelectedDossierId(selected?.value || '');
                                            setStagiairePage(1);
                                            setStagiaireSearch('');
                                        }}
                                        placeholder="Sélectionner un dossier..."
                                        noOptionsMessage={() => 'Aucun dossier disponible pour cette période'}
                                        isDisabled={isLoadingDossiers}
                                        classNamePrefix="react-select"
                                        styles={{
                                            control: (base) => ({ ...base, minHeight: 38, borderColor: '#dee2e6', fontSize: 13 }),
                                            menu: (base) => ({ ...base, zIndex: 9999 }),
                                        }}
                                    />
                                </Col>
                                <Col md={3}>
                                    <div className="d-flex gap-2">
                                        <Button color="success" size="sm"
                                            disabled={!selectedDossierId || processing}
                                            onClick={() => {
                                                const dossier = dossierOptions.find(o => o.value === selectedDossierId)?.dossier;

                                                if (dossier) {
                                                    const row: DossierRow = {
                                                        id: dossier.id,
                                                        numero: dossier.identifiant,
                                                        agence: { nom: dossier.agence },
                                                        source_financement: { libelle: dossier.source_financement },
                                                        nombre_stagiaires: dossier.nombre_stagiaires,
                                                        montant: dossier.montant_total,
                                                        montant_total: dossier.montant_total,
                                                        statut: 'En attente CB',
                                                        statut_code: 'TRANSMIS_CB',
                                                        date_creation: '',
                                                    };
                                                    handleValiderDossier(row);
                                                }
                                            }}>
                                            <i className="ri-check-line me-1"></i>Valider
                                        </Button>
                                        <Button color="danger" size="sm" outline
                                            disabled={!selectedDossierId}
                                            onClick={() => {
                                                const dossier = dossierOptions.find(o => o.value === selectedDossierId)?.dossier;

                                                if (dossier) {
                                                    const row: DossierRow = {
                                                        id: dossier.id,
                                                        numero: dossier.identifiant,
                                                        agence: { nom: dossier.agence },
                                                        source_financement: { libelle: dossier.source_financement },
                                                        nombre_stagiaires: dossier.nombre_stagiaires,
                                                        montant: dossier.montant_total,
                                                        montant_total: dossier.montant_total,
                                                        statut: 'En attente CB',
                                                        statut_code: 'TRANSMIS_CB',
                                                        date_creation: '',
                                                    };
                                                    handleAjournerDossier(row);
                                                }
                                            }}>
                                            <i className="ri-close-circle-line me-1"></i>Ajourner
                                        </Button>
                                    </div>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {/* ═══════ ONGLES PRINCIPAUX ═══════ */}
                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom-0 pb-0">
                            <h4 className="card-title mb-0">
                                <i className="ri-shield-check-line me-2 text-success"></i>
                                Vérification des États de Paiement
                            </h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: activeTab === '1' }, 'fw-semibold py-3')}
                                        onClick={() => setActiveTab('1')}>
                                        <i className="ri-folder-check-line me-1 align-middle"></i>
                                        Dossiers en attente <Badge color="primary" pill className="ms-2">{dossiersControle.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: activeTab === '2' }, 'fw-semibold py-3')}
                                        onClick={() => setActiveTab('2')}>
                                        <i className="ri-close-circle-line me-1 align-middle"></i>
                                        États Ajournés (Retours DMG) <Badge color="danger" pill className="ms-2">{etatsAjournes.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="pt-4 text-muted">
                                {/* ═══════ ONGLET 1 : DOSSIERS EN ATTENTE ═══════ */}
                                <TabPane tabId="1">
                                    {/* ── Tableau des dossiers ── */}
                                    {(() => {
                                        const totalPages = Math.max(1, Math.ceil(dossiersControle.length / DOSSIERS_PER_PAGE));
                                        const safeDossierPage = Math.min(dossierPage, totalPages);
                                        const startIdx = (safeDossierPage - 1) * DOSSIERS_PER_PAGE;
                                        const pageRows = dossiersControle.slice(startIdx, startIdx + DOSSIERS_PER_PAGE);

                                        return (
                                            <>
                                                <div className="table-responsive">
                                                    <table className="table table-striped table-hover align-middle mb-0">
                                                        <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                            <tr>
                                                                <th style={{ width: 40 }}>#</th>
                                                                <th>Numéro</th>
                                                                <th>Agence</th>
                                                                <th>Financement</th>
                                                                <th className="text-center">Nb Stagiaires</th>
                                                                <th className="text-end">Montant Total</th>
                                                                <th>Statut</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {pageRows.map((d, idx) => (
                                                                <tr key={d.id}
                                                                    className={selectedDossierId === String(d.id) ? 'table-active' : ''}
                                                                    onClick={() => {
                                                                        setSelectedDossierId(String(d.id));
                                                                        setStagiairePage(1);
                                                                        setStagiaireSearch('');
                                                                        setTimeout(() => stagiairesRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
                                                                    }}
                                                                    style={{ cursor: 'pointer' }}>
                                                                    <td>{startIdx + idx + 1}</td>
                                                                    <td className="fw-medium text-primary">{d.numero}</td>
                                                                    <td>{d.agence?.nom || '-'}</td>
                                                                    <td>
                                                                        <Badge color="info-subtle" className="text-info">{d.source_financement?.libelle || '-'}</Badge>
                                                                    </td>
                                                                    <td className="text-center">
                                                                        <Badge color="primary" pill>{d.nombre_stagiaires || 0}</Badge>
                                                                    </td>
                                                                    <td className="text-end fw-bold">
                                                                        {Number(d.montant_total || 0).toLocaleString('fr-FR')} FCFA
                                                                    </td>
                                                                    <td><Badge color={getStatutBadge(d.statut)} className="fs-11">{d.statut}</Badge></td>
                                                                    <td>
                                                                        <div className="d-flex gap-1" onClick={(e) => e.stopPropagation()}>
                                                                            <Button color="success" size="sm" outline
                                                                                onClick={() => handleValiderDossier(d)}
                                                                                title="Valider le dossier">
                                                                                <i className="ri-check-line"></i>
                                                                            </Button>
                                                                            <Button color="danger" size="sm" outline
                                                                                onClick={() => handleAjournerDossier(d)}
                                                                                title="Ajourner le dossier">
                                                                                <i className="ri-close-line"></i>
                                                                            </Button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                            {dossiersControle.length === 0 && (
                                                                <tr>
                                                                    <td colSpan={8} className="text-center py-4">
                                                                        <i className="ri-inbox-line fs-24 d-block mb-2 text-muted"></i>
                                                                        Aucun dossier en attente de contrôle pour cette période.
                                                                    </td>
                                                                </tr>
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {/* ── Pagination dossiers ── */}
                                                {dossiersControle.length > DOSSIERS_PER_PAGE && (() => {
                                                    // Pages visibles : max 7 avec ellipses
                                                    const maxVisible = 7;
                                                    let pages: (number | '...')[] = [];

                                                    if (totalPages <= maxVisible) {
                                                        pages = Array.from({ length: totalPages }, (_, i) => i + 1);
                                                    } else {
                                                        pages = [1];

                                                        if (safeDossierPage > 3) {
                                                            pages.push('...');
                                                        }

                                                        const start = Math.max(2, safeDossierPage - 1);
                                                        const end = Math.min(totalPages - 1, safeDossierPage + 1);

                                                        for (let i = start; i <= end; i++) {
                                                            pages.push(i);
                                                        }

                                                        if (safeDossierPage < totalPages - 2) {
                                                            pages.push('...');
                                                        }

                                                        pages.push(totalPages);
                                                    }

                                                    return (
                                                        <div className="d-flex justify-content-between align-items-center p-2 border-top mt-2">
                                                            <small className="text-muted">
                                                                Affichage {startIdx + 1}–{Math.min(startIdx + DOSSIERS_PER_PAGE, dossiersControle.length)} sur {dossiersControle.length} dossier(s)
                                                            </small>
                                                            <div className="d-flex align-items-center gap-1">
                                                                <Button size="sm" color="light" disabled={safeDossierPage <= 1}
                                                                    onClick={() => setDossierPage((p) => p - 1)}>
                                                                    <i className="ri-arrow-left-s-line"></i>
                                                                </Button>
                                                                {pages.map((page, i) =>
                                                                    page === '...' ? (
                                                                        <span key={`dots-${i}`} className="px-1 text-muted">…</span>
                                                                    ) : (
                                                                        <Button key={page} size="sm"
                                                                            color={page === safeDossierPage ? 'primary' : 'light'}
                                                                            onClick={() => setDossierPage(page)}>
                                                                            {page}
                                                                        </Button>
                                                                    )
                                                                )}
                                                                <Button size="sm" color="light" disabled={safeDossierPage >= totalPages}
                                                                    onClick={() => setDossierPage((p) => p + 1)}>
                                                                    <i className="ri-arrow-right-s-line"></i>
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    );
                                                })()}
                                            </>
                                        );
                                    })()}

                                    {/* ── Prompt sélection dossier ── */}
                                    {!selectedDossierId && dossiersControle.length > 0 && (
                                        <div className="text-center py-4 text-muted border-top mt-3">
                                            <i className="ri-cursor-line fs-24 d-block mb-2"></i>
                                            <p className="mb-0">Cliquez sur un dossier pour afficher la liste des stagiaires.</p>
                                        </div>
                                    )}

                                    {/* ── Liste des Stagiaires ── */}
                                    {selectedDossierId && (
                                        <Card className="border shadow-none mt-4" innerRef={stagiairesRef}>
                                            <CardHeader className="bg-info bg-opacity-10 py-2 d-flex justify-content-between align-items-center">
                                                <h6 className="card-title mb-0 fs-13 text-info">
                                                    <i className="ri-user-search-line me-1"></i>
                                                    Liste des Stagiaires du dossier
                                                    <Badge color="info" pill className="ms-2 fs-11">{stagiaireTotal}</Badge>
                                                </h6>
                                                <div className="d-flex align-items-center gap-2">
                                                    {totalMontant > 0 && (
                                                        <Badge color="success" className="fs-12">
                                                            Total: {totalMontant.toLocaleString('fr-FR')} FCFA
                                                        </Badge>
                                                    )}
                                                    <Input type="text" bsSize="sm" placeholder="Rechercher un stagiaire..."
                                                        style={{ maxWidth: 250 }}
                                                        value={stagiaireSearch}
                                                        onChange={(e) => {
                                                            setStagiaireSearch(e.target.value);
                                                            setStagiairePage(1);
                                                        }} />
                                                </div>
                                            </CardHeader>
                                            <CardBody className="p-0">
                                                {stagiaireLoading ? (
                                                    <div className="d-flex justify-content-center py-4">
                                                        <Spinner color="info" size="sm" />
                                                    </div>
                                                ) : stagiaires.length === 0 ? (
                                                    <p className="text-muted text-center py-4 mb-0">
                                                        <i className="ri-inbox-line me-1"></i>Aucun stagiaire trouvé.
                                                    </p>
                                                ) : (
                                                    <div className="table-responsive">
                                                        <table className="table table-striped table-hover align-middle mb-0">
                                                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                                <tr>
                                                                    <th style={{ width: 35 }}>
                                                                        <Input type="checkbox" className="form-check-input"
                                                                            checked={stagiaires.length > 0 && selectedStagiaireIds.length === stagiaires.length}
                                                                            onChange={toggleAllStagiaires} />
                                                                    </th>
                                                                    <th>Date Création</th>
                                                                    <th>Agence</th>
                                                                    <th>Entreprise</th>
                                                                    <th>Financement</th>
                                                                    <th>Type Stage</th>
                                                                    <th>N° AEJ</th>
                                                                    <th>Nom et Prénoms</th>
                                                                    <th>Date Naiss.</th>
                                                                    <th>Date Début</th>
                                                                    <th>Date Fin</th>
                                                                    <th>N° Trésor Pay</th>
                                                                    <th className="text-end">Montant</th>
                                                                    <th className="text-center">Fichiers</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {stagiaires.map((s) => (
                                                                    <tr key={s.paiement_id}>
                                                                        <td>
                                                                            <Input type="checkbox" className="form-check-input"
                                                                                checked={selectedStagiaireIds.includes(s.paiement_id)}
                                                                                onChange={() => toggleStagiaireSelection(s.paiement_id)} />
                                                                        </td>
                                                                        <td className="fs-12">{s.created_at}</td>
                                                                        <td>{s.agence}</td>
                                                                        <td className="text-truncate" style={{ maxWidth: 130 }}>{s.entreprise}</td>
                                                                        <td>
                                                                            <Badge color="info-subtle" className="text-info fs-11">{s.source_financement}</Badge>
                                                                        </td>
                                                                        <td className="text-truncate" style={{ maxWidth: 100 }}>{s.type_stage}</td>
                                                                        <td className="text-muted">{s.numero_aej}</td>
                                                                        <td className="fw-semibold">{s.nom} {s.prenoms}</td>
                                                                        <td className="fs-12">{s.date_naissance}</td>
                                                                        <td className="fs-12">{s.date_debut}</td>
                                                                        <td className="fs-12">{s.date_fin}</td>
                                                                        <td className="text-muted">{s.tresor_pay}</td>
                                                                        <td className="text-end fw-bold text-success">
                                                                            {Number(s.montant || 0).toLocaleString('fr-FR')} FCFA
                                                                        </td>
                                                                        <td className="text-center">
                                                                            <Button color="info" size="sm" outline
                                                                                onClick={() => handlePreview(s)}
                                                                                title="Prévisualiser les fichiers">
                                                                                <i className="ri-eye-line"></i>
                                                                            </Button>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}

                                                {/* ── Pagination stagiaires ── */}
                                                {(() => {
                                                    const stPages = Math.ceil(stagiaireTotal / 10);

                                                    if (stPages <= 1) {
                                                        return null;
                                                    }

                                                    const maxVis = 7;
                                                    let stPageNums: (number | '...')[] = [];

                                                    if (stPages <= maxVis) {
                                                        stPageNums = Array.from({ length: stPages }, (_, i) => i + 1);
                                                    } else {
                                                        stPageNums = [1];

                                                        if (stagiairePage > 3) {
                                                            stPageNums.push('...');
                                                        }

                                                        const s = Math.max(2, stagiairePage - 1);
                                                        const e = Math.min(stPages - 1, stagiairePage + 1);

                                                        for (let i = s; i <= e; i++) {
                                                            stPageNums.push(i);
                                                        }

                                                        if (stagiairePage < stPages - 2) {
                                                            stPageNums.push('...');
                                                        }

                                                        stPageNums.push(stPages);
                                                    }

                                                    return (
                                                        <div className="d-flex justify-content-between align-items-center p-2 border-top">
                                                            <small className="text-muted">
                                                                {stagiaireTotal} résultat(s) — Page {stagiairePage}/{stPages}
                                                            </small>
                                                            <div className="d-flex align-items-center gap-1">
                                                                <Button size="sm" color="light" disabled={stagiairePage <= 1}
                                                                    onClick={() => setStagiairePage((p) => p - 1)}>
                                                                    <i className="ri-arrow-left-s-line"></i>
                                                                </Button>
                                                                {stPageNums.map((page, i) =>
                                                                    page === '...' ? (
                                                                        <span key={`sdots-${i}`} className="px-1 text-muted">…</span>
                                                                    ) : (
                                                                        <Button key={page} size="sm"
                                                                            color={page === stagiairePage ? 'primary' : 'light'}
                                                                            onClick={() => setStagiairePage(page)}>
                                                                            {page}
                                                                        </Button>
                                                                    )
                                                                )}
                                                                <Button size="sm" color="light"
                                                                    disabled={stagiairePage >= stPages}
                                                                    onClick={() => setStagiairePage((p) => p + 1)}>
                                                                    <i className="ri-arrow-right-s-line"></i>
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    );
                                                })()}
                                            </CardBody>
                                        </Card>
                                    )}
                                </TabPane>

                                {/* ═══════ ONGLET 2 : ÉTATS AJOURNÉS ═══════ */}
                                <TabPane tabId="2">
                                    <div className="table-responsive">
                                        <table className="table table-striped table-hover align-middle mb-0">
                                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Numéro</th>
                                                    <th>Agence</th>
                                                    <th>Financement</th>
                                                    <th>Montant</th>
                                                    <th>Motif Ajournement</th>
                                                    <th>Date Ajournement</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {etatsAjournes.map((e, idx) => (
                                                    <tr key={e.id}>
                                                        <td>{idx + 1}</td>
                                                        <td className="fw-medium text-danger">{e.numero}</td>
                                                        <td>{e.agence?.nom || '-'}</td>
                                                        <td>{e.source_financement?.libelle || '-'}</td>
                                                        <td className="fw-bold">{Number(e.montant_total || 0).toLocaleString('fr-FR')} FCFA</td>
                                                        <td>{e.motif_ajournement || '-'}</td>
                                                        <td>{e.date_ajournement || e.date_transmission || '-'}</td>
                                                    </tr>
                                                ))}
                                                {etatsAjournes.length === 0 && (
                                                    <tr>
                                                        <td colSpan={7} className="text-center py-4">
                                                            <i className="ri-inbox-line fs-24 d-block mb-2 text-muted"></i>
                                                            Aucun état de paiement ajourné.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════════════════════════════════════════════════════════════
               MODAL VALIDATION DOSSIER
               ═══════════════════════════════════════════════════════════════ */}
            <Modal isOpen={modalValiderOpen} toggle={() => setModalValiderOpen(!modalValiderOpen)}>
                <ModalHeader toggle={() => setModalValiderOpen(!modalValiderOpen)}>
                    <i className="ri-check-double-line text-success me-2"></i>Validation du Dossier
                </ModalHeader>
                <ModalBody>
                    <div className="alert alert-success border-0 mb-3">
                        <i className="ri-information-line me-2"></i>
                        En validant ce dossier, il sera transmis à la DMG pour l'élaboration de l'Ordre de Paiement.
                    </div>
                    {(errors?.dossier || errors?.statut) && (
                        <div className="alert alert-danger border-0 mb-3">
                            <i className="ri-error-warning-line me-2"></i>
                            {errors.dossier || errors.statut}
                        </div>
                    )}
                    {dossierToAction && (
                        <div className="border rounded p-3 bg-light">
                            <Row className="g-2">
                                <Col md={6}><strong>Dossier :</strong> {dossierToAction.numero}</Col>
                                <Col md={6}><strong>Agence :</strong> {dossierToAction.agence?.nom}</Col>
                                <Col md={6}><strong>Stagiaires :</strong> {dossierToAction.nombre_stagiaires}</Col>
                                <Col md={6}><strong>Montant :</strong> <span className="text-success fw-bold">{Number(dossierToAction.montant_total || 0).toLocaleString('fr-FR')} FCFA</span></Col>
                            </Row>
                        </div>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalValiderOpen(false)}>Annuler</Button>
                    <Button color="success" onClick={confirmValider} disabled={processing}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : <><i className="ri-check-line me-1"></i>Confirmer la validation</>}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════════════════════════
               MODAL AJOURNEMENT DOSSIER
               ═══════════════════════════════════════════════════════════════ */}
            <Modal isOpen={modalAjournerOpen} toggle={() => setModalAjournerOpen(!modalAjournerOpen)}>
                <ModalHeader toggle={() => setModalAjournerOpen(!modalAjournerOpen)}>
                    <i className="ri-close-circle-line text-danger me-2"></i>Ajourner le Dossier
                </ModalHeader>
                <ModalBody>
                    <div className="alert alert-warning border-0 mb-3">
                        <i className="ri-alert-line me-2"></i>
                        En ajournant ce dossier, il retournera à la DMG pour correction.
                    </div>
                    {(errors?.dossier || errors?.statut) && (
                        <div className="alert alert-danger border-0 mb-3">
                            <i className="ri-error-warning-line me-2"></i>
                            {errors.dossier || errors.statut}
                        </div>
                    )}
                    {dossierToAction && (
                        <div className="border rounded p-3 bg-light mb-3">
                            <Row className="g-2">
                                <Col md={6}><strong>Dossier :</strong> {dossierToAction.numero}</Col>
                                <Col md={6}><strong>Agence :</strong> {dossierToAction.agence?.nom}</Col>
                            </Row>
                        </div>
                    )}
                    <div className="mb-3">
                        <Label className="form-label fw-semibold">Motif de l'ajournement <span className="text-danger">*</span></Label>
                        <Input type="textarea" rows={4}
                            value={motifAjourner}
                            onChange={(e) => setMotifAjourner(e.target.value)}
                            placeholder="Ex: Montant incohérent, bénéficiaire inéligible, document manquant..."
                            className={(motifAjourner.length > 0 && motifAjourner.length < 5) || errors?.motif ? 'is-invalid' : ''} />
                        {((motifAjourner.length > 0 && motifAjourner.length < 5) || errors?.motif) && (
                            <div className="invalid-feedback">
                                {errors?.motif || 'Le motif doit contenir au moins 5 caractères.'}
                            </div>
                        )}
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerOpen(false)}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjourner}
                        disabled={processing || motifAjourner.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : <><i className="ri-close-line me-1"></i>Confirmer l'ajournement</>}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════════════════════════
               MODAL PRÉVISUALISATION FICHIERS
               ═══════════════════════════════════════════════════════════════ */}
            <Modal isOpen={modalPreviewOpen} toggle={() => setModalPreviewOpen(!modalPreviewOpen)} size="xl">
                <ModalHeader toggle={() => setModalPreviewOpen(!modalPreviewOpen)}>
                    <i className="ri-file-search-line me-2 text-primary"></i>
                    Prévisualisation des Fichiers — {previewStagiaire ? `${previewStagiaire.nom} ${previewStagiaire.prenoms}` : ''}
                </ModalHeader>
                <ModalBody className="p-0">
                    {previewStagiaire && (
                        <div>
                            {/* ── Infos stagiaire ── */}
                            <div className="bg-light p-3 border-bottom">
                                <Row className="g-2 fs-12">
                                    <Col md={3}><strong>N° AEJ :</strong> {previewStagiaire.numero_aej}</Col>
                                    <Col md={3}><strong>Entreprise :</strong> {previewStagiaire.entreprise}</Col>
                                    <Col md={3}><strong>Montant :</strong> <span className="text-success fw-bold">{Number(previewStagiaire.montant || 0).toLocaleString('fr-FR')} FCFA</span></Col>
                                    <Col md={3}><strong>Dossier :</strong> {previewStagiaire.dossier_identifiant || '-'}</Col>
                                </Row>
                            </div>

                            {/* ── Onglets fichiers ── */}
                            <Nav tabs className="nav-tabs-custom nav-fill border-bottom mx-3 mt-2">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: previewTab === 'cni' }, 'fw-semibold')}
                                        onClick={() => setPreviewTab('cni')}>
                                        <i className="ri-id-card-line me-1"></i>Pièce d'Identité
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: previewTab === 'tresor' }, 'fw-semibold')}
                                        onClick={() => setPreviewTab('tresor')}>
                                        <i className="ri-money-dollar-circle-line me-1"></i>Trésor Money
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: previewTab === 'contrat' }, 'fw-semibold')}
                                        onClick={() => setPreviewTab('contrat')}>
                                        <i className="ri-file-text-line me-1"></i>Contrat
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }}
                                        className={classnames({ active: previewTab === 'attestation' }, 'fw-semibold')}
                                        onClick={() => setPreviewTab('attestation')}>
                                        <i className="ri-file-shield-line me-1"></i>Attestation de Présence
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={previewTab} className="p-3">
                                <TabPane tabId="cni">
                                    {previewLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="primary" /></div>
                                    ) : (() => {
                                        const doc = getDocumentByType('CNI') || getDocumentByType('PIECE_IDENTITE');

                                        return doc ? (
                                            <iframe
                                                src={getFileUrl(doc.chemin)}
                                                title="Pièce d'identité"
                                                style={{ width: '100%', height: 600, border: '1px solid #dee2e6', borderRadius: 4 }}
                                            />
                                        ) : (
                                            <div className="text-center py-5 text-muted">
                                                <i className="ri-file-excel-2-line fs-24 d-block mb-2"></i>
                                                Aucun fichier pièce d'identité disponible
                                            </div>
                                        );
                                    })()}
                                </TabPane>
                                <TabPane tabId="tresor">
                                    {previewLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="primary" /></div>
                                    ) : (() => {
                                        const doc = getDocumentByType('FICHE_YUP') || getDocumentByType('TRESOR_MONEY') || getDocumentByType('FICHE_TRESOR_MONEY');

                                        return doc ? (
                                            <iframe
                                                src={getFileUrl(doc.chemin)}
                                                title="Fiche Trésor Money"
                                                style={{ width: '100%', height: 600, border: '1px solid #dee2e6', borderRadius: 4 }}
                                            />
                                        ) : (
                                            <div className="text-center py-5 text-muted">
                                                <i className="ri-file-excel-2-line fs-24 d-block mb-2"></i>
                                                Aucun fichier Trésor Money disponible
                                            </div>
                                        );
                                    })()}
                                </TabPane>
                                <TabPane tabId="contrat">
                                    {previewLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="primary" /></div>
                                    ) : (() => {
                                        const doc = getDocumentByType('CONTRAT') || getDocumentByType('FILE_CONTRAT');

                                        return doc ? (
                                            <iframe
                                                src={getFileUrl(doc.chemin)}
                                                title="Contrat"
                                                style={{ width: '100%', height: 600, border: '1px solid #dee2e6', borderRadius: 4 }}
                                            />
                                        ) : (
                                            <div className="text-center py-5 text-muted">
                                                <i className="ri-file-excel-2-line fs-24 d-block mb-2"></i>
                                                Aucun fichier contrat disponible
                                            </div>
                                        );
                                    })()}
                                </TabPane>
                                <TabPane tabId="attestation">
                                    {previewLoading ? (
                                        <div className="d-flex justify-content-center py-5"><Spinner color="primary" /></div>
                                    ) : (() => {
                                        const doc = getDocumentByType('ATTESTATION_PRESENCE') || getDocumentByType('ATTESTATION');

                                        return doc ? (
                                            <iframe
                                                src={getFileUrl(doc.chemin)}
                                                title="Attestation de présence"
                                                style={{ width: '100%', height: 600, border: '1px solid #dee2e6', borderRadius: 4 }}
                                            />
                                        ) : (
                                            <div className="text-center py-5 text-muted">
                                                <i className="ri-file-excel-2-line fs-24 d-block mb-2"></i>
                                                Aucune attestation de présence disponible
                                            </div>
                                        );
                                    })()}
                                </TabPane>
                            </TabContent>
                        </div>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalPreviewOpen(false)}>
                        <i className="ri-close-line me-1"></i>Fermer
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CbPaiementsIndex;
