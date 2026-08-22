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
import Select from 'react-select';

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
    statut_code: string;
    date_creation: string;
}

interface DossierGroupeRow {
    id: number;
    numero: string;
    nature: string;
    statut: string;
    montant_total: number;
    observation?: string | null;
    dossiers_count: number;
    source_financement?: { nom: string } | null;
    attestation_path?: string | null;
    etat_financier_path?: string | null;
}

interface DossierMultiItem {
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
    dossiersGroupables: DossierRow[];
    dossiersEligiblesOp: DossierRow[];
    groupesDossiers: DossierGroupeRow[];
    ops: any[];
    opsEligiblesBordereau: any[];
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
        dossiersGroupables = [],
        dossiersEligiblesOp = [],
        groupesDossiers = [],
        ops = [],
        opsEligiblesBordereau = [],
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
    const [selectedDossierIds, setSelectedDossierIds] = useState<number[]>([]);
    const [selectedOpDossierIds, setSelectedOpDossierIds] = useState<number[]>([]);
    const [selectedOpIds, setSelectedOpIds] = useState<number[]>([]);

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
    const [moisDemarrage, setMoisDemarrage] = useState(moisActuel || '');
    const [moisPresence, setMoisPresence] = useState(moisActuel || '');
    const [moisDossiers, setMoisDossiers] = useState(moisActuel || '');

    const getMoisForTab = (tab: string) => {
        if (tab === '1') return moisDemarrage;
        if (tab === '2') return moisPresence;
        return moisDossiers;
    };
    const setMoisForTab = (tab: string, value: string) => {
        if (tab === '1') setMoisDemarrage(value);
        else if (tab === '2') setMoisPresence(value);
        else setMoisDossiers(value);
    };

    /* ─── Modales ─── */
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [modalDetailOpen, setModalDetailOpen] = useState(false);
    const [modalDossierOpen, setModalDossierOpen] = useState(false);
    const [modalGroupeOpen, setModalGroupeOpen] = useState(false);
    const [modalPreviewOpen, setModalPreviewOpen] = useState(false);
    const [previewStagiaire, setPreviewStagiaire] = useState<any>(null);
    const [previewDocs, setPreviewDocs] = useState<any[]>([]);
    const [isLoadingPreviewDocs, setIsLoadingPreviewDocs] = useState(false);
    const [activePreviewTab, setActivePreviewTab] = useState<string | null>(null);
    const [detailRow, setDetailRow] = useState<PaiementRow | null>(null);
    const [motifAjourner, setMotifAjourner] = useState('');
    const [dossierStatus, setDossierStatus] = useState('en_attente');
    const [observationGroupe, setObservationGroupe] = useState('');

    /* ─── États Multi-dossier intégrés ─── */
    const [multiDossiers, setMultiDossiers] = useState<DossierMultiItem[]>([]);
    const [isLoadingMultiDossiers, setIsLoadingMultiDossiers] = useState(false);
    const [selectedMultiDossierIds, setSelectedMultiDossierIds] = useState<number[]>([]);
    const [multiTypeTraitement, setMultiTypeTraitement] = useState('');
    const [stagiaires, setStagiaires] = useState<StagiaireRow[]>([]);
    const [selectedStagiaireIds, setSelectedStagiaireIds] = useState<number[]>([]);
    const [stagiaireSearch, setStagiaireSearch] = useState('');
    const [stagiairePage, setStagiairePage] = useState(1);
    const [stagiaireTotal, setStagiaireTotal] = useState(0);
    const [stagiaireLoading, setStagiaireLoading] = useState(false);
    const [modalMultiValiderOpen, setModalMultiValiderOpen] = useState(false);
    const [modalMultiAjournerDossierOpen, setModalMultiAjournerDossierOpen] = useState(false);
    const [modalMultiAjournerStagiaireOpen, setModalMultiAjournerStagiaireOpen] = useState(false);
    const [multiObservation, setMultiObservation] = useState('');
    const [motifMultiAjournerDossier, setMotifMultiAjournerDossier] = useState('');
    const [motifMultiAjournerStagiaire, setMotifMultiAjournerStagiaire] = useState('');

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
        const mois = getMoisForTab(activeTab);
        if (mois) params.mois = mois;
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
    }, [moisDemarrage, moisPresence, moisDossiers, selectedFilters, demarrageTab, activeTab]);

    const resetFilters = () => {
        setSelectedFilters({
            agence_id: '', entreprise_id: '', source_financement_id: '',
            type_stage_id: '', type_structure_id: '', date_debut: '',
            date_fin: '', date_validation_debut: '', date_validation_fin: '', search: '',
        });
        setMoisDemarrage(moisActuel || '');
        setMoisPresence(moisActuel || '');
        setMoisDossiers(moisActuel || '');
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
        params.set('mois', getMoisForTab(activeTab));
        params.set('type', type);
        params.set('nature', activeTab === '2' ? 'presence' : 'demarrage');
        Object.entries(selectedFilters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });
        if (scope === 'selected') ids.forEach((id) => params.append('ids[]', String(id)));
        window.open(`/dmg/paiements/generer-pdf?${params.toString()}`, '_blank');
    };

    const handleTransmettreDossier = (id: number) => {
        router.post(`/dmg/paiements/transmettre/${id}`, {}, { preserveScroll: true });
    };

    const toggleSelection = (id: number, setter: React.Dispatch<React.SetStateAction<number[]>>) => {
        setter((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
    };

    const handleGrouperDossiers = () => {
        if (!props.periode || selectedDossierIds.length < 2) return;
        setProcessing(true);
        router.post('/dmg/paiements/groupes', {
            periode_id: props.periode.id,
            dossiers: selectedDossierIds,
            observation: observationGroupe || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDossierIds([]);
                setObservationGroupe('');
                setModalGroupeOpen(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleTransmettreGroupe = (id: number) => {
        router.post(`/dmg/paiements/groupes/${id}/transmettre`, {}, { preserveScroll: true });
    };

    const handleGenererPdfsGroupe = (id: number) => {
        setProcessing(true);
        router.post(`/dmg/paiements/groupes/${id}/generer-pdfs`, {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const handleDownloadAttestation = (id: number) => {
        window.open(`/dmg/paiements/groupes/${id}/download-attestation`, '_blank');
    };

    const handleDownloadEtatFinancier = (id: number) => {
        window.open(`/dmg/paiements/groupes/${id}/download-etat-financier`, '_blank');
    };

    const handleElaborerSelection = () => {
        if (!props.periode || selectedOpDossierIds.length === 0) return;
        router.post('/dmg/paiements/elaborer-op', {
            dossiers: selectedOpDossierIds,
            periode_id: props.periode.id,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelectedOpDossierIds([]),
        });
    };

    const handleElaborerOp = (id: number) => {
        if (!props.periode) return;
        router.post('/dmg/paiements/elaborer-op', { dossiers: [id], periode_id: props.periode.id }, { preserveScroll: true });
    };

    const handleCreerBordereau = (id: number) => {
        if (!props.periode) return;
        router.post('/dmg/paiements/creer-bordereau', { ops: [id], periode_id: props.periode.id }, { preserveScroll: true });
    };

    const handleCreerBordereauSelection = () => {
        if (!props.periode || selectedOpIds.length === 0 || selectedOpIds.length > 10) return;
        router.post('/dmg/paiements/creer-bordereau', {
            ops: selectedOpIds,
            periode_id: props.periode.id,
        }, {
            preserveScroll: true,
            onSuccess: () => setSelectedOpIds([]),
        });
    };

    const handleTransmettreBordereau = (id: number) => {
        router.post(`/dmg/paiements/transmettre-bordereau/${id}`, {}, { preserveScroll: true });
    };

    const handlePreviewDocs = (stagiaire: any) => {
        setPreviewStagiaire(stagiaire);
        setPreviewDocs([]);
        setIsLoadingPreviewDocs(true);
        setModalPreviewOpen(true);
        setActivePreviewTab(null);
        
        fetch(`/dmg/paiements/documents?stage_id=${stagiaire.stage_id}`)
            .then(res => res.json())
            .then(data => {
                const docs = data.data || [];
                setPreviewDocs(docs);
                if (docs.length > 0) {
                    setActivePreviewTab(docs[0].id.toString());
                }
            })
            .catch(() => setPreviewDocs([]))
            .finally(() => setIsLoadingPreviewDocs(false));
    };

    /* ─── Données dossiers par statut ─── */
    const [dossierTab, setDossierTab] = useState('brouillon');

    /* ─── Dossiers CB (DMG vue) ─── */
    const [dossiersCbTransmis, setDossiersCbTransmis] = useState<any[]>([]);
    const [dossiersCbValides, setDossiersCbValides] = useState<any[]>([]);
    const [isLoadingCbDossiers, setIsLoadingCbDossiers] = useState(false);
    const [expandedCbDossierId, setExpandedCbDossierId] = useState<number | null>(null);
    const [cbStagiaires, setCbStagiaires] = useState<any[]>([]);
    const [cbStagiaireTotal, setCbStagiaireTotal] = useState(0);
    const [cbStagiairePage, setCbStagiairePage] = useState(1);
    const [cbStagiaireSearch, setCbStagiaireSearch] = useState('');
    const [cbStagiaireLoading, setCbStagiaireLoading] = useState(false);
    const [cbDossiersSearch, setCbDossiersSearch] = useState('');
    const CB_DOSSIERS_PER_PAGE = 5;
    const [cbTransmisPage, setCbTransmisPage] = useState(1);
    const [cbValidesPage, setCbValidesPage] = useState(1);
    const cbDossiersRef = React.useRef<HTMLDivElement>(null);

    /* ═══════════════════════════════════════════════════════════════════
       DOSSIERS CB — Chargement dossiers par statut
       ═══════════════════════════════════════════════════════════════════ */
    const loadDossiersCb = useCallback(() => {
        setIsLoadingCbDossiers(true);
        const mois = moisDossiers || moisActuel;
        const searchParam = cbDossiersSearch ? `&search=${encodeURIComponent(cbDossiersSearch)}` : '';
        Promise.all([
            fetch(`/dmg/paiements/dossiers-cb?mois=${mois}&statut=TRANSMIS_CB${searchParam}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then((r) => r.json()),
            fetch(`/dmg/paiements/dossiers-cb?mois=${mois}&statut=VALIDE_CB${searchParam}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then((r) => r.json()),
        ])
            .then(([transmis, valides]) => {
                setDossiersCbTransmis(transmis || []);
                setDossiersCbValides(valides || []);
            })
            .catch(() => {
                setDossiersCbTransmis([]);
                setDossiersCbValides([]);
            })
            .finally(() => setIsLoadingCbDossiers(false));
    }, [moisDossiers, moisActuel]);

    const loadCbStagiaires = useCallback((dossierId: number) => {
        setCbStagiaireLoading(true);
        const body = new URLSearchParams();
        body.set('dossier_id', String(dossierId));
        body.set('start', String((cbStagiairePage - 1) * 10));
        body.set('length', '10');
        body.set('search', cbStagiaireSearch);
        body.set('draw', String(Date.now()));

        fetch('/dmg/paiements/stagiaires', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
            body: body.toString(),
        })
            .then((r) => r.json())
            .then((res) => {
                setCbStagiaires(res.data || []);
                setCbStagiaireTotal(res.recordsFiltered || 0);
            })
            .catch(() => {
                setCbStagiaires([]);
                setCbStagiaireTotal(0);
            })
            .finally(() => setCbStagiaireLoading(false));
    }, [cbStagiairePage, cbStagiaireSearch]);

    const toggleExpandCbDossier = (dossierId: number) => {
        if (expandedCbDossierId === dossierId) {
            setExpandedCbDossierId(null);
            setCbStagiaires([]);
            setCbStagiaireTotal(0);
        } else {
            setExpandedCbDossierId(dossierId);
            setCbStagiairePage(1);
            setCbStagiaireSearch('');
        }
    };

    /* Charger les dossiers CB quand l'onglet transmis est actif */
    const prevDossierTabCb = React.useRef(dossierTab);
    React.useEffect(() => {
        if (dossierTab === 'transmis' && prevDossierTabCb.current !== 'transmis') {
            loadDossiersCb();
        }
        prevDossierTabCb.current = dossierTab;
    }, [dossierTab, loadDossiersCb]);

    React.useEffect(() => {
        if (dossierTab === 'transmis' && expandedCbDossierId) {
            loadCbStagiaires(expandedCbDossierId);
        }
    }, [expandedCbDossierId, cbStagiairePage, cbStagiaireSearch, dossierTab, loadCbStagiaires]);

    /* ═══════════════════════════════════════════════════════════════════
       MULTI-DOSSIER — Chargement dossiers via AJAX
       ═══════════════════════════════════════════════════════════════════ */
    const loadMultiDossiers = useCallback(() => {
        setIsLoadingMultiDossiers(true);
        const params = new URLSearchParams();
        if (moisDossiers) params.set('mois', moisDossiers);
        if (selectedFilters.agence_id) params.set('agence_id', selectedFilters.agence_id);
        if (selectedFilters.source_financement_id) params.set('source_financement_id', selectedFilters.source_financement_id);
        if (multiTypeTraitement) params.set('typetraitement', multiTypeTraitement);

        fetch(`/dmg/multi-dossier/dossiers?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((r) => r.json())
            .then((data: DossierMultiItem[]) => {
                setMultiDossiers(data);
                setSelectedMultiDossierIds([]);
                setStagiaires([]);
                setStagiaireTotal(0);
            })
            .catch(() => setMultiDossiers([]))
            .finally(() => setIsLoadingMultiDossiers(false));
    }, [moisDossiers, selectedFilters.agence_id, selectedFilters.source_financement_id, multiTypeTraitement]);

    /* ═══════════════════════════════════════════════════════════════════
       MULTI-DOSSIER — Chargement stagiaires (serveur-side)
       ═══════════════════════════════════════════════════════════════════ */
    const loadStagiairesMulti = useCallback(() => {
        if (selectedMultiDossierIds.length === 0) {
            setStagiaires([]);
            setStagiaireTotal(0);
            return;
        }
        setStagiaireLoading(true);
        const body = new URLSearchParams();
        body.set('draw', String(Date.now()));
        body.set('start', String((stagiairePage - 1) * 10));
        body.set('length', '10');
        body.set('search.value', stagiaireSearch);
        selectedMultiDossierIds.forEach((id) => body.append('dossiers[]', String(id)));

        fetch('/dmg/multi-dossier/stagiaires', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
            body: body.toString(),
        })
            .then((r) => r.json())
            .then((res) => {
                setStagiaires(res.data || []);
                setStagiaireTotal(res.recordsFiltered || 0);
            })
            .catch(() => { setStagiaires([]); setStagiaireTotal(0); })
            .finally(() => setStagiaireLoading(false));
    }, [selectedMultiDossierIds, stagiairePage, stagiaireSearch]);

    /* ═══════════════════════════════════════════════════════════════════
       MULTI-DOSSIER — Actions
       ═══════════════════════════════════════════════════════════════════ */
    const toggleMultiDossierSelection = (id: number) => {
        setSelectedMultiDossierIds((prev) => prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]);
        setStagiairePage(1);
    };

    const toggleAllMultiDossiers = () => {
        setSelectedMultiDossierIds((prev) => (prev.length === multiDossiers.length ? [] : multiDossiers.map((d) => d.id)));
        setStagiairePage(1);
    };

    const toggleStagiaireSelection = (id: number) => {
        setSelectedStagiaireIds((prev) => prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]);
    };

    const toggleAllStagiaires = () => {
        setSelectedStagiaireIds((prev) => (prev.length === stagiaires.length ? [] : stagiaires.map((s) => s.paiement_id)));
    };

    const handleMultiValiderSelection = () => {
        if (selectedMultiDossierIds.length === 0 || !moisDossiers) return;
        setProcessing(true);
        fetch('/dmg/multi-dossier/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            body: JSON.stringify({ dossiers: selectedMultiDossierIds, mois: moisDossiers, observation: multiObservation.trim() || null }),
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success) {
                    setModalMultiValiderOpen(false);
                    setMultiObservation('');
                    setSelectedMultiDossierIds([]);
                    loadMultiDossiers();
                    router.reload({ only: ['groupesDossiers'] });
                }
            })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleMultiAjournerDossier = () => {
        if (selectedMultiDossierIds.length === 0 || motifMultiAjournerDossier.trim().length < 5) return;
        setProcessing(true);
        fetch('/dmg/multi-dossier/ajourner-dossier', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            body: JSON.stringify({ dossier_id: selectedMultiDossierIds, motif: motifMultiAjournerDossier, mois: moisDossiers }),
        })
            .then((r) => r.json())
            .then(() => { setModalMultiAjournerDossierOpen(false); setMotifMultiAjournerDossier(''); setSelectedMultiDossierIds([]); loadMultiDossiers(); })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleMultiAjournerStagiaire = () => {
        if (selectedStagiaireIds.length === 0 || motifMultiAjournerStagiaire.trim().length < 5) return;
        setProcessing(true);
        fetch('/dmg/multi-dossier/ajourner-stagiaire', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            body: JSON.stringify({ paiementIds: selectedStagiaireIds, motif: motifMultiAjournerStagiaire }),
        })
            .then((r) => r.json())
            .then(() => { setModalMultiAjournerStagiaireOpen(false); setMotifMultiAjournerStagiaire(''); setSelectedStagiaireIds([]); loadStagiairesMulti(); })
            .catch(() => {})
            .finally(() => setProcessing(false));
    };

    const handleMultiGenererPdf = (type: 'paiement' | 'attestations') => {
        if (selectedMultiDossierIds.length === 0) return;
        const url = type === 'paiement' ? '/dmg/multi-dossier/generer-pdf-paiement' : '/dmg/multi-dossier/generer-pdf-attestations';
        const body = new URLSearchParams();
        selectedMultiDossierIds.forEach((id) => body.append('dossiers[]', String(id)));
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '') },
            body: body.toString(),
        })
            .then((r) => { if (!r.ok) throw new Error(); return r.blob(); })
            .then((blob) => { const u = window.URL.createObjectURL(blob); const a = document.createElement('a'); a.href = u; a.download = `${type}_${new Date().toISOString().slice(0, 10)}.pdf`; document.body.appendChild(a); a.click(); a.remove(); window.URL.revokeObjectURL(u); })
            .catch(() => {});
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
                {cell.row.original.statut_code === 'BROUILLON' && (
                    <Button color="success" size="sm" outline onClick={() => handleTransmettreDossier(cell.row.original.id)} title="Transmettre au CB">
                        <i className="ri-send-plane-line"></i>
                    </Button>
                )}
                {cell.row.original.statut_code === 'VALIDE_CB' && (
                    <Button color="primary" size="sm" outline onClick={() => handleElaborerOp(cell.row.original.id)} title="Elaborer un OP">
                        <i className="ri-file-list-3-line"></i>
                    </Button>
                )}
            </div>
        )},
    ], [props.periode]);

    /* ─── Charger les dossiers multi quand l'onglet est actif ou que la periode change ─── */
    const prevDossierTab = React.useRef(dossierTab);
    React.useEffect(() => {
        if (dossierTab === 'multi' && prevDossierTab.current !== 'multi') {
            loadMultiDossiers();
        }
        if (dossierTab === 'multi') {
            loadStagiairesMulti();
        }
        prevDossierTab.current = dossierTab;
    }, [dossierTab, loadMultiDossiers, loadStagiairesMulti]);

    const prevMoisDossiers = React.useRef(moisDossiers);
    React.useEffect(() => {
        if (dossierTab === 'multi' && prevMoisDossiers.current !== moisDossiers) {
            loadMultiDossiers();
        }
        prevMoisDossiers.current = moisDossiers;
    }, [moisDossiers, dossierTab, loadMultiDossiers]);

    const prevTypeTraitement = React.useRef(multiTypeTraitement);
    React.useEffect(() => {
        if (dossierTab === 'multi' && prevTypeTraitement.current !== multiTypeTraitement) {
            loadMultiDossiers();
        }
        prevTypeTraitement.current = multiTypeTraitement;
    }, [multiTypeTraitement, dossierTab, loadMultiDossiers]);

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
                        <Row className="g-3">
                            <Col xs={12}>
<Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' }, 'fw-semibold py-3')} onClick={() => toggleTab('1')}>
                                        <i className="ri-flag-line me-1 align-middle"></i>
                                        Attente Démarrage <Badge color="primary" pill className="ms-2">{compteurs?.global?.demarrage ?? attenteDemarrage.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' }, 'fw-semibold py-3')} onClick={() => toggleTab('2')}>
                                        <i className="ri-user-follow-line me-1 align-middle"></i>
                                        Attente Présence <Badge color="info" pill className="ms-2">{compteurs?.global?.presence ?? attentePresence.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' }, 'fw-semibold py-3')} onClick={() => toggleTab('3')}>
                                        <i className="ri-folder-2-line me-1 align-middle"></i>
                                        Dossiers & OP <Badge color="warning" pill className="ms-2">{dossiers.length + dossiersTransmis.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>
                            </Col>
                            <Col xs={12}>

<TabContent activeTab={activeTab} className="pt-4 text-muted">
                                {/* ═══════ ONGLET 1 : ATTENTE DÉMARRAGE ═══════ */}
                                <TabPane tabId="1">
                                    {/* ── Sélecteur de période Démarrage ── */}
                                    <div className="d-flex align-items-center gap-2 mb-3">
                                        <i className="ri-calendar-line text-primary fs-16"></i>
                                        <Label className="form-label fs-12 text-muted fw-semibold mb-0 me-1">Période :</Label>
                                        <Input type="select" bsSize="sm" style={{ width: 180 }} value={moisDemarrage}
                                            onChange={(e) => setMoisDemarrage(e.target.value)}>
                                            <option value="">Toutes les périodes</option>
                                            {periodeOptions.map((p) => <option key={p.id} value={p.code}>{p.code}</option>)}
                                        </Input>
                                        <Button color="primary" size="sm" onClick={applyFilters} disabled={isLoading}>
                                            <i className="ri-search-line me-1"></i>Appliquer
                                        </Button>
                                        <Badge color="primary" pill className="fs-11">{filteredDemarrageRows.length} paiement(s)</Badge>
                                    </div>
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
                                    <Row className="g-3">
                                        <Col xs={12}>
<Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'global' }, 'fw-semibold py-3')} onClick={() => toggleDemarrageTab('global')}>
                                                Cohorte Global {cohortBadge('global', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte1' }, 'fw-semibold py-3')} onClick={() => toggleDemarrageTab('cohorte1')}>
                                                Cohorte 1 {cohortBadge('cohorte1', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte2' }, 'fw-semibold py-3')} onClick={() => toggleDemarrageTab('cohorte2')}>
                                                Cohorte 2 {cohortBadge('cohorte2', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: demarrageTab === 'cohorte3' }, 'fw-semibold py-3')} onClick={() => toggleDemarrageTab('cohorte3')}>
                                                Cohorte 3 {cohortBadge('cohorte3', 'demarrage')}
                                            </NavLink>
                                        </NavItem>
                                    </Nav>
                                        </Col>
                                        <Col xs={12}>

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
                                        </Col>
                                    </Row>
                                </TabPane>

                                {/* ═══════ ONGLET 2 : ATTENTE PRÉSENCE ═══════ */}
                                <TabPane tabId="2">
                                    {/* ── Sélecteur de période Présence ── */}
                                    <div className="d-flex align-items-center gap-2 mb-3">
                                        <i className="ri-calendar-line text-info fs-16"></i>
                                        <Label className="form-label fs-12 text-muted fw-semibold mb-0 me-1">Période :</Label>
                                        <Input type="select" bsSize="sm" style={{ width: 180 }} value={moisPresence}
                                            onChange={(e) => setMoisPresence(e.target.value)}>
                                            <option value="">Toutes les périodes</option>
                                            {periodeOptions.map((p) => <option key={p.id} value={p.code}>{p.code}</option>)}
                                        </Input>
                                        <Button color="info" size="sm" onClick={applyFilters} disabled={isLoading}>
                                            <i className="ri-search-line me-1"></i>Appliquer
                                        </Button>
                                        <Badge color="info" pill className="fs-11">{currentPresenceRows.length} paiement(s)</Badge>
                                    </div>
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
                                    {/* ── Sélecteur de période Dossiers ── */}
                                    <div className="d-flex align-items-center gap-2 mb-3">
                                        <i className="ri-calendar-line text-warning fs-16"></i>
                                        <Label className="form-label fs-12 text-muted fw-semibold mb-0 me-1">Période :</Label>
                                        <Input type="select" bsSize="sm" style={{ width: 180 }} value={moisDossiers}
                                            onChange={(e) => setMoisDossiers(e.target.value)}>
                                            <option value="">Toutes les périodes</option>
                                            {periodeOptions.map((p) => <option key={p.id} value={p.code}>{p.code}</option>)}
                                        </Input>
                                        <Button color="warning" size="sm" onClick={applyFilters} disabled={isLoading}>
                                            <i className="ri-search-line me-1"></i>Appliquer
                                        </Button>
                                        <Badge color="warning" pill className="fs-11">{dossiers.length} dossier(s)</Badge>
                                    </div>
                                    {/* ── Sous-onglets dossiers ── */}
                                    <Row className="g-3">
                                        <Col xs={12}>
<Nav tabs className="nav-tabs-custom nav-success mb-0 border-bottom">
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'brouillon' }, 'fw-semibold py-3')} onClick={() => setDossierTab('brouillon')}>
                                                <i className="ri-draft-line me-1"></i>En élaboration <Badge color="warning" pill className="ms-2">{dossiers.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'transmis' }, 'fw-semibold py-3')} onClick={() => setDossierTab('transmis')}>
                                                <i className="ri-send-plane-line me-1"></i>Transmis CB <Badge color="info" pill className="ms-2">{dossiersCbTransmis.length + dossiersCbValides.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'ajournes' }, 'fw-semibold py-3')} onClick={() => setDossierTab('ajournes')}>
                                                <i className="ri-close-circle-line me-1"></i>Ajournés <Badge color="danger" pill className="ms-2">{dossiersAjournes.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'multi' }, 'fw-semibold py-3')} onClick={() => setDossierTab('multi')}>
                                                <i className="ri-folder-shared-line me-1"></i>Multi-dossiers <Badge color="warning" pill className="ms-2">{groupesDossiers.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'ops' }, 'fw-semibold py-3')} onClick={() => setDossierTab('ops')}>
                                                <i className="ri-file-list-3-line me-1"></i>Ordres de Paiement <Badge color="primary" pill className="ms-2">{ops.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: dossierTab === 'bordereaux' }, 'fw-semibold py-3')} onClick={() => setDossierTab('bordereaux')}>
                                                <i className="ri-file-shield-2-line me-1"></i>Bordereaux <Badge color="success" pill className="ms-2">{bordereaux.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                    </Nav>
                                        </Col>
                                        <Col xs={12}>

                                    <div className="d-flex gap-2 mb-3">
                                            <Button color="success" size="sm" onClick={handleGenererDossiers} disabled={!props.periode || processing || (selectedDemarrageIds.length + selectedPresenceIds.length === 0)}>
                                            {processing ? <><Spinner size="sm" className="me-1" />Génération...</> : <><i className="ri-folder-add-line me-1"></i>Générer les dossiers pour la période</>}
                                        </Button>
                                    </div>

<TabContent activeTab={dossierTab} className="pt-4">
                                        <TabPane tabId="brouillon">
                                            <TableContainerReactTable columns={dossierColumns} data={dossiers} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="transmis" ref={cbDossiersRef as any}>
                                            {isLoadingCbDossiers ? (
                                                <div className="d-flex justify-content-center py-5"><Spinner color="info" /></div>
                                            ) : (
                                                <>
                                                    {/* ═══ Section 1 : Dossiers en attente CB ═══ */}
                                                    <Card className="border shadow-none mb-4">
                                                        <CardHeader className="bg-warning bg-opacity-10 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                            <h6 className="card-title mb-0 fs-14 text-warning fw-bold">
                                                                <i className="ri-folder-check-line me-1"></i>
                                                                Dossiers en attente de validation CB
                                                                <Badge color="warning" pill className="ms-2 fs-11">{dossiersCbTransmis.length}</Badge>
                                                            </h6>
                                                            <div className="d-flex align-items-center">
                                                                <div className="search-box me-2">
                                                                    <Input
                                                                        type="text"
                                                                        placeholder="Rechercher dossier, stagiaire..."
                                                                        className="form-control form-control-sm search"
                                                                        value={cbDossiersSearch}
                                                                        onChange={(e) => setCbDossiersSearch(e.target.value)}
                                                                        onKeyDown={(e) => {
                                                                            if (e.key === 'Enter') loadDossiersCb();
                                                                        }}
                                                                        style={{ minWidth: '250px' }}
                                                                    />
                                                                    <i className="ri-search-line search-icon"></i>
                                                                </div>
                                                                <Button color="primary" size="sm" onClick={loadDossiersCb}>
                                                                    <i className="ri-search-line"></i>
                                                                </Button>
                                                            </div>
                                                        </CardHeader>
                                                        <CardBody className="p-0">
                                                            <div className="table-responsive">
                                                                <table className="table table-striped table-hover align-middle mb-0">
                                                                    <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                                        <tr>
                                                                            <th style={{ width: 40 }}>#</th>
                                                                            <th>Numéro</th>
                                                                            <th>Agence</th>
                                                                            <th>Financement</th>
                                                                            <th className="text-center">Stagiaires</th>
                                                                            <th className="text-end">Montant</th>
                                                                            <th>Statut</th>
                                                                            <th style={{ width: 60 }}></th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {(() => {
                                                                            const tp = Math.max(1, Math.ceil(dossiersCbTransmis.length / CB_DOSSIERS_PER_PAGE));
                                                                            const safePage = Math.min(cbTransmisPage, tp);
                                                                            const si = (safePage - 1) * CB_DOSSIERS_PER_PAGE;
                                                                            const rows = dossiersCbTransmis.slice(si, si + CB_DOSSIERS_PER_PAGE);
                                                                            return rows.map((d: any, idx: number) => (
                                                                                <React.Fragment key={d.id}>
                                                                                    <tr
                                                                                        className={expandedCbDossierId === d.id ? 'table-active' : ''}
                                                                                        style={{ cursor: 'pointer' }}
                                                                                        onClick={() => toggleExpandCbDossier(d.id)}>
                                                                                        <td>{si + idx + 1}</td>
                                                                                        <td className="fw-medium text-primary">{d.identifiant}</td>
                                                                                        <td>{d.agence}</td>
                                                                                        <td><Badge color="info-subtle" className="text-info">{d.source_financement}</Badge></td>
                                                                                        <td className="text-center"><Badge color="warning" pill>{d.nombre_stagiaires}</Badge></td>
                                                                                        <td className="text-end fw-bold">{Number(d.montant_total || 0).toLocaleString('fr-FR')} FCFA</td>
                                                                                        <td><Badge color="warning" className="fs-11">TRANSMIS_CB</Badge></td>
                                                                                        <td><i className={`ri-arrow-${expandedCbDossierId === d.id ? 'up' : 'down'}-s-line text-muted`}></i></td>
                                                                                    </tr>
                                                                                    {expandedCbDossierId === d.id && (
                                                                                        <tr>
                                                                                            <td colSpan={8} className="p-0 border-0">
                                                                                                <div className="bg-light p-3">
                                                                                                    <div className="d-flex justify-content-end mb-2">
                                                                                                        <Input
                                                                                                            type="text"
                                                                                                            bsSize="sm"
                                                                                                            placeholder="Rechercher stagiaire..."
                                                                                                            value={cbStagiaireSearch}
                                                                                                            onChange={(e) => setCbStagiaireSearch(e.target.value)}
                                                                                                            onKeyDown={(e) => {
                                                                                                                if (e.key === 'Enter') loadCbStagiaires(d.id);
                                                                                                            }}
                                                                                                            style={{ width: '200px' }}
                                                                                                        />
                                                                                                    </div>
                                                                                                    {cbStagiaireLoading ? (
                                                                                                        <div className="d-flex justify-content-center py-3"><Spinner size="sm" color="info" /></div>
                                                                                                    ) : cbStagiaires.length === 0 ? (
                                                                                                        <p className="text-muted text-center mb-0">Aucun stagiaire trouvé.</p>
                                                                                                    ) : (
                                                                                                        <>
                                                                                                            <table className="table table-sm table-bordered mb-2">
                                                                                                                <thead className="table-light fs-11">
                                                                                                                    <tr>
                                                                                                                        <th>N° AEJ</th>
                                                                                                                        <th>Nom et Prénoms</th>
                                                                                                                        <th>Entreprise</th>
                                                                                                                        <th>Financement</th>
                                                                                                                        <th>Type Stage</th>
                                                                                                                        <th>Début</th>
                                                                                                                        <th>Fin</th>
                                                                                                                        <th className="text-end">Montant</th>
                                                                                                                        <th className="text-center">Actions</th>
                                                                                                                    </tr>
                                                                                                                </thead>
                                                                                                                <tbody>
                                                                                                                    {cbStagiaires.map((s: any) => (
                                                                                                                        <tr key={s.paiement_id}>
                                                                                                                            <td className="text-muted">{s.numero_aej}</td>
                                                                                                                            <td className="fw-semibold">{s.nom} {s.prenoms}</td>
                                                                                                                            <td className="text-truncate" style={{ maxWidth: 120 }}>{s.entreprise}</td>
                                                                                                                            <td>{s.source_financement}</td>
                                                                                                                            <td>{s.type_stage}</td>
                                                                                                                            <td className="fs-12">{s.date_debut}</td>
                                                                                                                            <td className="fs-12">{s.date_fin}</td>
                                                                                                                            <td className="text-end fw-bold text-success">{Number(s.montant || 0).toLocaleString('fr-FR')} FCFA</td>
                                                                                                                            <td className="text-center">
                                                                                                                                <Button 
                                                                                                                                    color="info" 
                                                                                                                                    size="sm" 
                                                                                                                                    className="btn-icon" 
                                                                                                                                    outline
                                                                                                                                    onClick={() => handlePreviewDocs(s)}
                                                                                                                                    title="Prévisualiser les fichiers"
                                                                                                                                >
                                                                                                                                    <i className="ri-folder-open-line"></i>
                                                                                                                                </Button>
                                                                                                                            </td>
                                                                                                                        </tr>
                                                                                                                    ))}
                                                                                                                </tbody>
                                                                                                            </table>
                                                                                                            {cbStagiaireTotal > 10 && (
                                                                                                                <div className="d-flex justify-content-between align-items-center">
                                                                                                                    <small className="text-muted">{cbStagiaireTotal} stagiaire(s)</small>
                                                                                                                    <div className="d-flex gap-1">
                                                                                                                        <Button size="sm" color="light" disabled={cbStagiairePage <= 1} onClick={() => setCbStagiairePage((p) => p - 1)}><i className="ri-arrow-left-s-line"></i></Button>
                                                                                                                        <Button size="sm" color="light" disabled={cbStagiairePage >= Math.ceil(cbStagiaireTotal / 10)} onClick={() => setCbStagiairePage((p) => p + 1)}><i className="ri-arrow-right-s-line"></i></Button>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            )}
                                                                                                        </>
                                                                                                    )}
                                                                                                </div>
                                                                                            </td>
                                                                                        </tr>
                                                                                    )}
                                                                                </React.Fragment>
                                                                            ));
                                                                        })()}
                                                                        {dossiersCbTransmis.length === 0 && (
                                                                            <tr><td colSpan={8} className="text-center py-4 text-muted">
                                                                                <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun dossier en attente de validation CB.
                                                                            </td></tr>
                                                                        )}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            {dossiersCbTransmis.length > CB_DOSSIERS_PER_PAGE && (() => {
                                                                const tp = Math.ceil(dossiersCbTransmis.length / CB_DOSSIERS_PER_PAGE);
                                                                const maxV = 7;
                                                                let pgs: (number | '...')[] = [];
                                                                if (tp <= maxV) { pgs = Array.from({ length: tp }, (_, i) => i + 1); }
                                                                else {
                                                                    pgs = [1];
                                                                    if (cbTransmisPage > 3) pgs.push('...');
                                                                    for (let i = Math.max(2, cbTransmisPage - 1); i <= Math.min(tp - 1, cbTransmisPage + 1); i++) pgs.push(i);
                                                                    if (cbTransmisPage < tp - 2) pgs.push('...');
                                                                    pgs.push(tp);
                                                                }
                                                                const siT = (Math.min(cbTransmisPage, tp) - 1) * CB_DOSSIERS_PER_PAGE;
                                                                return (
                                                                    <div className="d-flex justify-content-between align-items-center p-2 border-top">
                                                                        <small className="text-muted">{siT + 1}–{Math.min(siT + CB_DOSSIERS_PER_PAGE, dossiersCbTransmis.length)} sur {dossiersCbTransmis.length}</small>
                                                                        <div className="d-flex align-items-center gap-1">
                                                                            <Button size="sm" color="light" disabled={cbTransmisPage <= 1} onClick={() => setCbTransmisPage((p) => p - 1)}><i className="ri-arrow-left-s-line"></i></Button>
                                                                            {pgs.map((page, i) => page === '...' ? <span key={`t-${i}`} className="px-1 text-muted">…</span> : <Button key={page} size="sm" color={page === cbTransmisPage ? 'warning' : 'light'} onClick={() => setCbTransmisPage(page)}>{page}</Button>)}
                                                                            <Button size="sm" color="light" disabled={cbTransmisPage >= tp} onClick={() => setCbTransmisPage((p) => p + 1)}><i className="ri-arrow-right-s-line"></i></Button>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })()}
                                                        </CardBody>
                                                    </Card>

                                                    {/* ═══ Section 2 : Dossiers validés CB ═══ */}
                                                    <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                                        <div>
                                                            <h5 className="fs-14 mb-1 text-success fw-bold">
                                                                <i className="ri-check-double-line me-1"></i>Dossiers validés CB — Éligibles OP
                                                            </h5>
                                                            <p className="text-muted mb-0 fs-12">Sélectionnez pour élaborer un Ordre de Paiement.</p>
                                                        </div>
                                                        <Button color="success" size="sm" disabled={selectedOpDossierIds.length === 0} onClick={handleElaborerSelection}>
                                                            <i className="ri-file-list-3-line me-1"></i>Élaborer l'OP ({selectedOpDossierIds.length})
                                                        </Button>
                                                    </div>
                                                    <Card className="border shadow-none">
                                                        <CardBody className="p-0">
                                                            <div className="table-responsive">
                                                                <table className="table table-striped table-hover align-middle mb-0">
                                                                    <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                                        <tr>
                                                                            <th style={{ width: 40 }}><Input type="checkbox" className="form-check-input" checked={selectedOpDossierIds.length === dossiersCbValides.length && dossiersCbValides.length > 0} onChange={() => { const all = dossiersCbValides.map((d: any) => d.id); setSelectedOpDossierIds((prev) => prev.length === all.length ? [] : all); }} /></th>
                                                                            <th>#</th>
                                                                            <th>Numéro</th>
                                                                            <th>Agence</th>
                                                                            <th>Financement</th>
                                                                            <th className="text-center">Stagiaires</th>
                                                                            <th className="text-end">Montant</th>
                                                                            <th>Statut</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {(() => {
                                                                            const tp = Math.max(1, Math.ceil(dossiersCbValides.length / CB_DOSSIERS_PER_PAGE));
                                                                            const safePage = Math.min(cbValidesPage, tp);
                                                                            const si = (safePage - 1) * CB_DOSSIERS_PER_PAGE;
                                                                            const rows = dossiersCbValides.slice(si, si + CB_DOSSIERS_PER_PAGE);
                                                                            return rows.map((d: any, idx: number) => (
                                                                                <tr key={d.id}>
                                                                                    <td><Input type="checkbox" className="form-check-input" checked={selectedOpDossierIds.includes(d.id)} onChange={() => toggleSelection(d.id, setSelectedOpDossierIds)} /></td>
                                                                                    <td>{si + idx + 1}</td>
                                                                                    <td className="fw-medium text-success">{d.identifiant}</td>
                                                                                    <td>{d.agence}</td>
                                                                                    <td><Badge color="success-subtle" className="text-success">{d.source_financement}</Badge></td>
                                                                                    <td className="text-center"><Badge color="success" pill>{d.nombre_stagiaires}</Badge></td>
                                                                                    <td className="text-end fw-bold">{Number(d.montant_total || 0).toLocaleString('fr-FR')} FCFA</td>
                                                                                    <td><Badge color="success" className="fs-11">VALIDE_CB</Badge></td>
                                                                                </tr>
                                                                            ));
                                                                        })()}
                                                                        {dossiersCbValides.length === 0 && (
                                                                            <tr><td colSpan={8} className="text-center py-4 text-muted">
                                                                                <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun dossier validé CB.
                                                                            </td></tr>
                                                                        )}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            {dossiersCbValides.length > CB_DOSSIERS_PER_PAGE && (() => {
                                                                const tp = Math.ceil(dossiersCbValides.length / CB_DOSSIERS_PER_PAGE);
                                                                const maxV = 7;
                                                                let pgs: (number | '...')[] = [];
                                                                if (tp <= maxV) { pgs = Array.from({ length: tp }, (_, i) => i + 1); }
                                                                else {
                                                                    pgs = [1];
                                                                    if (cbValidesPage > 3) pgs.push('...');
                                                                    for (let i = Math.max(2, cbValidesPage - 1); i <= Math.min(tp - 1, cbValidesPage + 1); i++) pgs.push(i);
                                                                    if (cbValidesPage < tp - 2) pgs.push('...');
                                                                    pgs.push(tp);
                                                                }
                                                                const siP = (Math.min(cbValidesPage, tp) - 1) * CB_DOSSIERS_PER_PAGE;
                                                                return (
                                                                    <div className="d-flex justify-content-between align-items-center p-2 border-top">
                                                                        <small className="text-muted">{siP + 1}–{Math.min(siP + CB_DOSSIERS_PER_PAGE, dossiersCbValides.length)} sur {dossiersCbValides.length}</small>
                                                                        <div className="d-flex align-items-center gap-1">
                                                                            <Button size="sm" color="light" disabled={cbValidesPage <= 1} onClick={() => setCbValidesPage((p) => p - 1)}><i className="ri-arrow-left-s-line"></i></Button>
                                                                            {pgs.map((page, i) => page === '...' ? <span key={`v-${i}`} className="px-1 text-muted">…</span> : <Button key={page} size="sm" color={page === cbValidesPage ? 'success' : 'light'} onClick={() => setCbValidesPage(page)}>{page}</Button>)}
                                                                            <Button size="sm" color="light" disabled={cbValidesPage >= tp} onClick={() => setCbValidesPage((p) => p + 1)}><i className="ri-arrow-right-s-line"></i></Button>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })()}
                                                        </CardBody>
                                                    </Card>
                                                </>
                                            )}
                                        </TabPane>
                                        <TabPane tabId="ajournes">
                                            <TableContainerReactTable columns={dossierColumns} data={dossiersAjournes} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="multi">
                                            {/* ── Filtres + Sélection + Actions ── */}
                                            <Row className="g-3 mb-3">
                                                {/* ── Filtres ── */}
                                                <Col lg={8} xl={9}>
                                                    <Card className="border shadow-none">
                                                        <CardHeader className="bg-light py-2 d-flex align-items-center gap-2">
                                                            <i className="ri-filter-3-line text-warning"></i>
                                                            <h6 className="card-title mb-0 fs-13 fw-semibold">Filtres et Sélection</h6>
                                                            {isLoadingMultiDossiers && <Spinner size="sm" color="warning" />}
                                                        </CardHeader>
                                                        <CardBody className="py-3">
                                                            <Row className="g-3 align-items-end">
                                                                <Col md={3}>
                                                                    <Label className="form-label fs-12 text-muted fw-semibold">Type Traitement</Label>
                                                                    <Input type="select" bsSize="sm" value={multiTypeTraitement}
                                                                        onChange={(e) => setMultiTypeTraitement(e.target.value)}>
                                                                        <option value="">Tout</option>
                                                                        <option value="DM">DÉMARRAGE</option>
                                                                        <option value="PS">PRÉSENCE</option>
                                                                    </Input>
                                                                </Col>
                                                                <Col md={9}>
                                                                    <Label className="form-label fs-12 text-muted fw-semibold">
                                                                        <i className="ri-folder-2-fill me-1 text-warning"></i>Sélectionner les dossiers
                                                                        <Badge color="secondary" pill className="ms-2 fs-11">{multiDossiers.length} disponible(s)</Badge>
                                                                    </Label>
                                                                    <Select
                                                                        isMulti
                                                                        options={multiDossiers.map((d) => ({
                                                                            value: d.id,
                                                                            label: `${d.identifiant} — ${d.agence} (${d.nombre_stagiaires} stagi., ${Number(d.montant_total || 0).toLocaleString('fr-FR')} FCFA)`,
                                                                            dossier: d,
                                                                        }))}
                                                                        value={multiDossiers
                                                                            .filter((d) => selectedMultiDossierIds.includes(d.id))
                                                                            .map((d) => ({
                                                                                value: d.id,
                                                                                label: `${d.identifiant} — ${d.agence} (${d.nombre_stagiaires})`,
                                                                            }))}
                                                                        onChange={(selected: any) => {
                                                                            const ids = (selected || []).map((s: any) => s.value);
                                                                            setSelectedMultiDossierIds(ids);
                                                                            setStagiairePage(1);
                                                                        }}
                                                                        placeholder="Rechercher et sélectionner des dossiers..."
                                                                        noOptionsMessage={() => 'Aucun dossier disponible'}
                                                                        isDisabled={isLoadingMultiDossiers}
                                                                        classNamePrefix="react-select"
                                                                        styles={{
                                                                            control: (base) => ({ ...base, minHeight: 38, borderColor: '#dee2e6', fontSize: 13 }),
                                                                            menu: (base) => ({ ...base, zIndex: 9999 }),
                                                                        }}
                                                                    />
                                                                </Col>
                                                            </Row>
                                                        </CardBody>
                                                    </Card>
                                                </Col>

                                                {/* ── Actions ── */}
                                                <Col lg={4} xl={3}>
                                                    <Card className="border shadow-none h-100">
                                                        <CardHeader className="bg-success bg-opacity-10 py-2">
                                                            <h6 className="card-title mb-0 fs-13 text-success">
                                                                <i className="ri-settings-3-line me-1"></i>Actions
                                                            </h6>
                                                        </CardHeader>
                                                        <CardBody className="py-2 d-flex flex-column gap-2">
                                                            <Button color="success" size="sm" block disabled={selectedMultiDossierIds.length === 0 || !moisDossiers || processing}
                                                                onClick={() => setModalMultiValiderOpen(true)}>
                                                                <i className="ri-check-double-line me-1"></i>Valider sélection
                                                            </Button>
                                                            <Button color="warning" size="sm" block disabled={selectedMultiDossierIds.length === 0}
                                                                onClick={() => setModalMultiAjournerDossierOpen(true)}>
                                                                <i className="ri-close-circle-line me-1"></i>Retirer le dossier
                                                            </Button>
                                                            <Button color="warning" size="sm" block disabled={selectedStagiaireIds.length === 0}
                                                                onClick={() => setModalMultiAjournerStagiaireOpen(true)}>
                                                                <i className="ri-user-unfollow-line me-1"></i>Retirer Stagiaire(s) ({selectedStagiaireIds.length})
                                                            </Button>
                                                            <hr className="my-1" />
                                                            <div className="d-flex gap-1">
                                                                <Button color="info" className="flex-fill" size="sm" disabled={selectedMultiDossierIds.length === 0}
                                                                    onClick={() => handleMultiGenererPdf('paiement')}>
                                                                    <i className="ri-file-text-line me-1"></i>État Paiement
                                                                </Button>
                                                                <Button color="info" className="flex-fill" size="sm" disabled={selectedMultiDossierIds.length === 0}
                                                                    onClick={() => handleMultiGenererPdf('attestations')}>
                                                                    <i className="ri-file-shield-line me-1"></i>ADD/ADP
                                                                </Button>
                                                            </div>
                                                            {selectedMultiDossierIds.length > 0 && (
                                                                <div className="text-center mt-1">
                                                                    <Badge color="success" pill className="fs-11">{selectedMultiDossierIds.length} dossier(s) sélectionné(s)</Badge>
                                                                </div>
                                                            )}
                                                        </CardBody>
                                                    </Card>
                                                </Col>
                                            </Row>

                                            {/* ── Tableau Stagiaires (serveur-side) ── */}
                                            {selectedMultiDossierIds.length > 0 && (
                                                <Card className="border shadow-none mb-3">
                                                    <CardHeader className="bg-info bg-opacity-10 py-2 d-flex justify-content-between align-items-center">
                                                        <h6 className="card-title mb-0 fs-13 text-info">
                                                            <i className="ri-user-search-line me-1"></i>Liste des Stagiaires
                                                            <Badge color="info" pill className="ms-2 fs-11">{stagiaireTotal}</Badge>
                                                        </h6>
                                                        <Input type="text" bsSize="sm" placeholder="Rechercher..." style={{ maxWidth: 220 }}
                                                            value={stagiaireSearch} onChange={(e) => { setStagiaireSearch(e.target.value); setStagiairePage(1); }} />
                                                    </CardHeader>
                                                    <CardBody className="p-0">
                                                        {stagiaireLoading ? (
                                                            <div className="d-flex justify-content-center py-4"><Spinner color="info" size="sm" /></div>
                                                        ) : stagiaires.length === 0 ? (
                                                            <p className="text-muted text-center py-4 mb-0"><i className="ri-inbox-line me-1"></i>Aucun stagiaire trouvé.</p>
                                                        ) : (
                                                            <div className="table-responsive">
                                                                <table className="table table-striped table-hover align-middle mb-0">
                                                                    <thead className="table-light text-uppercase fs-11 fw-semibold">
                                                                        <tr>
                                                                            <th style={{ width: 35 }}><Input type="checkbox" className="form-check-input"
                                                                                checked={stagiaires.length > 0 && selectedStagiaireIds.length === stagiaires.length}
                                                                                onChange={toggleAllStagiaires} /></th>
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
                                                                            <th className="text-center">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {stagiaires.map((s) => (
                                                                            <tr key={s.paiement_id}>
                                                                                <td><Input type="checkbox" className="form-check-input"
                                                                                    checked={selectedStagiaireIds.includes(s.paiement_id)}
                                                                                    onChange={() => toggleStagiaireSelection(s.paiement_id)} /></td>
                                                                                <td className="fs-12">{s.created_at}</td>
                                                                                <td>{s.agence}</td>
                                                                                <td className="text-truncate" style={{ maxWidth: 130 }}>{s.entreprise}</td>
                                                                                <td>{s.source_financement}</td>
                                                                                <td className="text-truncate" style={{ maxWidth: 100 }}>{s.type_stage}</td>
                                                                                <td className="text-muted">{s.numero_aej}</td>
                                                                                <td className="fw-semibold">{s.nom_prenoms}</td>
                                                                                <td className="fs-12">{s.date_naissance}</td>
                                                                                <td className="fs-12">{s.date_debut}</td>
                                                                                <td className="fs-12">{s.date_fin}</td>
                                                                                <td className="text-muted">{s.tresor_pay}</td>
                                                                                <td className="fw-bold text-success">{Number(s.montant || 0).toLocaleString('fr-FR')} FCFA</td>
                                                                                <td className="text-center">
                                                                                    <Button 
                                                                                        color="info" 
                                                                                        size="sm" 
                                                                                        className="btn-icon" 
                                                                                        outline
                                                                                        onClick={() => handlePreviewDocs(s)}
                                                                                        title="Prévisualiser les fichiers"
                                                                                    >
                                                                                        <i className="ri-folder-open-line"></i>
                                                                                    </Button>
                                                                                </td>
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        )}
                                                        {(() => {
                                                            const stPages = Math.ceil(stagiaireTotal / 10);
                                                            if (stPages <= 1) return null;
                                                            const maxVis = 7;
                                                            let stPageNums: (number | '...')[] = [];
                                                            if (stPages <= maxVis) { stPageNums = Array.from({ length: stPages }, (_, i) => i + 1); }
                                                            else {
                                                                stPageNums = [1];
                                                                if (stagiairePage > 3) stPageNums.push('...');
                                                                for (let i = Math.max(2, stagiairePage - 1); i <= Math.min(stPages - 1, stagiairePage + 1); i++) stPageNums.push(i);
                                                                if (stagiairePage < stPages - 2) stPageNums.push('...');
                                                                stPageNums.push(stPages);
                                                            }
                                                            return (
                                                                <div className="d-flex justify-content-between align-items-center p-2 border-top">
                                                                    <small className="text-muted">{stagiaireTotal} résultat(s) — Page {stagiairePage}/{stPages}</small>
                                                                    <div className="d-flex align-items-center gap-1">
                                                                        <Button size="sm" color="light" disabled={stagiairePage <= 1} onClick={() => setStagiairePage((p) => p - 1)}><i className="ri-arrow-left-s-line"></i></Button>
                                                                        {stPageNums.map((page, i) => page === '...' ? <span key={`mds-${i}`} className="px-1 text-muted">…</span> : <Button key={page} size="sm" color={page === stagiairePage ? 'primary' : 'light'} onClick={() => setStagiairePage(page)}>{page}</Button>)}
                                                                        <Button size="sm" color="light" disabled={stagiairePage >= stPages} onClick={() => setStagiairePage((p) => p + 1)}><i className="ri-arrow-right-s-line"></i></Button>
                                                                    </div>
                                                                </div>
                                                            );
                                                        })()}
                                                    </CardBody>
                                                </Card>
                                            )}

                                            {/* ── Multi-dossiers constitués ── */}
                                            <h5 className="fs-14 mb-3"><i className="ri-folder-shared-line me-1 text-warning"></i>Multi-dossiers constitués</h5>
                                            <TableContainerReactTable
                                                columns={[
                                                    { header: 'Numéro', cell: (c: any) => <span className="fw-medium text-primary">{c.row.original.numero}</span> },
                                                    { header: 'Nature', cell: (c: any) => c.row.original.nature },
                                                    { header: 'Financement', cell: (c: any) => c.row.original.source_financement?.nom || '-' },
                                                    { header: 'Dossiers', cell: (c: any) => <Badge color="info">{c.row.original.dossiers_count}</Badge> },
                                                    { header: 'Montant', cell: (c: any) => <span className="fw-bold">{Number(c.row.original.montant_total || 0).toLocaleString('fr-FR')} FCFA</span> },
                                                    { header: 'Statut', cell: (c: any) => <Badge color={getStatutBadge(c.row.original.statut)}>{c.row.original.statut}</Badge> },
                                                    { header: 'Actions', cell: (c: any) => (
                                                        <div className="d-flex gap-1">
                                                            {c.row.original.statut === 'BROUILLON' && (
                                                                <Button color="warning" size="sm" outline onClick={() => handleGenererPdfsGroupe(c.row.original.id)} title="Générer les PDFs"><i className="ri-file-pdf-2-line"></i></Button>
                                                            )}
                                                            {c.row.original.attestation_path && (
                                                                <Button color="info" size="sm" outline onClick={() => handleDownloadAttestation(c.row.original.id)} title="Attestation"><i className="ri-file-text-line"></i></Button>
                                                            )}
                                                            {c.row.original.etat_financier_path && (
                                                                <Button color="success" size="sm" outline onClick={() => handleDownloadEtatFinancier(c.row.original.id)} title="État financier"><i className="ri-money-dollar-circle-line"></i></Button>
                                                            )}
                                                            {c.row.original.statut === 'BROUILLON' && (
                                                                <Button color="info" size="sm" outline onClick={() => handleTransmettreGroupe(c.row.original.id)} title="Transmettre CB"><i className="ri-send-plane-line"></i></Button>
                                                            )}
                                                        </div>
                                                    ) },
                                                ]}
                                                data={groupesDossiers} isGlobalFilter={true} customPageSize={10}
                                                divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                        </TabPane>
                                        <TabPane tabId="ops">
                                            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                                <div>
                                                    <h5 className="fs-14 mb-1">OP en attente de bordereau</h5>
                                                    <p className="text-muted mb-0 fs-12">Le legacy limite un bordereau à 10 ordres de paiement.</p>
                                                </div>
                                                <Button color="success" size="sm" disabled={selectedOpIds.length === 0 || selectedOpIds.length > 10} onClick={handleCreerBordereauSelection}>
                                                    <i className="ri-file-shield-2-line me-1"></i>Créer le bordereau ({selectedOpIds.length}/10)
                                                </Button>
                                            </div>
                                            {opsEligiblesBordereau.length === 0 ? (
                                                <p className="text-muted text-center py-4"><i className="ri-inbox-line me-1"></i>Aucun ordre de paiement pour cette période.</p>
                                            ) : (
                                                <TableContainerReactTable
                                                    columns={[
                                                        { id: 'select', header: '', cell: (c: any) => <Input type="checkbox" checked={selectedOpIds.includes(c.row.original.id)} onChange={() => toggleSelection(c.row.original.id, setSelectedOpIds)} /> },
                                                        { header: 'Numéro', cell: (c: any) => <span className="fw-medium text-primary">{c.row.original.numero}</span> },
                                                        { header: 'Montant', cell: (c: any) => <span className="fw-bold">{Number(c.row.original.montant_total || 0).toLocaleString('fr-FR')} FCFA</span> },
                                                        { header: 'Statut', cell: (c: any) => <Badge color={getStatutBadge(c.row.original.statut)} className="fs-11">{c.row.original.statut}</Badge> },
                                                    ]}
                                                    data={opsEligiblesBordereau} isGlobalFilter={true} customPageSize={10}
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
                                                        { header: 'Actions', cell: (c: any) => c.row.original.statut === 'BROUILLON' ? <Button color="success" size="sm" outline onClick={() => handleTransmettreBordereau(c.row.original.id)}><i className="ri-send-plane-line me-1"></i>Transmettre AC</Button> : null },
                                                    ]}
                                                    data={bordereaux} isGlobalFilter={true} customPageSize={10}
                                                    divClass="table-responsive table-card mb-3" tableClass="table-striped align-middle table-nowrap mb-0" theadClass="table-light" />
                                            )}
                                        </TabPane>
                                    </TabContent>
                                        </Col>
                                    </Row>
                                </TabPane>
                            </TabContent>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════ MODALES ═══════ */}

            <Modal isOpen={modalGroupeOpen} toggle={() => !processing && setModalGroupeOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalGroupeOpen(false)}>
                    Créer un multi-dossier
                </ModalHeader>
                <ModalBody>
                    <Alert color="info" className="fs-12">
                        {selectedDossierIds.length} dossiers seront regroupés. Le serveur contrôle la période, la nature, le financement et l'absence d'OP ou de groupe actif.
                    </Alert>
                    <Label for="observation-groupe">Observation</Label>
                    <Input id="observation-groupe" type="textarea" rows={4} maxLength={1000} value={observationGroupe} onChange={(event) => setObservationGroupe(event.target.value)} disabled={processing} />
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalGroupeOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="warning" onClick={handleGrouperDossiers} disabled={processing || selectedDossierIds.length < 2}>
                        {processing ? <Spinner size="sm" className="me-1" /> : <i className="ri-folder-shared-line me-1"></i>}
                        Créer le multi-dossier
                    </Button>
                </ModalFooter>
            </Modal>

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

            {/* Modale — Prévisualisation des documents */}
            <Modal isOpen={modalPreviewOpen} toggle={() => setModalPreviewOpen(false)} size="xl" centered>
                <ModalHeader toggle={() => setModalPreviewOpen(false)} className="text-uppercase text-muted fs-15 fw-semibold border-bottom-0 pb-0">
                    Prévisualisation fichier
                </ModalHeader>
                <ModalBody className="pt-2 pb-4 px-4">
                    {isLoadingPreviewDocs ? (
                        <div className="d-flex justify-content-center py-5">
                            <Spinner color="primary" />
                        </div>
                    ) : previewDocs.length === 0 ? (
                        <div className="text-center py-5">
                            <i className="ri-file-close-line text-muted fs-48 mb-2 d-block"></i>
                            <p className="text-muted mb-0">Aucun document trouvé pour ce stagiaire.</p>
                        </div>
                    ) : (
                        <>
                            <Nav tabs className="nav-tabs-custom mb-3 border-bottom-0">
                                {previewDocs.map(doc => (
                                    <NavItem key={doc.id}>
                                        <NavLink
                                            style={{ cursor: 'pointer' }}
                                            className={classnames({ active: activePreviewTab === doc.id.toString() }, 'fw-semibold text-primary')}
                                            onClick={() => setActivePreviewTab(doc.id.toString())}
                                        >
                                            {(doc.type_nom || doc.type_code || doc.nom).toUpperCase()}
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>
                            <TabContent activeTab={activePreviewTab || ''}>
                                {previewDocs.map(doc => (
                                    <TabPane tabId={doc.id.toString()} key={doc.id}>
                                        <div className="border border-1" style={{ height: '70vh' }}>
                                            <iframe 
                                                src={`/storage/${doc.chemin}`} 
                                                width="100%" 
                                                height="100%" 
                                                style={{ border: 'none' }}
                                                title={doc.nom_original || doc.nom}
                                            />
                                        </div>
                                    </TabPane>
                                ))}
                            </TabContent>
                        </>
                    )}
                </ModalBody>
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

            {/* ═══════ MODALES MULTI-DOSSIER ═══════ */}

            {/* ─── Valider sélection ─── */}
            <Modal isOpen={modalMultiValiderOpen} toggle={() => !processing && setModalMultiValiderOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalMultiValiderOpen(false)} className="bg-success text-white">
                    <i className="ri-check-double-line me-2"></i>Validation de la Sélection
                </ModalHeader>
                <ModalBody>
                    <Alert color="info" className="mb-3">
                        Création d'un Multi-Dossier avec <strong className="text-primary">{selectedMultiDossierIds.length}</strong> dossier(s).
                    </Alert>
                    <Label className="form-label fw-semibold">Observation (optionnelle)</Label>
                    <Input type="textarea" rows={3} value={multiObservation}
                        onChange={(e) => setMultiObservation(e.target.value)}
                        placeholder="Note concernant ce Multi-Dossier..." disabled={processing} />
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalMultiValiderOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="success" onClick={handleMultiValiderSelection}
                        disabled={processing || selectedMultiDossierIds.length === 0 || !moisDossiers}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : <><i className="ri-check-double-line me-1"></i>Valider</>}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ─── Ajourner dossier ─── */}
            <Modal isOpen={modalMultiAjournerDossierOpen} toggle={() => !processing && setModalMultiAjournerDossierOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalMultiAjournerDossierOpen(false)}>
                    <i className="ri-close-circle-line me-2 text-warning"></i>Retirer le dossier
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">{selectedMultiDossierIds.length} dossier(s) seront retirés et leurs paiements remis en attente.</p>
                    <Label className="form-label fw-semibold">Motif <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={3} value={motifMultiAjournerDossier}
                        onChange={(e) => setMotifMultiAjournerDossier(e.target.value)}
                        placeholder="Motif du retrait..." disabled={processing} />
                    {motifMultiAjournerDossier.trim().length > 0 && motifMultiAjournerDossier.trim().length < 5 && (
                        <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalMultiAjournerDossierOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="warning" onClick={handleMultiAjournerDossier}
                        disabled={processing || motifMultiAjournerDossier.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ─── Ajourner stagiaire ─── */}
            <Modal isOpen={modalMultiAjournerStagiaireOpen} toggle={() => !processing && setModalMultiAjournerStagiaireOpen(false)} centered>
                <ModalHeader toggle={() => !processing && setModalMultiAjournerStagiaireOpen(false)}>
                    <i className="ri-user-unfollow-line me-2 text-warning"></i>Retirer le(s) stagiaire(s)
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted mb-3">{selectedStagiaireIds.length} stagiaire(s) seront retirés et remis en attente.</p>
                    <Label className="form-label fw-semibold">Motif <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={3} value={motifMultiAjournerStagiaire}
                        onChange={(e) => setMotifMultiAjournerStagiaire(e.target.value)}
                        placeholder="Motif du retrait..." disabled={processing} />
                    {motifMultiAjournerStagiaire.trim().length > 0 && motifMultiAjournerStagiaire.trim().length < 5 && (
                        <small className="text-danger">Le motif doit contenir au moins 5 caractères.</small>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalMultiAjournerStagiaireOpen(false)} disabled={processing}>Annuler</Button>
                    <Button color="warning" onClick={handleMultiAjournerStagiaire}
                        disabled={processing || motifMultiAjournerStagiaire.trim().length < 5}>
                        {processing ? <><Spinner size="sm" className="me-1" />Traitement...</> : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DmgPaiementsIndex;
