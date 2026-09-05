import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import classnames from 'classnames';
import React, { useEffect, useMemo, useState } from 'react';
import Select from 'react-select';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    Col,
    Collapse,
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
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import './ac-paiements.scss';

// Le client Axios global du template extrait déjà `response.data` via un
// intercepteur. Cette page a besoin de la réponse Axios standard afin de
// contrôler explicitement le JSON retourné par les routes Laravel.
const paiementHttp = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

type Ordre = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    nombre_dossiers: number;
    nombre_paiements: number;
    compteurs?: {
        total: number;
        payes: number;
        nonPayes: number;
        valides: number;
    };
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
    operationsRejetees?: Bordereau[];
    statutPaiements?: Bordereau[];
    statutCompteurs?: { payes: number; nonPayes: number };
    moisActuel: string;
    periodesDisponibles?: Array<{ code: string; count: number }>;
    vueActuelle?: Categorie;
    sousOngletActuel?: SousOngletStatuts;
};

type Pieces = {
    cni: boolean;
    tresor: boolean;
    contrat: boolean;
    attestation: boolean;
};

type Stagiaire = {
    paiement_id: number;
    statut_paiement: string;
    montant: string | number;
    paye_le?: string | null;
    decide_le?: string | null;
    beneficiaire_id?: number;
    stage_id?: number;
    pieces?: Pieces | null;
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

type LigneStagiaire = { stagiaire: Stagiaire; dossier: DossierDetail };
type LignePaiementSituation = {
    stagiaire: Stagiaire;
    dossier: {
        id: number;
        numero: string;
        date_creation?: string;
        agence?: string;
        source_financement?: string;
    };
    ordre: { id: number; numero: string; statut: string };
};
type SousOngletStatuts = 'par_op' | 'payes' | 'non_payes';
type Categorie = 'attente' | 'statuts' | 'operations_rejetees' | 'rejetes';
type OngletStagiaire =
    'attente' | 'valide' | 'paye' | 'non_paye' | 'rejete' | 'differe';
type ActionOp = 'retirer' | 'differer' | 'rejeter';
type OptionReferentiel = { id: number; libelle: string };
type DossierOption = {
    id: number;
    libelle: string;
    nombre_paiements: number;
    montant_total: string | number;
};
type MultiDossierOption = {
    id: number;
    libelle: string;
    nombre_dossiers?: number | null;
};

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
        confirmer_paiements: boolean;
    };
    referentiels: {
        agences: OptionReferentiel[];
        entreprises: OptionReferentiel[];
        sources_financement: OptionReferentiel[];
        types_stage: OptionReferentiel[];
    };
    dossiers_options: DossierOption[];
    multi_dossiers_options: MultiDossierOption[];
    dossiers: DossierDetail[];
};

type PiecesResponse = {
    pieces: Record<keyof Pieces, string | null>;
};

type FiltresOp = {
    agence_id: string;
    entreprise_id: string;
    source_financement_id: string;
    type_stage_id: string;
    dossier_id: string;
    multi_dossier_id: string;
    date_debut: string;
    date_fin: string;
    recherche: string;
};

type FlashProps = {
    flash?: { success?: string; error?: string };
    errors?: { motif?: string; bordereau?: string; ordre?: string };
};

const filtresVides: FiltresOp = {
    agence_id: '',
    entreprise_id: '',
    source_financement_id: '',
    type_stage_id: '',
    dossier_id: '',
    multi_dossier_id: '',
    date_debut: '',
    date_fin: '',
    recherche: '',
};

const categories: Array<{
    id: Categorie;
    libelle: string;
    icone: string;
    couleur: 'warning' | 'success' | 'danger';
}> = [
    {
        id: 'attente',
        libelle: 'À traiter',
        icone: 'ri-time-line',
        couleur: 'warning',
    },
    {
        id: 'statuts',
        libelle: 'Status paiement',
        icone: 'ri-checkbox-circle-line',
        couleur: 'success',
    },
    {
        id: 'operations_rejetees',
        libelle: 'OP rejetées',
        icone: 'ri-close-circle-line',
        couleur: 'danger',
    },
    {
        id: 'rejetes',
        libelle: 'Bordereaux retournés',
        icone: 'ri-arrow-go-back-line',
        couleur: 'danger',
    },
];

const ongletsStagiaires: Array<{
    id: OngletStagiaire;
    libelle: string;
    icone: string;
    couleur: 'warning' | 'success' | 'danger' | 'secondary';
}> = [
    {
        id: 'attente',
        libelle: 'En attente',
        icone: 'ri-time-line',
        couleur: 'warning',
    },
    {
        id: 'valide',
        libelle: 'À confirmer',
        icone: 'ri-time-line',
        couleur: 'warning',
    },
    {
        id: 'paye',
        libelle: 'Payés',
        icone: 'ri-checkbox-circle-line',
        couleur: 'success',
    },
    {
        id: 'non_paye',
        libelle: 'Non payés',
        icone: 'ri-close-circle-line',
        couleur: 'danger',
    },
    {
        id: 'rejete',
        libelle: 'Rejetés',
        icone: 'ri-close-circle-line',
        couleur: 'danger',
    },
    {
        id: 'differe',
        libelle: 'Différés',
        icone: 'ri-pause-circle-line',
        couleur: 'secondary',
    },
];

const piecesConfig: Array<{
    id: keyof Pieces;
    libelle: string;
    icone: string;
}> = [
    { id: 'cni', libelle: 'Pièce d’identité', icone: 'ri-id-card-line' },
    { id: 'tresor', libelle: 'Fiche Trésor Money', icone: 'ri-bank-card-line' },
    { id: 'contrat', libelle: 'Contrat / avenant', icone: 'ri-file-text-line' },
    {
        id: 'attestation',
        libelle: 'Attestation de présence',
        icone: 'ri-calendar-check-line',
    },
];

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

const libelleStatutOp = (statut: string) => {
    const libelles: Record<string, string> = {
        EN_BORDEREAU: 'En attente de traitement',
        VISE_AC: 'Visée AC',
        REJETE_AC: 'Rejetée AC',
        REJETE_AC_DEFINITIF: 'Rejetée définitivement',
        DIFFERE_AC: 'Différée vers la DMG',
    };

    return (
        libelles[statut] ??
        statut
            .replaceAll('_', ' ')
            .toLowerCase()
            .replace(/^./, (lettre) => lettre.toUpperCase())
    );
};

function EmptyState({
    icon = 'ri-inbox-archive-line',
    title,
    text,
}: {
    icon?: string;
    title: string;
    text: string;
}) {
    return (
        <div className="ac-empty-state">
            <span className="ac-empty-state__icon">
                <i className={icon} />
            </span>
            <h5 className="mt-3 mb-1">{title}</h5>
            <p className="mb-0 text-muted">{text}</p>
        </div>
    );
}

export default function AcPaiementsIndex({
    bordereauxAttente = [],
    bordereauxRejetes = [],
    bordereauxVises = [],
    operationsRejetees = [],
    statutPaiements,
    moisActuel,
    periodesDisponibles = [],
    vueActuelle = 'attente',
    sousOngletActuel = 'par_op',
}: Props) {
    const { flash, errors } = usePage<FlashProps>().props;
    const [categorie, setCategorie] = useState<Categorie>(
        categories.some((item) => item.id === vueActuelle)
            ? vueActuelle
            : 'attente',
    );
    const [details, setDetails] = useState<Bordereau | null>(null);
    const [ordreDetail, setOrdreDetail] = useState<OrdreDetail | null>(null);
    const [selectedOrdreId, setSelectedOrdreId] = useState<number | null>(null);
    const [ongletStagiaire, setOngletStagiaire] =
        useState<OngletStagiaire>('attente');
    const [filtresOp, setFiltresOp] = useState<FiltresOp>(filtresVides);
    const [filtresOuverts, setFiltresOuverts] = useState(false);
    const [selection, setSelection] = useState<number[]>([]);
    const [loadingOrdre, setLoadingOrdre] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [ordreAValider, setOrdreAValider] = useState<Ordre | null>(null);
    const [actionOp, setActionOp] = useState<{
        ordre: Ordre;
        type: ActionOp;
    } | null>(null);
    const [differePartiel, setDifferePartiel] = useState(false);
    const [situationPaiements, setSituationPaiements] = useState<
        number[] | null
    >(null);
    const [situationPaiement, setSituationPaiement] = useState<
        'PAYE' | 'NON_PAYE' | ''
    >('');
    const [piecesStagiaire, setPiecesStagiaire] = useState<Stagiaire | null>(
        null,
    );
    const [loadingPieces, setLoadingPieces] = useState(false);
    const [motifOp, setMotifOp] = useState('');
    const [processing, setProcessing] = useState(false);
    const [sousOngletStatuts, setSousOngletStatuts] =
        useState<SousOngletStatuts>(
            ['payes', 'non_payes'].includes(sousOngletActuel ?? 'par_op')
                ? (sousOngletActuel as SousOngletStatuts)
                : 'par_op',
        );
    const [lignesSituation, setLignesSituation] = useState<
        LignePaiementSituation[]
    >([]);
    const [totalSituation, setTotalSituation] = useState(0);
    const [montantSituation, setMontantSituation] = useState(0);
    const [pageSituation, setPageSituation] = useState(1);
    const [loadingSituation, setLoadingSituation] = useState(false);

    const selectStyles = {
        control: (base: Record<string, unknown>) => ({
            ...base,
            minHeight: 43,
            borderColor: '#dbe2ea',
            borderRadius: 9,
            boxShadow: 'none',
        }),
        menu: (base: Record<string, unknown>) => ({ ...base, zIndex: 10 }),
    };

    const bordereauxParCategorie = useMemo(
        () => ({
            attente: bordereauxAttente,
            statuts: statutPaiements ?? bordereauxVises,
            operations_rejetees: operationsRejetees,
            rejetes: bordereauxRejetes,
        }),
        [
            bordereauxAttente,
            bordereauxVises,
            operationsRejetees,
            bordereauxRejetes,
            statutPaiements,
        ],
    );
    const bordereauxCourants = bordereauxParCategorie[categorie];
    const categorieCourante = categories.find((item) => item.id === categorie);
    const libelleConteneur =
        categorie === 'operations_rejetees' ? 'groupe d’OP' : 'bordereau';
    const libelleConteneurCapitalise =
        categorie === 'operations_rejetees' ? 'Groupe d’OP' : 'Bordereau';
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

    const lignesAffichees = useMemo<LigneStagiaire[]>(
        () =>
            (ordreDetail?.dossiers || []).flatMap((dossier) =>
                dossier.stagiaires.map((stagiaire) => ({ stagiaire, dossier })),
            ),
        [ordreDetail],
    );
    const selectionnables = useMemo(
        () =>
            ongletStagiaire === 'attente' || ongletStagiaire === 'valide'
                ? lignesAffichees.map(({ stagiaire }) => stagiaire.paiement_id)
                : [],
        [lignesAffichees, ongletStagiaire],
    );
    const toutSelectionne =
        selectionnables.length > 0 &&
        selectionnables.every((id) => selection.includes(id));
    const ordreSelectionne = details?.ordres.find(
        (ordre) => ordre.id === ordreDetail?.id,
    );
    const aDesActions = Boolean(
        ordreDetail &&
        [
            ordreDetail.actions.valider,
            ordreDetail.actions.differer,
            ordreDetail.actions.rejeter,
            ordreDetail.actions.retirer,
        ].some(Boolean),
    );
    const ordreOptions = useMemo(
        () =>
            (details?.ordres ?? []).map((ordre) => {
                const compteurs =
                    categorie === 'statuts' && ordre.compteurs
                        ? ` — ${ordre.compteurs.payes} payé${ordre.compteurs.payes > 1 ? 's' : ''} · ${ordre.compteurs.nonPayes} non payé${ordre.compteurs.nonPayes > 1 ? 's' : ''}`
                        : '';

                return {
                    value: ordre.id,
                    label: `${ordre.numero} — ${libelleStatutOp(ordre.statut)} — ${ordre.nombre_paiements} paiement${ordre.nombre_paiements > 1 ? 's' : ''}${compteurs}`,
                };
            }),
        [details, categorie],
    );
    const dossierOptions = useMemo(
        () =>
            (ordreDetail?.dossiers_options ?? []).map((dossier) => ({
                value: dossier.id,
                label: `${dossier.libelle} — ${dossier.nombre_paiements} paiement${dossier.nombre_paiements > 1 ? 's' : ''}`,
            })),
        [ordreDetail],
    );
    const multiDossierOptions = useMemo(
        () =>
            (ordreDetail?.multi_dossiers_options ?? []).map((groupe) => ({
                value: groupe.id,
                label: groupe.nombre_dossiers
                    ? `${groupe.libelle} — ${groupe.nombre_dossiers} dossiers`
                    : groupe.libelle,
            })),
        [ordreDetail],
    );

    const changerMois = (mois: string) => {
        router.get(
            '/agent-comptable/paiements',
            {
                mois,
                vue: categorie,
                ...(categorie === 'statuts' && sousOngletStatuts !== 'par_op'
                    ? { sous_onglet: sousOngletStatuts }
                    : {}),
            },
            { preserveState: false, replace: true },
        );
    };

    const ongletDepuisStatut = (statut: string): OngletStagiaire => {
        if (statut === 'VISE_AC') {
            return 'valide';
        }

        if (statut.includes('REJETE')) {
            return 'rejete';
        }

        if (statut.includes('DIFFERE')) {
            return 'differe';
        }

        return 'attente';
    };

    const chargerOrdre = async (
        ordreId: number,
        onglet: OngletStagiaire,
        filtres: FiltresOp,
    ) => {
        setLoadingOrdre(true);
        setDetailError('');
        setSelection([]);

        try {
            const response = await paiementHttp.get<OrdreDetail>(
                `/agent-comptable/paiements/ordres/${ordreId}/details`,
                { params: { onglet, ...filtres }, timeout: 15000 },
            );

            if (
                !response.data ||
                typeof response.data !== 'object' ||
                !Array.isArray(response.data.dossiers)
            ) {
                throw new Error('Réponse invalide du détail de l’OP.');
            }

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

    const chargerSituation = async (
        situation: Exclude<SousOngletStatuts, 'par_op'>,
        page = 1,
        append = false,
    ) => {
        setLoadingSituation(true);

        try {
            const response = await paiementHttp.get<{
                rows: LignePaiementSituation[];
                total: number;
                montant_total: number;
                page: number;
            }>('/agent-comptable/paiements/statuts', {
                params: { mois: moisActuel, situation, page },
                timeout: 20000,
            });

            setLignesSituation((actuelles) =>
                append
                    ? [...actuelles, ...response.data.rows]
                    : response.data.rows,
            );
            setTotalSituation(response.data.total);
            setMontantSituation(response.data.montant_total);
            setPageSituation(response.data.page);
        } catch {
            if (!append) {
                setLignesSituation([]);
                setTotalSituation(0);
                setMontantSituation(0);
            }
        } finally {
            setLoadingSituation(false);
        }
    };

    const changerSousOngletStatuts = (onglet: SousOngletStatuts) => {
        setSousOngletStatuts(onglet);

        if (onglet !== 'par_op') {
            void chargerSituation(onglet);
        }
    };

    const urlExportStatuts = (format: 'excel' | 'pdf') => {
        const situation = sousOngletStatuts === 'payes' ? 'paye' : 'non_paye';
        const parametres = new URLSearchParams({
            mois: moisActuel,
            situation,
            format,
        });

        return `/agent-comptable/paiements/statuts/export?${parametres.toString()}`;
    };

    // Chargement initial uniquement, depuis l'onglet porté par l'URL ;
    // le chargement d'état est délégué à `chargerSituation`.
    useEffect(() => {
        if (categorie === 'statuts' && sousOngletStatuts !== 'par_op') {
            // eslint-disable-next-line react-hooks/set-state-in-effect
            void chargerSituation(sousOngletStatuts);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const ouvrirPieces = async (stagiaire: Stagiaire) => {
        if (!stagiaire.stage_id) {
            return;
        }

        setPiecesStagiaire(stagiaire);

        if (stagiaire.pieces) {
            return;
        }

        setLoadingPieces(true);

        try {
            const response = await paiementHttp.get<PiecesResponse>(
                '/agent-comptable/paiements/pieces',
                { params: { stage_id: stagiaire.stage_id }, timeout: 10000 },
            );
            const pieces = Object.fromEntries(
                piecesConfig.map((piece) => [
                    piece.id,
                    Boolean(response.data.pieces?.[piece.id]),
                ]),
            ) as Pieces;
            setPiecesStagiaire({ ...stagiaire, pieces });
        } catch {
            setPiecesStagiaire({
                ...stagiaire,
                pieces: {
                    cni: false,
                    tresor: false,
                    contrat: false,
                    attestation: false,
                },
            });
        } finally {
            setLoadingPieces(false);
        }
    };

    const choisirBordereau = (bordereauId: number) => {
        const bordereau =
            bordereauxCourants.find((item) => item.id === bordereauId) ?? null;
        setDetails(bordereau);
        setOrdreDetail(null);
        setSelectedOrdreId(null);
        setFiltresOp(filtresVides);
        setFiltresOuverts(false);
        setSelection([]);
        setOngletStagiaire('attente');
    };

    const changerCategorie = (nouvelleCategorie: Categorie) => {
        setCategorie(nouvelleCategorie);
        setDetails(null);
        setOrdreDetail(null);
        setSelectedOrdreId(null);
        setFiltresOp(filtresVides);
        setSelection([]);
        setDetailError('');
        setSousOngletStatuts('par_op');
    };

    const changerOrdre = (ordreId: number) => {
        const ordre = details?.ordres.find((item) => item.id === ordreId);

        if (!ordre) {
            return;
        }

        setSelectedOrdreId(ordreId);
        const onglet = ongletDepuisStatut(ordre.statut);
        setOngletStagiaire(onglet);
        setFiltresOp(filtresVides);
        void chargerOrdre(ordreId, onglet, filtresVides);
    };

    const changerOnglet = (onglet: OngletStagiaire) => {
        setOngletStagiaire(onglet);

        if (ordreDetail) {
            void chargerOrdre(ordreDetail.id, onglet, filtresOp);
        }
    };

    const appliquerFiltres = () => {
        if (ordreDetail) {
            void chargerOrdre(ordreDetail.id, ongletStagiaire, filtresOp);
        }
    };

    const changerFiltreDossier = (
        champ: 'dossier_id' | 'multi_dossier_id',
        valeur: number | null,
    ) => {
        const prochain: FiltresOp = {
            ...filtresOp,
            [champ]: valeur ? String(valeur) : '',
            ...(champ === 'dossier_id'
                ? { multi_dossier_id: '' }
                : { dossier_id: '' }),
        };

        setFiltresOp(prochain);

        if (ordreDetail) {
            void chargerOrdre(ordreDetail.id, ongletStagiaire, prochain);
        }
    };

    const reinitialiserFiltres = () => {
        setFiltresOp(filtresVides);

        if (ordreDetail) {
            void chargerOrdre(ordreDetail.id, ongletStagiaire, filtresVides);
        }
    };

    const basculerSelection = (paiementId: number) => {
        setSelection((actuelle) =>
            actuelle.includes(paiementId)
                ? actuelle.filter((id) => id !== paiementId)
                : [...actuelle, paiementId],
        );
    };

    const fermerTraitement = () => {
        setDetails(null);
        setOrdreDetail(null);
        setSelection([]);
        setDetailError('');
    };

    const soumettre = (
        url: string,
        data: Record<string, unknown>,
        fermer: () => void,
    ) => {
        setProcessing(true);
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: () => {
                setMotifOp('');
                fermer();
            },
            onFinish: () => setProcessing(false),
        });
    };

    const confirmerValidationOp = () => {
        if (!ordreAValider) {
            return;
        }

        soumettre(
            `/agent-comptable/paiements/ordres/${ordreAValider.id}/valider`,
            {},
            () => {
                setOrdreAValider(null);
                fermerTraitement();
            },
        );
    };

    const confirmerActionOp = () => {
        if (!actionOp || motifOp.trim().length < 5) {
            return;
        }

        soumettre(
            `/agent-comptable/paiements/ordres/${actionOp.ordre.id}/${actionOp.type}`,
            { motif: motifOp },
            () => {
                setActionOp(null);
                fermerTraitement();
            },
        );
    };

    const confirmerDifferePartiel = () => {
        if (!ordreDetail || !selection.length || motifOp.trim().length < 5) {
            return;
        }

        soumettre(
            `/agent-comptable/paiements/ordres/${ordreDetail.id}/differer-stagiaires`,
            { motif: motifOp, paiement_ids: selection },
            () => {
                setDifferePartiel(false);
                fermerTraitement();
            },
        );
    };

    const ouvrirSituationPaiements = (
        paiementIds: number[],
        situation: 'PAYE' | 'NON_PAYE' | '' = '',
    ) => {
        if (!paiementIds.length) {
            return;
        }

        setSituationPaiements(paiementIds);
        setSituationPaiement(situation);
        setMotifOp('');
    };

    const confirmerSituationPaiements = () => {
        if (
            !ordreDetail ||
            !situationPaiements?.length ||
            !situationPaiement ||
            motifOp.trim().length < 3
        ) {
            return;
        }

        setProcessing(true);
        router.post(
            `/agent-comptable/paiements/ordres/${ordreDetail.id}/situation-paiements`,
            {
                paiement_ids: situationPaiements,
                situation: situationPaiement,
                motif: motifOp,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSituationPaiements(null);
                    setSituationPaiement('');
                    setMotifOp('');
                    setSelection([]);
                    void chargerOrdre(
                        ordreDetail.id,
                        ongletStagiaire,
                        filtresOp,
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const ouvrirActionOp = (ordre: Ordre, type: ActionOp) => {
        setActionOp({ ordre, type });
        setMotifOp('');
    };

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

                    <Card className="ac-flow-card border-0">
                        <CardBody className="p-0">
                            <div className="ac-flow-header p-lg-4 p-3">
                                <div>
                                    <span className="text-uppercase fs-11 fw-semibold text-success">
                                        Parcours AC
                                    </span>
                                    <h4 className="mt-1 mb-1">
                                        Choisir le {libelleConteneur} et l’OP à
                                        contrôler
                                    </h4>
                                    <p className="mb-0 text-muted">
                                        Sélectionnez dans l’ordre : période,
                                        {` ${libelleConteneur}, puis ordre de paiement.`}
                                    </p>
                                </div>
                                <div
                                    className="ac-flow-steps"
                                    aria-label="Étapes du traitement"
                                >
                                    <span className="active">
                                        <b>1</b> {libelleConteneurCapitalise}
                                    </span>
                                    <i className="ri-arrow-right-line" />
                                    <span className={details ? 'active' : ''}>
                                        <b>2</b> OP
                                    </span>
                                    <i className="ri-arrow-right-line" />
                                    <span
                                        className={ordreDetail ? 'active' : ''}
                                    >
                                        <b>3</b> Décision
                                    </span>
                                </div>
                            </div>

                            <Nav
                                tabs
                                className="ac-tabs px-lg-4 px-3"
                                role="tablist"
                            >
                                {categories.map((item) => (
                                    <NavItem key={item.id}>
                                        <NavLink
                                            role="tab"
                                            aria-selected={
                                                categorie === item.id
                                            }
                                            className={classnames({
                                                active: categorie === item.id,
                                            })}
                                            onClick={() =>
                                                changerCategorie(item.id)
                                            }
                                        >
                                            <i
                                                className={`${item.icone} me-2`}
                                            />
                                            {item.libelle}
                                            <Badge
                                                pill
                                                color={item.couleur}
                                                className="ms-2"
                                            >
                                                {
                                                    bordereauxParCategorie[
                                                        item.id
                                                    ].length
                                                }
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            {categorie === 'statuts' && (
                                <Nav
                                    pills
                                    className="ac-sous-tabs px-lg-4 px-3"
                                    role="tablist"
                                    aria-label="Vue Status paiement"
                                >
                                    <NavItem>
                                        <NavLink
                                            role="tab"
                                            aria-selected={
                                                sousOngletStatuts === 'par_op'
                                            }
                                            className={classnames({
                                                active:
                                                    sousOngletStatuts ===
                                                    'par_op',
                                            })}
                                            onClick={() =>
                                                changerSousOngletStatuts(
                                                    'par_op',
                                                )
                                            }
                                        >
                                            <i className="ri-stack-line me-2" />
                                            Par OP
                                        </NavLink>
                                    </NavItem>
                                    <NavItem>
                                        <NavLink
                                            role="tab"
                                            aria-selected={
                                                sousOngletStatuts === 'payes'
                                            }
                                            className={classnames({
                                                active:
                                                    sousOngletStatuts ===
                                                    'payes',
                                            })}
                                            onClick={() =>
                                                changerSousOngletStatuts(
                                                    'payes',
                                                )
                                            }
                                        >
                                            <i className="ri-checkbox-circle-line me-2" />
                                            Payés
                                            <Badge
                                                pill
                                                color="success"
                                                className="ms-2"
                                            >
                                                {statutCompteurs?.payes ?? 0}
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                    <NavItem>
                                        <NavLink
                                            role="tab"
                                            aria-selected={
                                                sousOngletStatuts ===
                                                'non_payes'
                                            }
                                            className={classnames({
                                                active:
                                                    sousOngletStatuts ===
                                                    'non_payes',
                                            })}
                                            onClick={() =>
                                                changerSousOngletStatuts(
                                                    'non_payes',
                                                )
                                            }
                                        >
                                            <i className="ri-close-circle-line me-2" />
                                            Non payés
                                            <Badge
                                                pill
                                                color="danger"
                                                className="ms-2"
                                            >
                                                {statutCompteurs?.nonPayes ?? 0}
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                </Nav>
                            )}

                            <div className="p-lg-4 p-3">
                                {categorie === 'statuts' &&
                                sousOngletStatuts !== 'par_op' ? (
                                    <Row className="g-3 align-items-end">
                                        <Col xl={3} md={4}>
                                            <Label className="ac-field-label">
                                                Période de paiement
                                            </Label>
                                            <Input
                                                className="ac-control"
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
                                                        — {periode.count} à
                                                        traiter
                                                    </option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={9}>
                                            <Alert
                                                color="info"
                                                className="mb-0"
                                            >
                                                <i className="ri-information-line me-2" />
                                                Liste globale des paiements déjà{' '}
                                                {sousOngletStatuts === 'payes'
                                                    ? 'payés'
                                                    : 'marqués non payés'}{' '}
                                                pour {libelleMois(moisActuel)}.
                                            </Alert>
                                        </Col>
                                    </Row>
                                ) : (
                                    <>
                                        <Row className="g-3 align-items-end">
                                            <Col xl={3} md={4}>
                                                <Label className="ac-field-label">
                                                    1. Période de paiement
                                                </Label>
                                                <Input
                                                    className="ac-control"
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
                                                            — {periode.count} à
                                                            traiter
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xl={4} md={4}>
                                                <Label className="ac-field-label">
                                                    2.{' '}
                                                    {libelleConteneurCapitalise}
                                                </Label>
                                                <Select
                                                    classNamePrefix="react-select"
                                                    isClearable
                                                    isSearchable
                                                    options={bordereauxCourants.map(
                                                        (bordereau) => ({
                                                            value: bordereau.id,
                                                            label: `${bordereau.numero} — ${bordereau.nombre_ordres} OP — ${formatMontant(bordereau.montant_total)} FCFA`,
                                                        }),
                                                    )}
                                                    value={
                                                        bordereauxCourants
                                                            .filter(
                                                                (bordereau) =>
                                                                    bordereau.id ===
                                                                    details?.id,
                                                            )
                                                            .map(
                                                                (
                                                                    bordereau,
                                                                ) => ({
                                                                    value: bordereau.id,
                                                                    label: `${bordereau.numero} — ${bordereau.nombre_ordres} OP — ${formatMontant(bordereau.montant_total)} FCFA`,
                                                                }),
                                                            )[0] ?? null
                                                    }
                                                    onChange={(option) =>
                                                        choisirBordereau(
                                                            option?.value ?? 0,
                                                        )
                                                    }
                                                    placeholder={`Rechercher un ${libelleConteneur}…`}
                                                    noOptionsMessage={() =>
                                                        `Aucun ${libelleConteneur} disponible`
                                                    }
                                                    styles={selectStyles}
                                                />
                                            </Col>
                                            <Col xl={5} md={4}>
                                                <Label className="ac-field-label">
                                                    3. Ordre de paiement
                                                </Label>
                                                <Select
                                                    classNamePrefix="react-select"
                                                    isClearable
                                                    isSearchable
                                                    isDisabled={
                                                        !details || loadingOrdre
                                                    }
                                                    options={ordreOptions}
                                                    value={
                                                        ordreOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                selectedOrdreId,
                                                        ) ?? null
                                                    }
                                                    onChange={(option) =>
                                                        changerOrdre(
                                                            option?.value ?? 0,
                                                        )
                                                    }
                                                    placeholder={
                                                        details
                                                            ? 'Rechercher une OP…'
                                                            : `Choisir d’abord un ${libelleConteneur}`
                                                    }
                                                    noOptionsMessage={() =>
                                                        `Aucune OP disponible dans ce ${libelleConteneur}`
                                                    }
                                                    styles={selectStyles}
                                                />
                                            </Col>
                                        </Row>

                                        <Row className="g-3 align-items-end mt-1">
                                            <Col xl={6} md={6}>
                                                <Label className="ac-field-label">
                                                    4. Dossier
                                                </Label>
                                                <Select
                                                    classNamePrefix="react-select"
                                                    isClearable
                                                    isSearchable
                                                    isDisabled={
                                                        !ordreDetail ||
                                                        loadingOrdre
                                                    }
                                                    options={dossierOptions}
                                                    value={
                                                        dossierOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                Number(
                                                                    filtresOp.dossier_id,
                                                                ),
                                                        ) ?? null
                                                    }
                                                    onChange={(option) =>
                                                        changerFiltreDossier(
                                                            'dossier_id',
                                                            option?.value ??
                                                                null,
                                                        )
                                                    }
                                                    placeholder={
                                                        ordreDetail
                                                            ? 'Tous les dossiers de l’OP'
                                                            : 'Choisir d’abord une OP'
                                                    }
                                                    noOptionsMessage={() =>
                                                        'Aucun dossier dans cette OP'
                                                    }
                                                    styles={selectStyles}
                                                />
                                            </Col>
                                            <Col xl={6} md={6}>
                                                <Label className="ac-field-label">
                                                    5. Multi-dossier
                                                </Label>
                                                <Select
                                                    classNamePrefix="react-select"
                                                    isClearable
                                                    isSearchable
                                                    isDisabled={
                                                        !ordreDetail ||
                                                        loadingOrdre
                                                    }
                                                    options={
                                                        multiDossierOptions
                                                    }
                                                    value={
                                                        multiDossierOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                Number(
                                                                    filtresOp.multi_dossier_id,
                                                                ),
                                                        ) ?? null
                                                    }
                                                    onChange={(option) =>
                                                        changerFiltreDossier(
                                                            'multi_dossier_id',
                                                            option?.value ??
                                                                null,
                                                        )
                                                    }
                                                    placeholder={
                                                        ordreDetail
                                                            ? 'Tous les multi-dossiers de l’OP'
                                                            : 'Choisir d’abord une OP'
                                                    }
                                                    noOptionsMessage={() =>
                                                        'Aucun multi-dossier dans cette OP'
                                                    }
                                                    styles={selectStyles}
                                                />
                                            </Col>
                                        </Row>

                                        {!bordereauxCourants.length && (
                                            <Alert
                                                color="info"
                                                className="mt-3 mb-0"
                                            >
                                                <i className="ri-information-line me-2" />
                                                Aucun {libelleConteneur} dans la
                                                rubrique «{' '}
                                                {categorieCourante?.libelle} »
                                                pour {libelleMois(moisActuel)}.
                                            </Alert>
                                        )}
                                    </>
                                )}
                            </div>
                        </CardBody>
                    </Card>

                    {categorie === 'statuts' &&
                        sousOngletStatuts === 'par_op' &&
                        details && (
                            <Card className="ac-content-card mt-3 border-0">
                                <CardBody className="p-0">
                                    <div className="ac-op-header p-lg-4 p-3">
                                        <div>
                                            <span className="text-uppercase fs-11 fw-semibold text-muted">
                                                Ordres de paiement du{' '}
                                                {libelleConteneur}
                                            </span>
                                            <h4 className="mt-1 mb-0">
                                                {details.numero}
                                            </h4>
                                        </div>
                                        <div className="text-md-end">
                                            <span className="fs-11 text-uppercase d-block text-muted">
                                                {details.nombre_ordres} OP
                                            </span>
                                        </div>
                                    </div>
                                    <div className="p-lg-4 p-3 pt-0">
                                        <div className="ac-op-liste">
                                            {details.ordres.map((ordre) => {
                                                const compteurs =
                                                    ordre.compteurs;
                                                const total =
                                                    compteurs?.total ??
                                                    ordre.nombre_paiements;
                                                const traitees =
                                                    (compteurs?.payes ?? 0) +
                                                    (compteurs?.nonPayes ?? 0);
                                                const cloturee =
                                                    Boolean(compteurs) &&
                                                    total > 0 &&
                                                    traitees === total;
                                                const couleurStatut =
                                                    ordre.statut === 'VISE_AC'
                                                        ? 'success'
                                                        : ordre.statut.includes(
                                                                'REJETE',
                                                            )
                                                          ? 'danger'
                                                          : 'warning';

                                                return (
                                                    <button
                                                        type="button"
                                                        key={ordre.id}
                                                        className={classnames(
                                                            'ac-op-liste-ligne',
                                                            {
                                                                active:
                                                                    ordre.id ===
                                                                    selectedOrdreId,
                                                            },
                                                        )}
                                                        onClick={() =>
                                                            changerOrdre(
                                                                ordre.id,
                                                            )
                                                        }
                                                    >
                                                        <span className="ac-op-liste-numero">
                                                            <strong className="text-dark">
                                                                {ordre.numero}
                                                            </strong>
                                                            <Badge
                                                                color={
                                                                    couleurStatut
                                                                }
                                                            >
                                                                {libelleStatutOp(
                                                                    ordre.statut,
                                                                )}
                                                            </Badge>
                                                            {cloturee && (
                                                                <Badge
                                                                    color="success"
                                                                    className="ms-1"
                                                                >
                                                                    Clôturée
                                                                </Badge>
                                                            )}
                                                        </span>
                                                        <span className="ac-op-liste-compteurs">
                                                            {compteurs ? (
                                                                <>
                                                                    <Badge
                                                                        pill
                                                                        color="success"
                                                                    >
                                                                        {
                                                                            compteurs.payes
                                                                        }{' '}
                                                                        payés
                                                                    </Badge>
                                                                    <Badge
                                                                        pill
                                                                        color="danger"
                                                                    >
                                                                        {
                                                                            compteurs.nonPayes
                                                                        }{' '}
                                                                        non
                                                                        payés
                                                                    </Badge>
                                                                    {compteurs.valides >
                                                                        0 && (
                                                                        <Badge
                                                                            pill
                                                                            color="warning"
                                                                        >
                                                                            {
                                                                                compteurs.valides
                                                                            }{' '}
                                                                            à
                                                                            confirmer
                                                                        </Badge>
                                                                    )}
                                                                </>
                                                            ) : (
                                                                <span className="fs-12 text-muted">
                                                                    {
                                                                        ordre.nombre_paiements
                                                                    }{' '}
                                                                    paiements
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="ac-op-liste-montant">
                                                            <span className="fs-11 text-uppercase d-block text-muted">
                                                                Montant
                                                            </span>
                                                            <strong>
                                                                {formatMontant(
                                                                    ordre.montant_total,
                                                                )}{' '}
                                                                FCFA
                                                            </strong>
                                                        </span>
                                                        <i className="ri-arrow-right-s-line ac-op-liste-chevron" />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        )}

                    {categorie === 'statuts' &&
                    sousOngletStatuts !== 'par_op' ? (
                        <Card className="ac-content-card border-0">
                            <CardBody className="p-0">
                                <div className="ac-op-header p-lg-4 p-3">
                                    <div>
                                        <span className="text-uppercase fs-11 fw-semibold text-muted">
                                            Paiements{' '}
                                            {sousOngletStatuts === 'payes'
                                                ? 'payés'
                                                : 'marqués non payés'}
                                        </span>
                                        <div className="d-flex align-items-center mt-1 flex-wrap gap-2">
                                            <h4 className="mb-0">
                                                {sousOngletStatuts === 'payes'
                                                    ? 'Payés'
                                                    : 'Non payés'}{' '}
                                                — {libelleMois(moisActuel)}
                                            </h4>
                                            <Badge
                                                color={
                                                    sousOngletStatuts ===
                                                    'payes'
                                                        ? 'success'
                                                        : 'danger'
                                                }
                                            >
                                                {totalSituation} paiement
                                                {totalSituation > 1 ? 's' : ''}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="d-flex align-items-end flex-wrap gap-2">
                                        <div className="text-md-end me-2">
                                            <span className="fs-11 text-uppercase d-block text-muted">
                                                Montant{' '}
                                                {sousOngletStatuts === 'payes'
                                                    ? 'payé'
                                                    : 'annulé'}
                                            </span>
                                            <strong className="fs-5">
                                                {formatMontant(
                                                    montantSituation,
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </div>
                                        {lignesSituation.length > 0 && (
                                            <>
                                                <a
                                                    className="btn btn-sm btn-light border"
                                                    href={urlExportStatuts(
                                                        'excel',
                                                    )}
                                                    title="Extraire en Excel"
                                                >
                                                    <i className="ri-file-excel-2-line me-1" />
                                                    Excel
                                                </a>
                                                <a
                                                    className="btn btn-sm btn-light border"
                                                    href={urlExportStatuts(
                                                        'pdf',
                                                    )}
                                                    title="Extraire en PDF"
                                                >
                                                    <i className="ri-file-pdf-2-line me-1" />
                                                    PDF
                                                </a>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <div className="px-lg-4 px-3 pb-4">
                                    {loadingSituation &&
                                    !lignesSituation.length ? (
                                        <div className="ac-detail-loading">
                                            <Spinner color="success" />
                                            <span>
                                                Chargement des paiements…
                                            </span>
                                        </div>
                                    ) : !lignesSituation.length ? (
                                        <EmptyState
                                            icon={
                                                sousOngletStatuts === 'payes'
                                                    ? 'ri-checkbox-circle-line'
                                                    : 'ri-close-circle-line'
                                            }
                                            title={`Aucun paiement ${sousOngletStatuts === 'payes' ? 'payé' : 'non payé'}`}
                                            text={`Aucun paiement ${sousOngletStatuts === 'payes' ? 'payé' : 'marqué non payé'} pour ${libelleMois(moisActuel)}.`}
                                        />
                                    ) : (
                                        <>
                                            <div className="table-responsive ac-table-wrap">
                                                <Table
                                                    hover
                                                    className="ac-table mb-0 align-middle"
                                                >
                                                    <thead>
                                                        <tr>
                                                            <th>Dossier</th>
                                                            <th>OP</th>
                                                            <th>
                                                                Bénéficiaire
                                                            </th>
                                                            <th>Affectation</th>
                                                            <th>Stage</th>
                                                            <th>Paiement</th>
                                                            <th className="text-center">
                                                                Confirmé le
                                                            </th>
                                                            <th className="text-center">
                                                                Pièces
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {lignesSituation.map(
                                                            ({
                                                                stagiaire,
                                                                dossier,
                                                                ordre,
                                                            }) => {
                                                                const piecesConnues =
                                                                    Boolean(
                                                                        stagiaire.pieces,
                                                                    );
                                                                const aDesPieces =
                                                                    piecesConnues &&
                                                                    Object.values(
                                                                        stagiaire.pieces ??
                                                                            {},
                                                                    ).some(
                                                                        Boolean,
                                                                    );

                                                                return (
                                                                    <tr
                                                                        key={
                                                                            stagiaire.paiement_id
                                                                        }
                                                                    >
                                                                        <td>
                                                                            <strong className="text-dark">
                                                                                {
                                                                                    dossier.numero
                                                                                }
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Créé
                                                                                le{' '}
                                                                                {dossier.date_creation ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <strong className="text-dark">
                                                                                {
                                                                                    ordre.numero
                                                                                }
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {libelleStatutOp(
                                                                                    ordre.statut,
                                                                                )}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <strong>
                                                                                {stagiaire.nom ||
                                                                                    '—'}{' '}
                                                                                {stagiaire.prenoms ||
                                                                                    ''}
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-primary">
                                                                                AEJ
                                                                                :{' '}
                                                                                {stagiaire.numero_aej ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Né(e)
                                                                                le{' '}
                                                                                {stagiaire.date_naissance ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span className="d-block">
                                                                                {dossier.agence ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {stagiaire.entreprise ||
                                                                                    'Entreprise non renseignée'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span className="d-block">
                                                                                {stagiaire.type_stage ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {stagiaire.date_debut ||
                                                                                    '—'}{' '}
                                                                                →{' '}
                                                                                {stagiaire.date_fin ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <strong className="text-dark">
                                                                                {formatMontant(
                                                                                    stagiaire.montant,
                                                                                )}{' '}
                                                                                FCFA
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Trésor
                                                                                Money
                                                                                :{' '}
                                                                                {stagiaire.numero_tresor_money ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {dossier.source_financement ||
                                                                                    'Financement non renseigné'}
                                                                            </span>
                                                                        </td>
                                                                        <td className="text-center">
                                                                            {stagiaire.decide_le ? (
                                                                                <>
                                                                                    <Badge
                                                                                        pill
                                                                                        color={
                                                                                            sousOngletStatuts ===
                                                                                            'payes'
                                                                                                ? 'success'
                                                                                                : 'danger'
                                                                                        }
                                                                                        className="d-inline-block mb-1"
                                                                                    >
                                                                                        {sousOngletStatuts ===
                                                                                        'payes'
                                                                                            ? 'Payé'
                                                                                            : 'Non payé'}
                                                                                    </Badge>
                                                                                    <span className="d-block fs-12 text-muted">
                                                                                        {
                                                                                            stagiaire.decide_le
                                                                                        }
                                                                                    </span>
                                                                                </>
                                                                            ) : (
                                                                                <span className="fs-12 text-muted">
                                                                                    —
                                                                                </span>
                                                                            )}
                                                                        </td>
                                                                        <td className="text-center">
                                                                            <Button
                                                                                size="sm"
                                                                                color={
                                                                                    piecesConnues &&
                                                                                    !aDesPieces
                                                                                        ? 'light'
                                                                                        : 'primary'
                                                                                }
                                                                                outline={
                                                                                    !piecesConnues ||
                                                                                    aDesPieces
                                                                                }
                                                                                disabled={
                                                                                    !stagiaire.stage_id
                                                                                }
                                                                                onClick={() =>
                                                                                    void ouvrirPieces(
                                                                                        stagiaire,
                                                                                    )
                                                                                }
                                                                            >
                                                                                {loadingPieces &&
                                                                                piecesStagiaire?.stage_id ===
                                                                                    stagiaire.stage_id ? (
                                                                                    <Spinner
                                                                                        size="sm"
                                                                                        className="me-1"
                                                                                    />
                                                                                ) : (
                                                                                    <i className="ri-eye-line me-1" />
                                                                                )}
                                                                                {!piecesConnues
                                                                                    ? 'Vérifier'
                                                                                    : aDesPieces
                                                                                      ? 'Voir'
                                                                                      : 'Aucune'}
                                                                            </Button>
                                                                        </td>
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>

                                            {lignesSituation.length <
                                                totalSituation && (
                                                <div className="pt-3 text-center">
                                                    <Button
                                                        color="light"
                                                        size="sm"
                                                        disabled={
                                                            loadingSituation
                                                        }
                                                        onClick={() =>
                                                            void chargerSituation(
                                                                sousOngletStatuts as Exclude<
                                                                    SousOngletStatuts,
                                                                    'par_op'
                                                                >,
                                                                pageSituation +
                                                                    1,
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        {loadingSituation ? (
                                                            <Spinner
                                                                size="sm"
                                                                className="me-1"
                                                            />
                                                        ) : (
                                                            <i className="ri-add-line me-1" />
                                                        )}
                                                        Charger plus (
                                                        {totalSituation -
                                                            lignesSituation.length}{' '}
                                                        restant
                                                        {totalSituation -
                                                            lignesSituation.length >
                                                        1
                                                            ? 's'
                                                            : ''}
                                                        )
                                                    </Button>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                            </CardBody>
                        </Card>
                    ) : !details ? (
                        <Card className="ac-content-card border-0">
                            <CardBody>
                                <EmptyState
                                    icon="ri-file-list-3-line"
                                    title={`Sélectionnez un ${libelleConteneur}`}
                                    text="Ses OP et leurs stagiaires apparaîtront ici, sans changer de page."
                                />
                            </CardBody>
                        </Card>
                    ) : (
                        <>
                            <Card className="ac-context-card border-0">
                                <CardBody className="p-3">
                                    <div className="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                        <div className="d-flex align-items-center gap-3">
                                            <span className="ac-context-icon">
                                                <i className="ri-file-list-3-line" />
                                            </span>
                                            <div>
                                                <div className="d-flex align-items-center flex-wrap gap-2">
                                                    <h5 className="mb-0">
                                                        {details.numero}
                                                    </h5>
                                                    <Badge
                                                        color={
                                                            categorie ===
                                                            'attente'
                                                                ? 'warning'
                                                                : categorie ===
                                                                    'statuts'
                                                                  ? 'success'
                                                                  : 'danger'
                                                        }
                                                    >
                                                        {
                                                            categorieCourante?.libelle
                                                        }
                                                    </Badge>
                                                </div>
                                                <div className="fs-13 mt-1 text-muted">
                                                    {details.source_financement
                                                        ?.libelle ||
                                                        'Financement non renseigné'}{' '}
                                                    · {details.nombre_dossiers}{' '}
                                                    dossiers ·{' '}
                                                    {details.nombre_paiements}{' '}
                                                    paiements
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-md-end">
                                            <span className="fs-11 text-uppercase d-block text-muted">
                                                Montant du {libelleConteneur}
                                            </span>
                                            <strong className="fs-5 text-dark">
                                                {formatMontant(
                                                    details.montant_total,
                                                )}{' '}
                                                FCFA
                                            </strong>
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>

                            <Card className="ac-content-card border-0">
                                <CardBody className="p-0">
                                    <div className="ac-op-header p-lg-4 p-3">
                                        <div>
                                            <span className="text-uppercase fs-11 fw-semibold text-muted">
                                                Ordre de paiement
                                            </span>
                                            <div className="d-flex align-items-center mt-1 flex-wrap gap-2">
                                                <h4 className="mb-0">
                                                    {loadingOrdre
                                                        ? 'Chargement…'
                                                        : ordreDetail?.numero ||
                                                          (selectedOrdreId
                                                              ? 'Détail indisponible'
                                                              : 'Sélectionnez une OP')}
                                                </h4>
                                                {ordreDetail && (
                                                    <Badge
                                                        color={
                                                            ordreDetail.statut ===
                                                            'VISE_AC'
                                                                ? 'success'
                                                                : ordreDetail.statut.includes(
                                                                        'REJETE',
                                                                    )
                                                                  ? 'danger'
                                                                  : 'warning'
                                                        }
                                                    >
                                                        {libelleStatutOp(
                                                            ordreDetail.statut,
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                        {ordreDetail && (
                                            <div className="text-md-end">
                                                <span className="fs-11 text-uppercase d-block text-muted">
                                                    Montant de l’OP
                                                </span>
                                                <strong className="fs-5">
                                                    {formatMontant(
                                                        ordreDetail.montant_total,
                                                    )}{' '}
                                                    FCFA
                                                </strong>
                                            </div>
                                        )}
                                    </div>

                                    {ordreDetail && aDesActions && (
                                        <div className="ac-decision-bar px-lg-4 px-3 py-3">
                                            <div className="d-flex align-items-center flex-wrap gap-2">
                                                <span className="fw-semibold me-2">
                                                    <i className="ri-scales-3-line me-2" />
                                                    Décision sur l’OP
                                                </span>
                                                {ordreDetail.actions.valider &&
                                                    ordreSelectionne && (
                                                        <Button
                                                            size="sm"
                                                            color="success"
                                                            onClick={() =>
                                                                setOrdreAValider(
                                                                    ordreSelectionne,
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-check-double-line me-1" />
                                                            Valider l’OP
                                                        </Button>
                                                    )}
                                                {ordreDetail.actions.differer &&
                                                    ordreSelectionne && (
                                                        <Button
                                                            size="sm"
                                                            color="warning"
                                                            onClick={() =>
                                                                ouvrirActionOp(
                                                                    ordreSelectionne,
                                                                    'differer',
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-pause-circle-line me-1" />
                                                            Différer l’OP
                                                        </Button>
                                                    )}
                                                {ordreDetail.actions.rejeter &&
                                                    ordreSelectionne && (
                                                        <Button
                                                            size="sm"
                                                            color="danger"
                                                            outline
                                                            onClick={() =>
                                                                ouvrirActionOp(
                                                                    ordreSelectionne,
                                                                    'rejeter',
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-close-circle-line me-1" />
                                                            Rejeter l’OP
                                                        </Button>
                                                    )}
                                                {ordreDetail.actions.retirer &&
                                                    ordreSelectionne && (
                                                        <Button
                                                            size="sm"
                                                            color="secondary"
                                                            outline
                                                            onClick={() =>
                                                                ouvrirActionOp(
                                                                    ordreSelectionne,
                                                                    'retirer',
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-subtract-line me-1" />
                                                            Retirer du bordereau
                                                        </Button>
                                                    )}
                                            </div>
                                        </div>
                                    )}

                                    <Nav
                                        tabs
                                        className="ac-tabs ac-status-tabs px-lg-4 px-3"
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
                                                    {onglet.libelle}
                                                    <Badge
                                                        pill
                                                        color={onglet.couleur}
                                                        className="ms-2"
                                                    >
                                                        {ordreDetail?.compteurs[
                                                            onglet.id
                                                        ] ?? 0}
                                                    </Badge>
                                                </NavLink>
                                            </NavItem>
                                        ))}
                                    </Nav>

                                    <div className="ac-search-bar p-lg-4 p-3">
                                        <Row className="g-2 align-items-end">
                                            <Col lg={7} md={6}>
                                                <Label className="ac-field-label">
                                                    Rechercher un stagiaire ou
                                                    un dossier
                                                </Label>
                                                <div className="position-relative">
                                                    <i className="ri-search-line ac-input-icon" />
                                                    <Input
                                                        className="ac-control ps-5"
                                                        value={
                                                            filtresOp.recherche
                                                        }
                                                        onChange={(event) =>
                                                            setFiltresOp({
                                                                ...filtresOp,
                                                                recherche:
                                                                    event.target
                                                                        .value,
                                                            })
                                                        }
                                                        onKeyDown={(event) =>
                                                            event.key ===
                                                                'Enter' &&
                                                            appliquerFiltres()
                                                        }
                                                        placeholder="Nom, prénoms, numéro AEJ ou numéro de dossier"
                                                    />
                                                </div>
                                            </Col>
                                            <Col lg="auto" md={3}>
                                                <Button
                                                    color="success"
                                                    className="ac-search-button w-100"
                                                    disabled={loadingOrdre}
                                                    onClick={appliquerFiltres}
                                                >
                                                    <i className="ri-search-line me-1" />
                                                    Rechercher
                                                </Button>
                                            </Col>
                                            <Col lg="auto" md={3}>
                                                <Button
                                                    color="light"
                                                    className="ac-search-button w-100"
                                                    onClick={() =>
                                                        setFiltresOuverts(
                                                            (ouvert) => !ouvert,
                                                        )
                                                    }
                                                >
                                                    <i className="ri-equalizer-line me-1" />
                                                    Filtres avancés
                                                    <i
                                                        className={`ms-2 ${filtresOuverts ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'}`}
                                                    />
                                                </Button>
                                            </Col>
                                        </Row>
                                        <Collapse isOpen={filtresOuverts}>
                                            <div className="ac-advanced-filters mt-3 pt-3">
                                                <Row className="g-3 align-items-end">
                                                    {(
                                                        [
                                                            {
                                                                champ: 'agence_id',
                                                                label: 'Agence',
                                                                options:
                                                                    ordreDetail
                                                                        ?.referentiels
                                                                        .agences,
                                                            },
                                                            {
                                                                champ: 'entreprise_id',
                                                                label: 'Entreprise',
                                                                options:
                                                                    ordreDetail
                                                                        ?.referentiels
                                                                        .entreprises,
                                                            },
                                                            {
                                                                champ: 'source_financement_id',
                                                                label: 'Financement',
                                                                options:
                                                                    ordreDetail
                                                                        ?.referentiels
                                                                        .sources_financement,
                                                            },
                                                            {
                                                                champ: 'type_stage_id',
                                                                label: 'Type de stage',
                                                                options:
                                                                    ordreDetail
                                                                        ?.referentiels
                                                                        .types_stage,
                                                            },
                                                        ] as Array<{
                                                            champ: keyof FiltresOp;
                                                            label: string;
                                                            options?: OptionReferentiel[];
                                                        }>
                                                    ).map(
                                                        ({
                                                            champ,
                                                            label,
                                                            options = [],
                                                        }) => (
                                                            <Col
                                                                key={champ}
                                                                xl={3}
                                                                md={6}
                                                            >
                                                                <Label className="ac-field-label">
                                                                    {label}
                                                                </Label>
                                                                <Select
                                                                    classNamePrefix="react-select"
                                                                    isClearable
                                                                    isSearchable
                                                                    options={options.map(
                                                                        (
                                                                            option,
                                                                        ) => ({
                                                                            value: option.id,
                                                                            label: option.libelle,
                                                                        }),
                                                                    )}
                                                                    value={
                                                                        options
                                                                            .filter(
                                                                                (
                                                                                    option,
                                                                                ) =>
                                                                                    option.id ===
                                                                                    Number(
                                                                                        filtresOp[
                                                                                            champ
                                                                                        ],
                                                                                    ),
                                                                            )
                                                                            .map(
                                                                                (
                                                                                    option,
                                                                                ) => ({
                                                                                    value: option.id,
                                                                                    label: option.libelle,
                                                                                }),
                                                                            )[0] ??
                                                                        null
                                                                    }
                                                                    onChange={(
                                                                        option,
                                                                    ) =>
                                                                        setFiltresOp(
                                                                            {
                                                                                ...filtresOp,
                                                                                [champ]:
                                                                                    option
                                                                                        ? String(
                                                                                              option.value,
                                                                                          )
                                                                                        : '',
                                                                            },
                                                                        )
                                                                    }
                                                                    placeholder={`Toutes les ${label.toLowerCase()}…`}
                                                                    noOptionsMessage={() =>
                                                                        'Aucune option'
                                                                    }
                                                                    styles={
                                                                        selectStyles
                                                                    }
                                                                />
                                                            </Col>
                                                        ),
                                                    )}
                                                    <Col xl={3} md={6}>
                                                        <Label className="ac-field-label">
                                                            Date début
                                                        </Label>
                                                        <Input
                                                            className="ac-control"
                                                            type="date"
                                                            value={
                                                                filtresOp.date_debut
                                                            }
                                                            onChange={(event) =>
                                                                setFiltresOp({
                                                                    ...filtresOp,
                                                                    date_debut:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                })
                                                            }
                                                        />
                                                    </Col>
                                                    <Col xl={3} md={6}>
                                                        <Label className="ac-field-label">
                                                            Date fin
                                                        </Label>
                                                        <Input
                                                            className="ac-control"
                                                            type="date"
                                                            value={
                                                                filtresOp.date_fin
                                                            }
                                                            onChange={(event) =>
                                                                setFiltresOp({
                                                                    ...filtresOp,
                                                                    date_fin:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                })
                                                            }
                                                        />
                                                    </Col>
                                                    <Col
                                                        xs={12}
                                                        className="text-end"
                                                    >
                                                        <Button
                                                            size="sm"
                                                            color="link"
                                                            className="text-muted"
                                                            onClick={
                                                                reinitialiserFiltres
                                                            }
                                                        >
                                                            <i className="ri-refresh-line me-1" />
                                                            Réinitialiser les
                                                            filtres
                                                        </Button>
                                                    </Col>
                                                </Row>
                                            </div>
                                        </Collapse>
                                    </div>

                                    <div className="px-lg-4 px-3 pb-4">
                                        {selection.length > 0 &&
                                            ongletStagiaire === 'attente' &&
                                            ordreDetail?.actions
                                                .differer_stagiaires && (
                                                <Alert
                                                    color="warning"
                                                    className="ac-selection-bar d-flex align-items-center justify-content-between flex-wrap gap-2"
                                                >
                                                    <span>
                                                        <strong>
                                                            {selection.length}
                                                        </strong>{' '}
                                                        stagiaire
                                                        {selection.length > 1
                                                            ? 's'
                                                            : ''}{' '}
                                                        sélectionné
                                                        {selection.length > 1
                                                            ? 's'
                                                            : ''}
                                                    </span>
                                                    <Button
                                                        size="sm"
                                                        color="warning"
                                                        onClick={() => {
                                                            setMotifOp('');
                                                            setDifferePartiel(
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        <i className="ri-arrow-go-back-line me-1" />
                                                        Différer la sélection
                                                        vers la DMG
                                                    </Button>
                                                </Alert>
                                            )}

                                        {selection.length > 0 &&
                                            ongletStagiaire === 'valide' &&
                                            ordreDetail?.actions
                                                .confirmer_paiements && (
                                                <Alert
                                                    color="info"
                                                    className="ac-selection-bar d-flex align-items-center justify-content-between flex-wrap gap-2"
                                                >
                                                    <span>
                                                        <strong>
                                                            {selection.length}
                                                        </strong>{' '}
                                                        paiement
                                                        {selection.length > 1
                                                            ? 's'
                                                            : ''}{' '}
                                                        à confirmer
                                                    </span>
                                                    <div className="d-flex gap-2">
                                                        <Button
                                                            size="sm"
                                                            color="success"
                                                            onClick={() =>
                                                                ouvrirSituationPaiements(
                                                                    selection,
                                                                    'PAYE',
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-check-line me-1" />
                                                            Marquer payé(s)
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            color="danger"
                                                            onClick={() =>
                                                                ouvrirSituationPaiements(
                                                                    selection,
                                                                    'NON_PAYE',
                                                                )
                                                            }
                                                        >
                                                            <i className="ri-close-line me-1" />
                                                            Annuler paiement
                                                        </Button>
                                                    </div>
                                                </Alert>
                                            )}

                                        {loadingOrdre ? (
                                            <div className="ac-detail-loading">
                                                <Spinner color="success" />
                                                <span>Chargement de l’OP…</span>
                                            </div>
                                        ) : detailError ? (
                                            <Alert
                                                color="danger"
                                                className="mb-0"
                                            >
                                                {detailError}
                                            </Alert>
                                        ) : !ordreDetail ? (
                                            <EmptyState
                                                title="Sélectionnez une OP"
                                                text="La liste des stagiaires sera chargée automatiquement."
                                            />
                                        ) : !lignesAffichees.length ? (
                                            <EmptyState
                                                title="Aucun stagiaire"
                                                text="Aucun paiement ne correspond à cet onglet et aux filtres appliqués."
                                            />
                                        ) : (
                                            <div className="table-responsive ac-table-wrap">
                                                <Table
                                                    hover
                                                    className="ac-table mb-0 align-middle"
                                                >
                                                    <thead>
                                                        <tr>
                                                            {(ongletStagiaire ===
                                                                'attente' ||
                                                                ongletStagiaire ===
                                                                    'valide') && (
                                                                <th
                                                                    className="text-center"
                                                                    style={{
                                                                        width: 44,
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
                                                            <th>Dossier</th>
                                                            <th>
                                                                Bénéficiaire
                                                            </th>
                                                            <th>Affectation</th>
                                                            <th>Stage</th>
                                                            <th>Paiement</th>
                                                            <th className="text-center">
                                                                Pièces
                                                            </th>
                                                            {ongletStagiaire ===
                                                                'valide' && (
                                                                <th className="text-center">
                                                                    Situation
                                                                </th>
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {lignesAffichees.map(
                                                            ({
                                                                stagiaire,
                                                                dossier,
                                                            }) => {
                                                                const piecesConnues =
                                                                    Boolean(
                                                                        stagiaire.pieces,
                                                                    );
                                                                const aDesPieces =
                                                                    piecesConnues &&
                                                                    Object.values(
                                                                        stagiaire.pieces ??
                                                                            {},
                                                                    ).some(
                                                                        Boolean,
                                                                    );

                                                                return (
                                                                    <tr
                                                                        key={
                                                                            stagiaire.paiement_id
                                                                        }
                                                                    >
                                                                        {(ongletStagiaire ===
                                                                            'attente' ||
                                                                            ongletStagiaire ===
                                                                                'valide') && (
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
                                                                        <td>
                                                                            <strong className="text-dark">
                                                                                {
                                                                                    dossier.numero
                                                                                }
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Créé
                                                                                le{' '}
                                                                                {dossier.date_creation ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <strong>
                                                                                {stagiaire.nom ||
                                                                                    '—'}{' '}
                                                                                {stagiaire.prenoms ||
                                                                                    ''}
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-primary">
                                                                                AEJ
                                                                                :{' '}
                                                                                {stagiaire.numero_aej ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Né(e)
                                                                                le{' '}
                                                                                {stagiaire.date_naissance ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span className="d-block">
                                                                                {dossier.agence ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {stagiaire.entreprise ||
                                                                                    'Entreprise non renseignée'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <span className="d-block">
                                                                                {stagiaire.type_stage ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {stagiaire.date_debut ||
                                                                                    '—'}{' '}
                                                                                →{' '}
                                                                                {stagiaire.date_fin ||
                                                                                    '—'}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            <strong className="text-dark">
                                                                                {formatMontant(
                                                                                    stagiaire.montant,
                                                                                )}{' '}
                                                                                FCFA
                                                                            </strong>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                Trésor
                                                                                Money
                                                                                :{' '}
                                                                                {stagiaire.numero_tresor_money ||
                                                                                    '—'}
                                                                            </span>
                                                                            <span className="d-block fs-12 text-muted">
                                                                                {dossier.source_financement ||
                                                                                    'Financement non renseigné'}
                                                                            </span>
                                                                        </td>
                                                                        <td className="text-center">
                                                                            <Button
                                                                                size="sm"
                                                                                color={
                                                                                    piecesConnues &&
                                                                                    !aDesPieces
                                                                                        ? 'light'
                                                                                        : 'primary'
                                                                                }
                                                                                outline={
                                                                                    !piecesConnues ||
                                                                                    aDesPieces
                                                                                }
                                                                                disabled={
                                                                                    !stagiaire.stage_id
                                                                                }
                                                                                onClick={() =>
                                                                                    void ouvrirPieces(
                                                                                        stagiaire,
                                                                                    )
                                                                                }
                                                                            >
                                                                                {loadingPieces &&
                                                                                piecesStagiaire?.stage_id ===
                                                                                    stagiaire.stage_id ? (
                                                                                    <Spinner
                                                                                        size="sm"
                                                                                        className="me-1"
                                                                                    />
                                                                                ) : (
                                                                                    <i className="ri-eye-line me-1" />
                                                                                )}
                                                                                {!piecesConnues
                                                                                    ? 'Vérifier'
                                                                                    : aDesPieces
                                                                                      ? 'Voir'
                                                                                      : 'Aucune'}
                                                                            </Button>
                                                                        </td>
                                                                        {ongletStagiaire ===
                                                                            'valide' && (
                                                                            <td className="text-center">
                                                                                <Button
                                                                                    size="sm"
                                                                                    color="success"
                                                                                    outline
                                                                                    disabled={
                                                                                        !ordreDetail
                                                                                            .actions
                                                                                            .confirmer_paiements
                                                                                    }
                                                                                    onClick={() =>
                                                                                        ouvrirSituationPaiements(
                                                                                            [
                                                                                                stagiaire.paiement_id,
                                                                                            ],
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <i className="ri-check-double-line me-1" />
                                                                                    Confirmer
                                                                                </Button>
                                                                            </td>
                                                                        )}
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        )}
                                    </div>
                                </CardBody>
                            </Card>
                        </>
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
                        Le bordereau sera clôturé après la validation de sa
                        dernière OP.
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
                        color="success"
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
                        <strong>{ordreDetail?.numero}</strong> reste en attente.
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

            <Modal
                isOpen={Boolean(situationPaiements)}
                toggle={() => !processing && setSituationPaiements(null)}
                centered
            >
                <ModalHeader
                    toggle={() => !processing && setSituationPaiements(null)}
                >
                    Situation paiement
                </ModalHeader>
                <ModalBody>
                    <Alert color="info" className="fs-13">
                        <i className="ri-information-line me-2" />
                        Confirmez la situation de{' '}
                        <strong>{situationPaiements?.length ?? 0}</strong>{' '}
                        paiement(s) de l’OP{' '}
                        <strong>{ordreDetail?.numero}</strong>.
                    </Alert>
                    <Label className="fw-semibold">Statut</Label>
                    <Select
                        classNamePrefix="react-select"
                        isSearchable={false}
                        options={[
                            { value: 'PAYE', label: 'Payé' },
                            { value: 'NON_PAYE', label: 'Non payé / annulé' },
                        ]}
                        value={
                            situationPaiement
                                ? {
                                      value: situationPaiement,
                                      label:
                                          situationPaiement === 'PAYE'
                                              ? 'Payé'
                                              : 'Non payé / annulé',
                                  }
                                : null
                        }
                        onChange={(option) =>
                            setSituationPaiement(
                                (option?.value as 'PAYE' | 'NON_PAYE') ?? '',
                            )
                        }
                        placeholder="Sélectionner la situation…"
                        styles={selectStyles}
                    />
                    <Label className="fw-semibold mt-3">
                        Commentaire obligatoire
                    </Label>
                    <Input
                        invalid={Boolean(errors?.motif)}
                        type="textarea"
                        rows={4}
                        maxLength={2000}
                        value={motifOp}
                        onChange={(event) => setMotifOp(event.target.value)}
                        placeholder="Précisez la confirmation ou le motif d’annulation…"
                    />
                    <FormFeedback>{errors?.motif}</FormFeedback>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        disabled={processing}
                        onClick={() => setSituationPaiements(null)}
                    >
                        Annuler
                    </Button>
                    <Button
                        color={
                            situationPaiement === 'NON_PAYE'
                                ? 'danger'
                                : 'success'
                        }
                        disabled={
                            processing ||
                            !situationPaiement ||
                            motifOp.trim().length < 3
                        }
                        onClick={confirmerSituationPaiements}
                    >
                        {processing && <Spinner size="sm" className="me-2" />}
                        Enregistrer
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal
                isOpen={Boolean(piecesStagiaire)}
                toggle={() => setPiecesStagiaire(null)}
                centered
                size="lg"
            >
                <ModalHeader toggle={() => setPiecesStagiaire(null)}>
                    Pièces justificatives — {piecesStagiaire?.nom}{' '}
                    {piecesStagiaire?.prenoms}
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        Ouvrez chaque document disponible dans un nouvel onglet
                        pour effectuer le contrôle.
                    </p>
                    <Row className="g-3">
                        {piecesConfig.map((piece) => {
                            const disponible = Boolean(
                                piecesStagiaire?.pieces?.[piece.id],
                            );
                            const href = piecesStagiaire?.stage_id
                                ? `/agent-comptable/paiements/piece?stage_id=${piecesStagiaire.stage_id}&type=${piece.id}`
                                : '#';

                            return (
                                <Col md={6} key={piece.id}>
                                    <a
                                        className={classnames('ac-piece-card', {
                                            'is-disabled': !disponible,
                                        })}
                                        href={disponible ? href : undefined}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-disabled={!disponible}
                                    >
                                        <span>
                                            <i className={piece.icone} />
                                        </span>
                                        <div>
                                            <strong>{piece.libelle}</strong>
                                            <small>
                                                {disponible
                                                    ? 'Ouvrir le document'
                                                    : 'Document non disponible'}
                                            </small>
                                        </div>
                                        <i
                                            className={
                                                disponible
                                                    ? 'ri-external-link-line'
                                                    : 'ri-forbid-line'
                                            }
                                        />
                                    </a>
                                </Col>
                            );
                        })}
                    </Row>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        onClick={() => setPiecesStagiaire(null)}
                    >
                        Fermer
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
}
