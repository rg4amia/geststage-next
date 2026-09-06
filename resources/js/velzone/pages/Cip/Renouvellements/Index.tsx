import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    FormFeedback,
    FormGroup,
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

/* ═══════════════════════════════════════════════════════════
   TYPES
   ═══════════════════════════════════════════════════════════ */

type RenewalTab = 'attente' | 'anticipe' | 'ajourne' | 'chef_validation';

interface LigneStage {
    id: number;
    stage_id: number;
    contrat_id: number | null;
    avenant_id: number | null;
    beneficiaire: {
        nom: string;
        prenoms: string;
        matricule: string;
        sexe: string;
        date_naissance: string;
        type_paiement: string;
        numero_tresor_money: string;
        numero_wave: string;
    };
    entreprise: string;
    entreprise_id: number | null;
    agence: string;
    agence_id: number | null;
    source_financement: string;
    source_financement_code: string;
    source_financement_id: number | null;
    type_stage: string;
    type_stage_id: number | null;
    type_structure: {
        id: number | null;
        nom: string | null;
        badge_couleur: 'success' | 'primary' | 'secondary' | 'warning';
    };
    created_at: string;
    date_demande: string | null;
    date_debut: string;
    date_fin_prevue: string;
    date_effet: string | null;
    nouvelle_date_fin: string | null;
    jours_restants: number | null;
    statut_renouvellement: string | null;
    motif: string | null;
    motif_ajournement: string | null;
    document_avenant_path: string | null;
    decideur_nom: string | null;
    decide_le: string | null;
    prime: number | null;
}

interface Pagination<T> {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    links: any[];
}

interface Props {
    onglet: RenewalTab;
    stages: Pagination<LigneStage>;
    compteurs: Record<RenewalTab, number>;
    filters: Record<string, string>;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    typesstructures: Record<string, string>;
    peutValiderCa: boolean;
}

interface FilterState {
    recherche: string;
    agence_id: string;
    entreprise_id: string;
    source_financement_id: string;
    type_stage_id: string;
    type_structure_id: string;
    date_debut: string;
    date_fin: string;
}

const DEFAULT_FILTERS: FilterState = {
    recherche: '',
    agence_id: '',
    entreprise_id: '',
    source_financement_id: '',
    type_stage_id: '',
    type_structure_id: '',
    date_debut: '',
    date_fin: '',
};

/* ═══════════════════════════════════════════════════════════
   CONSTANTES ONGLETS
   ═══════════════════════════════════════════════════════════ */

const ONGLETS: { id: RenewalTab; label: string; compteur: RenewalTab; color: string; icon: string }[] = [
    { id: 'attente', label: 'À renouveler', compteur: 'attente', color: 'warning', icon: 'ri-time-line' },
    { id: 'anticipe', label: 'Renouvellement anticipé', compteur: 'anticipe', color: 'info', icon: 'ri-calendar-event-line' },
    { id: 'ajourne', label: 'Ajourné / Chef agence', compteur: 'ajourne', color: 'danger', icon: 'ri-arrow-go-back-line' },
    { id: 'chef_validation', label: "Validation Chef d'agence", compteur: 'chef_validation', color: 'primary', icon: 'ri-shield-check-line' },
];

const MESSAGES_ONGLET: Record<RenewalTab, { color: string; icon: string; message: string }> = {
    attente: { color: 'warning', icon: 'ri-time-line', message: "Stagiaires arrivés à terme et disponibles pour renouvellement." },
    anticipe: { color: 'info', icon: 'ri-calendar-event-line', message: "Stagiaires dont le contrat peut être anticipé avant échéance (≤ 10 jours)." },
    ajourne: { color: 'danger', icon: 'ri-arrow-go-back-line', message: "Renouvellements retournés par le Chef d'agence pour correction CIP." },
    chef_validation: { color: 'primary', icon: 'ri-shield-check-line', message: "Renouvellements soumis par les CIP en attente de décision Chef d'agence." },
};

/* ═══════════════════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════════════════ */

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

const formatInputDate = (date: string | null | undefined): string => {
    if (!date) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}/.test(date)) {
        return date.slice(0, 10);
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(date)) {
        const [day, month, year] = date.split('/');

        return `${year}-${month}-${day}`;
    }

    return date;
};

const nonEmptyParams = (filters: FilterState): Record<string, string> => {
    const params: Record<string, string> = {};

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '') {
            params[key] = value;
        }
    });

    return params;
};

const statusBadge = (statut: string | null, fallbackTab: RenewalTab) => {
    const fallback = {
        attente: 'À renouveler',
        anticipe: 'Anticipé',
        ajourne: 'Ajourné',
        chef_validation: 'Attente CA',
    }[fallbackTab];

    const map: Record<string, { color: string; label: string }> = {
        ATTENTE_CA: { color: 'primary', label: 'Attente CA' },
        VALIDE: { color: 'success', label: 'Validé' },
        AJOURNE: { color: 'danger', label: 'Ajourné' },
    };
    const item = statut && map[statut] ? map[statut] : { color: 'secondary', label: statut || fallback };

    return <span className={`badge bg-${item.color}-subtle text-${item.color}`}>{item.label}</span>;
};

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */

const CipRenouvellementsIndex = ({
    onglet = 'attente',
    stages,
    compteurs,
    filters = {},
    agences = {},
    entreprises = {},
    typesfinancements = {},
    typestages = {},
    typesstructures = {},
    peutValiderCa = false,
}: Props) => {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    const [currentTab, setCurrentTab] = useState<RenewalTab>(onglet || 'attente');
    const [selectedFilters, setSelectedFilters] = useState<FilterState>({
        ...DEFAULT_FILTERS,
        recherche: filters.recherche ?? '',
        agence_id: filters.agence_id ?? '',
        entreprise_id: filters.entreprise_id ?? '',
        source_financement_id: filters.source_financement_id ?? '',
        type_stage_id: filters.type_stage_id ?? '',
        type_structure_id: filters.type_structure_id ?? '',
        date_debut: filters.date_debut ?? '',
        date_fin: filters.date_fin ?? '',
    });
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    /* ─── Modale renouvellement ─── */
    const [aRenouveler, setARenouveler] = useState<LigneStage | null>(null);
    const [dureeMois, setDureeMois] = useState('6');
    const [dateDebutAvenant, setDateDebutAvenant] = useState('');
    const [dateFinAvenant, setDateFinAvenant] = useState('');
    const [typeStructureId, setTypeStructureId] = useState('');
    const [prime, setPrime] = useState('');
    const [observation, setObservation] = useState('');
    const [contratAvenant, setContratAvenant] = useState<File | null>(null);
    const [contratAvenantError, setContratAvenantError] = useState('');
    const [enCours, setEnCours] = useState(false);

    /* ─── Modale ajournement ─── */
    const [aAjourner, setAAjourner] = useState<LigneStage | null>(null);
    const [isAjournementGroupe, setIsAjournementGroupe] = useState(false);
    const [motifAjournement, setMotifAjournement] = useState('');
    const [ajournementError, setAjournementError] = useState('');

    /* ─── Modale détail ─── */
    const [detailStage, setDetailStage] = useState<LigneStage | null>(null);

    /* ─── Onglets filtrés (seulement les autorisés) ─── */
    const ongletsVisibles = useMemo(
        () => ONGLETS.filter((o) => peutValiderCa || o.id !== 'chef_validation'),
        [peutValiderCa],
    );

    /* ─── Navigation / Filtres ─── */
    const naviguer = (tab: RenewalTab, filtres: FilterState, extra: Record<string, string | number> = {}) => {
        router.get(
            '/cip/renouvellements',
            { onglet: tab, ...nonEmptyParams(filtres), ...extra },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleTab = (tab: RenewalTab) => {
        if (currentTab === tab) {
            return;
        }

        setCurrentTab(tab);
        setSelectedIds([]);
        naviguer(tab, selectedFilters, { page: 1 });
    };

    const handleFilterChange = (field: keyof FilterState, value: string) => {
        setSelectedFilters((prev) => ({ ...prev, [field]: value }));
    };

    const appliquerFiltres = () => {
        setSelectedIds([]);
        naviguer(currentTab, selectedFilters, { page: 1 });
    };

    const reinitialiser = () => {
        setSelectedFilters(DEFAULT_FILTERS);
        setSelectedIds([]);
        naviguer(currentTab, DEFAULT_FILTERS, { page: 1 });
    };

    const exporter = () => {
        const params = new URLSearchParams({ onglet: currentTab, ...nonEmptyParams(selectedFilters) });

        window.location.href = `/cip/renouvellements/export?${params.toString()}`;
    };

    /* ─── Actions CIP ─── */
    const calculerDateFin = (dateDebutStr: string, mois: number): string => {
        if (!dateDebutStr) {
            return '';
        }

        const date = new Date(dateDebutStr);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const day = date.getDate();
        date.setMonth(date.getMonth() + mois);

        if (date.getDate() !== day) {
            date.setDate(0);
        }

        return date.toISOString().split('T')[0];
    };

    const ouvrirModalRenouveler = (ligne: LigneStage) => {
        const dateDebutDefaut = formatInputDate(ligne.date_fin_prevue) || new Date().toISOString().split('T')[0];

        setARenouveler(ligne);
        setDureeMois('6');
        setDateDebutAvenant(dateDebutDefaut);
        setDateFinAvenant(calculerDateFin(dateDebutDefaut, 6));
        setObservation('');
        setContratAvenant(null);
        setContratAvenantError('');
        setTypeStructureId(ligne.type_structure?.id ? String(ligne.type_structure.id) : '');
        setPrime(ligne.prime !== null ? String(ligne.prime) : '');
    };

    const fermerModalRenouveler = () => {
        setARenouveler(null);
        setContratAvenant(null);
        setContratAvenantError('');
        setObservation('');
    };

    const codeFinancement = (aRenouveler?.source_financement_code ?? '').toUpperCase();
    const isBudgetAej = codeFinancement.includes('BUDGET');
    const isC2dOrPejedec = codeFinancement.includes('C2D') || codeFinancement.includes('PEJEDEC');
    const isPejedec = codeFinancement.includes('PEJEDEC');
    const jourDateDebut = dateDebutAvenant ? new Date(dateDebutAvenant).getDate() : null;
    const isJourCohorteValide = !jourDateDebut || isPejedec || [1, 2, 3, 4, 5, 10, 20].includes(jourDateDebut);

    const isFormRenouvellementValide = useMemo(() => {
        if (!aRenouveler || !dateDebutAvenant || !isJourCohorteValide) {
            return false;
        }

        if (!observation.trim() || !contratAvenant) {
            return false;
        }

        if (isBudgetAej && !typeStructureId) {
            return false;
        }

        if (isC2dOrPejedec && (prime === '' || Number(prime) < 0)) {
            return false;
        }

        return true;
    }, [aRenouveler, contratAvenant, dateDebutAvenant, isBudgetAej, isC2dOrPejedec, isJourCohorteValide, observation, prime, typeStructureId]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            setContratAvenant(null);
            setContratAvenantError('');

            return;
        }

        const ext = file.name.split('.').pop()?.toLowerCase();

        if (!ext || !['pdf', 'doc', 'docx'].includes(ext)) {
            setContratAvenant(null);
            setContratAvenantError('Le fichier doit être au format PDF, DOC ou DOCX.');

            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setContratAvenant(null);
            setContratAvenantError('Le fichier ne doit pas dépasser 10 Mo.');

            return;
        }

        setContratAvenant(file);
        setContratAvenantError('');
    };

    const soumettreRenouvellement = (e: React.FormEvent) => {
        e.preventDefault();

        if (!aRenouveler || !isFormRenouvellementValide || enCours) {
            return;
        }

        setEnCours(true);

        const formData = new FormData();
        formData.append('duree_mois', dureeMois);
        formData.append('date_debut_avenant', dateDebutAvenant);
        formData.append('observation', observation);

        if (contratAvenant) {
            formData.append('contrat_avenant', contratAvenant);
        }

        if (typeStructureId) {
            formData.append('type_structure_id', typeStructureId);
        }

        if (prime !== '') {
            formData.append('prime', prime);
        }

        router.post(`/cip/renouvellements/${aRenouveler.id}/renouveler`, formData as any, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: fermerModalRenouveler,
            onFinish: () => setEnCours(false),
        });
    };

    const renvoyerAuChefAgence = (ligne: LigneStage) => {
        if (!ligne.avenant_id) {
            return;
        }

        router.post(`/cip/renouvellements/avenant/${ligne.avenant_id}/renvoyer`, {}, { preserveScroll: true });
    };

    const validerParChefAgence = (ligne: LigneStage) => {
        if (!ligne.avenant_id) {
            return;
        }

        if (window.confirm(`Confirmez-vous la validation du renouvellement de ${ligne.beneficiaire.nom} ${ligne.beneficiaire.prenoms} ?`)) {
            router.post(`/cip/renouvellements/avenant/${ligne.avenant_id}/valider`, {}, { preserveScroll: true });
        }
    };

    const ouvrirModalAjourner = (ligne: LigneStage) => {
        setAAjourner(ligne);
        setIsAjournementGroupe(false);
        setMotifAjournement('');
        setAjournementError('');
    };

    const ouvrirModalAjournementGroupe = () => {
        if (selectedIds.length === 0) {
            return;
        }

        setAAjourner(null);
        setIsAjournementGroupe(true);
        setMotifAjournement('');
        setAjournementError('');
    };

    const fermerModalAjournement = () => {
        setAAjourner(null);
        setIsAjournementGroupe(false);
        setMotifAjournement('');
        setAjournementError('');
    };

    const confirmerAjournement = () => {
        if (!motifAjournement.trim() || motifAjournement.trim().length < 3) {
            setAjournementError("L'observation ou motif de correction est obligatoire (minimum 3 caractères).");

            return;
        }

        if (isAjournementGroupe) {
            router.post(
                '/cip/renouvellements/avenants/ajourner-groupe',
                {
                    avenant_ids: selectedIds,
                    observation: motifAjournement,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        fermerModalAjournement();
                        setSelectedIds([]);
                    },
                },
            );

            return;
        }

        if (!aAjourner?.avenant_id) {
            return;
        }

        router.post(
            `/cip/renouvellements/avenant/${aAjourner.avenant_id}/ajourner`,
            { observation: motifAjournement },
            {
                preserveScroll: true,
                onSuccess: fermerModalAjournement,
            },
        );
    };

    const validerSelectionGroupe = () => {
        if (selectedIds.length === 0) {
            return;
        }

        if (window.confirm(`Confirmez-vous la validation des ${selectedIds.length} renouvellement(s) sélectionné(s) ?`)) {
            router.post(
                '/cip/renouvellements/avenants/valider-groupe',
                { avenant_ids: selectedIds },
                {
                    preserveScroll: true,
                    onSuccess: () => setSelectedIds([]),
                },
            );
        }
    };

    const renouvelerSelectionGroupe = () => {
        if (selectedIds.length === 0) {
            return;
        }

        const mois = window.prompt('Nombre de mois pour le renouvellement groupé :', '6');

        if (!mois) {
            return;
        }

        router.post(
            '/cip/renouvellements/renouveler-groupe',
            {
                stage_ids: selectedIds,
                duree_mois: Number(mois) || 6,
            },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedIds([]),
            },
        );
    };

    const telechargerAvenantPdf = (
        stageId: number,
        dateDebut?: string,
        dateFin?: string,
        montantPrime?: string | number,
    ) => {
        const params = new URLSearchParams({
            stage_id: String(stageId),
            ...(dateDebut ? { date_debut: dateDebut } : {}),
            ...(dateFin ? { date_fin: dateFin } : {}),
            ...(montantPrime !== undefined && montantPrime !== '' ? { prime: String(montantPrime) } : {}),
        });

        window.open(`/cip/renouvellements/avenant/generer-pdf?${params.toString()}`, '_blank');
    };

    /* ─── Sélection ─── */
    const selectableIds = useMemo(
        () =>
            (stages?.data ?? [])
                .map((stage) => (currentTab === 'chef_validation' ? stage.avenant_id : stage.id))
                .filter((id): id is number => Boolean(id)),
        [currentTab, stages?.data],
    );

    const toggleSelectAll = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSelectedIds(e.target.checked ? selectableIds : []);
    };

    const toggleSelectRow = (targetId: number | null) => {
        if (!targetId) {
            return;
        }

        setSelectedIds((prev) => (prev.includes(targetId) ? prev.filter((id) => id !== targetId) : [...prev, targetId]));
    };

    const exportParams = useMemo(
        () => new URLSearchParams({ onglet: currentTab, ...nonEmptyParams(selectedFilters) }).toString(),
        [currentTab, selectedFilters],
    );

    /* ═══════════════════════════════════════════════════════════
       RENDU
       ═══════════════════════════════════════════════════════════ */

    return (
        <React.Fragment>
            <Head title="Espace CIP - Renouvellements de contrat" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Renouvellements de contrat" pageTitle="Stagiaires" />

                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-check-double-line me-2 align-middle"></i>
                            {flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-error-warning-line me-2 align-middle"></i>
                            {flash.error}
                        </Alert>
                    )}

                    <Card>
                        {/* ─── Onglets dans le CardHeader (style Visas) ─── */}
                        <CardHeader className="border-0 pb-0">
                            <Nav tabs className="nav-tabs-custom nav-success flex-wrap">
                                {ongletsVisibles.map((item) => (
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
                                                {compteurs[item.compteur] ?? 0}
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
                                        {Object.entries(agences).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
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
                                        {Object.entries(entreprises).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
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
                                        {Object.entries(typesfinancements).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
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
                                        {Object.entries(typestages).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Type de structure</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.type_structure_id}
                                        onChange={(e) => handleFilterChange('type_structure_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {Object.entries(typesstructures).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Date début (à partir du)</Label>
                                    <Input
                                        type="date"
                                        value={selectedFilters.date_debut}
                                        onChange={(e) => handleFilterChange('date_debut', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Date fin (jusqu'au)</Label>
                                    <Input
                                        type="date"
                                        value={selectedFilters.date_fin}
                                        onChange={(e) => handleFilterChange('date_fin', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Recherche</Label>
                                    <Input
                                        type="text"
                                        value={selectedFilters.recherche}
                                        onChange={(e) => handleFilterChange('recherche', e.target.value)}
                                        placeholder="Nom, AEJ, entreprise…"
                                    />
                                </Col>
                                <Col md={6} className="d-flex align-items-end gap-2">
                                    <Button color="primary" onClick={appliquerFiltres}>
                                        <i className="ri-search-line align-bottom me-1" /> Filtrer
                                    </Button>
                                    <Button color="light" onClick={reinitialiser}>Réinitialiser</Button>
                                    <Button color="success" outline onClick={exporter}>
                                        <i className="ri-download-2-line align-bottom me-1" /> Export CSV
                                    </Button>
                                </Col>
                            </Row>
                        </CardBody>

                        {/* ─── Tableau (CardBody dédié, style Visas) ─── */}
                        <CardBody>
                            {selectedIds.length > 0 && (
                                <Alert color="info" className="border-0 d-flex align-items-center justify-content-between py-2 mb-3">
                                    <span className="fw-semibold">
                                        <i className="ri-checkbox-line me-1"></i>
                                        {selectedIds.length} dossier(s) sélectionné(s)
                                    </span>
                                    <div className="d-flex gap-2">
                                        {(currentTab === 'attente' || currentTab === 'anticipe') && (
                                            <Button color="success" size="sm" onClick={renouvelerSelectionGroupe}>
                                                <i className="ri-loop-right-line me-1"></i>Renouveler la sélection
                                            </Button>
                                        )}
                                        {currentTab === 'chef_validation' && (
                                            <>
                                                <Button color="success" size="sm" onClick={validerSelectionGroupe}>
                                                    <i className="ri-check-double-line me-1"></i>Valider la sélection
                                                </Button>
                                                <Button color="danger" size="sm" outline onClick={ouvrirModalAjournementGroupe}>
                                                    <i className="ri-close-circle-line me-1"></i>Ajourner la sélection
                                                </Button>
                                            </>
                                        )}
                                        <Button color="soft-secondary" size="sm" onClick={() => setSelectedIds([])}>
                                            Désélectionner
                                        </Button>
                                    </div>
                                </Alert>
                            )}

                            <div className="table-responsive">
                                <Table className="table-sm align-middle table-nowrap mb-0">
                                    <thead className="table-light">
                                        <tr>
                                            <th style={{ width: 40 }}>
                                                <div className="form-check">
                                                    <input
                                                        type="checkbox"
                                                        className="form-check-input"
                                                        checked={selectableIds.length > 0 && selectedIds.length === selectableIds.length}
                                                        onChange={toggleSelectAll}
                                                    />
                                                </div>
                                            </th>
                                            <th>Bénéficiaire</th>
                                            <th>N° AEJ</th>
                                            <th>Début</th>
                                            <th>Fin prévue</th>
                                            {currentTab === 'ajourne' && <th>Nouvelle fin</th>}
                                            {currentTab === 'anticipe' && <th>Jours restants</th>}
                                            <th>Entreprise</th>
                                            <th>Agence</th>
                                            <th>Financement</th>
                                            <th>Type stage</th>
                                            <th>Structure</th>
                                            <th>Paiement</th>
                                            <th>Statut</th>
                                            {currentTab === 'ajourne' && <th>Motif CA</th>}
                                            {currentTab === 'chef_validation' && <th>Demande</th>}
                                            <th className="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(stages?.data ?? []).map((row) => {
                                            const rowId = currentTab === 'chef_validation' ? row.avenant_id : row.id;

                                            return (
                                                <tr key={row.id}>
                                                    <td>
                                                        <div className="form-check">
                                                            <input
                                                                type="checkbox"
                                                                className="form-check-input"
                                                                disabled={!rowId}
                                                                checked={Boolean(rowId && selectedIds.includes(rowId))}
                                                                onChange={() => toggleSelectRow(rowId)}
                                                            />
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div className="fw-semibold text-dark">
                                                            {row.beneficiaire?.nom} {row.beneficiaire?.prenoms}
                                                        </div>
                                                        <div className="text-muted small">{row.beneficiaire?.sexe || '-'}</div>
                                                    </td>
                                                    <td>{row.beneficiaire?.matricule || '-'}</td>
                                                    <td className="text-nowrap">{formatDateFr(row.date_debut)}</td>
                                                    <td className="text-nowrap">{formatDateFr(row.date_fin_prevue)}</td>
                                                    {currentTab === 'ajourne' && (
                                                        <td className="text-nowrap text-success fw-semibold">
                                                            {row.nouvelle_date_fin ? formatDateFr(row.nouvelle_date_fin) : '-'}
                                                        </td>
                                                    )}
                                                    {currentTab === 'anticipe' && (
                                                        <td>
                                                            {row.jours_restants !== null ? (
                                                                <Badge color="info" pill>J-{row.jours_restants}</Badge>
                                                            ) : (
                                                                '-'
                                                            )}
                                                        </td>
                                                    )}
                                                    <td>
                                                        <div className="text-truncate" style={{ maxWidth: 180 }} title={row.entreprise}>
                                                            {row.entreprise || '-'}
                                                        </div>
                                                    </td>
                                                    <td>{row.agence || '-'}</td>
                                                    <td>{row.source_financement || '-'}</td>
                                                    <td>{row.type_stage || '-'}</td>
                                                    <td>
                                                        <Badge color={row.type_structure?.badge_couleur || 'secondary'} pill>
                                                            {row.type_structure?.nom || 'Non renseigné'}
                                                        </Badge>
                                                    </td>
                                                    <td>
                                                        <span className="badge bg-light text-dark">{row.beneficiaire?.type_paiement || '-'}</span>
                                                        {row.beneficiaire?.numero_tresor_money && row.beneficiaire.numero_tresor_money !== '-' && (
                                                            <div className="text-muted small">TM: {row.beneficiaire.numero_tresor_money}</div>
                                                        )}
                                                        {row.beneficiaire?.numero_wave && row.beneficiaire.numero_wave !== '-' && (
                                                            <div className="text-muted small">Wave: {row.beneficiaire.numero_wave}</div>
                                                        )}
                                                    </td>
                                                    <td>{statusBadge(row.statut_renouvellement, currentTab)}</td>
                                                    {currentTab === 'ajourne' && (
                                                        <td>
                                                            <span className="text-danger fw-semibold small">
                                                                {row.motif_ajournement || '-'}
                                                            </span>
                                                        </td>
                                                    )}
                                                    {currentTab === 'chef_validation' && (
                                                        <td>
                                                            <div className="small">
                                                                <div>{row.date_demande || row.created_at || '-'}</div>
                                                                {row.motif && (
                                                                    <div className="text-muted text-truncate" style={{ maxWidth: 180 }}>
                                                                        {row.motif}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </td>
                                                    )}
                                                    <td className="text-end">
                                                        <div className="d-flex gap-1 justify-content-end">
                                                            {currentTab === 'chef_validation' && (
                                                                <>
                                                                    <Button
                                                                        color="light"
                                                                        size="sm"
                                                                        title="Voir les détails"
                                                                        onClick={() => setDetailStage(row)}
                                                                    >
                                                                        <i className="ri-eye-line align-bottom"></i>
                                                                    </Button>
                                                                    <Button
                                                                        color="success"
                                                                        size="sm"
                                                                        onClick={() => validerParChefAgence(row)}
                                                                    >
                                                                        <i className="ri-check-line me-1"></i>Valider
                                                                    </Button>
                                                                    <Button
                                                                        color="danger"
                                                                        size="sm"
                                                                        onClick={() => ouvrirModalAjourner(row)}
                                                                    >
                                                                        <i className="ri-close-line me-1"></i>Ajourner
                                                                    </Button>
                                                                </>
                                                            )}
                                                            {currentTab === 'ajourne' && (
                                                                <Button
                                                                    color="primary"
                                                                    size="sm"
                                                                    onClick={() => renvoyerAuChefAgence(row)}
                                                                >
                                                                    <i className="ri-send-plane-line me-1"></i>Renvoyer au CA
                                                                </Button>
                                                            )}
                                                            {(currentTab === 'attente' || currentTab === 'anticipe') && (
                                                                <Button
                                                                    color="success"
                                                                    size="sm"
                                                                    onClick={() => ouvrirModalRenouveler(row)}
                                                                >
                                                                    <i className="ri-loop-right-line me-1"></i>Renouveler
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        {(stages?.data ?? []).length === 0 && (
                                            <tr>
                                                <td colSpan={14} className="text-center">
                                                    Aucun dossier dans cette corbeille.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </Table>
                            </div>

                            {stages && (
                                <ServerPagination pagination={normalizePagination(stages)} itemLabel="dossiers" />
                            )}
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* ═══════════════════════════════════════════
                MODALE 1 — Renouvellement de contrat
               ═══════════════════════════════════════════ */}
            <Modal isOpen={aRenouveler !== null} toggle={fermerModalRenouveler} size="lg" centered>
                <ModalHeader toggle={fermerModalRenouveler}>
                    <i className="ri-loop-right-line me-2 text-success"></i>
                    Renouveler le contrat de stage
                </ModalHeader>
                <form onSubmit={soumettreRenouvellement}>
                    <ModalBody>
                        {aRenouveler && (
                            <div className="bg-light p-3 rounded mb-3">
                                <Row>
                                    <Col md={6}>
                                        <div className="mb-1">
                                            <strong>Bénéficiaire :</strong> {aRenouveler.beneficiaire.nom} {aRenouveler.beneficiaire.prenoms}
                                        </div>
                                        <div className="mb-1">
                                            <strong>Matricule AEJ :</strong> {aRenouveler.beneficiaire.matricule || '-'}
                                        </div>
                                    </Col>
                                    <Col md={6}>
                                        <div className="mb-1">
                                            <strong>Financement :</strong> {aRenouveler.source_financement}
                                        </div>
                                        <div className="mb-1">
                                            <strong>Fin initiale :</strong> {aRenouveler.date_fin_prevue}
                                        </div>
                                    </Col>
                                </Row>
                            </div>
                        )}

                        <Row className="g-3">
                            <Col md={6}>
                                <FormGroup>
                                    <Label for="duree_mois">
                                        Durée du renouvellement <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        id="duree_mois"
                                        type="select"
                                        value={dureeMois}
                                        onChange={(e) => {
                                            const value = e.target.value;

                                            setDureeMois(value);
                                            setDateFinAvenant(calculerDateFin(dateDebutAvenant, Number(value)));
                                        }}
                                        required
                                    >
                                        {[1, 2, 3, 6, 9, 12].map((month) => (
                                            <option key={month} value={month}>
                                                {month} mois
                                            </option>
                                        ))}
                                    </Input>
                                </FormGroup>
                            </Col>
                            <Col md={6}>
                                <FormGroup>
                                    <Label for="date_debut_avenant">
                                        Date début avenant <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        id="date_debut_avenant"
                                        type="date"
                                        value={dateDebutAvenant}
                                        invalid={!isJourCohorteValide}
                                        onChange={(e) => {
                                            const value = e.target.value;

                                            setDateDebutAvenant(value);
                                            setDateFinAvenant(calculerDateFin(value, Number(dureeMois)));
                                        }}
                                        required
                                    />
                                    {!isJourCohorteValide && (
                                        <FormFeedback>
                                            Pour ce financement, le jour doit être le 1 au 5, 10 ou 20 du mois.
                                        </FormFeedback>
                                    )}
                                </FormGroup>
                            </Col>
                            <Col md={6}>
                                <FormGroup>
                                    <Label for="date_fin_avenant">Date fin avenant calculée</Label>
                                    <Input id="date_fin_avenant" type="date" value={dateFinAvenant} readOnly className="bg-light" />
                                </FormGroup>
                            </Col>
                            {isBudgetAej && (
                                <Col md={6}>
                                    <FormGroup>
                                        <Label for="type_structure_id">
                                            Type de structure <span className="text-danger">* (BUDGET AEJ)</span>
                                        </Label>
                                        <Input
                                            id="type_structure_id"
                                            type="select"
                                            value={typeStructureId}
                                            onChange={(e) => setTypeStructureId(e.target.value)}
                                            required
                                        >
                                            <option value="">Sélectionner une structure</option>
                                            {Object.entries(typesstructures).map(([id, nom]) => (
                                                <option key={id} value={id}>{nom}</option>
                                            ))}
                                        </Input>
                                    </FormGroup>
                                </Col>
                            )}
                            {isC2dOrPejedec && (
                                <Col md={6}>
                                    <FormGroup>
                                        <Label for="prime">
                                            Montant de la prime mensuelle <span className="text-danger">*</span>
                                        </Label>
                                        <Input
                                            id="prime"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            placeholder="Ex: 45000"
                                            value={prime}
                                            onChange={(e) => setPrime(e.target.value)}
                                            required
                                        />
                                    </FormGroup>
                                </Col>
                            )}
                            <Col md={12}>
                                <FormGroup>
                                    <Label for="contrat_avenant">
                                        Joindre le contrat d'avenant signé <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        id="contrat_avenant"
                                        type="file"
                                        accept=".pdf,.doc,.docx"
                                        invalid={Boolean(contratAvenantError)}
                                        onChange={handleFileChange}
                                        required
                                    />
                                    {contratAvenantError ? (
                                        <FormFeedback>{contratAvenantError}</FormFeedback>
                                    ) : (
                                        <small className="text-muted">PDF, DOC ou DOCX — maximum 10 Mo</small>
                                    )}
                                </FormGroup>
                            </Col>
                            <Col md={12}>
                                <FormGroup>
                                    <Label for="observation">
                                        Observation / motif du renouvellement <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        id="observation"
                                        type="textarea"
                                        rows={3}
                                        placeholder="Précisez les justifications du renouvellement..."
                                        value={observation}
                                        onChange={(e) => setObservation(e.target.value)}
                                        required
                                    />
                                </FormGroup>
                            </Col>
                        </Row>
                    </ModalBody>
                    <ModalFooter className="d-flex justify-content-between">
                        <div>
                            {aRenouveler && (
                                <Button
                                    type="button"
                                    color="info"
                                    outline
                                    onClick={() => telechargerAvenantPdf(aRenouveler.id, dateDebutAvenant, dateFinAvenant, prime)}
                                >
                                    <i className="ri-printer-line me-1"></i>Générer avenant PDF
                                </Button>
                            )}
                        </div>
                        <div className="d-flex gap-2">
                            <Button type="button" color="light" onClick={fermerModalRenouveler} disabled={enCours}>
                                Annuler
                            </Button>
                            <Button type="submit" color="success" disabled={!isFormRenouvellementValide || enCours}>
                                {enCours ? (
                                    <>
                                        <Spinner size="sm" className="me-2" />
                                        Envoi...
                                    </>
                                ) : (
                                    <>
                                        <i className="ri-send-plane-line me-1"></i>Soumettre au chef d'agence
                                    </>
                                )}
                            </Button>
                        </div>
                    </ModalFooter>
                </form>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE 2 — Ajourner le renouvellement
               ═══════════════════════════════════════════ */}
            <Modal isOpen={aAjourner !== null || isAjournementGroupe} toggle={fermerModalAjournement} centered>
                <ModalHeader toggle={fermerModalAjournement} className="bg-danger-subtle">
                    <i className="ri-error-warning-line me-2 text-danger"></i>
                    Ajourner le renouvellement
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        {isAjournementGroupe
                            ? `Vous allez ajourner ${selectedIds.length} renouvellement(s) sélectionné(s).`
                            : `Ajournement du renouvellement de ${aAjourner?.beneficiaire.nom} ${aAjourner?.beneficiaire.prenoms}.`}
                    </p>
                    <FormGroup className="mb-0">
                        <Label for="motifAjournement">
                            Motif du refus / corrections demandées au CIP <span className="text-danger">*</span>
                        </Label>
                        <Input
                            id="motifAjournement"
                            type="textarea"
                            rows={4}
                            invalid={Boolean(ajournementError)}
                            placeholder="Indiquez la raison du rejet pour permettre au CIP de corriger..."
                            value={motifAjournement}
                            onChange={(e) => {
                                setMotifAjournement(e.target.value);
                                setAjournementError('');
                            }}
                            required
                        />
                        {ajournementError && <FormFeedback>{ajournementError}</FormFeedback>}
                    </FormGroup>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={fermerModalAjournement}>
                        Annuler
                    </Button>
                    <Button color="danger" onClick={confirmerAjournement}>
                        <i className="ri-close-circle-line me-1"></i>Confirmer l'ajournement
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ═══════════════════════════════════════════
                MODALE 3 — Détails du renouvellement
               ═══════════════════════════════════════════ */}
            <Modal isOpen={detailStage !== null} toggle={() => setDetailStage(null)} size="lg" centered>
                <ModalHeader toggle={() => setDetailStage(null)}>
                    <i className="ri-eye-line me-2 text-primary"></i>
                    Détails du renouvellement de stage
                </ModalHeader>
                <ModalBody>
                    {detailStage && (
                        <Row className="g-3">
                            <Col md={6}>
                                <h6 className="text-primary border-bottom pb-1">Bénéficiaire</h6>
                                <div><strong>Nom & prénoms :</strong> {detailStage.beneficiaire.nom} {detailStage.beneficiaire.prenoms}</div>
                                <div><strong>Matricule AEJ :</strong> {detailStage.beneficiaire.matricule || '-'}</div>
                                <div><strong>Date de naissance :</strong> {detailStage.beneficiaire.date_naissance || '-'}</div>
                                <div><strong>Sexe :</strong> {detailStage.beneficiaire.sexe || '-'}</div>
                                <div><strong>Moyen paiement :</strong> {detailStage.beneficiaire.type_paiement || '-'}</div>
                            </Col>
                            <Col md={6}>
                                <h6 className="text-primary border-bottom pb-1">Stage & entreprise</h6>
                                <div><strong>Entreprise :</strong> {detailStage.entreprise || '-'}</div>
                                <div><strong>Agence :</strong> {detailStage.agence || '-'}</div>
                                <div><strong>Financement :</strong> {detailStage.source_financement || '-'}</div>
                                <div><strong>Type stage :</strong> {detailStage.type_stage || '-'}</div>
                                <div><strong>Type structure :</strong> {detailStage.type_structure?.nom || 'Non renseigné'}</div>
                            </Col>
                            <Col md={12}>
                                <h6 className="text-primary border-bottom pb-1">Dates & avenant</h6>
                                <div><strong>Période initiale :</strong> Du {detailStage.date_debut || '-'} au {detailStage.date_fin_prevue || '-'}</div>
                                {detailStage.nouvelle_date_fin && (
                                    <div className="text-success fw-bold">
                                        <strong>Nouvelle date fin demandée :</strong> {detailStage.nouvelle_date_fin}
                                    </div>
                                )}
                                {detailStage.motif && (
                                    <div className="mt-2">
                                        <strong>Motif proposé :</strong> {detailStage.motif}
                                    </div>
                                )}
                                {detailStage.document_avenant_path && (
                                    <div className="mt-2">
                                        <strong>Fichier joint :</strong>
                                        <a
                                            href={`/storage/${detailStage.document_avenant_path}`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="btn btn-sm btn-outline-primary ms-2"
                                        >
                                            <i className="ri-attachment-line me-1"></i>Télécharger le document joint
                                        </a>
                                    </div>
                                )}
                            </Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    {detailStage && (
                        <Button
                            color="info"
                            outline
                            onClick={() => telechargerAvenantPdf(
                                detailStage.id,
                                detailStage.date_debut,
                                detailStage.nouvelle_date_fin || undefined,
                                detailStage.prime || undefined,
                            )}
                        >
                            <i className="ri-file-pdf-line me-1"></i>Aperçu avenant PDF
                        </Button>
                    )}
                    <Button color="light" onClick={() => setDetailStage(null)}>
                        Fermer
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CipRenouvellementsIndex;
