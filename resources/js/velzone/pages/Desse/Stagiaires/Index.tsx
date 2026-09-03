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
    doublonCle?: string | null;
    groupe?: { cle: string; nb_profils: number } | null;
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
    if (!dateStr) {
return '-';
}

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

const formatDateTimeFr = (dateStr: string | null | undefined) => {
    if (!dateStr) {
        return '-';
    }

    try {
        return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
            + ' ' + new Date(dateStr).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return dateStr;
    }
};

const corbeilleLabel = (code: string) => {
    const map: Record<string, string> = {
        cip_ajourne_desse: "Ajourné par la DESSE — en correction CIP",
        desse_retour_agence: 'Retour agence (à traiter par la DESSE)',
        dmg_attente_paiement_presence: 'DMG — attente paiement présence',
        dmg_attente_paiement_demarrage: 'DMG — attente paiement démarrage',
    };

    return map[code] || code;
};

/* ─── Rendu de la timeline d'historique (Retour Chef d'Agence) ─── */
const evenementStyle = (type: string) => {
    switch (type) {
        case 'DESSE_RETOUR_VALIDATION':
            return { badge: 'success', libelle: 'Validé par la DESSE', icone: 'ri-check-line', pastille: { borderColor: '#10b981', color: '#10b981' } };
        case 'DESSE_RETOUR_AJOURNEMENT':
            return { badge: 'warning', libelle: 'Renvoyé au CIP par la DESSE', icone: 'ri-arrow-go-back-line', pastille: { borderColor: '#f59e0b', color: '#f59e0b' } };
        default:
            return { badge: 'secondary', libelle: 'Traitement du dossier', icone: 'ri-file-transfer-line', pastille: { borderColor: '#6c757d', color: '#6c757d' } };
    }
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const DesseStagiairesIndex = (props: PageProps) => {
    const {
        tab,
        typeDoublon,
        doublonCle = null,
        groupe = null,
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
    const [currentGroupeCle, setCurrentGroupeCle] = useState<string | null>(doublonCle || null);
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

    /* ─── Modale Traitement Retour Chef d'Agence (validé/ajourné + motif) ─── */
    const [retourModalOpen, setRetourModalOpen] = useState(false);
    const [selectedRetour, setSelectedRetour] = useState<any>(null);
    const [retourDecision, setRetourDecision] = useState('valide');
    const [retourMotif, setRetourMotif] = useState('');

    /* ─── Modale Détails (Doublons Traités) ─── */
    const [detailModalOpen, setDetailModalOpen] = useState(false);
    const [selectedDecision, setSelectedDecision] = useState<any>(null);

    const openDetailModal = (row: any) => {
        setSelectedDecision(row);
        setDetailModalOpen(true);
    };

    /* ─── Modale Historique (Retour Chef d'Agence) ─── */
    const [historiqueModalOpen, setHistoriqueModalOpen] = useState(false);
    const [historiqueLoading, setHistoriqueLoading] = useState(false);
    const [historiqueData, setHistoriqueData] = useState<{
        instance_id: number;
        beneficiaire?: { nom?: string; prenoms?: string };
        corbeille_actuelle?: string;
        evenements: any[];
    } | null>(null);

    const openHistoriqueModal = async (row: any) => {
        setHistoriqueModalOpen(true);
        setHistoriqueLoading(true);
        setHistoriqueData(null);

        try {
            const response = await fetch(`/desse/stagiaires/retour-agence/${row.id}/historique`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'Erreur lors du chargement de l\'historique.');
            }

            setHistoriqueData(payload);
        } catch (error) {
            alert(error instanceof Error ? error.message : "Impossible de charger l'historique de ce dossier.");
            setHistoriqueModalOpen(false);
        } finally {
            setHistoriqueLoading(false);
        }
    };

    const closeHistoriqueModal = () => {
        if (!historiqueLoading) {
            setHistoriqueModalOpen(false);
            setHistoriqueData(null);
        }
    };

    /* ─── Navigation / Filtres ─── */
    /** Paramètres d'URL communs (onglet + type de doublon + clé de groupe + filtres). */
    const buildParams = useCallback(
        (activeTab: string, activeTypeDoublon: string, currentFilters: typeof selectedFilters, groupeCle?: string) => {
            const params: Record<string, string> = { tab: activeTab };

            if (activeTab === 'doublons') {
                params.type_doublon = activeTypeDoublon;

                if (groupeCle) {
                    params.doublon_cle = groupeCle;
                }
            }

            Object.entries(currentFilters).forEach(([key, val]) => {
                if (val) {
                    params[key] = val;
                }
            });

            return params;
        },
        [],
    );

    const applyNav = useCallback(
        (activeTab: string, activeTypeDoublon: string, currentFilters: typeof selectedFilters, groupeCle?: string) => {
            router.get('/desse/stagiaires', buildParams(activeTab, activeTypeDoublon, currentFilters, groupeCle), {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [buildParams],
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
            setCurrentGroupeCle(null);
            applyNav('doublons', t, selectedFilters);
        }
    };

    const openGroupe = useCallback(
        (groupe: any) => {
            setCurrentGroupeCle(groupe.cle);
            applyNav('doublons', currentTypeDoublon, selectedFilters, groupe.cle);
        },
        [applyNav, currentTypeDoublon, selectedFilters],
    );

    const backToGroupes = useCallback(
        () => {
            setCurrentGroupeCle(null);
            applyNav('doublons', currentTypeDoublon, selectedFilters);
        },
        [applyNav, currentTypeDoublon, selectedFilters],
    );

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
        if (!selectedInstance || isProcessing) {
return;
}

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

    /* ─── Actions : Retour Chef d'Agence ───
       Portage du traitement legacy (étape 7/8, etape_next=9) : choix validé/ajourné + motif,
       enregistrés avant la transition (dossier → file DMG ou retour CIP pour nouvelle correction). */
    const openRetourModal = (row: any) => {
        setSelectedRetour(row);
        setRetourDecision('valide');
        setRetourMotif('');
        setRetourModalOpen(true);
    };

    const confirmRetour = () => {
        if (!selectedRetour || isProcessing) {
return;
}

        if (retourDecision === 'ajourne' && retourMotif.trim().length < 5) {
            alert('Le motif est obligatoire (5 caractères minimum) pour renvoyer le dossier au CIP.');

            return;
        }

        setIsProcessing(true);
        router.post(`/desse/stagiaires/retour-agence/${selectedRetour.id}/valider`, {
            decision: retourDecision,
            motif: retourMotif.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setRetourModalOpen(false);
                setSelectedRetour(null);
                setRetourDecision('valide');
                setRetourMotif('');
            },
            onFinish: () => setIsProcessing(false),
        });
    };

    /* ─── Actions : Doublons à Traiter ─── */
    const openDoublonModal = (row: any) => {
        setSelectedInstance(row);
        setDecisionDoublon('non_avere');
        setMotifDoublon('');
        setDoublonModalOpen(true);
    };

    const confirmTraiterDoublon = () => {
        if (!selectedInstance || isProcessing) {
return;
}

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
            { header: 'CIP en charge', cell: (cell: any) => cell.row.original.stage?.conseiller?.nom_complet || '-' },
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

    const doublonLabel = useMemo(
        () => doublonTypes.find((d) => d.value === currentTypeDoublon)?.label || currentTypeDoublon,
        [doublonTypes, currentTypeDoublon],
    );

    /* Vue « profils du groupe » : une ligne par dossier partageant la même clé de doublon. */
    const columnsGroupeProfils = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Demandeur',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;

                    return (
                        <div>
                            <h5 className="fs-14 mb-1 text-danger">{b?.nom} {b?.prenoms}</h5>
                            <p className="text-muted mb-0">Né(e) le {formatDateFr(b?.date_naissance)} · {b?.email || 'email non renseigné'}</p>
                        </div>
                    );
                },
            },
            { header: 'Matricule AEJ', cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_aej || '-' },
            { header: "Pièce d'identité", cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_piece_identite || '-' },
            { header: 'N° CMU', cell: (cell: any) => cell.row.original.stage?.beneficiaire?.numero_cmu || '-' },
            { header: 'Contacts', cell: (cell: any) => cell.row.original.stage?.beneficiaire?.telephone_principal || '-' },
            { header: 'Agence', cell: (cell: any) => cell.row.original.stage?.agence?.nom || '-' },
            {
                header: 'Situation',
                cell: (cell: any) => {
                    const s = cell.row.original.stage?.situation_stage;

                    return s ? <Badge color="success" className="text-capitalize">{s}</Badge> : '-';
                },
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

    /* Vue « groupes » (maître) : une ligne par clé de doublon avec ses profils en aperçu. */
    const columnsGroupes = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Clé du doublon',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const typeFound = doublonTypes.find((d) => d.value === currentTypeDoublon);

                    return (
                        <div>
                            <span className="badge bg-warning-subtle text-warning fw-semibold fs-13">{row.cle}</span>
                            <p className="text-muted small mb-0 mt-1">Clé normalisée : {row.cle?.toLowerCase()} · {typeFound?.label || currentTypeDoublon}</p>
                        </div>
                    );
                },
            },
            {
                header: 'Profils',
                cell: (cell: any) => {
                    const row = cell.row.original;

                    return (
                        <div className="d-flex flex-wrap gap-1">
                            {(row.profils || []).map((p: any) => (
                                <span key={p.id} className="badge bg-soft-dark text-dark border rounded-pill">
                                    {p.stage?.beneficiaire?.nom} {p.stage?.beneficiaire?.prenoms}
                                </span>
                            ))}
                            {row.nb_profils > (row.profils || []).length && (
                                <span className="badge bg-light text-muted border rounded-pill">+{row.nb_profils - (row.profils || []).length}</span>
                            )}
                        </div>
                    );
                },
            },
            { header: 'Nb profils', cell: (cell: any) => <Badge color="info" pill>{cell.row.original.nb_profils}</Badge>, size: 90 },
            { header: 'N° AEJ (aperçu)', cell: (cell: any) => cell.row.original.profils?.[0]?.stage?.beneficiaire?.numero_aej || '-' },
            { header: 'Agence (aperçu)', cell: (cell: any) => cell.row.original.profils?.[0]?.stage?.agence?.nom || '-' },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <Button color="info" size="sm" outline onClick={() => openGroupe(cell.row.original)}>
                        <i className="ri-eye-line align-bottom me-1"></i> Voir les profils
                    </Button>
                ),
                size: 150,
            },
        ],
        [doublonTypes, currentTypeDoublon, openGroupe],
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
                    <div className="d-flex gap-2">
                        <Button color="success" size="sm" outline onClick={() => openRetourModal(cell.row.original)} disabled={isProcessing}>
                            <i className="ri-send-plane-line align-bottom me-1"></i> Valider / Renvoyer
                        </Button>
                        <Button color="info" size="sm" outline onClick={() => openHistoriqueModal(cell.row.original)}>
                            <i className="ri-history-line align-bottom me-1"></i> Détails
                        </Button>
                    </div>
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

                    return (
                        <div>
                            <h5 className="fs-14 mb-1">{b?.nom} {b?.prenoms}</h5>
                            <p className="text-muted mb-0">{b?.numero_aej || '-'}</p>
                        </div>
                    );
                },
            },
            {
                header: 'Type de doublon',
                cell: (cell: any) => {
                    const found = doublonTypes.find((d) => d.value === cell.row.original.type_doublon);

                    return found?.label || cell.row.original.type_doublon;
                },
            },
            { header: 'N° AEJ', cell: (cell: any) => cell.row.original.instance?.stage?.beneficiaire?.numero_aej || '-' },
            { header: 'Téléphone', cell: (cell: any) => cell.row.original.instance?.stage?.beneficiaire?.telephone_principal || '-' },
            {
                header: 'N° Wave / Trésor Money',
                cell: (cell: any) => {
                    const b = cell.row.original.instance?.stage?.beneficiaire;

                    return b?.numero_tresor_money || b?.numero_wave || '-';
                },
            },
            { header: 'Source financement', cell: (cell: any) => cell.row.original.instance?.stage?.source_financement?.nom || '-' },
            { header: 'Type stage', cell: (cell: any) => cell.row.original.instance?.stage?.type_stage?.nom || '-' },
            { header: 'Agence', cell: (cell: any) => cell.row.original.instance?.stage?.agence?.nom || '-' },
            { header: 'CIP en charge', cell: (cell: any) => cell.row.original.instance?.stage?.conseiller?.nom_complet || '-' },
            {
                header: 'Situation',
                cell: (cell: any) => {
                    const situation = cell.row.original.instance?.stage?.situation_stage;

                    return situation ? <Badge color="info" className="text-capitalize">{situation}</Badge> : '-';
                },
            },
            { header: 'Décision', cell: (cell: any) => decisionBadge(cell.row.original.decision) },
            { header: 'Motif', cell: (cell: any) => <span className="text-muted" title={cell.row.original.motif}>{(cell.row.original.motif || '').length > 60 ? (cell.row.original.motif || '').slice(0, 60) + '…' : cell.row.original.motif}</span> },
            { header: 'Traité par', cell: (cell: any) => cell.row.original.decide_par?.name || '-' },
            { header: 'Date', cell: (cell: any) => formatDateFr(cell.row.original.decide_le) },
            {
                header: 'Actions',
                cell: (cell: any) => (
                    <Button color="info" size="sm" outline onClick={() => openDetailModal(cell.row.original)}>
                        <i className="ri-eye-line align-bottom me-1"></i> Détails
                    </Button>
                ),
            },
        ],
        [doublonTypes],
    );

    const currentColumns = useMemo(() => {
        switch (currentTab) {
            case 'doublons': return currentGroupeCle ? columnsGroupeProfils : columnsGroupes;
            case 'retour_chefagence': return columnsRetourChefAgence;
            case 'doublons_traites': return columnsDoublonsTraites;
            default: return columnsAttente;
        }
    }, [currentTab, currentGroupeCle, columnsAttente, columnsGroupes, columnsGroupeProfils, columnsRetourChefAgence, columnsDoublonsTraites]);

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

                            {/* ─── Contexte de groupe (vue « profils du groupe ») ─── */}
                            {currentTab === 'doublons' && currentGroupeCle && (
                                <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-3 p-3 mb-3 bg-light-subtle">
                                    <div className="d-flex flex-wrap align-items-center gap-2">
                                        <Button color="light" size="sm" outline onClick={backToGroupes}>
                                            <i className="ri-arrow-left-line align-bottom me-1"></i> Groupes
                                        </Button>
                                        <div className="d-flex align-items-center gap-2">
                                            <span className="badge bg-warning-subtle text-warning fw-semibold fs-14 py-2 px-3">{currentGroupeCle}</span>
                                            <Badge color="orange" className="text-uppercase">{doublonLabel}</Badge>
                                        </div>
                                    </div>
                                    <button type="button" className="btn btn-sm rounded-pill px-3" style={{ backgroundColor: '#e87853', color: '#fff' }}>
                                        <i className="ri-group-line me-1"></i>
                                        {groupe?.nb_profils ?? data?.total ?? 0} profils
                                    </button>
                                </div>
                            )}

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
                                    const params = buildParams(currentTab, currentTypeDoublon, selectedFilters, currentGroupeCle || undefined);
                                    params.page = String(page);
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
                MODALE — Traitement Retour Chef d'Agence
               ═══════════════════════════════════════════ */}
            <Modal isOpen={retourModalOpen} toggle={() => !isProcessing && setRetourModalOpen(false)} centered>
                <ModalHeader toggle={() => !isProcessing && setRetourModalOpen(false)}>
                    Traitement du dossier retourné par le Chef d'Agence
                </ModalHeader>
                <ModalBody>
                    {selectedRetour && (
                        <p className="fw-medium mb-3">
                            Dossier : {selectedRetour.stage?.beneficiaire?.nom} {selectedRetour.stage?.beneficiaire?.prenoms}
                            <span className="text-muted fw-normal"> — {selectedRetour.stage?.entreprise?.raison_sociale || 'Entreprise inconnue'}</span>
                        </p>
                    )}
                    <div className="mb-3">
                        <Label className="form-label">Décision</Label>
                        <div className="d-flex flex-column gap-2">
                            <div
                                className={`border rounded-3 p-2 px-3 d-flex align-items-start gap-2 ${retourDecision === 'valide' ? 'border-success bg-success-subtle' : ''}`}
                                style={{ cursor: 'pointer' }}
                                onClick={() => setRetourDecision('valide')}
                            >
                                <Input type="radio" name="retour_decision" value="valide" checked={retourDecision === 'valide'} onChange={() => setRetourDecision('valide')} />
                                <div>
                                    <span className="fw-medium d-block">Valider et transmettre à la DMG</span>
                                    <small className="text-muted">Le dossier est libéré du circuit doublon et rejoint la file DMG (présence ou démarrage selon le cycle).</small>
                                </div>
                            </div>
                            <div
                                className={`border rounded-3 p-2 px-3 d-flex align-items-start gap-2 ${retourDecision === 'ajourne' ? 'border-warning bg-warning-subtle' : ''}`}
                                style={{ cursor: 'pointer' }}
                                onClick={() => setRetourDecision('ajourne')}
                            >
                                <Input type="radio" name="retour_decision" value="ajourne" checked={retourDecision === 'ajourne'} onChange={() => setRetourDecision('ajourne')} />
                                <div>
                                    <span className="fw-medium d-block">Renvoyer au CIP pour nouvelle correction</span>
                                    <small className="text-muted">Le dossier reste dans le circuit retour ; le motif ci-dessous est obligatoire.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="mb-0">
                        <Label className="form-label">Motif{retourDecision === 'ajourne' && <span className="text-danger"> *</span>}</Label>
                        <Input
                            type="textarea"
                            rows={3}
                            placeholder={retourDecision === 'ajourne' ? 'Ex : Le numéro de pièce ne correspond pas au dossier d\'origine…' : 'Motif facultatif pour l\'historique du traitement'}
                            value={retourMotif}
                            onChange={(e) => setRetourMotif(e.target.value)}
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setRetourModalOpen(false)} disabled={isProcessing}>Annuler</Button>
                    <Button
                        color={retourDecision === 'ajourne' ? 'warning' : 'success'}
                        onClick={confirmRetour}
                        disabled={isProcessing || (retourDecision === 'ajourne' && retourMotif.trim().length < 5)}
                    >
                        {isProcessing ? <><Spinner size="sm" className="me-2" />Traitement...</> : retourDecision === 'ajourne' ? 'Renvoyer au CIP' : 'Valider et transmettre à la DMG'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE — Historique (Retour Chef d'Agence)
               ═══════════════════════════════════════════ */}
            <Modal isOpen={historiqueModalOpen} toggle={closeHistoriqueModal} centered size="lg">
                <ModalHeader toggle={closeHistoriqueModal}>
                    <i className="ri-history-line me-2 text-info"></i>
                    Historique du dossier retourné par le Chef d'Agence
                </ModalHeader>
                <ModalBody>
                    {historiqueLoading ? (
                        <div className="text-center py-5">
                            <Spinner color="info" />
                            <p className="text-muted mt-2 mb-0">Chargement de l'historique…</p>
                        </div>
                    ) : historiqueData ? (
                        <React.Fragment>
                            {/* En-tête dossier */}
                            <div className="border rounded p-3 mb-3 bg-light-subtle">
                                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <p className="mb-0 fw-medium">
                                            <i className="ri-user-3-line me-1"></i>
                                            {historiqueData.beneficiaire?.nom || '-'} {historiqueData.beneficiaire?.prenoms || ''}
                                        </p>
                                        <small className="text-muted">Dossier n° {historiqueData.instance_id}</small>
                                    </div>
                                    <div className="text-end">
                                        <small className="text-muted d-block">Situation actuelle</small>
                                        <Badge color="warning" className="text-capitalize">
                                            {corbeilleLabel(historiqueData.corbeille_actuelle || '')}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            {historiqueData.evenements.length === 0 ? (
                                <p className="text-muted text-center py-4 mb-0">
                                    <i className="ri-information-line me-1"></i>
                                    Aucun traitement enregistré pour ce dossier.
                                </p>
                            ) : (
                                <div className="position-relative">
                                    {/* Ligne verticale de la timeline */}
                                    <div className="position-absolute top-0 bottom-0 start-1" style={{ width: 2, backgroundColor: '#e9ebec' }}></div>
                                    {historiqueData.evenements.map((ev: any) => (
                                        <div className="d-flex gap-3 mb-3 position-relative" key={ev.id}>
                                            {/* Pastille */}
                                            <div
                                                className="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                style={{ width: 26, height: 26, zIndex: 1, backgroundColor: '#fff', border: '2px solid', ...evenementStyle(ev.type).pastille }}
                                            >
                                                <i className={evenementStyle(ev.type).icone} style={{ fontSize: 12 }}></i>
                                            </div>
                                            {/* Contenu */}
                                            <div className="flex-grow-1 border rounded-3 p-3 bg-white">
                                                <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                    <div>
                                                        <Badge color={evenementStyle(ev.type).badge} className="me-2">{evenementStyle(ev.type).libelle}</Badge>
                                                        {ev.etape_cible?.nom && ev.etape_cible?.code !== 'CIP_AJOURNE_DESSE' && (
                                                            <span className="text-muted small">
                                                                <i className="ri-arrow-right-line me-1"></i>{ev.etape_cible.nom}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <small className="text-muted">
                                                        <i className="ri-time-line me-1"></i>
                                                        {formatDateTimeFr(ev.survenu_le)} · {ev.auteur || 'Utilisateur inconnu'}
                                                    </small>
                                                </div>
                                                {ev.type === 'DESSE_RETOUR_AJOURNEMENT' && (
                                                    <p className="mb-0 mt-2 small">
                                                        <strong className="text-warning">Motif du renvoi au CIP :</strong>{' '}
                                                        {ev.donnees?.motif || '-'}
                                                    </p>
                                                )}
                                                {ev.type === 'DESSE_RETOUR_VALIDATION' && (
                                                    <p className="mb-0 mt-2 small">
                                                        <strong className="text-success">Décision :</strong> dossier validé et transmis à la DMG
                                                        {ev.donnees?.motif ? ` — ${ev.donnees.motif}` : ''}
                                                    </p>
                                                )}
                                                {ev.type === 'MIGRATION_STATUT' && (ev.donnees?.commentaire || ev.donnees?.description) && (
                                                    <p className="mb-0 mt-2 small text-muted">
                                                        {ev.donnees?.commentaire || ev.donnees?.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </React.Fragment>
                    ) : null}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={closeHistoriqueModal} disabled={historiqueLoading}>Fermer</Button>
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

            {/* ═══════════════════════════════════════════
                MODALE — Détails (Doublons Traités)
               ═══════════════════════════════════════════ */}
            <Modal isOpen={detailModalOpen} toggle={() => setDetailModalOpen(false)} centered size="lg">
                <ModalHeader toggle={() => setDetailModalOpen(false)}>
                    <i className="ri-information-line me-2 text-info"></i>
                    Détails du doublon traité
                </ModalHeader>
                <ModalBody>
                    {selectedDecision && (() => {
                        const stage = selectedDecision.instance?.stage;
                        const b = stage?.beneficiaire;

                        return (
                            <Row className="g-4">
                                {/* Identité */}
                                <Col md={6}>
                                    <div className="border-start border-3 border-primary ps-3">
                                        <h6 className="text-primary fw-bold mb-3">
                                            <i className="ri-user-3-line me-1" />Identité
                                        </h6>
                                        <p className="mb-1"><strong>Nom :</strong> {b?.nom}</p>
                                        <p className="mb-1"><strong>Prénoms :</strong> {b?.prenoms}</p>
                                        <p className="mb-1"><strong>N° AEJ :</strong> {b?.numero_aej || '-'}</p>
                                        <p className="mb-1"><strong>Téléphone :</strong> {b?.telephone_principal || '-'}</p>
                                        <p className="mb-1"><strong>N° Pièce d'identité :</strong> {b?.numero_piece_identite || '-'}</p>
                                        <p className="mb-1"><strong>N° CMU :</strong> {b?.numero_cmu || '-'}</p>
                                    </div>
                                </Col>

                                {/* Paiement */}
                                <Col md={6}>
                                    <div className="border-start border-3 border-danger ps-3">
                                        <h6 className="text-danger fw-bold mb-3">
                                            <i className="ri-bank-card-line me-1" />Paiement
                                        </h6>
                                        <p className="mb-1"><strong>Type paiement :</strong> {b?.type_paiement?.nom || '-'}</p>
                                        <p className="mb-1">
                                            <strong>N° Wave / Trésor Money :</strong>{' '}
                                            {b?.numero_tresor_money || b?.numero_wave || '-'}
                                        </p>
                                    </div>
                                </Col>

                                {/* Stage */}
                                <Col md={6}>
                                    <div className="border-start border-3 border-success ps-3">
                                        <h6 className="text-success fw-bold mb-3">
                                            <i className="ri-briefcase-line me-1" />Stage
                                        </h6>
                                        <p className="mb-1"><strong>Entreprise :</strong> {stage?.entreprise?.raison_sociale || '-'}</p>
                                        <p className="mb-1"><strong>Agence :</strong> {stage?.agence?.nom || '-'}</p>
                                        <p className="mb-1"><strong>CIP en charge :</strong> {stage?.conseiller?.nom_complet || '-'}</p>
                                        <p className="mb-1"><strong>Type de stage :</strong> {stage?.type_stage?.nom || '-'}</p>
                                        <p className="mb-1"><strong>Financement :</strong> {stage?.source_financement?.nom || '-'}</p>
                                        <p className="mb-1"><strong>Situation :</strong> {stage?.situation_stage || '-'}</p>
                                        <p className="mb-1"><strong>Date début :</strong> {formatDateFr(stage?.date_debut)}</p>
                                        <p className="mb-1"><strong>Date fin :</strong> {formatDateFr(stage?.date_fin_prevue)}</p>
                                    </div>
                                </Col>

                                {/* Décision */}
                                <Col md={6}>
                                    <div className="border-start border-3 border-warning ps-3">
                                        <h6 className="text-warning fw-bold mb-3">
                                            <i className="ri-file-list-3-line me-1" />Décision
                                        </h6>
                                        <p className="mb-1"><strong>Type de doublon :</strong> {doublonTypes.find(d => d.value === selectedDecision.type_doublon)?.label || selectedDecision.type_doublon}</p>
                                        <p className="mb-1"><strong>Décision :</strong> {decisionBadge(selectedDecision.decision)}</p>
                                        <p className="mb-1"><strong>Traité par :</strong> {selectedDecision.decide_par?.name || '-'}</p>
                                        <p className="mb-1"><strong>Date :</strong> {formatDateFr(selectedDecision.decide_le)}</p>
                                        <p className="mb-1"><strong>Motif :</strong> {selectedDecision.motif || '-'}</p>
                                    </div>
                                </Col>
                            </Row>
                        );
                    })()}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setDetailModalOpen(false)}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DesseStagiairesIndex;
