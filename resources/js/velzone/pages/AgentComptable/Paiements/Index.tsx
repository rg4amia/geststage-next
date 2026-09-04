import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import classnames from 'classnames';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    Col,
    Container,
    FormFeedback,
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
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import './ac-paiements.scss';

type Ordre = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    nombre_dossiers: number;
    nombre_paiements: number;
    agences: string;
};

type Bordereau = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    date_transmission?: string;
    date_traitement?: string;
    motif?: string;
    agences?: string;
    nombre_ordres: number;
    nombre_dossiers: number;
    nombre_paiements: number;
    source_financement?: { code?: string; libelle?: string };
    ordres: Ordre[];
};

type Props = {
    bordereauxAttente?: Bordereau[];
    bordereauxRejetes?: Bordereau[];
    bordereauxVises?: Bordereau[];
    moisActuel: string;
    periodesDisponibles?: Array<{ code: string; count: number }>;
};

type Stagiaire = {
    paiement_id: number;
    statut_paiement: string;
    montant: string | number;
    beneficiaire_id?: number;
    numero_aej?: string;
    nom?: string;
    prenoms?: string;
    date_naissance?: string;
    numero_tresor_money?: string;
    entreprise?: string;
    type_stage?: string;
    date_debut?: string;
    date_fin?: string;
};

type DossierDetail = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    date_creation?: string;
    agence?: string;
    source_financement?: string;
    stagiaires: Stagiaire[];
};

type LigneStagiaire = {
    stagiaire: Stagiaire;
    dossier: DossierDetail;
};

type OngletStagiaire = 'attente' | 'valide' | 'rejete' | 'differe';

type OptionReferentiel = { id: number; libelle: string };

type OrdreDetail = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    onglet: OngletStagiaire;
    compteurs: Record<OngletStagiaire, number>;
    actions: {
        valider: boolean;
        differer: boolean;
        differer_stagiaires: boolean;
        rejeter: boolean;
        retirer: boolean;
    };
    referentiels: {
        agences: OptionReferentiel[];
        entreprises: OptionReferentiel[];
        sources_financement: OptionReferentiel[];
        types_stage: OptionReferentiel[];
    };
    dossiers: DossierDetail[];
};

type FiltresOp = {
    agence_id: string;
    entreprise_id: string;
    source_financement_id: string;
    type_stage_id: string;
    recherche: string;
};

type ActionOp = 'retirer' | 'differer' | 'rejeter';

const filtresVides: FiltresOp = {
    agence_id: '',
    entreprise_id: '',
    source_financement_id: '',
    type_stage_id: '',
    recherche: '',
};

const ongletsStagiaires: Array<{
    id: OngletStagiaire;
    libelle: string;
    icone: string;
    couleur: string;
}> = [
    {
        id: 'attente',
        libelle: 'OP en attente',
        icone: 'ri-time-line',
        couleur: 'warning',
    },
    {
        id: 'valide',
        libelle: 'OP validé',
        icone: 'ri-checkbox-circle-line',
        couleur: 'success',
    },
    {
        id: 'rejete',
        libelle: 'OP rejeté',
        icone: 'ri-close-circle-line',
        couleur: 'danger',
    },
    {
        id: 'differe',
        libelle: 'OP différé',
        icone: 'ri-pause-circle-line',
        couleur: 'secondary',
    },
];

type FlashProps = {
    flash?: { success?: string; error?: string };
    errors?: { motif?: string; bordereau?: string; ordre?: string };
};

const formatMontant = (valeur: string | number) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(
        Number(valeur || 0),
    );

const libelleMois = (code: string) => {
    const [annee, mois] = code.split('-').map(Number);

    if (!annee || !mois) {
        return code;
    }

    return new Intl.DateTimeFormat('fr-FR', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(annee, mois - 1, 1));
};

const statutsOp: Record<
    string,
    {
        libelle: string;
        couleur: 'warning' | 'success' | 'danger' | 'secondary';
    }
> = {
    EN_BORDEREAU: { libelle: 'En bordereau', couleur: 'warning' },
    VISE_AC: { libelle: 'Visé AC', couleur: 'success' },
    REJETE_AC_DEFINITIF: { libelle: 'Rejeté AC', couleur: 'danger' },
    DIFFERE_AC: { libelle: 'Différé AC', couleur: 'secondary' },
    REJETE_DMG: { libelle: 'Retour DMG', couleur: 'danger' },
    DIFFERE_DMG: { libelle: 'Retour DMG', couleur: 'warning' },
};

const libelleStatutOp = (statut: string) =>
    statutsOp[statut]?.libelle ??
    statut
        .replaceAll('_', ' ')
        .toLowerCase()
        .replace(/^./, (lettre) => lettre.toUpperCase());

const actionsOp: Record<
    ActionOp,
    {
        titre: string;
        description: string;
        bouton: string;
        color: 'warning' | 'danger';
    }
> = {
    retirer: {
        titre: 'Retirer cette OP du bordereau ?',
        description:
            'L’OP restera disponible pour être intégrée à un nouveau bordereau par la DMG.',
        bouton: 'Retirer l’OP',
        color: 'warning',
    },
    differer: {
        titre: 'Différer toute cette OP ?',
        description:
            'Tous ses dossiers et paiements seront retournés à la DMG pour correction.',
        bouton: 'Différer l’OP',
        color: 'warning',
    },
    rejeter: {
        titre: 'Rejeter toute cette OP ?',
        description:
            'Tous les paiements de l’OP seront marqués comme rejetés par l’Agent Comptable.',
        bouton: 'Rejeter l’OP',
        color: 'danger',
    },
};

function StatCard({
    icon,
    label,
    value,
    hint,
    tone,
}: {
    icon: string;
    label: string;
    value: string | number;
    hint: string;
    tone: 'warning' | 'success' | 'danger' | 'info';
}) {
    return (
        <Card className={`ac-stat-card ac-stat-card--${tone} mb-0 h-100`}>
            <CardBody className="d-flex align-items-center gap-3">
                <span className="ac-stat-card__icon" aria-hidden="true">
                    <i className={icon} />
                </span>
                <div className="min-w-0">
                    <div className="text-uppercase fw-semibold fs-12 text-muted">
                        {label}
                    </div>
                    <div className="fs-4 fw-bold text-dark lh-sm mt-1">
                        {value}
                    </div>
                    <div className="fs-12 mt-1 text-muted">{hint}</div>
                </div>
            </CardBody>
        </Card>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="ac-empty-state">
            <span className="ac-empty-state__icon">
                <i className="ri-inbox-archive-line" />
            </span>
            <h5 className="mb-1">Aucun résultat</h5>
            <p className="mb-0 text-muted">{text}</p>
        </div>
    );
}

export default function AcPaiementsIndex({
    bordereauxAttente = [],
    bordereauxRejetes = [],
    bordereauxVises = [],
    moisActuel,
    periodesDisponibles = [],
}: Props) {
    const { flash, errors } = usePage<FlashProps>().props;
    const [activeTab, setActiveTab] = useState<'attente' | 'rejetes' | 'vises'>(
        'attente',
    );
    const [recherche, setRecherche] = useState('');
    const [details, setDetails] = useState<Bordereau | null>(null);
    const [ordreDetail, setOrdreDetail] = useState<OrdreDetail | null>(null);
    const [loadingOrdre, setLoadingOrdre] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [ordreAValider, setOrdreAValider] = useState<Ordre | null>(null);
    const [actionOp, setActionOp] = useState<{
        ordre: Ordre;
        type: ActionOp;
    } | null>(null);
    const [motifOp, setMotifOp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [ongletStagiaire, setOngletStagiaire] =
        useState<OngletStagiaire>('attente');
    const [filtresOp, setFiltresOp] = useState<FiltresOp>(filtresVides);
    const [selection, setSelection] = useState<number[]>([]);
    const [differePartiel, setDifferePartiel] = useState(false);
    const workspaceRef = useRef<HTMLElement | null>(null);
    const listeRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (details) {
            workspaceRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
    }, [details]);

    const montantAttente = useMemo(
        () =>
            bordereauxAttente.reduce(
                (total, bordereau) =>
                    total + Number(bordereau.montant_total || 0),
                0,
            ),
        [bordereauxAttente],
    );
    const nombreOrdres = useMemo(
        () =>
            bordereauxAttente.reduce(
                (total, bordereau) => total + bordereau.nombre_ordres,
                0,
            ),
        [bordereauxAttente],
    );
    const montantVise = useMemo(
        () =>
            bordereauxVises.reduce(
                (total, bordereau) =>
                    total + Number(bordereau.montant_total || 0),
                0,
            ),
        [bordereauxVises],
    );

    const filtrer = (liste: Bordereau[]) => {
        const terme = recherche.trim().toLocaleLowerCase('fr');

        if (!terme) {
            return liste;
        }

        return liste.filter((bordereau) =>
            [
                bordereau.numero,
                bordereau.agences,
                bordereau.source_financement?.libelle,
                ...bordereau.ordres.map((ordre) => ordre.numero),
            ]
                .filter(Boolean)
                .some((valeur) =>
                    String(valeur).toLocaleLowerCase('fr').includes(terme),
                ),
        );
    };

    const attenteFiltree = useMemo(
        () => filtrer(bordereauxAttente),
        [bordereauxAttente, recherche],
    );
    const rejetesFiltres = useMemo(
        () => filtrer(bordereauxRejetes),
        [bordereauxRejetes, recherche],
    );
    const visesFiltres = useMemo(
        () => filtrer(bordereauxVises),
        [bordereauxVises, recherche],
    );
    const nbResultats = useMemo(() => {
        if (activeTab === 'vises') {
            return visesFiltres.length;
        }

        if (activeTab === 'rejetes') {
            return rejetesFiltres.length;
        }

        return attenteFiltree.length;
    }, [activeTab, attenteFiltree, visesFiltres, rejetesFiltres]);

    const periodes = useMemo(() => {
        const options = [...periodesDisponibles];

        if (!options.some((periode) => periode.code === moisActuel)) {
            options.unshift({
                code: moisActuel,
                count: bordereauxAttente.length,
            });
        }

        return options;
    }, [periodesDisponibles, moisActuel, bordereauxAttente.length]);

    const changerMois = (mois: string) => {
        router.get(
            '/agent-comptable/paiements',
            { mois },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    const chargerOrdre = async (
        ordreId: number,
        onglet: OngletStagiaire = ongletStagiaire,
        filtres: FiltresOp = filtresOp,
    ) => {
        setLoadingOrdre(true);
        setDetailError('');
        setSelection([]);

        try {
            const response = await axios.get<OrdreDetail>(
                `/agent-comptable/paiements/ordres/${ordreId}/details`,
                {
                    params: { onglet, ...filtres },
                },
            );
            setOrdreDetail(response.data);
        } catch {
            setOrdreDetail(null);
            setDetailError(
                'Impossible de charger les dossiers et les stagiaires de cette OP.',
            );
        } finally {
            setLoadingOrdre(false);
        }
    };

    const changerOrdreSel = (ordreId: number) => {
        setOngletStagiaire('attente');
        setFiltresOp(filtresVides);
        setSelection([]);
        chargerOrdre(ordreId, 'attente', filtresVides);
    };

    const changerOnglet = (onglet: OngletStagiaire) => {
        setOngletStagiaire(onglet);

        if (ordreDetail) {
            chargerOrdre(ordreDetail.id, onglet);
        }
    };

    const appliquerFiltres = (filtres: FiltresOp) => {
        setFiltresOp(filtres);

        if (ordreDetail) {
            chargerOrdre(ordreDetail.id, ongletStagiaire, filtres);
        }
    };

    const ouvrirDetails = (bordereau: Bordereau) => {
        setDetails(bordereau);
        setFiltresOp(filtresVides);
        const ordreInitial =
            bordereau.ordres.find((ordre) => ordre.statut === 'EN_BORDEREAU') ||
            bordereau.ordres[0];

        if (ordreInitial) {
            const ongletInitial: OngletStagiaire =
                ordreInitial.statut === 'VISE_AC'
                    ? 'valide'
                    : ordreInitial.statut.includes('REJETE')
                      ? 'rejete'
                      : ordreInitial.statut.includes('DIFFERE')
                        ? 'differe'
                        : 'attente';
            setOngletStagiaire(ongletInitial);
            setSelection([]);
            chargerOrdre(ordreInitial.id, ongletInitial, filtresVides);
        } else {
            setOrdreDetail(null);
        }
    };

    const fermerDetails = () => {
        setDetails(null);
        setOrdreDetail(null);
        setDetailError('');
        setSelection([]);
        listeRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    };

    const ouvrirDepuisLigne = (
        event: React.MouseEvent<HTMLTableRowElement>,
        bordereau: Bordereau,
    ) => {
        if ((event.target as HTMLElement).closest('button, a, input, label')) {
            return;
        }

        ouvrirDetails(bordereau);
    };

    const lignesAffichees = useMemo<LigneStagiaire[]>(
        () =>
            (ordreDetail?.dossiers || []).flatMap((dossier) =>
                dossier.stagiaires.map((stagiaire) => ({ stagiaire, dossier })),
            ),
        [ordreDetail],
    );
    const stagiairesAffiches = useMemo(
        () => lignesAffichees.map((ligne) => ligne.stagiaire),
        [lignesAffichees],
    );
    const selectionnables = useMemo(
        () =>
            ongletStagiaire === 'attente'
                ? stagiairesAffiches.map((stagiaire) => stagiaire.paiement_id)
                : [],
        [stagiairesAffiches, ongletStagiaire],
    );
    const toutSelectionne =
        selectionnables.length > 0 &&
        selectionnables.every((id) => selection.includes(id));

    const basculerSelection = (paiementId: number) => {
        setSelection((actuelle) =>
            actuelle.includes(paiementId)
                ? actuelle.filter((id) => id !== paiementId)
                : [...actuelle, paiementId],
        );
    };

    const confirmerDifferePartiel = () => {
        if (!ordreDetail || !selection.length || motifOp.trim().length < 5) {
            return;
        }

        setProcessing(true);
        router.post(
            `/agent-comptable/paiements/ordres/${ordreDetail.id}/differer-stagiaires`,
            {
                motif: motifOp,
                paiement_ids: selection,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDifferePartiel(false);
                    setMotifOp('');
                    fermerDetails();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmerValidationOp = () => {
        if (!ordreAValider) {
            return;
        }

        setProcessing(true);
        router.post(
            `/agent-comptable/paiements/ordres/${ordreAValider.id}/valider`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOrdreAValider(null);
                    fermerDetails();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const confirmerActionOp = () => {
        if (!actionOp || motifOp.trim().length < 5) {
            return;
        }

        setProcessing(true);
        router.post(
            `/agent-comptable/paiements/ordres/${actionOp.ordre.id}/${actionOp.type}`,
            { motif: motifOp },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setActionOp(null);
                    setMotifOp('');
                    fermerDetails();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const ouvrirActionOp = (ordre: Ordre, type: ActionOp) => {
        setActionOp({ ordre, type });
        setMotifOp('');
    };

    const ordreSelectionne = details?.ordres.find(
        (ordre) => ordre.id === ordreDetail?.id,
    );

    return (
        <>
            <Head title="Traitement des bordereaux - Agent Comptable" />
            <div className="page-content ac-payments-page">
                <Container fluid>
                    <BreadCrumb
                        title="Traitement des bordereaux"
                        pageTitle="Agent comptable"
                    />
                    {(flash?.success ||
                        flash?.error ||
                        errors?.bordereau ||
                        errors?.ordre) && (
                        <Alert
                            color={flash?.success ? 'success' : 'danger'}
                            className="d-flex align-items-center gap-2"
                        >
                            <i
                                className={
                                    flash?.success
                                        ? 'ri-checkbox-circle-line'
                                        : 'ri-error-warning-line'
                                }
                            />
                            {flash?.success ||
                                flash?.error ||
                                errors?.bordereau ||
                                errors?.ordre}
                        </Alert>
                    )}

                    <Row className="g-3 mb-4">
                        <Col xl={3} md={6}>
                            <StatCard
                                icon="ri-time-line"
                                label="À traiter"
                                value={bordereauxAttente.length}
                                hint={`${nombreOrdres} ordre${nombreOrdres > 1 ? 's' : ''} de paiement`}
                                tone="warning"
                            />
                        </Col>
                        <Col xl={3} md={6}>
                            <StatCard
                                icon="ri-money-dollar-circle-line"
                                label="Montant en attente"
                                value={`${formatMontant(montantAttente)} FCFA`}
                                hint="Montant cumulé à contrôler"
                                tone="success"
                            />
                        </Col>
                        <Col xl={3} md={6}>
                            <StatCard
                                icon="ri-checkbox-circle-line"
                                label="Visés"
                                value={bordereauxVises.length}
                                hint={`${formatMontant(montantVise)} FCFA validés`}
                                tone="info"
                            />
                        </Col>
                        <Col xl={3} md={6}>
                            <StatCard
                                icon="ri-arrow-go-back-line"
                                label="Retours AC"
                                value={bordereauxRejetes.length}
                                hint="Différés et rejets définitifs"
                                tone="danger"
                            />
                        </Col>
                    </Row>

                    <Card
                        className="ac-workspace-card border-0"
                        innerRef={listeRef}
                    >
                        <CardBody className="p-0">
                            <div className="ac-toolbar p-lg-4 p-3">
                                <Row className="align-items-end g-3">
                                    <Col lg={4} md={6}>
                                        <Label className="form-label text-uppercase fs-12 fw-semibold">
                                            Période
                                        </Label>
                                        <div className="position-relative">
                                            <i className="ri-calendar-event-line ac-input-icon" />
                                            <Input
                                                className="ac-control ps-5"
                                                type="select"
                                                value={moisActuel}
                                                onChange={(event) =>
                                                    changerMois(
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {periodes.map((periode) => (
                                                    <option
                                                        key={periode.code}
                                                        value={periode.code}
                                                    >
                                                        {libelleMois(
                                                            periode.code,
                                                        )}{' '}
                                                        — {periode.count} en
                                                        attente
                                                    </option>
                                                ))}
                                            </Input>
                                        </div>
                                    </Col>
                                    <Col lg={5} md={6}>
                                        <Label className="form-label text-uppercase fs-12 fw-semibold">
                                            Recherche rapide
                                        </Label>
                                        <div className="position-relative">
                                            <i className="ri-search-line ac-input-icon" />
                                            <Input
                                                className="ac-control ps-5"
                                                value={recherche}
                                                onChange={(event) =>
                                                    setRecherche(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Bordereau, OP, agence ou financement…"
                                            />
                                            {recherche && (
                                                <Button
                                                    color="link"
                                                    className="ac-clear-search"
                                                    onClick={() =>
                                                        setRecherche('')
                                                    }
                                                    aria-label="Effacer la recherche"
                                                >
                                                    <i className="ri-close-line" />
                                                </Button>
                                            )}
                                        </div>
                                    </Col>
                                    <Col lg={3} className="text-lg-end">
                                        <div className="fs-13 pb-2 text-muted">
                                            <i className="ri-information-line me-1" />
                                            {recherche ? (
                                                <>
                                                    {nbResultats} bordereau
                                                    {nbResultats > 1
                                                        ? 'x'
                                                        : ''}{' '}
                                                    trouvé
                                                    {nbResultats > 1 ? 's' : ''}
                                                </>
                                            ) : (
                                                'Cliquez sur un bordereau pour ouvrir le détail de ses OP'
                                            )}
                                        </div>
                                    </Col>
                                </Row>
                            </div>

                            <Nav
                                tabs
                                className="ac-tabs px-lg-4 px-3"
                                role="tablist"
                            >
                                <NavItem>
                                    <NavLink
                                        role="tab"
                                        aria-selected={activeTab === 'attente'}
                                        className={classnames({
                                            active: activeTab === 'attente',
                                        })}
                                        onClick={() => setActiveTab('attente')}
                                    >
                                        <i className="ri-time-line me-2" />
                                        En attente{' '}
                                        <Badge
                                            pill
                                            color="warning"
                                            className="ms-2"
                                        >
                                            {bordereauxAttente.length}
                                        </Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink
                                        role="tab"
                                        aria-selected={activeTab === 'vises'}
                                        className={classnames({
                                            active: activeTab === 'vises',
                                        })}
                                        onClick={() => setActiveTab('vises')}
                                    >
                                        <i className="ri-checkbox-circle-line me-2" />
                                        Visés AC{' '}
                                        <Badge
                                            pill
                                            color="success"
                                            className="ms-2"
                                        >
                                            {bordereauxVises.length}
                                        </Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink
                                        role="tab"
                                        aria-selected={activeTab === 'rejetes'}
                                        className={classnames({
                                            active: activeTab === 'rejetes',
                                        })}
                                        onClick={() => setActiveTab('rejetes')}
                                    >
                                        <i className="ri-arrow-go-back-line me-2" />
                                        Retours DMG{' '}
                                        <Badge
                                            pill
                                            color="danger"
                                            className="ms-2"
                                        >
                                            {bordereauxRejetes.length}
                                        </Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent
                                activeTab={activeTab}
                                className="p-lg-4 pt-lg-3 p-3"
                            >
                                <TabPane tabId="attente">
                                    {attenteFiltree.length ? (
                                        <div className="table-responsive ac-table-wrap">
                                            <Table
                                                hover
                                                className="ac-table mb-0 align-middle"
                                            >
                                                <thead>
                                                    <tr>
                                                        <th>Bordereau</th>
                                                        <th>
                                                            Financement /
                                                            agences
                                                        </th>
                                                        <th>Composition</th>
                                                        <th className="text-end">
                                                            Montant
                                                        </th>
                                                        <th>Transmission</th>
                                                        <th className="text-end">
                                                            Actions
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {attenteFiltree.map(
                                                        (bordereau) => (
                                                            <tr
                                                                key={
                                                                    bordereau.id
                                                                }
                                                                className="ac-bordereau-row"
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    ouvrirDepuisLigne(
                                                                        event,
                                                                        bordereau,
                                                                    )
                                                                }
                                                            >
                                                                <td>
                                                                    <button
                                                                        type="button"
                                                                        className="ac-number-link"
                                                                        onClick={() =>
                                                                            ouvrirDetails(
                                                                                bordereau,
                                                                            )
                                                                        }
                                                                    >
                                                                        {
                                                                            bordereau.numero
                                                                        }
                                                                    </button>
                                                                    <div className="fs-12 mt-1 text-muted">
                                                                        {
                                                                            bordereau.nombre_paiements
                                                                        }{' '}
                                                                        paiement(s)
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div className="fw-medium">
                                                                        {bordereau
                                                                            .source_financement
                                                                            ?.libelle ||
                                                                            'Non renseigné'}
                                                                    </div>
                                                                    <div
                                                                        className="fs-12 text-truncate ac-agencies text-muted"
                                                                        title={
                                                                            bordereau.agences
                                                                        }
                                                                    >
                                                                        {bordereau.agences ||
                                                                            'Aucune agence'}
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <Badge
                                                                        color="info"
                                                                        className="bg-info-subtle text-info me-1"
                                                                    >
                                                                        {
                                                                            bordereau.nombre_ordres
                                                                        }{' '}
                                                                        OP
                                                                    </Badge>
                                                                    <Badge
                                                                        color="secondary"
                                                                        className="bg-secondary-subtle text-secondary"
                                                                    >
                                                                        {
                                                                            bordereau.nombre_dossiers
                                                                        }{' '}
                                                                        dossiers
                                                                    </Badge>
                                                                </td>
                                                                <td className="fw-semibold text-dark text-end">
                                                                    {formatMontant(
                                                                        bordereau.montant_total,
                                                                    )}{' '}
                                                                    <span className="fs-11 text-muted">
                                                                        FCFA
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <i className="ri-calendar-line me-1 text-muted" />
                                                                    {bordereau.date_transmission ||
                                                                        '—'}
                                                                </td>
                                                                <td className="text-end text-nowrap">
                                                                    <Button
                                                                        size="sm"
                                                                        color="primary"
                                                                        onClick={() =>
                                                                            ouvrirDetails(
                                                                                bordereau,
                                                                            )
                                                                        }
                                                                    >
                                                                        <i className="ri-list-check-2 me-1" />
                                                                        Traiter
                                                                        les OP
                                                                    </Button>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </Table>
                                        </div>
                                    ) : (
                                        <EmptyState
                                            text={
                                                recherche
                                                    ? 'Aucun bordereau ne correspond à la recherche.'
                                                    : 'Aucun bordereau éligible pour cette période.'
                                            }
                                        />
                                    )}
                                </TabPane>

                                <TabPane tabId="vises">
                                    {visesFiltres.length ? (
                                        <div className="table-responsive ac-table-wrap">
                                            <Table
                                                hover
                                                className="ac-table mb-0 align-middle"
                                            >
                                                <thead>
                                                    <tr>
                                                        <th>Bordereau</th>
                                                        <th>Financement</th>
                                                        <th>Paiements</th>
                                                        <th className="text-end">
                                                            Montant
                                                        </th>
                                                        <th>Date du visa</th>
                                                        <th>Statut</th>
                                                        <th className="text-end">
                                                            Actions
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {visesFiltres.map(
                                                        (bordereau) => (
                                                            <tr
                                                                key={
                                                                    bordereau.id
                                                                }
                                                                className="ac-bordereau-row"
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    ouvrirDepuisLigne(
                                                                        event,
                                                                        bordereau,
                                                                    )
                                                                }
                                                            >
                                                                <td>
                                                                    <button
                                                                        type="button"
                                                                        className="ac-number-link"
                                                                        onClick={() =>
                                                                            ouvrirDetails(
                                                                                bordereau,
                                                                            )
                                                                        }
                                                                    >
                                                                        {
                                                                            bordereau.numero
                                                                        }
                                                                    </button>
                                                                </td>
                                                                <td>
                                                                    {bordereau
                                                                        .source_financement
                                                                        ?.libelle ||
                                                                        '—'}
                                                                </td>
                                                                <td>
                                                                    <div>
                                                                        {
                                                                            bordereau.nombre_paiements
                                                                        }
                                                                    </div>
                                                                    <small className="fs-12 text-muted">
                                                                        {
                                                                            bordereau.nombre_ordres
                                                                        }{' '}
                                                                        OP
                                                                    </small>
                                                                </td>
                                                                <td className="fw-semibold text-end">
                                                                    {formatMontant(
                                                                        bordereau.montant_total,
                                                                    )}{' '}
                                                                    FCFA
                                                                </td>
                                                                <td>
                                                                    {bordereau.date_traitement ||
                                                                        '—'}
                                                                </td>
                                                                <td>
                                                                    <Badge
                                                                        color="success"
                                                                        className="bg-success-subtle text-success"
                                                                    >
                                                                        <i className="ri-checkbox-circle-line me-1" />
                                                                        Visé AC
                                                                    </Badge>
                                                                </td>
                                                                <td className="text-end text-nowrap">
                                                                    <Button
                                                                        size="sm"
                                                                        color="light"
                                                                        onClick={() =>
                                                                            ouvrirDetails(
                                                                                bordereau,
                                                                            )
                                                                        }
                                                                    >
                                                                        <i className="ri-eye-line me-1" />
                                                                        Consulter
                                                                    </Button>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </Table>
                                        </div>
                                    ) : (
                                        <EmptyState text="Aucun bordereau visé pour cette période." />
                                    )}
                                </TabPane>

                                <TabPane tabId="rejetes">
                                    {rejetesFiltres.length ? (
                                        <div className="table-responsive ac-table-wrap">
                                            <Table
                                                hover
                                                className="ac-table mb-0 align-middle"
                                            >
                                                <thead>
                                                    <tr>
                                                        <th>Bordereau</th>
                                                        <th>Décision</th>
                                                        <th>Motif</th>
                                                        <th className="text-end">
                                                            Montant
                                                        </th>
                                                        <th>Date</th>
                                                        <th />
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {rejetesFiltres.map(
                                                        (bordereau) => (
                                                            <tr
                                                                key={
                                                                    bordereau.id
                                                                }
                                                                className="ac-bordereau-row"
                                                                onClick={(
                                                                    event,
                                                                ) =>
                                                                    ouvrirDepuisLigne(
                                                                        event,
                                                                        bordereau,
                                                                    )
                                                                }
                                                            >
                                                                <td className="fw-semibold">
                                                                    {
                                                                        bordereau.numero
                                                                    }
                                                                </td>
                                                                <td>
                                                                    <Badge
                                                                        color={
                                                                            bordereau.statut ===
                                                                            'REJETE_AC_DEFINITIF'
                                                                                ? 'danger'
                                                                                : 'warning'
                                                                        }
                                                                        className={
                                                                            bordereau.statut ===
                                                                            'REJETE_AC_DEFINITIF'
                                                                                ? ''
                                                                                : 'bg-warning-subtle text-warning'
                                                                        }
                                                                    >
                                                                        {bordereau.statut ===
                                                                        'REJETE_AC_DEFINITIF'
                                                                            ? 'Rejet définitif'
                                                                            : 'Traité avec retour'}
                                                                    </Badge>
                                                                </td>
                                                                <td
                                                                    className="ac-motif-cell"
                                                                    title={
                                                                        bordereau.motif
                                                                    }
                                                                >
                                                                    {bordereau.motif ||
                                                                        'Voir les décisions des OP'}
                                                                </td>
                                                                <td className="fw-semibold text-end">
                                                                    {formatMontant(
                                                                        bordereau.montant_total,
                                                                    )}{' '}
                                                                    FCFA
                                                                </td>
                                                                <td>
                                                                    {bordereau.date_traitement ||
                                                                        '—'}
                                                                </td>
                                                                <td className="text-end">
                                                                    <Button
                                                                        size="sm"
                                                                        color="light"
                                                                        onClick={() =>
                                                                            ouvrirDetails(
                                                                                bordereau,
                                                                            )
                                                                        }
                                                                    >
                                                                        <i className="ri-eye-line me-1" />
                                                                        Consulter
                                                                    </Button>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </Table>
                                        </div>
                                    ) : (
                                        <EmptyState text="Aucun retour ou rejet pour cette période." />
                                    )}
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>

                    {details && (
                        <section
                            ref={workspaceRef}
                            className="ac-workspace-section"
                            aria-label={`Traitement du bordereau ${details.numero}`}
                        >
                            <Card className="ac-ws-head-card border-0">
                                <CardBody className="p-3">
                                    <Row className="align-items-center g-3">
                                        <Col
                                            md={7}
                                            className="d-flex align-items-center gap-3"
                                        >
                                            <Button
                                                color="light"
                                                size="sm"
                                                className="ac-ws-back"
                                                onClick={fermerDetails}
                                                aria-label="Retour à la liste des bordereaux"
                                            >
                                                <i className="ri-arrow-left-line" />
                                            </Button>
                                            <div className="min-w-0">
                                                <span className="fs-12 text-uppercase text-muted">
                                                    Bordereau sélectionné
                                                </span>
                                                <h5 className="text-truncate mb-0">
                                                    {details.numero}
                                                </h5>
                                            </div>
                                        </Col>
                                        <Col
                                            md={5}
                                            className="ac-ws-summary d-flex justify-content-md-end flex-wrap gap-2"
                                        >
                                            <div className="ac-ws-chip">
                                                <span>Montant total</span>
                                                <strong>
                                                    {formatMontant(
                                                        details.montant_total,
                                                    )}{' '}
                                                    FCFA
                                                </strong>
                                            </div>
                                            <div className="ac-ws-chip">
                                                <span>OP traitées</span>
                                                <strong>
                                                    {
                                                        details.ordres.filter(
                                                            (ordre) =>
                                                                ordre.statut !==
                                                                'EN_BORDEREAU',
                                                        ).length
                                                    }{' '}
                                                    / {details.nombre_ordres}
                                                </strong>
                                            </div>
                                            <div className="ac-ws-chip">
                                                <span>Financement</span>
                                                <strong className="text-truncate">
                                                    {details.source_financement
                                                        ?.libelle ||
                                                        'Non renseigné'}
                                                </strong>
                                            </div>
                                        </Col>
                                    </Row>
                                </CardBody>
                            </Card>

                            <Card className="ac-filter-card border-0">
                                <CardBody className="p-lg-4 p-3">
                                    <div className="ac-card-heading">
                                        <h4 className="mb-0">
                                            <i className="ri-filter-3-line me-2" />
                                            Filtres de recherche
                                        </h4>
                                        <span className="fs-12 text-muted">
                                            Sélectionnez l’OP puis affinez la
                                            liste des stagiaires
                                        </span>
                                    </div>
                                    <Row className="g-3 align-items-end">
                                        <Col lg={3} md={6}>
                                            <Label className="form-label ac-field-label">
                                                N° OP
                                            </Label>
                                            <Input
                                                className="ac-control"
                                                type="select"
                                                value={ordreDetail?.id ?? ''}
                                                disabled={loadingOrdre}
                                                onChange={(event) => {
                                                    const ordreId = Number(
                                                        event.target.value,
                                                    );

                                                    if (ordreId) {
                                                        changerOrdreSel(
                                                            ordreId,
                                                        );
                                                    }
                                                }}
                                            >
                                                <option value="">
                                                    Sélectionner OP
                                                </option>
                                                {details.ordres.map((ordre) => (
                                                    <option
                                                        key={ordre.id}
                                                        value={ordre.id}
                                                    >
                                                        {ordre.numero} —{' '}
                                                        {libelleStatutOp(
                                                            ordre.statut,
                                                        )}{' '}
                                                        (
                                                        {ordre.nombre_paiements}{' '}
                                                        paiement
                                                        {ordre.nombre_paiements >
                                                        1
                                                            ? 's'
                                                            : ''}
                                                        )
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col lg={3} md={6}>
                                            <Label className="form-label ac-field-label">
                                                Agence
                                            </Label>
                                            <Input
                                                className="ac-control"
                                                type="select"
                                                value={filtresOp.agence_id}
                                                onChange={(event) =>
                                                    appliquerFiltres({
                                                        ...filtresOp,
                                                        agence_id:
                                                            event.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">Tout</option>
                                                {(
                                                    ordreDetail?.referentiels
                                                        .agences || []
                                                ).map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.libelle}
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col lg={3} md={6}>
                                            <Label className="form-label ac-field-label">
                                                Entreprise
                                            </Label>
                                            <Input
                                                className="ac-control"
                                                type="select"
                                                value={filtresOp.entreprise_id}
                                                onChange={(event) =>
                                                    appliquerFiltres({
                                                        ...filtresOp,
                                                        entreprise_id:
                                                            event.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">Tout</option>
                                                {(
                                                    ordreDetail?.referentiels
                                                        .entreprises || []
                                                ).map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.libelle}
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col lg={3} md={6}>
                                            <Label className="form-label ac-field-label">
                                                Type de financement
                                            </Label>
                                            <Input
                                                className="ac-control"
                                                type="select"
                                                value={
                                                    filtresOp.source_financement_id
                                                }
                                                onChange={(event) =>
                                                    appliquerFiltres({
                                                        ...filtresOp,
                                                        source_financement_id:
                                                            event.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">Tout</option>
                                                {(
                                                    ordreDetail?.referentiels
                                                        .sources_financement ||
                                                    []
                                                ).map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.libelle}
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col lg={3} md={6}>
                                            <Label className="form-label ac-field-label">
                                                Type de stage
                                            </Label>
                                            <Input
                                                className="ac-control"
                                                type="select"
                                                value={filtresOp.type_stage_id}
                                                onChange={(event) =>
                                                    appliquerFiltres({
                                                        ...filtresOp,
                                                        type_stage_id:
                                                            event.target.value,
                                                    })
                                                }
                                            >
                                                <option value="">Tout</option>
                                                {(
                                                    ordreDetail?.referentiels
                                                        .types_stage || []
                                                ).map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.libelle}
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col lg={6} md={6}>
                                            <Label className="form-label ac-field-label">
                                                Stagiaire / dossier
                                            </Label>
                                            <div className="position-relative">
                                                <i className="ri-search-line ac-input-icon" />
                                                <Input
                                                    className="ac-control ps-5"
                                                    value={filtresOp.recherche}
                                                    placeholder="Nom, prénom ou N° AEJ…"
                                                    onChange={(event) =>
                                                        setFiltresOp({
                                                            ...filtresOp,
                                                            recherche:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                            'Enter'
                                                        ) {
                                                            appliquerFiltres(
                                                                filtresOp,
                                                            );
                                                        }
                                                    }}
                                                />
                                            </div>
                                        </Col>
                                        <Col lg={3} md={6}>
                                            <Button
                                                color="success"
                                                className="ac-btn-rechercher w-100"
                                                disabled={loadingOrdre}
                                                onClick={() =>
                                                    appliquerFiltres(filtresOp)
                                                }
                                            >
                                                <i className="ri-search-line me-2" />
                                                Rechercher
                                            </Button>
                                        </Col>
                                    </Row>
                                </CardBody>
                            </Card>

                            <Card className="ac-actions-card border-0">
                                <CardBody className="p-lg-4 p-3">
                                    <div className="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div className="ac-card-heading">
                                            <h4 className="mb-1">
                                                <i className="ri-slideshow-4-line me-2" />
                                                Traitement sur les OP
                                                sélectionnées
                                            </h4>
                                            {ordreDetail && (
                                                <div className="fs-13 text-muted">
                                                    OP{' '}
                                                    <strong className="text-dark">
                                                        {ordreDetail.numero}
                                                    </strong>{' '}
                                                    <Badge
                                                        color={
                                                            statutsOp[
                                                                ordreDetail
                                                                    .statut
                                                            ]?.couleur ||
                                                            'secondary'
                                                        }
                                                        pill
                                                        className="ms-1"
                                                    >
                                                        {libelleStatutOp(
                                                            ordreDetail.statut,
                                                        )}
                                                    </Badge>
                                                </div>
                                            )}
                                        </div>
                                        <div className="d-flex align-items-center flex-wrap gap-2">
                                            {selection.length > 0 && (
                                                <span className="ac-selection-chip">
                                                    <i className="ri-checkbox-multiple-line me-1" />
                                                    {selection.length} stagiaire
                                                    {selection.length > 1
                                                        ? 's'
                                                        : ''}{' '}
                                                    sélectionné
                                                    {selection.length > 1
                                                        ? 's'
                                                        : ''}
                                                </span>
                                            )}
                                            <Button
                                                size="sm"
                                                color="warning"
                                                outline
                                                disabled={
                                                    !ordreDetail?.actions
                                                        .retirer || loadingOrdre
                                                }
                                                onClick={() =>
                                                    ordreSelectionne &&
                                                    ouvrirActionOp(
                                                        ordreSelectionne,
                                                        'retirer',
                                                    )
                                                }
                                            >
                                                <i className="ri-subtract-line me-1" />
                                                Retirer OP
                                            </Button>
                                            {ordreDetail?.actions
                                                .differer_stagiaires &&
                                                ongletStagiaire ===
                                                    'attente' && (
                                                    <Button
                                                        size="sm"
                                                        color="warning"
                                                        outline
                                                        disabled={
                                                            !selection.length ||
                                                            loadingOrdre
                                                        }
                                                        onClick={() => {
                                                            setDifferePartiel(
                                                                true,
                                                            );
                                                            setMotifOp('');
                                                        }}
                                                    >
                                                        <i className="ri-pause-circle-line me-1" />
                                                        Différer la sélection
                                                    </Button>
                                                )}
                                            <Button
                                                size="sm"
                                                color="warning"
                                                disabled={
                                                    !ordreDetail?.actions
                                                        .differer ||
                                                    loadingOrdre
                                                }
                                                onClick={() =>
                                                    ordreSelectionne &&
                                                    ouvrirActionOp(
                                                        ordreSelectionne,
                                                        'differer',
                                                    )
                                                }
                                            >
                                                <i className="ri-pause-circle-line me-1" />
                                                Différer OP (Global)
                                            </Button>
                                            <Button
                                                size="sm"
                                                color="danger"
                                                disabled={
                                                    !ordreDetail?.actions
                                                        .rejeter || loadingOrdre
                                                }
                                                onClick={() =>
                                                    ordreSelectionne &&
                                                    ouvrirActionOp(
                                                        ordreSelectionne,
                                                        'rejeter',
                                                    )
                                                }
                                            >
                                                <i className="ri-close-circle-line me-1" />
                                                Rejeter
                                            </Button>
                                            <Button
                                                size="sm"
                                                color="primary"
                                                disabled={
                                                    !ordreDetail?.actions
                                                        .valider || loadingOrdre
                                                }
                                                onClick={() =>
                                                    ordreSelectionne &&
                                                    setOrdreAValider(
                                                        ordreSelectionne,
                                                    )
                                                }
                                            >
                                                <i className="ri-check-double-line me-1" />
                                                Valider
                                            </Button>
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>

                            <Card className="ac-flat-card border-0">
                                <CardBody className="p-0">
                                    <Nav
                                        tabs
                                        className="ac-tabs ac-ws-tabs px-lg-4 px-3"
                                        role="tablist"
                                    >
                                        {ongletsStagiaires.map((onglet) => (
                                            <NavItem key={onglet.id}>
                                                <NavLink
                                                    role="tab"
                                                    aria-selected={
                                                        ongletStagiaire ===
                                                        onglet.id
                                                    }
                                                    className={classnames({
                                                        active:
                                                            ongletStagiaire ===
                                                            onglet.id,
                                                    })}
                                                    onClick={() =>
                                                        changerOnglet(onglet.id)
                                                    }
                                                >
                                                    <i
                                                        className={`${onglet.icone} me-2`}
                                                    />
                                                    OP {onglet.libelle}
                                                    <span className="d-none d-md-inline">
                                                        {' '}
                                                        (Liste stagiaires)
                                                    </span>
                                                    <Badge
                                                        pill
                                                        color={onglet.couleur}
                                                        className="ms-2"
                                                    >
                                                        {ordreDetail
                                                            ? ordreDetail
                                                                  .compteurs[
                                                                  onglet.id
                                                              ]
                                                            : 0}
                                                    </Badge>
                                                </NavLink>
                                            </NavItem>
                                        ))}
                                    </Nav>

                                    <div className="p-lg-4 p-3">
                                        {loadingOrdre && (
                                            <div className="ac-detail-loading">
                                                <Spinner color="primary" />
                                                <span>
                                                    Chargement des stagiaires…
                                                </span>
                                            </div>
                                        )}
                                        {!loadingOrdre && detailError && (
                                            <Alert
                                                color="danger"
                                                className="mb-0"
                                            >
                                                {detailError}
                                            </Alert>
                                        )}
                                        {!loadingOrdre &&
                                            !detailError &&
                                            !ordreDetail && (
                                                <EmptyState text="Sélectionnez une OP pour afficher ses dossiers et stagiaires." />
                                            )}
                                        {!loadingOrdre &&
                                            !detailError &&
                                            ordreDetail &&
                                            !lignesAffichees.length && (
                                                <EmptyState text="Aucun stagiaire dans cet onglet pour les filtres appliqués." />
                                            )}
                                        {!loadingOrdre &&
                                            !detailError &&
                                            ordreDetail &&
                                            lignesAffichees.length > 0 && (
                                                <div className="table-responsive ac-flat-table-wrap">
                                                    <Table
                                                        hover
                                                        className="ac-table ac-flat-table mb-0 align-middle"
                                                    >
                                                        <thead>
                                                            <tr>
                                                                {ongletStagiaire ===
                                                                    'attente' && (
                                                                    <th
                                                                        style={{
                                                                            width: 40,
                                                                        }}
                                                                    >
                                                                        <Input
                                                                            type="checkbox"
                                                                            checked={
                                                                                toutSelectionne
                                                                            }
                                                                            onChange={() =>
                                                                                setSelection(
                                                                                    toutSelectionne
                                                                                        ? []
                                                                                        : selectionnables,
                                                                                )
                                                                            }
                                                                            aria-label="Tout sélectionner"
                                                                        />
                                                                    </th>
                                                                )}
                                                                <th>
                                                                    N° dossier
                                                                </th>
                                                                <th>
                                                                    Date
                                                                    création
                                                                </th>
                                                                <th>Agence</th>
                                                                <th>
                                                                    Entreprise
                                                                </th>
                                                                <th>
                                                                    Source de
                                                                    financement
                                                                </th>
                                                                <th>
                                                                    Type de
                                                                    stagiaire
                                                                </th>
                                                                <th>
                                                                    Numéro AEJ
                                                                </th>
                                                                <th>
                                                                    Nom et
                                                                    prénoms
                                                                </th>
                                                                <th>
                                                                    Date de
                                                                    naissance
                                                                </th>
                                                                <th>Début</th>
                                                                <th>Fin</th>
                                                                <th>
                                                                    N° Trésor
                                                                    Money
                                                                </th>
                                                                <th className="text-center">
                                                                    Pièce jointe
                                                                </th>
                                                                <th className="text-end">
                                                                    Montant
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {lignesAffichees.map(
                                                                ({
                                                                    stagiaire,
                                                                    dossier,
                                                                }) => (
                                                                    <tr
                                                                        key={
                                                                            stagiaire.paiement_id
                                                                        }
                                                                    >
                                                                        {ongletStagiaire ===
                                                                            'attente' && (
                                                                            <td className="text-center">
                                                                                <Input
                                                                                    type="checkbox"
                                                                                    checked={selection.includes(
                                                                                        stagiaire.paiement_id,
                                                                                    )}
                                                                                    onChange={() =>
                                                                                        basculerSelection(
                                                                                            stagiaire.paiement_id,
                                                                                        )
                                                                                    }
                                                                                    aria-label={`Sélectionner ${stagiaire.nom || stagiaire.paiement_id}`}
                                                                                />
                                                                            </td>
                                                                        )}
                                                                        <td className="fw-semibold text-nowrap">
                                                                            {
                                                                                dossier.numero
                                                                            }
                                                                        </td>
                                                                        <td className="text-nowrap">
                                                                            {dossier.date_creation ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {dossier.agence ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {stagiaire.entreprise ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {dossier.source_financement ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            {stagiaire.type_stage ||
                                                                                '—'}
                                                                        </td>
                                                                        <td className="fw-semibold text-nowrap text-primary">
                                                                            {stagiaire.numero_aej ||
                                                                                '—'}
                                                                        </td>
                                                                        <td>
                                                                            <strong>
                                                                                {stagiaire.nom ||
                                                                                    '—'}{' '}
                                                                                {stagiaire.prenoms ||
                                                                                    ''}
                                                                            </strong>
                                                                        </td>
                                                                        <td className="text-nowrap">
                                                                            {stagiaire.date_naissance ||
                                                                                '—'}
                                                                        </td>
                                                                        <td className="text-nowrap">
                                                                            {stagiaire.date_debut ||
                                                                                '—'}
                                                                        </td>
                                                                        <td className="text-nowrap">
                                                                            {stagiaire.date_fin ||
                                                                                '—'}
                                                                        </td>
                                                                        <td className="text-nowrap">
                                                                            {stagiaire.numero_tresor_money ||
                                                                                '—'}
                                                                        </td>
                                                                        <td className="text-center text-muted">
                                                                            <i
                                                                                className="ri-file-line"
                                                                                title="Pièce jointe non disponible"
                                                                            />
                                                                        </td>
                                                                        <td className="fw-semibold text-end text-nowrap">
                                                                            {formatMontant(
                                                                                stagiaire.montant,
                                                                            )}{' '}
                                                                            <span className="fs-11 text-muted">
                                                                                FCFA
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </Table>
                                                </div>
                                            )}
                                    </div>
                                </CardBody>
                            </Card>
                        </section>
                    )}
                </Container>
            </div>

            <Modal
                isOpen={Boolean(ordreAValider)}
                toggle={() => !processing && setOrdreAValider(null)}
                centered
            >
                <ModalBody className="p-4 text-center">
                    <span className="ac-confirm-icon ac-confirm-icon--success">
                        <i className="ri-check-double-line" />
                    </span>
                    <h4 className="mt-3">Valider cette OP ?</h4>
                    <p className="mb-1 text-muted">
                        L’OP <strong>{ordreAValider?.numero}</strong> sera
                        validée avec ses {ordreAValider?.nombre_paiements}{' '}
                        paiement(s).
                    </p>
                    <p className="fw-semibold mb-0">
                        {formatMontant(ordreAValider?.montant_total || 0)} FCFA
                    </p>
                    <Alert color="info" className="fs-13 mt-3 mb-0 text-start">
                        <i className="ri-information-line me-2" />
                        Le bordereau sera automatiquement clôturé après la
                        validation de sa dernière OP.
                    </Alert>
                </ModalBody>
                <ModalFooter className="justify-content-center border-0 pt-0 pb-4">
                    <Button
                        color="light"
                        disabled={processing}
                        onClick={() => setOrdreAValider(null)}
                    >
                        Annuler
                    </Button>
                    <Button
                        color="primary"
                        disabled={processing}
                        onClick={confirmerValidationOp}
                    >
                        {processing && <Spinner size="sm" className="me-2" />}
                        Valider l’OP
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal
                isOpen={Boolean(actionOp)}
                toggle={() => !processing && setActionOp(null)}
                centered
            >
                <ModalHeader toggle={() => !processing && setActionOp(null)}>
                    {actionOp ? actionsOp[actionOp.type].titre : ''}
                </ModalHeader>
                <ModalBody>
                    {actionOp && (
                        <Alert
                            color={actionsOp[actionOp.type].color}
                            className="fs-13"
                        >
                            <i className="ri-information-line me-2" />
                            {actionsOp[actionOp.type].description}
                        </Alert>
                    )}
                    <div className="bg-light mb-3 rounded p-3">
                        <span className="fs-12 d-block text-muted">
                            OP concernée
                        </span>
                        <strong>{actionOp?.ordre.numero}</strong>
                        <span className="fw-semibold float-end">
                            {formatMontant(actionOp?.ordre.montant_total || 0)}{' '}
                            FCFA
                        </span>
                    </div>
                    <Label className="fw-semibold">Motif obligatoire</Label>
                    <Input
                        invalid={Boolean(errors?.motif)}
                        type="textarea"
                        rows={4}
                        maxLength={2000}
                        value={motifOp}
                        onChange={(event) => setMotifOp(event.target.value)}
                        placeholder="Précisez clairement le motif de la décision…"
                    />
                    <FormFeedback>{errors?.motif}</FormFeedback>
                    <div className="d-flex justify-content-between fs-11 mt-1 text-muted">
                        <span>5 caractères minimum</span>
                        <span>{motifOp.length}/2000</span>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        disabled={processing}
                        onClick={() => setActionOp(null)}
                    >
                        Annuler
                    </Button>
                    <Button
                        color={
                            actionOp
                                ? actionsOp[actionOp.type].color
                                : 'secondary'
                        }
                        disabled={processing || motifOp.trim().length < 5}
                        onClick={confirmerActionOp}
                    >
                        {processing && <Spinner size="sm" className="me-2" />}
                        {actionOp
                            ? actionsOp[actionOp.type].bouton
                            : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal
                isOpen={differePartiel}
                toggle={() => !processing && setDifferePartiel(false)}
                centered
            >
                <ModalHeader
                    toggle={() => !processing && setDifferePartiel(false)}
                >
                    Différer les stagiaires sélectionnés ?
                </ModalHeader>
                <ModalBody>
                    <Alert color="warning" className="fs-13">
                        <i className="ri-information-line me-2" />
                        Les {selection.length} stagiaire(s) sélectionné(s)
                        retournent à la DMG. Le reste de l’OP{' '}
                        <strong>{ordreDetail?.numero}</strong> reste en attente
                        de votre visa.
                    </Alert>
                    <Label className="fw-semibold">Motif obligatoire</Label>
                    <Input
                        invalid={Boolean(errors?.motif)}
                        type="textarea"
                        rows={4}
                        maxLength={2000}
                        value={motifOp}
                        onChange={(event) => setMotifOp(event.target.value)}
                        placeholder="Précisez le motif du retour à la DMG…"
                    />
                    <FormFeedback>{errors?.motif}</FormFeedback>
                    <div className="d-flex justify-content-between fs-11 mt-1 text-muted">
                        <span>5 caractères minimum</span>
                        <span>{motifOp.length}/2000</span>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        disabled={processing}
                        onClick={() => setDifferePartiel(false)}
                    >
                        Annuler
                    </Button>
                    <Button
                        color="warning"
                        disabled={processing || motifOp.trim().length < 5}
                        onClick={confirmerDifferePartiel}
                    >
                        {processing && <Spinner size="sm" className="me-2" />}
                        Différer la sélection
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
}
