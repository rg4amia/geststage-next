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
interface PageProps {
    moisEnAttente: { value: string; label: string; count: number }[];
    moisActuel: string | null;
    activeTab: string;
    pointagesSoumis: any;
    pointagesCorrigeAdp: any;
    filters: Record<string, string>;
    agences: { id: number; nom: string }[];
    entreprises: { id: number; raison_sociale: string }[];
    sourcesFinancement: { id: number; nom: string }[];
    typesStage: { id: number; nom: string }[];
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
    };
    const s = map[statut] || { color: 'secondary', label: statut || '-' };
    return <span className={`badge bg-${s.color}-subtle text-${s.color}`}>{s.label}</span>;
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const PointagesIndex = (props: PageProps) => {
    const {
        moisEnAttente = [],
        moisActuel,
        activeTab: initialTab = 'attente',
        pointagesSoumis,
        pointagesCorrigeAdp,
        filters = {},
        agences = [],
        entreprises = [],
        sourcesFinancement = [],
        typesStage = [],
    } = props;

    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const [currentTab, setCurrentTab] = useState(initialTab);
    const [selectedMois, setSelectedMois] = useState(moisActuel || '');
    const [selectedFilters, setSelectedFilters] = useState({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        search: filters?.search || '',
    });

    /* ─── Sélection batch ─── */
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    /* ─── Modales ─── */
    const [ajournerModalOpen, setAjournerModalOpen] = useState(false);
    const [ajournerTarget, setAjournerTarget] = useState<any>(null);
    const [ajournerMotif, setAjournerMotif] = useState('');
    const [ajournerGroupeModalOpen, setAjournerGroupeModalOpen] = useState(false);
    const [ajournerGroupeMotif, setAjournerGroupeMotif] = useState('');
    const [rejeterModalOpen, setRejeterModalOpen] = useState(false);
    const [rejeterTarget, setRejeterTarget] = useState<any>(null);
    const [isProcessing, setIsProcessing] = useState(false);

    /* ─── Navigation / Filtres ─── */
    const applyFilters = useCallback(
        (mois: string, tab: string, currentFilters: typeof selectedFilters) => {
            const params: Record<string, string> = { tab };
            if (mois) params.mois = mois;
            Object.entries(currentFilters).forEach(([key, val]) => {
                if (val) params[key] = val;
            });
            router.get('/chefagence/pointages', params, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [],
    );

    const handleMoisChange = (mois: string) => {
        setSelectedMois(mois);
        setSelectedIds([]);
        applyFilters(mois, currentTab, selectedFilters);
    };

    const toggleTab = (t: string) => {
        if (currentTab !== t) {
            setCurrentTab(t);
            setSelectedIds([]);
            applyFilters(selectedMois, t, selectedFilters);
        }
    };

    const handleFilterChange = (field: string, value: string) => {
        const newFilters = { ...selectedFilters, [field]: value };
        setSelectedFilters(newFilters);
        applyFilters(selectedMois, currentTab, newFilters);
    };

    const resetFilters = () => {
        const defaultFilters = { agence_id: '', entreprise_id: '', source_financement_id: '', type_stage_id: '', search: '' };
        setSelectedFilters(defaultFilters);
        applyFilters(selectedMois, currentTab, defaultFilters);
    };

    /* ─── Sélection batch ─── */
    const toggleSelectAll = (rows: any[]) => {
        if (selectedIds.length === rows.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(rows.map((r: any) => r.id));
        }
    };

    const toggleSelectOne = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    /* ─── Validation individuelle ─── */
    const handleValider = (pointageId: number) => {
        if (isProcessing) return;
        setIsProcessing(true);
        router.post(`/chefagence/pointages/valider/${pointageId}`, {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Validation groupée ─── */
    const handleValiderGroupe = () => {
        if (selectedIds.length === 0 || isProcessing) return;
        setIsProcessing(true);
        router.post('/chefagence/pointages/valider-groupe', {
            pointage_ids: selectedIds,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelectedIds([]),
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Validation par filtre (source_financement + type_stage) ─── */
    const hasFilterValidation = selectedFilters.source_financement_id && selectedFilters.type_stage_id;

    const handleValiderParFiltre = () => {
        if (!hasFilterValidation || !selectedMois || isProcessing) return;
        setIsProcessing(true);
        router.post('/chefagence/pointages/valider-par-filtre', {
            mois: selectedMois,
            source_financement_id: selectedFilters.source_financement_id,
            type_stage_id: selectedFilters.type_stage_id,
        }, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Ajournement individuel ─── */
    const openAjournerModal = (pointage: any) => {
        setAjournerTarget(pointage);
        setAjournerMotif('');
        setAjournerModalOpen(true);
    };

    const confirmAjourner = () => {
        if (!ajournerTarget || isProcessing) return;
        setIsProcessing(true);
        router.post(`/chefagence/pointages/ajourner/${ajournerTarget.id}`, {
            motif: ajournerMotif,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setAjournerModalOpen(false);
                setAjournerTarget(null);
                setAjournerMotif('');
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Ajournement groupé ─── */
    const openAjournerGroupeModal = () => {
        if (selectedIds.length === 0) return;
        setAjournerGroupeMotif('');
        setAjournerGroupeModalOpen(true);
    };

    const confirmAjournerGroupe = () => {
        if (selectedIds.length === 0 || isProcessing) return;
        setIsProcessing(true);
        router.post('/chefagence/pointages/ajourner-groupe', {
            pointage_ids: selectedIds,
            motif: ajournerGroupeMotif,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setAjournerGroupeModalOpen(false);
                setAjournerGroupeMotif('');
                setSelectedIds([]);
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Validation correction ADP ─── */
    const handleValiderAdp = (pointageId: number) => {
        if (isProcessing) return;
        setIsProcessing(true);
        router.post(`/chefagence/pointages-adp/${pointageId}/valider`, {}, {
            preserveScroll: true,
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Rejet correction ADP ─── */
    const openRejeterModal = (pointage: any) => {
        setRejeterTarget(pointage);
        setRejeterModalOpen(true);
    };

    const confirmRejeter = () => {
        if (!rejeterTarget || isProcessing) return;
        setIsProcessing(true);
        router.post(`/chefagence/pointages-adp/${rejeterTarget.id}/rejeter`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setRejeterModalOpen(false);
                setRejeterTarget(null);
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Génération Attestation PDF ─── */
    const handleGenererAttestation = () => {
        if (!selectedMois || isProcessing) return;
        setIsProcessing(true);

        const formData = new FormData();
        formData.append('mois', selectedMois);
        if (selectedFilters.source_financement_id) {
            formData.append('source_financement_id', selectedFilters.source_financement_id);
        }
        if (selectedFilters.type_stage_id) {
            formData.append('type_stage_id', selectedFilters.type_stage_id);
        }
        if (selectedIds.length > 0) {
            selectedIds.forEach((id) => formData.append('pointage_ids[]', String(id)));
        }
        formData.append('mode_traitement', '1');

        // Utiliser fetch pour télécharger le PDF
        fetch('/chefagence/pointages/generer-attestation', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })
            .then((response) => {
                if (!response.ok) throw new Error('Erreur lors de la génération');
                return response.blob();
            })
            .then((blob) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `ATTESTATION_PRESENCE_${selectedMois}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
            })
            .catch((err) => {
                console.error(err);
            })
            .finally(() => setIsProcessing(false));
    };

    /* ─── Données table ─── */
    const soumisData = pointagesSoumis?.data || [];
    const corrigeData = pointagesCorrigeAdp?.data || [];
    const currentData = currentTab === 'attente' ? soumisData : corrigeData;

    /* ─── Colonnes Pointages soumis ─── */
    const columnsSoumis = useMemo(
        () => [
            {
                id: 'select',
                header: () => (
                    <input
                        type="checkbox"
                        className="form-check-input"
                        checked={selectedIds.length === soumisData.length && soumisData.length > 0}
                        onChange={() => toggleSelectAll(soumisData)}
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
                header: 'Agence',
                cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'Financement',
                cell: (cell: any) => cell.row.original.stage?.sourceFinancement?.nom || '-',
            },
            {
                header: 'Type Stage',
                cell: (cell: any) => cell.row.original.stage?.typeStage?.nom || '-',
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return <span className="fw-medium">{b?.nom} {b?.prenoms}</span>;
                },
            },
            {
                header: 'Date début',
                cell: (cell: any) => formatDateFr(cell.row.original.stage?.date_debut),
            },
            {
                header: 'Date fin',
                cell: (cell: any) => formatDateFr(cell.row.original.stage?.date_fin_prevue),
            },
            {
                header: 'Jours Présents',
                cell: (cell: any) => cell.row.original.versionCourante?.jours_presents ?? '-',
            },
            {
                header: 'Statut',
                cell: (cell: any) => statusBadge(cell.row.original.statut),
            },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <div className="d-flex gap-1">
                        <Button color="success" size="sm" outline onClick={() => handleValider(cell.row.original.id)} title="Valider">
                            <i className="ri-check-line me-1"></i>Valider
                        </Button>
                        <Button color="warning" size="sm" outline onClick={() => openAjournerModal(cell.row.original)} title="Ajourner">
                            <i className="ri-close-line me-1"></i>Ajourner
                        </Button>
                    </div>
                ),
            },
        ],
        [selectedIds, soumisData],
    );

    /* ─── Colonnes Corrections ADP ─── */
    const columnsCorrige = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Agence',
                cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-',
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_aej || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return <span className="fw-medium">{b?.nom} {b?.prenoms}</span>;
                },
            },
            {
                header: 'Financement',
                cell: (cell: any) => cell.row.original.stage?.sourceFinancement?.nom || '-',
            },
            {
                header: 'Jours Présents',
                cell: (cell: any) => cell.row.original.versionCourante?.jours_presents ?? '-',
            },
            {
                header: 'Statut',
                cell: (cell: any) => statusBadge(cell.row.original.statut),
            },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <div className="d-flex gap-1">
                        <Button color="success" size="sm" outline onClick={() => handleValiderAdp(cell.row.original.id)} title="Accepter correction">
                            <i className="ri-check-line me-1"></i>Accepter
                        </Button>
                        <Button color="danger" size="sm" outline onClick={() => openRejeterModal(cell.row.original)} title="Rejeter">
                            <i className="ri-close-line me-1"></i>Rejeter
                        </Button>
                    </div>
                ),
            },
        ],
        [corrigeData],
    );

    const currentColumns = currentTab === 'attente' ? columnsSoumis : columnsCorrige;

    /* ─── Onglets ─── */
    const tabs = [
        {
            key: 'attente',
            label: 'NOUVEAUX POINTAGES',
            count: pointagesSoumis?.total || soumisData.length || 0,
            color: 'info',
            icon: 'ri-time-line',
        },
        {
            key: 'corrige_adp',
            label: 'CORRECTIONS ADP',
            count: pointagesCorrigeAdp?.total || corrigeData.length || 0,
            color: 'warning',
            icon: 'ri-edit-line',
        },
    ];

    return (
        <React.Fragment>
            <Head title="Validation Pointages - Chef d'Agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validation des Pointages" pageTitle="Chef d'Agence" />

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

                    <Row>
                        <Col lg={12}>
                            <Card className="shadow-sm border-0">
                                <CardHeader className="bg-transparent border-bottom-0 pb-0">
                                    <div className="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                                        <h4 className="card-title mb-0">
                                            <i className="ri-calendar-check-line me-2 text-primary"></i>
                                            Validation des Pointages
                                        </h4>
                                        <Button color="soft-secondary" size="sm" onClick={resetFilters}>
                                            <i className="ri-refresh-line me-1"></i>Réinitialiser les filtres
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    {/* ─── Sélecteur de Mois ─── */}
                                    <Row className="g-3 mb-4">
                                        <Col md={3}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                bsSize="sm"
                                                value={selectedMois}
                                                onChange={(e) => handleMoisChange(e.target.value)}
                                            >
                                                <option value="">Sélectionner un mois</option>
                                                {moisEnAttente.map((m) => (
                                                    <option key={m.value} value={m.value}>
                                                        {m.label} ({m.count} en attente)
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>

                                        {/* ─── Filtres ─── */}
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                            <Input
                                                type="select"
                                                bsSize="sm"
                                                value={selectedFilters.agence_id}
                                                onChange={(e) => handleFilterChange('agence_id', e.target.value)}
                                            >
                                                <option value="">Toutes</option>
                                                {agences.map((a) => (
                                                    <option key={a.id} value={a.id}>{a.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Entreprise</Label>
                                            <Input
                                                type="select"
                                                bsSize="sm"
                                                value={selectedFilters.entreprise_id}
                                                onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}
                                            >
                                                <option value="">Toutes</option>
                                                {entreprises.map((e) => (
                                                    <option key={e.id} value={e.id}>{e.raison_sociale}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Financement</Label>
                                            <Input
                                                type="select"
                                                bsSize="sm"
                                                value={selectedFilters.source_financement_id}
                                                onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}
                                            >
                                                <option value="">Tous</option>
                                                {sourcesFinancement.map((sf) => (
                                                    <option key={sf.id} value={sf.id}>{sf.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type Stage</Label>
                                            <Input
                                                type="select"
                                                bsSize="sm"
                                                value={selectedFilters.type_stage_id}
                                                onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}
                                            >
                                                <option value="">Tous</option>
                                                {typesStage.map((ts) => (
                                                    <option key={ts.id} value={ts.id}>{ts.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={1}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Recherche</Label>
                                            <Input
                                                type="text"
                                                bsSize="sm"
                                                placeholder="Nom, N° AEJ..."
                                                value={selectedFilters.search}
                                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                            />
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
                                        {/* ─── Onglet Nouveaux Pointages ─── */}
                                        <TabPane tabId="attente">
                                            {/* Barre d'actions groupées */}
                                            <div className="d-flex gap-2 mb-3 p-2 bg-light rounded">
                                                {selectedIds.length > 0 && (
                                                    <>
                                                        <span className="text-muted align-self-center">
                                                            <strong>{selectedIds.length}</strong> élément(s) sélectionné(s)
                                                        </span>
                                                        <Button color="success" size="sm" onClick={handleValiderGroupe} disabled={isProcessing}>
                                                            <i className="ri-check-double-line me-1"></i>Valider la sélection
                                                        </Button>
                                                        <Button color="warning" size="sm" onClick={openAjournerGroupeModal} disabled={isProcessing}>
                                                            <i className="ri-close-circle-line me-1"></i>Ajourner la sélection
                                                        </Button>
                                                    </>
                                                )}
                                                {hasFilterValidation && (
                                                    <Button color="success" size="sm" onClick={handleValiderParFiltre} disabled={isProcessing}>
                                                        <i className="ri-check-double-line me-1"></i>
                                                        Valider tous les pointages
                                                    </Button>
                                                )}
                                                <Button
                                                    color="primary"
                                                    size="sm"
                                                    onClick={handleGenererAttestation}
                                                    disabled={isProcessing || !selectedMois}
                                                    className="ms-auto"
                                                >
                                                    <i className="ri-file-pdf-2-line me-1"></i>Générer Attestation PDF
                                                </Button>
                                            </div>

                                            <TableContainerReactTable
                                                columns={columnsSoumis}
                                                data={soumisData}
                                                isGlobalFilter={false}
                                                customPageSize={20}
                                                divClass="table-responsive table-card mt-1 mb-1"
                                                tableClass="align-middle table-nowrap table-hover"
                                                theadClass="table-light"
                                                SearchPlaceholder="Recherche..."
                                                isServerPagination={true}
                                                serverPagination={pointagesSoumis}
                                                onPageChange={(page: number) => {
                                                    const params: Record<string, string> = { tab: 'attente', page: String(page) };
                                                    if (selectedMois) params.mois = selectedMois;
                                                    Object.entries(selectedFilters).forEach(([k, v]) => {
                                                        if (v) params[k] = v;
                                                    });
                                                    router.get('/chefagence/pointages', params, { preserveState: true, preserveScroll: true });
                                                }}
                                            />
                                        </TabPane>

                                        {/* ─── Onglet Corrections ADP ─── */}
                                        <TabPane tabId="corrige_adp">
                                            <Alert color="warning" className="border-0 mb-3">
                                                <i className="ri-information-line me-2"></i>
                                                Ces pointages ont été corrigés par le CIP après un ajournement DMG.
                                                Vous pouvez accepter la correction (resoumission) ou rejeter le dossier.
                                            </Alert>

                                            <TableContainerReactTable
                                                columns={columnsCorrige}
                                                data={corrigeData}
                                                isGlobalFilter={false}
                                                customPageSize={20}
                                                divClass="table-responsive table-card mt-1 mb-1"
                                                tableClass="align-middle table-nowrap table-hover"
                                                theadClass="table-light"
                                                SearchPlaceholder="Recherche..."
                                                isServerPagination={true}
                                                serverPagination={pointagesCorrigeAdp}
                                                onPageChange={(page: number) => {
                                                    const params: Record<string, string> = { tab: 'corrige_adp', page: String(page) };
                                                    if (selectedMois) params.mois = selectedMois;
                                                    Object.entries(selectedFilters).forEach(([k, v]) => {
                                                        if (v) params[k] = v;
                                                    });
                                                    router.get('/chefagence/pointages', params, { preserveState: true, preserveScroll: true });
                                                }}
                                            />
                                        </TabPane>
                                    </TabContent>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            {/* ═══════════════════════════════════════════
                MODALE — Ajournement Individuel
               ═══════════════════════════════════════════ */}
            <Modal isOpen={ajournerModalOpen} toggle={() => !isProcessing && setAjournerModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setAjournerModalOpen(false)}>
                    <i className="ri-close-circle-line me-2 text-warning"></i>
                    Ajourner le pointage
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        Le pointage retournera au CIP pour correction. Veuillez spécifier le motif.
                    </p>
                    <div className="mb-0">
                        <Label className="form-label">Motif <span className="text-danger">*</span></Label>
                        <Input
                            type="textarea"
                            rows={3}
                            value={ajournerMotif}
                            onChange={(e) => setAjournerMotif(e.target.value)}
                            placeholder="Ex: Absence non justifiée, erreur de saisie..."
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setAjournerModalOpen(false)} disabled={isProcessing}>Fermer</Button>
                    <Button
                        color="warning"
                        onClick={confirmAjourner}
                        disabled={isProcessing || ajournerMotif.length < 5}
                    >
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Ajournement Groupé
               ═══════════════════════════════════════════ */}
            <Modal isOpen={ajournerGroupeModalOpen} toggle={() => !isProcessing && setAjournerGroupeModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setAjournerGroupeModalOpen(false)}>
                    <i className="ri-close-circle-line me-2 text-warning"></i>
                    Ajourner la sélection ({selectedIds.length} pointages)
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        Tous les pointages sélectionnés seront ajournés et retournés au CIP pour correction.
                    </p>
                    <div className="mb-0">
                        <Label className="form-label">Motif <span className="text-danger">*</span></Label>
                        <Input
                            type="textarea"
                            rows={3}
                            value={ajournerGroupeMotif}
                            onChange={(e) => setAjournerGroupeMotif(e.target.value)}
                            placeholder="Motif de l'ajournement groupé..."
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setAjournerGroupeModalOpen(false)} disabled={isProcessing}>Fermer</Button>
                    <Button
                        color="warning"
                        onClick={confirmAjournerGroupe}
                        disabled={isProcessing || ajournerGroupeMotif.length < 5}
                    >
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer l\'ajournement'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Rejet Correction ADP
               ═══════════════════════════════════════════ */}
            <Modal isOpen={rejeterModalOpen} toggle={() => !isProcessing && setRejeterModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setRejeterModalOpen(false)}>
                    <i className="ri-close-circle-line me-2 text-danger"></i>
                    Rejeter la correction
                </ModalHeader>
                <ModalBody>
                    <Alert color="danger" className="border-0 mb-3">
                        <i className="ri-error-warning-line me-2"></i>
                        Le dossier sera renvoyé dans la corbeille "Mes Stagiaires" du CIP pour correction du contrat.
                    </Alert>
                    <p className="text-muted">
                        Êtes-vous sûr de vouloir rejeter cette correction ?
                    </p>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setRejeterModalOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button color="danger" onClick={confirmRejeter} disabled={isProcessing}>
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : 'Confirmer le rejet'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default PointagesIndex;
