import { Head, router, usePage } from '@inertiajs/react';
import React, { useCallback, useEffect, useState } from 'react';
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
    Row,
    Spinner,
} from 'reactstrap';

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

interface DossierItem {
    id: number;
    identifiant: string;
    agence: string;
    source_financement: string;
    nature: string;
    nombre_stagiaires: number;
    montant_total: number;
    statut: string;
}

interface StagiaireRow {
    paiement_id: number;
    created_at: string;
    agence: string;
    entreprise: string;
    source_financement: string;
    type_stage: string;
    numero_aej: string;
    nom_prenoms: string;
    date_naissance: string;
    date_debut: string;
    date_fin: string;
    tresor_pay: string;
    montant: number;
    statut: string;
}

interface PageProps {
    periodeOptions: PeriodeOption[];
    agences: RefItem[];
    entreprises: RefItem[];
    sourcesFinancement: RefItem[];
    typesStage: RefItem[];
    moisActuel: string;
}

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════════════════════ */
const MultiDossierIndex = (props: PageProps) => {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    /* ─── États filtres ─── */
    const [selectedMois, setSelectedMois] = useState(props.moisActuel || '');
    const [selectedTypeTraitement, setSelectedTypeTraitement] = useState('');
    const [selectedAgence, setSelectedAgence] = useState('');
    const [selectedSourceFinancement, setSelectedSourceFinancement] = useState('');
    const [selectedTypeStage, setSelectedTypeStage] = useState('');

    /* ─── États dossiers ─── */
    const [dossiers, setDossiers] = useState<DossierItem[]>([]);
    const [selectedDossierIds, setSelectedDossierIds] = useState<number[]>([]);
    const [isLoadingDossiers, setIsLoadingDossiers] = useState(false);

    /* ─── États stagiaires ─── */
    const [stagiaires, setStagiaires] = useState<StagiaireRow[]>([]);
    const [selectedStagiaireIds, setSelectedStagiaireIds] = useState<number[]>([]);
    const [stagiaireSearch, setStagiaireSearch] = useState('');
    const [stagiairePage, setStagiairePage] = useState(1);
    const [stagiaireTotal, setStagiaireTotal] = useState(0);
    const [stagiaireLoading, setStagiaireLoading] = useState(false);
    const perPage = 10;

    /* ─── États modales ─── */
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [modalAjournerDossierOpen, setModalAjournerDossierOpen] = useState(false);
    const [modalAjournerStagiaireOpen, setModalAjournerStagiaireOpen] = useState(false);
    const [observation, setObservation] = useState('');
    const [motifAjournerDossier, setMotifAjournerDossier] = useState('');
    const [motifAjournerStagiaire, setMotifAjournerStagiaire] = useState('');
    const [processing, setProcessing] = useState(false);

    /* ═══════════════ CHARGEMENT DOSSIERS ═══════════════ */
    const loadDossiers = useCallback(() => {
        setIsLoadingDossiers(true);
        const params = new URLSearchParams();
        if (selectedMois) params.set('mois', selectedMois);
        if (selectedAgence) params.set('agence_id', selectedAgence);
        if (selectedSourceFinancement) params.set('source_financement_id', selectedSourceFinancement);
        if (selectedTypeTraitement) params.set('typetraitement', selectedTypeTraitement);

        fetch(`/dmg/multi-dossier/dossiers?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.json())
            .then((data: DossierItem[]) => {
                setDossiers(data);
                setSelectedDossierIds([]);
                setStagiaires([]);
                setStagiaireTotal(0);
            })
            .catch(() => setDossiers([]))
            .finally(() => setIsLoadingDossiers(false));
    }, [selectedMois, selectedAgence, selectedSourceFinancement, selectedTypeTraitement]);

    useEffect(() => {
        loadDossiers();
    }, [loadDossiers]);

    /* ═══════════════ CHARGEMENT STAGIAIRES ═══════════════ */
    const loadStagiaires = useCallback(() => {
        if (selectedDossierIds.length === 0) {
            setStagiaires([]);
            setStagiaireTotal(0);
            return;
        }

        setStagiaireLoading(true);
        const body = new URLSearchParams();
        body.set('draw', String(Date.now()));
        body.set('start', String((stagiairePage - 1) * perPage));
        body.set('length', String(perPage));
        body.set('search.value', stagiaireSearch);
        selectedDossierIds.forEach((id) => body.append('dossiers[]', String(id)));

        fetch('/dmg/multi-dossier/stagiaires', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: body.toString(),
        })
            .then((r) => r.json())
            .then((res) => {
                setStagiaires(res.data || []);
                setStagiaireTotal(res.recordsFiltered || 0);
            })
            .catch(() => {
                setStagiaires([]);
                setStagiaireTotal(0);
            })
            .finally(() => setStagiaireLoading(false));
    }, [selectedDossierIds, stagiairePage, stagiaireSearch]);

    useEffect(() => {
        loadStagiaires();
    }, [loadStagiaires]);

    /* ═══════════════ SÉLECTION ═══════════════ */
    const toggleDossierSelection = (id: number) => {
        setSelectedDossierIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
        setStagiairePage(1);
    };

    const toggleAllDossiers = () => {
        if (selectedDossierIds.length === dossiers.length) {
            setSelectedDossierIds([]);
        } else {
            setSelectedDossierIds(dossiers.map((d) => d.id));
        }
        setStagiairePage(1);
    };

    const toggleStagiaireSelection = (id: number) => {
        setSelectedStagiaireIds((prev) =>
            prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
        );
    };

    const toggleAllStagiaires = () => {
        if (selectedStagiaireIds.length === stagiaires.length) {
            setSelectedStagiaireIds([]);
        } else {
            setSelectedStagiaireIds(stagiaires.map((s) => s.paiement_id));
        }
    };

    /* ═══════════════ ACTIONS ═══════════════ */
    const handleValiderSelection = () => {
        if (selectedDossierIds.length === 0 || !selectedMois) return;
        setProcessing(true);

        fetch('/dmg/multi-dossier/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: JSON.stringify({
                dossiers: selectedDossierIds,
                mois: selectedMois,
                observation: observation.trim() || null,
            }),
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success) {
                    setModalValiderOpen(false);
                    setObservation('');
                    setSelectedDossierIds([]);
                    loadDossiers();
                    router.reload({ only: [] });
                }
            })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleAjournerDossier = () => {
        if (selectedDossierIds.length === 0 || motifAjournerDossier.trim().length < 5) return;
        setProcessing(true);

        fetch('/dmg/multi-dossier/ajourner-dossier', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: JSON.stringify({
                dossier_id: selectedDossierIds,
                motif: motifAjournerDossier,
                mois: selectedMois,
            }),
        })
            .then((r) => r.json())
            .then(() => {
                setModalAjournerDossierOpen(false);
                setMotifAjournerDossier('');
                setSelectedDossierIds([]);
                loadDossiers();
            })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleAjournerStagiaire = () => {
        if (selectedStagiaireIds.length === 0 || motifAjournerStagiaire.trim().length < 5) return;
        setProcessing(true);

        fetch('/dmg/multi-dossier/ajourner-stagiaire', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: JSON.stringify({
                paiementIds: selectedStagiaireIds,
                motif: motifAjournerStagiaire,
            }),
        })
            .then((r) => r.json())
            .then(() => {
                setModalAjournerStagiaireOpen(false);
                setMotifAjournerStagiaire('');
                setSelectedStagiaireIds([]);
                loadStagiaires();
            })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleGenererPdf = (type: 'paiement' | 'attestations') => {
        if (selectedDossierIds.length === 0) return;
        const url = type === 'paiement'
            ? '/dmg/multi-dossier/generer-pdf-paiement'
            : '/dmg/multi-dossier/generer-pdf-attestations';

        const body = new URLSearchParams();
        selectedDossierIds.forEach((id) => body.append('dossiers[]', String(id)));

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''
                ),
            },
            body: body.toString(),
        })
            .then((r) => {
                if (!r.ok) throw new Error('Erreur');
                return r.blob();
            })
            .then((blob) => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${type}_${new Date().toISOString().slice(0, 10)}.pdf`;
                document.body.appendChild(a);
                a.click();
                a.remove();
            })
            .catch(() => {});
    };

    /* ─── Pagination stagiaires ─── */
    const totalPages = Math.ceil(stagiaireTotal / perPage);

    /* ═══════════════ RENDU ═══════════════ */
    return (
        <React.Fragment>
            <Head title="Gestion des Multi-Dossiers" />
            <div className="page-content">
                <Container fluid>
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

                    {/* ─── Breadcrumb ─── */}
                    <div className="d-flex align-items-center mb-3">
                        <h5 className="mb-0 fw-bold">
                            <i className="ri-folder-shared-line me-2 text-success"></i>
                            Gestion des Multi-Dossiers
                        </h5>
                    </div>

                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom">
                            <h5 className="card-title mb-0">
                                <i className="ri-filter-3-line me-2 text-primary"></i>
                                Filtres et Sélection
                            </h5>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3">
                                {/* ── Filtres ── */}
                                <Col lg={8} xl={9}>
                                    <Row className="g-3">
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">
                                                <i className="ri-calendar-line me-1"></i>Période (Mois)
                                            </Label>
                                            <Input type="select" bsSize="sm" value={selectedMois}
                                                onChange={(e) => setSelectedMois(e.target.value)}>
                                                <option value="">Toutes</option>
                                                {props.periodeOptions.map((p) => (
                                                    <option key={p.id} value={p.code}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">Type Traitement</Label>
                                            <Input type="select" bsSize="sm" value={selectedTypeTraitement}
                                                onChange={(e) => setSelectedTypeTraitement(e.target.value)}>
                                                <option value="">Tout</option>
                                                <option value="DM">DEMARRAGE</option>
                                                <option value="PS">PRESENCE</option>
                                            </Input>
                                        </Col>
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">Agence</Label>
                                            <Input type="select" bsSize="sm" value={selectedAgence}
                                                onChange={(e) => setSelectedAgence(e.target.value)}>
                                                <option value="">Toutes</option>
                                                {props.agences.map((a) => (
                                                    <option key={a.id} value={a.id}>{a.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">Source Financement</Label>
                                            <Input type="select" bsSize="sm" value={selectedSourceFinancement}
                                                onChange={(e) => setSelectedSourceFinancement(e.target.value)}>
                                                <option value="">Toutes</option>
                                                {props.sourcesFinancement.map((sf) => (
                                                    <option key={sf.id} value={sf.id}>{sf.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">Type de Stage</Label>
                                            <Input type="select" bsSize="sm" value={selectedTypeStage}
                                                onChange={(e) => setSelectedTypeStage(e.target.value)}>
                                                <option value="">Tous</option>
                                                {props.typesStage.map((ts) => (
                                                    <option key={ts.id} value={ts.id}>{ts.nom}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={4}>
                                            <Label className="form-label fw-semibold fs-12 text-muted">
                                                <i className="ri-folder-2-line me-1 text-warning"></i>Dossiers disponibles
                                            </Label>
                                            <div className="border rounded p-2 bg-light" style={{ minHeight: 38 }}>
                                                {isLoadingDossiers ? (
                                                    <Spinner size="sm" color="primary" />
                                                ) : dossiers.length === 0 ? (
                                                    <small className="text-muted">Aucun dossier</small>
                                                ) : (
                                                    <small className="text-muted">
                                                        <Badge color="info" pill>{dossiers.length}</Badge> dossier(s) disponible(s)
                                                    </small>
                                                )}
                                            </div>
                                        </Col>
                                    </Row>
                                </Col>

                                {/* ── Actions ── */}
                                <Col lg={4} xl={3}>
                                    <Label className="form-label fw-semibold fs-12 text-muted d-block">
                                        <i className="ri-settings-3-line me-1 text-success"></i>Actions
                                    </Label>
                                    <div className="d-flex flex-column gap-2">
                                        <Button color="success" block size="sm"
                                            disabled={selectedDossierIds.length === 0 || !selectedMois || processing}
                                            onClick={() => setModalValiderOpen(true)}>
                                            <i className="ri-check-double-line me-1"></i>Valider sélection
                                        </Button>

                                        {modalValiderOpen && (
                                            <small className="text-danger">
                                                {selectedDossierIds.length === 0 && 'Sélectionnez au moins un dossier'}
                                                {!selectedMois && 'Choisissez une période'}
                                            </small>
                                        )}

                                        <Button color="warning" block size="sm"
                                            disabled={selectedDossierIds.length === 0}
                                            onClick={() => setModalAjournerDossierOpen(true)}>
                                            <i className="ri-close-circle-line me-1"></i>Retirer le dossier
                                        </Button>

                                        <Button color="warning" block size="sm"
                                            disabled={selectedStagiaireIds.length === 0}
                                            onClick={() => setModalAjournerStagiaireOpen(true)}>
                                            <i className="ri-user-unfollow-line me-1"></i>Retirer Stagiaire(s) ({selectedStagiaireIds.length})
                                        </Button>

                                        <div className="d-flex gap-1">
                                            <Button color="info" className="flex-fill" size="sm"
                                                disabled={selectedDossierIds.length === 0}
                                                onClick={() => handleGenererPdf('paiement')}>
                                                <i className="ri-file-text-line me-1"></i>État de Paiement
                                            </Button>
                                            <Button color="info" className="flex-fill" size="sm"
                                                disabled={selectedDossierIds.length === 0}
                                                onClick={() => handleGenererPdf('attestations')}>
                                                <i className="ri-file-shield-line me-1"></i>ADD / ADP
                                            </Button>
                                        </div>

                                        {selectedDossierIds.length > 0 && (
                                            <div className="text-center p-2 bg-light rounded">
                                                <small className="text-muted">
                                                    <i className="ri-information-line me-1"></i>
                                                    {selectedDossierIds.length} dossier(s) sélectionné(s)
                                                </small>
                                            </div>
                                        )}
                                    </div>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {/* ─── Sélection dossiers ── */}
                    {selectedDossierIds.length > 0 && (
                        <Card className="shadow-sm border-0 mt-3">
                            <CardBody className="py-2">
                                <div className="d-flex flex-wrap gap-1 align-items-center">
                                    <small className="text-muted me-2 fw-semibold">
                                        <i className="ri-folder-2-line me-1"></i>Dossiers sélectionnés :
                                    </small>
                                    {selectedDossierIds.map((id) => {
                                        const d = dossiers.find((x) => x.id === id);
                                        return d ? (
                                            <Badge key={id} color="primary" pill className="fs-12 py-2 px-2"
                                                style={{ cursor: 'pointer' }}
                                                onClick={() => toggleDossierSelection(id)}>
                                                <i className="ri-folder-2-fill me-1"></i>
                                                {d.identifiant}
                                                <i className="ri-close-circle-fill ms-1"></i>
                                            </Badge>
                                        ) : null;
                                    })}
                                </div>
                            </CardBody>
                        </Card>
                    )}

                    {/* ─── Liste des Stagiaires ── */}
                    {selectedDossierIds.length > 0 && (
                        <Card className="shadow-sm border-0 mt-3">
                            <CardHeader className="bg-transparent d-flex justify-content-between align-items-center">
                                <h5 className="mb-0 fw-semibold">
                                    <i className="ri-user-search-line me-2 text-info"></i>
                                    Liste des Stagiaires
                                    <Badge color="info" pill className="ms-2">{stagiaireTotal}</Badge>
                                </h5>
                                <Input type="text" bsSize="sm" placeholder="Rechercher..."
                                    style={{ maxWidth: 250 }}
                                    value={stagiaireSearch}
                                    onChange={(e) => { setStagiaireSearch(e.target.value); setStagiairePage(1); }} />
                            </CardHeader>
                            <CardBody className="p-0">
                                {stagiaireLoading ? (
                                    <div className="d-flex justify-content-center py-5">
                                        <Spinner color="info" />
                                    </div>
                                ) : stagiaires.length === 0 ? (
                                    <div className="text-center py-5 text-muted">
                                        <i className="ri-inbox-line fs-1 d-block mb-2"></i>
                                        Aucun stagiaire trouvé
                                    </div>
                                ) : (
                                    <div className="table-responsive">
                                        <table className="table table-striped table-hover align-middle mb-0">
                                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                <tr>
                                                    <th style={{ width: 40 }}>
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
                                                    <th>Montant</th>
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
                                                        <td className="text-truncate" style={{ maxWidth: 150 }}>{s.entreprise}</td>
                                                        <td>{s.source_financement}</td>
                                                        <td className="text-truncate" style={{ maxWidth: 120 }}>{s.type_stage}</td>
                                                        <td className="text-muted">{s.numero_aej}</td>
                                                        <td className="fw-semibold">{s.nom_prenoms}</td>
                                                        <td className="fs-12">{s.date_naissance}</td>
                                                        <td className="fs-12">{s.date_debut}</td>
                                                        <td className="fs-12">{s.date_fin}</td>
                                                        <td className="text-muted">{s.tresor_pay}</td>
                                                        <td className="fw-bold text-success">
                                                            {Number(s.montant || 0).toLocaleString('fr-FR')} FCFA
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}

                                {/* ── Pagination ── */}
                                {totalPages > 1 && (
                                    <div className="d-flex justify-content-between align-items-center p-3 border-top">
                                        <small className="text-muted">
                                            Affichage de {((stagiairePage - 1) * perPage) + 1} à {Math.min(stagiairePage * perPage, stagiaireTotal)} sur {stagiaireTotal}
                                        </small>
                                        <div className="d-flex gap-1">
                                            <Button size="sm" color="light" disabled={stagiairePage <= 1}
                                                onClick={() => setStagiairePage((p) => p - 1)}>
                                                <i className="ri-arrow-left-s-line"></i>
                                            </Button>
                                            {Array.from({ length: Math.min(totalPages, 5) }, (_, i) => {
                                                const page = i + 1;
                                                return (
                                                    <Button key={page} size="sm"
                                                        color={page === stagiairePage ? 'primary' : 'light'}
                                                        onClick={() => setStagiairePage(page)}>
                                                        {page}
                                                    </Button>
                                                );
                                            })}
                                            <Button size="sm" color="light" disabled={stagiairePage >= totalPages}
                                                onClick={() => setStagiairePage((p) => p + 1)}>
                                                <i className="ri-arrow-right-s-line"></i>
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardBody>
                        </Card>
                    )}
                </Container>
            </div>

            {/* ═══════ MODALES ═══════ */}

            {/* ─── Valider sélection ─── */}
            <Modal isOpen={modalValiderOpen} toggle={() => !processing && setModalValiderOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalValiderOpen(false)} className="bg-success text-white">
                    <i className="ri-check-double-line me-2"></i>Validation de la Sélection
                </ModalHeader>
                <ModalBody>
                    <Alert color="info" className="mb-3">
                        Vous êtes sur le point de créer un Multi-Dossier regroupant{' '}
                        <strong className="text-primary">{selectedDossierIds.length}</strong> dossier(s).
                    </Alert>
                    <Label className="form-label fw-semibold">Observation (optionnelle)</Label>
                    <Input type="textarea" rows={4} value={observation}
                        onChange={(e) => setObservation(e.target.value)}
                        placeholder="Note ou observation concernant ce Multi-Dossier..."
                        disabled={processing} />
                    <small className="text-muted">Cette observation sera visible dans l'historique.</small>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalValiderOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="success" onClick={handleValiderSelection}
                        disabled={processing || selectedDossierIds.length === 0 || !selectedMois}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : <><i className="ri-check-double-line me-1"></i>Valider</>}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ─── Ajourner dossier ─── */}
            <Modal isOpen={modalAjournerDossierOpen} toggle={() => !processing && setModalAjournerDossierOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalAjournerDossierOpen(false)}>
                    <i className="ri-close-circle-line me-2 text-warning"></i>Retirer le dossier
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">
                        Les {selectedDossierIds.length} dossier(s) sélectionné(s) seront retirés et leurs paiements remis en attente.
                    </p>
                    <Label className="form-label fw-semibold">Motif <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={3} value={motifAjournerDossier}
                        onChange={(e) => setMotifAjournerDossier(e.target.value)}
                        placeholder="Motif du retrait..."
                        disabled={processing} />
                    {motifAjournerDossier.trim().length > 0 && motifAjournerDossier.trim().length < 5 && (
                        <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerDossierOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="warning" onClick={handleAjournerDossier}
                        disabled={processing || motifAjournerDossier.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ─── Ajourner stagiaire ─── */}
            <Modal isOpen={modalAjournerStagiaireOpen} toggle={() => !processing && setModalAjournerStagiaireOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalAjournerStagiaireOpen(false)}>
                    <i className="ri-user-unfollow-line me-2 text-warning"></i>Retirer le(s) stagiaire(s)
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">
                        {selectedStagiaireIds.length} stagiaire(s) seront retirés et remis en attente de traitement.
                    </p>
                    <Label className="form-label fw-semibold">Motif <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={3} value={motifAjournerStagiaire}
                        onChange={(e) => setMotifAjournerStagiaire(e.target.value)}
                        placeholder="Motif du retrait..."
                        disabled={processing} />
                    {motifAjournerStagiaire.trim().length > 0 && motifAjournerStagiaire.trim().length < 5 && (
                        <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerStagiaireOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="warning" onClick={handleAjournerStagiaire}
                        disabled={processing || motifAjournerStagiaire.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default MultiDossierIndex;
