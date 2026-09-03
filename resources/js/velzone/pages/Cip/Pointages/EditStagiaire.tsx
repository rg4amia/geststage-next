import { Head, router, useForm, usePage } from '@inertiajs/react';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
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
    Form,
    Input,
    Label,
    Row,
    Spinner,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

/* ═══════════════════════════════════════════════════════════════════════════
   TYPES
   ═══════════════════════════════════════════════════════════════════════════ */
interface RefItem {
    id: number;
    nom: string;
    code?: string;
    niveau_id?: number;
}

interface OffreItem {
    id: number;
    numero: string;
    intitule: string;
    nombre_de_place: number;
    entreprise_id: number;
    agence_id: number;
    type_stage_id: number;
    source_financement_id: number;
    entreprise?: { raison_sociale: string; sigle?: string };
    agence?: { nom: string };
    typeStage?: { nom: string };
    sourceFinancement?: { nom: string };
}

interface ConseillerItem {
    id: number;
    nom: string;
    prenoms?: string;
    nom_complet: string;
    agence_id: number;
    agence?: { id: number; nom: string };
}

interface TypePaiementItem extends RefItem {
    est_tresor_money: boolean;
    est_wave: boolean;
}

interface CodeItem {
    code: string;
    nom: string;
}

interface DocumentDepose {
    code: string | null;
    nom: string | null;
    derniere_version: {
        numero_version: number;
        chemin: string;
        nom_original: string;
    } | null;
}

interface Props {
    stage: any;
    rejet: {
        paiement_id: number;
        montant: string | number;
        motif: string;
        decide_le: string | null;
        auteur: string | null;
    } | null;
    peutTransmettre: boolean;
    documents: DocumentDepose[];
    typesDocument: CodeItem[];
    references: {
        typesPaiement: TypePaiementItem[];
        entreprises: { id: number; raison_sociale: string }[];
        typesStage: RefItem[];
        communes: RefItem[];
        diplomes: RefItem[];
        niveauxEtude: RefItem[];
        typesEnseignement: RefItem[];
        handicaps: RefItem[];
        typesHandicap: RefItem[];
        liensParente: RefItem[];
        typesPieceIdentite: string[];
        statutsStage: CodeItem[];
        situationsStage: CodeItem[];
    };
    // Données alignées sur Inscriptions/Create
    offres: OffreItem[];
    agences: RefItem[];
    originesStagiaire: RefItem[];
    sourcesFinancement: RefItem[];
    typesStructure: RefItem[];
    conseillers: ConseillerItem[];
    authUserAgenceIds: number[];
    returnTo?: { tab?: string; mois?: string | null };
}

type Action = 'enregistrer' | 'enregistrer_transmettre';

/* ═══════════════════════════════════════════════════════════════════════════
   CONSTANTES (alignées sur Inscriptions/Create)
   ═══════════════════════════════════════════════════════════════════════════ */
const ALLOWED_COHORT_DAYS = [1, 2, 3, 4, 5, 10, 20];

const TYPE_PIECE_OPTIONS = [
    { value: "ATTESTATION D'IDENTITÉ", label: "Attestation d'identite" },
    { value: 'AUTRES', label: 'Autres' },
    { value: 'RÉCIPISSÉ CNI', label: 'Recepisse CNI' },
    { value: 'CARTE CMU', label: 'Carte CMU' },
    { value: "CARTE NATIONALE D'IDENTITÉ (BLANC)", label: 'CNI blanc' },
    { value: "CARTE NATIONALE D'IDENTITÉ (ORANGE)", label: 'CNI orange' },
    { value: 'PASSEPORT', label: 'Passeport' },
];

const HANDICAP_OPTIONS = [
    { value: 'HANDICAP', label: 'Handicap' },
    { value: 'SANS HANDICAP', label: 'Sans handicap' },
];

const TYPE_HANDICAP_OPTIONS = [
    { value: 'HANDICAP MENTAL', label: 'Handicap mental' },
    { value: 'HANDICAP MOTEUR', label: 'Handicap moteur' },
    { value: 'HANDICAP PSYCHIQUE', label: 'Handicap psychique' },
    { value: 'HANDICAP SENSORIEL', label: 'Handicap sensoriel' },
    { value: 'MALADIE INVALIDANTE', label: 'Maladie invalidante' },
    { value: 'AUTRE', label: 'Autre' },
];

const ALLOWED_DOC_EXTENSIONS = ['pdf', 'doc', 'docx'];
const ALLOWED_IMAGE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
const MAX_FILE_SIZE_KB = 10240; // 10 MB

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS (alignés sur Inscriptions/Create)
   ═══════════════════════════════════════════════════════════════════════════ */
/** Les dates arrivent en ISO complet ; `<Input type="date">` n'accepte que YYYY-MM-DD. */
const jour = (valeur: any): string => (valeur ? String(valeur).slice(0, 10) : '');

const texte = (valeur: any): string => (valeur === null || valeur === undefined ? '' : String(valeur));

const todayString = () => new Date().toISOString().slice(0, 10);

const normalizeLabel = (v: string) =>
    v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

const addMonths = (d: Date, m: number) => {
    const n = new Date(d);
    n.setMonth(n.getMonth() + m);
    return n;
};
const addDays = (d: Date, days: number) => {
    const n = new Date(d);
    n.setDate(n.getDate() + days);
    return n;
};
const fmt = (d: Date) => d.toISOString().slice(0, 10);

const calculateDateFin = (dateDebut: string, duree: string): string => {
    if (!dateDebut || !duree) return '';
    const start = new Date(`${dateDebut}T00:00:00`);
    if (isNaN(start.getTime())) return '';
    if (duree === '1.5') {
        return start.getDate() > 1 ? fmt(addDays(start, 45)) : fmt(addDays(addMonths(start, 1), 14));
    }
    const months = Number(duree);
    if (!isFinite(months)) return '';
    return fmt(addDays(addMonths(start, months), -1));
};

const calculAge = (dateNaissance: string): number => {
    if (!dateNaissance) return 0;
    const birth = new Date(`${dateNaissance}T00:00:00`);
    if (isNaN(birth.getTime())) return 0;
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const monthDiff = now.getMonth() - birth.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) age--;
    return age;
};

const calculDateDemarrageCap = (dateDebut: string, nbMois: number): string => {
    if (!dateDebut || nbMois <= 0) return '';
    const start = new Date(`${dateDebut}T00:00:00`);
    return fmt(addMonths(start, nbMois));
};

const getPieceMaxLength = (typePiece: string): number => {
    const map: Record<string, number> = {
        "ATTESTATION D'IDENTITÉ": 20,
        ATTESTATION: 20,
        AUTRES: 20,
        'RÉCIPISSÉ CNI': 11,
        'CARTE CMU': 13,
        "CARTE NATIONALE D'IDENTITÉ (BLANC)": 9,
        "CARTE NATIONALE D'IDENTITÉ (ORANGE)": 10,
        PASSEPORT: 10,
    };
    return map[normalizeLabel(typePiece)] ?? 20;
};

const getPiecePrefix = (typePiece: string): string => {
    const n = normalizeLabel(typePiece);
    if (n.includes('BLANC')) return 'CI';
    if (n.includes('ORANGE')) return 'C';
    return '';
};

const validateFileExtension = (file: File, allowedExts: string[]): boolean => {
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    return allowedExts.includes(ext);
};

const validateFileSize = (file: File, maxKB: number): boolean => {
    return file.size <= maxKB * 1024;
};

/** Types de stage filtrés selon l'origine et la financement (par CODE) */
const getFilteredTypeStageIds = (
    typesStage: RefItem[],
    originesStagiaire: RefItem[],
    sourcesFinancement: RefItem[],
    origineId: string,
    financementId: string,
): string[] => {
    const origine = originesStagiaire.find(o => String(o.id) === origineId);
    const financement = sourcesFinancement.find(s => String(s.id) === financementId);
    const fCode = normalizeLabel(financement?.code || financement?.nom || '');
    const oCode = normalizeLabel(origine?.code || origine?.nom || '');

    if (fCode.includes('C2D') || fCode.includes('PEJEDEC')) {
        return typesStage.filter(ts => normalizeLabel(ts.nom).includes('QUALIFICATION')).map(ts => String(ts.id));
    }
    if (fCode.includes('BUDGET AEJ') && oCode.includes('CAPITALISATION AVEC')) {
        return typesStage.filter(ts => normalizeLabel(ts.nom).includes('ECOLE')).map(ts => String(ts.id));
    }
    if (fCode.includes('BUDGET AEJ') && oCode.includes('NON CAPITALISATION')) {
        return typesStage
            .filter(ts => normalizeLabel(ts.nom).includes('ECOLE') || normalizeLabel(ts.nom).includes('QUALIFICATION'))
            .map(ts => String(ts.id));
    }
    return typesStage.map(ts => String(ts.id));
};

const getDurationOptions = (
    financementCode: string,
    typeStageLabel: string,
): { value: string; label: string }[] => {
    const isEcole = normalizeLabel(typeStageLabel).includes('ECOLE');
    const isQualif = normalizeLabel(typeStageLabel).includes('QUALIFICATION');

    if (isEcole) {
        return [
            { value: '1', label: '1 mois' },
            { value: '1.5', label: '1.5 mois' },
            { value: '2', label: '2 mois' },
            { value: '3', label: '3 mois' },
            { value: '6', label: '6 mois' },
        ];
    }
    if (isQualif) {
        return [{ value: '6', label: '6 mois' }];
    }
    return [
        { value: '1', label: '1 mois' },
        { value: '1.5', label: '1.5 mois' },
        { value: '2', label: '2 mois' },
        { value: '3', label: '3 mois' },
        { value: '6', label: '6 mois' },
    ];
};

/** Calcul du numéro pièce avec préfixe */
const computePieceDisplay = (typePiece: string, numeroPiece: string): string => {
    const prefix = getPiecePrefix(typePiece);
    if (prefix && numeroPiece.startsWith(prefix)) {
        return numeroPiece.slice(prefix.length);
    }
    return numeroPiece;
};

/* ═══════════════════════════════════════════════════════════════════════════
   WRAPPER REACT-SELECT
   ═══════════════════════════════════════════════════════════════════════════ */
interface OptionSelect {
    value: string;
    label: string;
}

const RsSelect = ({
    value,
    options,
    onChange,
    placeholder,
    invalid,
    id,
    isDisabled,
}: {
    value: string;
    options: OptionSelect[];
    onChange: (val: string) => void;
    placeholder?: string;
    invalid?: boolean;
    id?: string;
    isDisabled?: boolean;
}) => (
    <Select<OptionSelect>
        inputId={id}
        className={invalid ? 'is-invalid' : ''}
        classNamePrefix="select2"
        options={options}
        value={options.find(o => o.value === value) || null}
        onChange={opt => onChange(opt?.value ?? '')}
        placeholder={placeholder || 'Sélectionner...'}
        isClearable
        isDisabled={isDisabled}
        noOptionsMessage={() => 'Aucune option'}
    />
);

const optionsRef = (items: RefItem[] = []): OptionSelect[] =>
    items.map(item => ({ value: String(item.id), label: item.nom }));

const optionsCode = (items: CodeItem[] = []): OptionSelect[] =>
    items.map(item => ({ value: item.code, label: item.nom }));

/* ═══════════════════════════════════════════════════════════════════════════
   PAGE
   ═══════════════════════════════════════════════════════════════════════════ */
export default function EditStagiaire({
    stage,
    rejet,
    peutTransmettre,
    documents,
    typesDocument,
    references,
    offres,
    agences,
    originesStagiaire,
    sourcesFinancement,
    typesStructure,
    conseillers,
    authUserAgenceIds,
    returnTo,
}: Props) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const beneficiaire = stage.beneficiaire || {};
    const [transmissionEnCours, setTransmissionEnCours] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const { data, setData, post, transform, processing } = useForm<Record<string, any>>({
        action: 'enregistrer' as Action,
        motif: '',
        return_tab: returnTo?.tab || 'ajourne_dmg',
        mois: returnTo?.mois || '',

        // Structure du stage (nouveaux champs)
        agence_id: texte(stage.agence_id),
        conseiller_id: texte(stage.conseiller_id),
        origine_stagiaire_id: texte(stage.origine_stagiaire_id),
        offre_emploi_id: texte(stage.offre_emploi_id),
        source_financement_id: texte(stage.source_financement_id),
        type_structure_id: texte(stage.type_structure_id),
        date_entree_portefeuille: jour(stage.date_entree_portefeuille),

        // Identité
        nom: texte(beneficiaire.nom),
        prenoms: texte(beneficiaire.prenoms),
        sexe: texte(beneficiaire.sexe),
        date_naissance: jour(beneficiaire.date_naissance),
        lieu_naissance: texte(beneficiaire.lieu_naissance),
        sous_prefecture_naissance: texte(beneficiaire.sous_prefecture_naissance),
        commune_residence_id: texte(beneficiaire.commune_residence_id),
        sous_prefecture_residence: texte(beneficiaire.sous_prefecture_residence),
        nature_piece_identite: texte(beneficiaire.nature_piece_identite),
        numero_piece_identite: texte(beneficiaire.numero_piece_identite),
        numero_cmu: texte(beneficiaire.numero_cmu),

        // Contacts
        telephone_principal: texte(beneficiaire.telephone_principal),
        telephone_secondaire: texte(beneficiaire.telephone_secondaire),
        email: texte(beneficiaire.email),
        personne_urgence: texte(beneficiaire.personne_urgence),
        lien_parente_id: texte(beneficiaire.lien_parente_id),
        contact_urgence_1: texte(beneficiaire.contact_urgence_1),
        contact_urgence_2: texte(beneficiaire.contact_urgence_2),

        // Formation
        niveau_etude_id: texte(beneficiaire.niveau_etude_id),
        diplome_id: texte(beneficiaire.diplome_id),
        autre_diplome: texte(beneficiaire.autre_diplome),
        specialite: texte(beneficiaire.specialite),
        annee_diplome: texte(beneficiaire.annee_diplome),
        etablissement_frequente: texte(beneficiaire.etablissement_frequente),
        type_enseignement_id: texte(beneficiaire.type_enseignement_id),
        handicap_id: texte(beneficiaire.handicap_id),
        type_handicap_id: texte(beneficiaire.type_handicap_id),
        autre_handicap: texte(beneficiaire.autre_handicap),

        // Paiement
        type_paiement_id: texte(beneficiaire.type_paiement_id),
        numero_tresor_money: texte(beneficiaire.numero_tresor_money),
        numero_wave: texte(beneficiaire.numero_wave),

        // Stage
        entreprise_id: texte(stage.entreprise_id),
        type_stage_id: texte(stage.type_stage_id),
        service_affectation: texte(stage.service_affectation),
        intitule_poste: texte(stage.intitule_poste),
        localite_stage: texte(stage.localite_stage),
        commune_stage: texte(stage.commune_stage),
        sous_prefecture_stage: texte(stage.sous_prefecture_stage),
        nom_encadreur: texte(stage.nom_encadreur),
        fonction_encadreur: texte(stage.fonction_encadreur),
        contact_encadreur: texte(stage.contact_encadreur),
        statut_stage: texte(stage.statut_stage),
        situation_stage: texte(stage.situation_stage),
        date_debut: jour(stage.date_debut),
        date_fin_prevue: jour(stage.date_fin_prevue),
        duree_stage: '', // calculé côté client
        nbr_mois_capitaliser: texte(stage.nbr_mois_capitaliser),
        date_demarrage_capitalisation: jour(stage.date_demarrage_capitalisation),
        date_demarrage_capitalisation_sans_financiere: jour(stage.date_demarrage_capitalisation_sans_financiere),
        observations: texte(stage.observations),

        // Pièces justificatives
        documents: {} as Record<string, File>,
    });

    /* ─── Derived ─── */
    const origineId = String(data.origine_stagiaire_id || '');
    const financementId = String(data.source_financement_id || '');
    const age = calculAge(data.date_naissance);

    const selectedOrigine = originesStagiaire.find(o => String(o.id) === origineId);
    const selectedFinancement = sourcesFinancement.find(s => String(s.id) === financementId);
    const origineCode = normalizeLabel(selectedOrigine?.code || selectedOrigine?.nom || '');
    const financementCode = normalizeLabel(selectedFinancement?.code || selectedFinancement?.nom || '');

    const isAEJ = origineCode.includes('NON CAPITALISATION');
    const isPEJEDEC = origineCode.includes('CAPITALISATION AVEC');
    const isSpontaneOuDAICG =
        origineCode.includes('CAPITALISATION SANS') || origineCode.includes('PRE FINANCE');
    const isFinancementBMZ = financementCode.includes('PEJEDEC');
    const isFinancementBailleurs = financementCode.includes('BUDGET AEJ');
    const isFinancementC2d = financementCode.includes('C2D');

    function getStageLabel(): string {
        const ts = references.typesStage.find(t => String(t.id) === String(data.type_stage_id));
        return ts?.nom || '';
    }
    const stageLabel = normalizeLabel(getStageLabel());
    const isStageEcole = stageLabel.includes('ECOLE');
    const isStageQualification = stageLabel.includes('QUALIFICATION');

    const showOffre = isAEJ || isPEJEDEC;
    const showDateDebut = isAEJ || isPEJEDEC;
    const showCapitalisationPejedec = isPEJEDEC;
    const showCapitalisationSansFinanciere = isSpontaneOuDAICG;
    const showWave = isFinancementBMZ;
    const showTresorMoney = !isFinancementBMZ;
    const showTypeStructure = isFinancementBailleurs && (isStageEcole || isStageQualification);
    const requiresYup = isFinancementC2d;
    const requiresWave = isFinancementBMZ;

    const typePaiement = useMemo(
        () => references.typesPaiement.find(t => String(t.id) === String(data.type_paiement_id)),
        [references.typesPaiement, data.type_paiement_id],
    );

    const documentsDeposes = useMemo(() => {
        const parCode: Record<string, DocumentDepose> = {};
        documents.forEach(doc => {
            if (doc.code) parCode[doc.code] = doc;
        });
        return parCode;
    }, [documents]);

    /* ─── Options filtrées ─── */
    const filteredTypesStageIds = useMemo(
        () =>
            getFilteredTypeStageIds(
                references.typesStage,
                originesStagiaire,
                sourcesFinancement,
                origineId,
                financementId,
            ),
        [references.typesStage, originesStagiaire, sourcesFinancement, origineId, financementId],
    );

    const filteredTypesStage = useMemo(
        () => references.typesStage.filter(ts => filteredTypesStageIds.includes(String(ts.id))),
        [references.typesStage, filteredTypesStageIds],
    );

    const durationOptions = useMemo(
        () => getDurationOptions(financementCode, getStageLabel()),
        [financementCode, data.type_stage_id],
    );

    const filteredDiplomes = useMemo(() => {
        if (!data.niveau_etude_id) return references.diplomes;
        return references.diplomes.filter(
            d => !d.niveau_id || String(d.niveau_id) === String(data.niveau_etude_id),
        );
    }, [references.diplomes, data.niveau_etude_id]);

    const entrepriseOptions = useMemo(() => {
        if (!data.agence_id) return references.entreprises.map(e => ({ value: String(e.id), label: e.raison_sociale }));
        const unique = new Map<number, OffreItem>();
        offres
            .filter(o => o.agence_id === Number(data.agence_id))
            .forEach(o => {
                if (!unique.has(o.entreprise_id)) unique.set(o.entreprise_id, o);
            });
        return Array.from(unique.values()).map(o => ({
            value: String(o.entreprise_id),
            label: o.entreprise?.raison_sociale || `Entreprise #${o.entreprise_id}`,
        }));
    }, [offres, data.agence_id, references.entreprises]);

    const piecePrefix = getPiecePrefix(data.nature_piece_identite);
    const pieceMaxLength = getPieceMaxLength(data.nature_piece_identite);
    const pieceNumberDisplay = computePieceDisplay(data.nature_piece_identite, data.numero_piece_identite);

    const isNiveauAucun = String(data.niveau_etude_id) === '1';
    const isDiplomeAutre = String(data.diplome_id) === '42';

    /* ═══════════════════════════════════════════════════════════════════════
       AUTO-EFFECTS (alignés sur Inscriptions/Create)
       ═══════════════════════════════════════════════════════════════════════ */

    /** Origine PEJEDEC → forcer financement=3, stage=ECOLE, durée=6 */
    useEffect(() => {
        if (isPEJEDEC) {
            const ecole = references.typesStage.find(ts =>
                normalizeLabel(ts.nom).includes('ECOLE'),
            );
            setData('source_financement_id', '3');
            if (ecole) setData('type_stage_id', String(ecole.id));
            setData('duree_stage', '6');
        }
    }, [origineId]);

    /** Origine Spontané/DAICG → date_debut masquée (capitalisation) */
    useEffect(() => {
        if (isSpontaneOuDAICG) {
            setData('date_debut', '');
        }
    }, [origineId]);

    /** Type financement → auto-sets type paiement */
    useEffect(() => {
        if (isFinancementBMZ) {
            const waveType = references.typesPaiement.find(p =>
                normalizeLabel(p.nom).includes('WAVE'),
            );
            setData('type_paiement_id', waveType ? String(waveType.id) : '2');
        } else if (financementId) {
            const yupType = references.typesPaiement.find(
                p =>
                    normalizeLabel(p.nom).includes('TRESOR') ||
                    normalizeLabel(p.nom).includes('YUP'),
            );
            setData('type_paiement_id', yupType ? String(yupType.id) : '1');
        }
    }, [financementId]);

    /** Type financement → filtre type stage + durée + reset dates */
    useEffect(() => {
        if (isFinancementBMZ || isFinancementC2d) {
            const qualif = references.typesStage.find(ts =>
                normalizeLabel(ts.nom).includes('QUALIFICATION'),
            );
            if (qualif) setData('type_stage_id', String(qualif.id));
            setData('duree_stage', '6');
        } else if (isFinancementBailleurs && isPEJEDEC) {
            const ecole = references.typesStage.find(ts =>
                normalizeLabel(ts.nom).includes('ECOLE'),
            );
            if (ecole) setData('type_stage_id', String(ecole.id));
            setData('duree_stage', '6');
        }
    }, [financementId]);

    /** Niveau étude = 1 (AUCUN) → auto-fill */
    useEffect(() => {
        if (isNiveauAucun) {
            const diplomeAucun = references.diplomes.find(d => normalizeLabel(d.nom) === 'AUCUN');
            const typeEnsAucun = references.typesEnseignement.find(t => normalizeLabel(t.nom) === 'AUCUN');
            setData('diplome_id', diplomeAucun ? String(diplomeAucun.id) : '');
            setData('specialite', 'AUCUN');
            setData('etablissement_frequente', 'AUCUN');
            setData('type_enseignement_id', typeEnsAucun ? String(typeEnsAucun.id) : '');
            setData('annee_diplome', '');
        }
    }, [data.niveau_etude_id]);

    /** Capitalisation PEJEDEC → calcul date_demarrage */
    useEffect(() => {
        if (isPEJEDEC && data.date_debut && Number(data.nbr_mois_capitaliser) > 0) {
            const d = calculDateDemarrageCap(data.date_debut, Number(data.nbr_mois_capitaliser));
            setData('date_demarrage_capitalisation', d);
        }
    }, [data.date_debut, data.nbr_mois_capitaliser]);

    /** Calcul date fin auto */
    useEffect(() => {
        const base =
            isSpontaneOuDAICG && data.date_demarrage_capitalisation_sans_financiere
                ? data.date_demarrage_capitalisation_sans_financiere
                : data.date_debut;
        const fin = calculateDateFin(base, data.duree_stage);
        if (fin && fin !== data.date_fin_prevue) {
            setData('date_fin_prevue', fin);
        }
    }, [data.date_debut, data.duree_stage, data.date_demarrage_capitalisation_sans_financiere]);

    /* ═══════════════════════════════════════════════════════════════════════
       VALIDATION (alignée sur Inscriptions/Create)
       ═══════════════════════════════════════════════════════════════════════ */
    const validate = useCallback((): Record<string, string> => {
        const e: Record<string, string> = {};
        const req = (f: string, v: string, label: string) => {
            if (!v?.trim()) e[f] = `${label} est obligatoire.`;
        };

        // Structure
        req('agence_id', data.agence_id, 'Agence');
        req('source_financement_id', data.source_financement_id, 'Source de financement');
        req('type_stage_id', data.type_stage_id, 'Type de stage');

        // Identité
        req('nom', data.nom, 'Nom');
        req('prenoms', data.prenoms, 'Prénoms');
        req('date_naissance', data.date_naissance, 'Date de naissance');
        if (data.date_naissance && new Date(data.date_naissance) >= new Date()) {
            e.date_naissance = "La date de naissance doit être antérieure à aujourd'hui.";
        }
        if (data.date_naissance) {
            const minimumAge = isFinancementBailleurs && isStageEcole ? 14 : 17;
            if (age < minimumAge) {
                e.date_naissance = `Le stagiaire doit avoir au moins ${minimumAge} ans.`;
            }
            if (age > 40) {
                e.date_naissance = 'Le stagiaire ne doit pas dépasser 40 ans.';
            }
        }
        req('sexe', data.sexe, 'Sexe');
        req('lieu_naissance', data.lieu_naissance, 'Lieu de naissance');
        req('sous_prefecture_naissance', data.sous_prefecture_naissance, 'Sous-préfecture de naissance');
        req('sous_prefecture_residence', data.sous_prefecture_residence, 'S/P de résidence');
        req('nature_piece_identite', data.nature_piece_identite, 'Nature pièce');
        req('numero_piece_identite', data.numero_piece_identite, 'Numéro pièce');
        req('numero_cmu', data.numero_cmu, 'Numéro CMU');

        // Contacts
        req('telephone_principal', data.telephone_principal, 'Contact téléphonique 1');
        if (data.telephone_principal && data.telephone_principal.length !== 10) {
            e.telephone_principal = 'Le contact doit avoir exactement 10 chiffres.';
        }
        if (data.telephone_secondaire && data.telephone_secondaire.length !== 0 && data.telephone_secondaire.length !== 10) {
            e.telephone_secondaire = 'Le contact doit avoir exactement 10 chiffres.';
        }
        req('personne_urgence', data.personne_urgence, 'Personne à contacter en cas d\'urgence');
        req('lien_parente_id', data.lien_parente_id, 'Lien de parenté');
        req('contact_urgence_1', data.contact_urgence_1, 'Contact parent 1');
        if (data.contact_urgence_1 && data.contact_urgence_1.length !== 10) {
            e.contact_urgence_1 = 'Le contact doit avoir exactement 10 chiffres.';
        }
        if (data.contact_urgence_2 && data.contact_urgence_2.length !== 0 && data.contact_urgence_2.length !== 10) {
            e.contact_urgence_2 = 'Le contact doit avoir exactement 10 chiffres.';
        }

        // Formation
        req('niveau_etude_id', data.niveau_etude_id, "Niveau d'études");
        req('diplome_id', data.diplome_id, 'Diplôme');
        if (isDiplomeAutre) req('autre_diplome', data.autre_diplome, 'Préciser le diplôme');
        req('specialite', data.specialite, 'Spécialité');
        if (!isNiveauAucun) {
            req('annee_diplome', data.annee_diplome, 'Année du diplôme');
            if (data.annee_diplome && data.annee_diplome.length !== 4) {
                e.annee_diplome = "L'année doit avoir 4 chiffres.";
            }
            if (data.annee_diplome && data.date_naissance) {
                const yearBirth = new Date(data.date_naissance).getFullYear();
                const yearDiplome = parseInt(data.annee_diplome, 10);
                if (yearDiplome < yearBirth) {
                    e.annee_diplome = "L'année du diplôme est antérieure à la date de naissance.";
                }
            }
        }
        req('etablissement_frequente', data.etablissement_frequente, 'Établissement fréquenté');
        req('type_enseignement_id', data.type_enseignement_id, "Type d'enseignement");
        req('handicap_id', data.handicap_id, 'Handicap');
        if (data.handicap_id === 'HANDICAP') {
            req('type_handicap_id', data.type_handicap_id, 'Type de handicap');
        }
        if (data.type_handicap_id === 'AUTRE') {
            req('autre_handicap', data.autre_handicap, 'Autre handicap');
        }

        // Paiement
        req('type_paiement_id', data.type_paiement_id, 'Type de paiement');
        if (requiresYup) {
            req('numero_tresor_money', data.numero_tresor_money, 'Numéro Trésor Money');
            if (data.numero_tresor_money && data.numero_tresor_money.length !== 10) {
                e.numero_tresor_money = 'Le numéro doit avoir exactement 10 chiffres.';
            }
        }
        if (requiresWave) {
            req('numero_wave', data.numero_wave, 'Numéro Wave');
            if (data.numero_wave && data.numero_wave.length !== 10) {
                e.numero_wave = 'Le numéro doit avoir exactement 10 chiffres.';
            }
        }

        // Stage
        req('entreprise_id', data.entreprise_id, 'Entreprise');
        req('service_affectation', data.service_affectation, "Service d'affectation");
        req('localite_stage', data.localite_stage, 'Localité / Lieu de stage');
        req('commune_stage', data.commune_stage, 'Commune du lieu de stage');
        req('sous_prefecture_stage', data.sous_prefecture_stage, 'S/P du lieu de stage');
        req('nom_encadreur', data.nom_encadreur, "Nom et prénom de l'encadreur");
        req('fonction_encadreur', data.fonction_encadreur, "Fonction de l'encadreur");
        req('contact_encadreur', data.contact_encadreur, "Numéro de l'encadreur");
        if (data.contact_encadreur && data.contact_encadreur.length !== 10) {
            e.contact_encadreur = 'Le contact doit avoir exactement 10 chiffres.';
        }
        req('situation_stage', data.situation_stage, 'Situation du stage');

        // Dates
        if (showDateDebut) {
            req('date_debut', data.date_debut, 'Date de début de stage');
            if (data.date_debut) {
                const d = new Date(`${data.date_debut}T00:00:00`);
                const now = new Date();
                if (d > now) {
                    e.date_debut = 'La date de début ne peut pas être dans le futur.';
                }
                const fiveYearsAgo = new Date();
                fiveYearsAgo.setFullYear(fiveYearsAgo.getFullYear() - 5);
                if (d < fiveYearsAgo) {
                    e.date_debut = 'La date de début ne peut pas être antérieure à 5 ans.';
                }
                if (!isFinancementBMZ) {
                    const day = d.getDate();
                    if (!ALLOWED_COHORT_DAYS.includes(day)) {
                        e.date_debut = 'Veuillez choisir le 1 à 5, 10 ou 20 du mois.';
                    }
                }
            }
        }
        if (isSpontaneOuDAICG) {
            req(
                'date_demarrage_capitalisation_sans_financiere',
                data.date_demarrage_capitalisation_sans_financiere,
                'Date démarrage capitalisation',
            );
        }
        if (isPEJEDEC) {
            if (!data.nbr_mois_capitaliser || Number(data.nbr_mois_capitaliser) <= 0) {
                e.nbr_mois_capitaliser = 'Le nombre de mois à capitaliser est obligatoire.';
            }
            if (
                data.nbr_mois_capitaliser &&
                data.duree_stage &&
                Number(data.nbr_mois_capitaliser) >= Number(data.duree_stage)
            ) {
                e.nbr_mois_capitaliser = 'Le mois de capitalisation est supérieur à la durée du stage !';
            }
            if (!data.date_demarrage_capitalisation) {
                e.date_demarrage_capitalisation = 'La date de démarrage de la capitalisation est obligatoire.';
            }
        }

        req('date_fin_prevue', data.date_fin_prevue, 'Date fin prévisionnelle');
        if (data.date_debut && data.date_fin_prevue && data.date_fin_prevue < data.date_debut) {
            e.date_fin_prevue = 'La date de fin doit être postérieure à la date de début.';
        }

        req('observations', data.observations, 'Observations');

        return e;
    }, [
        data, age, isPEJEDEC, isSpontaneOuDAICG, isFinancementBMZ, isFinancementBailleurs,
        isFinancementC2d, isStageEcole, isNiveauAucun, isDiplomeAutre, showDateDebut, requiresYup,
        requiresWave,
    ]);

    /* ═══════════════════════════════════════════════════════════════════════
       SUBMIT
       ═══════════════════════════════════════════════════════════════════════ */
    const champ = (nom: string) => ({
        value: data[nom] ?? '',
        onChange: (e: React.ChangeEvent<HTMLInputElement>) => setData(nom, e.target.value),
        invalid: !!errors[nom],
    });

    const erreur = (nom: string) =>
        errors[nom] ? <div className="invalid-feedback d-block">{errors[nom]}</div> : null;

    const soumettre = (e: React.FormEvent, action: Action) => {
        e.preventDefault();
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length > 0) return;

        transform(valeurs => ({ ...valeurs, action }));
        post(`/cip/pointages/update-stagiaire/${stage.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const transmettreSeulement = () => {
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length > 0) return;

        setTransmissionEnCours(true);
        router.post(
            `/cip/pointages/transmettre-correction-dmg/${stage.id}`,
            { motif: data.motif, return_tab: data.return_tab, mois: data.mois },
            { onFinish: () => setTransmissionEnCours(false) },
        );
    };

    const occupe = processing || transmissionEnCours;

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER
       ═══════════════════════════════════════════════════════════════════════ */
    return (
        <React.Fragment>
            <Head title="Traitement du rejet DMG" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Traitement du rejet DMG" pageTitle="Pointages" />

                    {flash?.success && (
                        <Alert color="success" className="border-0">
                            <i className="ri-check-double-line me-2" />
                            {flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0">
                            <i className="ri-error-warning-line me-2" />
                            {flash.error}
                        </Alert>
                    )}

                    {/* ── Motif du rejet DMG ── */}
                    {rejet ? (
                        <Alert color="warning" className="border-0">
                            <h6 className="alert-heading mb-2">
                                <i className="ri-close-circle-line me-2" />
                                Paiement ajourné par la DMG
                                <Badge color="warning" className="ms-2">
                                    {rejet.montant} FCFA
                                </Badge>
                            </h6>
                            <p className="mb-1">
                                <strong>Motif :</strong> {rejet.motif}
                            </p>
                            <p className="mb-0 text-muted">
                                {rejet.decide_le ? `Le ${rejet.decide_le}` : ''}
                                {rejet.auteur ? ` — par ${rejet.auteur}` : ''}
                            </p>
                        </Alert>
                    ) : (
                        <Alert color="info" className="border-0">
                            <i className="ri-information-line me-2" />
                            Aucun paiement ajourné par la DMG n'est rattaché à ce stagiaire : la
                            fiche reste modifiable, mais il n'y a rien à transmettre au Chef
                            d'Agence.
                        </Alert>
                    )}

                    <Form onSubmit={e => soumettre(e, 'enregistrer')} encType="multipart/form-data">
                        <Row>
                            <Col lg={12}>
                                {/* ─────────────── STRUCTURE DU STAGE ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-danger-subtle">
                                        <h5 className="card-title mb-0 text-danger fw-bold">
                                            <i className="ri-building-2-line me-1" />
                                            STRUCTURE DU STAGE
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Agence <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="agence_id"
                                                    value={data.agence_id}
                                                    options={agences
                                                        .filter(
                                                            a =>
                                                                authUserAgenceIds.length === 0 ||
                                                                authUserAgenceIds.includes(a.id),
                                                        )
                                                        .map(a => ({
                                                            value: String(a.id),
                                                            label: a.nom,
                                                        }))}
                                                    onChange={v =>
                                                        setData('agence_id', v)
                                                    }
                                                    invalid={!!errors.agence_id}
                                                />
                                                {erreur('agence_id')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">Conseiller référent</Label>
                                                <RsSelect
                                                    id="conseiller_id"
                                                    value={data.conseiller_id}
                                                    options={conseillers
                                                        .filter(
                                                            c =>
                                                                data.agence_id &&
                                                                c.agence_id === Number(data.agence_id),
                                                        )
                                                        .map(c => ({
                                                            value: String(c.id),
                                                            label: `${c.nom} ${c.prenoms || ''}`,
                                                        }))}
                                                    onChange={v => setData('conseiller_id', v)}
                                                    placeholder={
                                                        data.agence_id
                                                            ? 'Sélectionner un conseiller'
                                                            : "Sélectionner agence d'abord"
                                                    }
                                                    isDisabled={!data.agence_id}
                                                />
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">Origine du stagiaire</Label>
                                                <RsSelect
                                                    id="origine_stagiaire_id"
                                                    value={data.origine_stagiaire_id}
                                                    options={originesStagiaire.map(o => ({
                                                        value: String(o.id),
                                                        label: o.nom,
                                                    }))}
                                                    onChange={v =>
                                                        setData('origine_stagiaire_id', v)
                                                    }
                                                />
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Source de financement{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="source_financement_id"
                                                    value={data.source_financement_id}
                                                    options={sourcesFinancement.map(s => ({
                                                        value: String(s.id),
                                                        label: s.nom,
                                                    }))}
                                                    onChange={v =>
                                                        setData('source_financement_id', v)
                                                    }
                                                    invalid={!!errors.source_financement_id}
                                                />
                                                {erreur('source_financement_id')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Type de stage <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="type_stage_id"
                                                    value={data.type_stage_id}
                                                    options={filteredTypesStage.map(ts => ({
                                                        value: String(ts.id),
                                                        label: ts.nom,
                                                    }))}
                                                    onChange={v => setData('type_stage_id', v)}
                                                    invalid={!!errors.type_stage_id}
                                                />
                                                {erreur('type_stage_id')}
                                            </Col>
                                            {showTypeStructure && (
                                                <Col md={4}>
                                                    <Label className="fw-semibold">
                                                        Type de structure{' '}
                                                        <span className="text-danger">*</span>
                                                    </Label>
                                                    <RsSelect
                                                        id="type_structure_id"
                                                        value={data.type_structure_id}
                                                        options={typesStructure.map(ts => ({
                                                            value: String(ts.id),
                                                            label: ts.nom,
                                                        }))}
                                                        onChange={v =>
                                                            setData('type_structure_id', v)
                                                        }
                                                    />
                                                </Col>
                                            )}
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Date d'entrée dans le portefeuille
                                                </Label>
                                                <Input
                                                    type="date"
                                                    {...champ('date_entree_portefeuille')}
                                                />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── IDENTITÉ ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-primary-subtle">
                                        <h5 className="card-title mb-0 text-primary fw-bold">
                                            <i className="ri-user-line me-1" />
                                            IDENTITÉ DU STAGIAIRE
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Nom <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('nom')}
                                                    onChange={e =>
                                                        setData(
                                                            'nom',
                                                            e.target.value
                                                                .toUpperCase()
                                                                .replace(/[^A-Z\s\-']/g, ''),
                                                        )
                                                    }
                                                />
                                                {erreur('nom')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Prénoms <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('prenoms')}
                                                    onChange={e =>
                                                        setData(
                                                            'prenoms',
                                                            e.target.value
                                                                .toUpperCase()
                                                                .replace(/[^A-Z\s\-']/g, ''),
                                                        )
                                                    }
                                                />
                                                {erreur('prenoms')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Sexe <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="sexe"
                                                    value={data.sexe}
                                                    options={[
                                                        { value: 'Femme', label: 'Femme' },
                                                        { value: 'Homme', label: 'Homme' },
                                                    ]}
                                                    onChange={val => setData('sexe', val)}
                                                    invalid={!!errors.sexe}
                                                />
                                                {erreur('sexe')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Date de naissance{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="date"
                                                    {...champ('date_naissance')}
                                                    max={todayString()}
                                                />
                                                {age > 0 && (
                                                    <small className="text-muted">{age} ans</small>
                                                )}
                                                {erreur('date_naissance')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Lieu de naissance{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input type="text" {...champ('lieu_naissance')} />
                                                {erreur('lieu_naissance')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Sous-préfecture de naissance{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="sous_prefecture_naissance"
                                                    value={data.sous_prefecture_naissance}
                                                    options={[
                                                        ...references.communes.map(c => ({
                                                            value: c.nom,
                                                            label: c.nom,
                                                        })),
                                                        { value: 'autre', label: 'Autre' },
                                                    ]}
                                                    onChange={val =>
                                                        setData('sous_prefecture_naissance', val)
                                                    }
                                                    invalid={!!errors.sous_prefecture_naissance}
                                                />
                                                {erreur('sous_prefecture_naissance')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">Commune de résidence</Label>
                                                <RsSelect
                                                    id="commune_residence_id"
                                                    value={data.commune_residence_id}
                                                    options={optionsRef(references.communes)}
                                                    onChange={val =>
                                                        setData('commune_residence_id', val)
                                                    }
                                                    invalid={!!errors.commune_residence_id}
                                                />
                                                {erreur('commune_residence_id')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    S/P de résidence{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="sous_prefecture_residence"
                                                    value={data.sous_prefecture_residence}
                                                    options={references.communes.map(c => ({
                                                        value: c.nom,
                                                        label: c.nom,
                                                    }))}
                                                    onChange={val =>
                                                        setData('sous_prefecture_residence', val)
                                                    }
                                                    invalid={!!errors.sous_prefecture_residence}
                                                />
                                                {erreur('sous_prefecture_residence')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    N° CMU <span className="text-danger">*</span>
                                                </Label>
                                                <Input type="text" {...champ('numero_cmu')} />
                                                {erreur('numero_cmu')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Nature de la pièce d'identité{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="nature_piece_identite"
                                                    value={data.nature_piece_identite}
                                                    options={TYPE_PIECE_OPTIONS}
                                                    onChange={val =>
                                                        setData('nature_piece_identite', val)
                                                    }
                                                    invalid={!!errors.nature_piece_identite}
                                                />
                                                {erreur('nature_piece_identite')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    N° de la pièce d'identité{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <div className="input-group">
                                                    {piecePrefix && (
                                                        <span className="input-group-text">
                                                            {piecePrefix}
                                                        </span>
                                                    )}
                                                    <Input
                                                        type="text"
                                                        value={pieceNumberDisplay}
                                                        maxLength={pieceMaxLength}
                                                        onChange={e => {
                                                            const raw = e.target.value
                                                                .replace(/\s/g, '')
                                                                .toUpperCase();
                                                            setData(
                                                                'numero_piece_identite',
                                                                `${piecePrefix}${raw}`,
                                                            );
                                                        }}
                                                        invalid={!!errors.numero_piece_identite}
                                                    />
                                                </div>
                                                {erreur('numero_piece_identite')}
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── CONTACTS ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-primary-subtle">
                                        <h5 className="card-title mb-0 text-primary fw-bold">
                                            <i className="ri-phone-line me-1" />
                                            CONTACTS
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Téléphone principal{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('telephone_principal')}
                                                    onChange={e =>
                                                        setData(
                                                            'telephone_principal',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                    maxLength={10}
                                                />
                                                {erreur('telephone_principal')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Téléphone secondaire
                                                </Label>
                                                <Input
                                                    type="text"
                                                    value={data.telephone_secondaire}
                                                    onChange={e =>
                                                        setData(
                                                            'telephone_secondaire',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                    maxLength={10}
                                                />
                                                {erreur('telephone_secondaire')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">E-mail</Label>
                                                <Input type="email" {...champ('email')} />
                                                {erreur('email')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Personne à prévenir{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input type="text" {...champ('personne_urgence')} />
                                                {erreur('personne_urgence')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Lien de parenté{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="lien_parente_id"
                                                    value={data.lien_parente_id}
                                                    options={optionsRef(references.liensParente)}
                                                    onChange={val =>
                                                        setData('lien_parente_id', val)
                                                    }
                                                    invalid={!!errors.lien_parente_id}
                                                />
                                                {erreur('lien_parente_id')}
                                            </Col>
                                            <Col md={2}>
                                                <Label className="fw-semibold">
                                                    Contact urgence 1{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('contact_urgence_1')}
                                                    onChange={e =>
                                                        setData(
                                                            'contact_urgence_1',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                    maxLength={10}
                                                />
                                                {erreur('contact_urgence_1')}
                                            </Col>
                                            <Col md={2}>
                                                <Label className="fw-semibold">
                                                    Contact urgence 2
                                                </Label>
                                                <Input
                                                    type="text"
                                                    value={data.contact_urgence_2}
                                                    onChange={e =>
                                                        setData(
                                                            'contact_urgence_2',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                    maxLength={10}
                                                />
                                                {erreur('contact_urgence_2')}
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── FORMATION ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-primary-subtle">
                                        <h5 className="card-title mb-0 text-primary fw-bold">
                                            <i className="ri-graduation-cap-line me-1" />
                                           FORMATION ET DIPLÔME
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Niveau d'étude{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="niveau_etude_id"
                                                    value={data.niveau_etude_id}
                                                    options={optionsRef(references.niveauxEtude)}
                                                    onChange={val =>
                                                        setData('niveau_etude_id', val)
                                                    }
                                                    invalid={!!errors.niveau_etude_id}
                                                />
                                                {erreur('niveau_etude_id')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Diplôme <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="diplome_id"
                                                    value={data.diplome_id}
                                                    options={optionsRef(filteredDiplomes)}
                                                    onChange={val => setData('diplome_id', val)}
                                                    invalid={!!errors.diplome_id}
                                                />
                                                {erreur('diplome_id')}
                                            </Col>
                                            {isDiplomeAutre && (
                                                <Col md={4}>
                                                    <Label className="fw-semibold">
                                                        Autre diplôme (à préciser){' '}
                                                        <span className="text-danger">*</span>
                                                    </Label>
                                                    <Input type="text" {...champ('autre_diplome')} />
                                                    {erreur('autre_diplome')}
                                                </Col>
                                            )}
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Spécialité <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('specialite')}
                                                    onChange={e =>
                                                        setData(
                                                            'specialite',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('specialite')}
                                            </Col>
                                            <Col md={2}>
                                                <Label className="fw-semibold">
                                                    Année du diplôme{' '}
                                                    {isNiveauAucun ? '' : (
                                                        <span className="text-danger">*</span>
                                                    )}
                                                </Label>
                                                <Input
                                                    type="number"
                                                    {...champ('annee_diplome')}
                                                    onChange={e =>
                                                        setData(
                                                            'annee_diplome',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 4),
                                                        )
                                                    }
                                                    maxLength={4}
                                                    readOnly={isNiveauAucun}
                                                />
                                                {erreur('annee_diplome')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Établissement fréquenté{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input type="text" {...champ('etablissement_frequente')} />
                                                {erreur('etablissement_frequente')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Type d'enseignement{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="type_enseignement_id"
                                                    value={data.type_enseignement_id}
                                                    options={optionsRef(references.typesEnseignement)}
                                                    onChange={val =>
                                                        setData('type_enseignement_id', val)
                                                    }
                                                    invalid={!!errors.type_enseignement_id}
                                                />
                                                {erreur('type_enseignement_id')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Handicap <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="handicap_id"
                                                    value={data.handicap_id}
                                                    options={HANDICAP_OPTIONS}
                                                    onChange={val =>
                                                        setData('handicap_id', val)
                                                    }
                                                    invalid={!!errors.handicap_id}
                                                />
                                                {erreur('handicap_id')}
                                            </Col>
                                            {data.handicap_id === 'HANDICAP' && (
                                                <>
                                                    <Col md={4}>
                                                        <Label className="fw-semibold">
                                                            Type de handicap{' '}
                                                            <span className="text-danger">*</span>
                                                        </Label>
                                                        <RsSelect
                                                            id="type_handicap_id"
                                                            value={data.type_handicap_id}
                                                            options={TYPE_HANDICAP_OPTIONS}
                                                            onChange={val =>
                                                                setData('type_handicap_id', val)
                                                            }
                                                            invalid={!!errors.type_handicap_id}
                                                        />
                                                        {erreur('type_handicap_id')}
                                                    </Col>
                                                    {data.type_handicap_id === 'AUTRE' && (
                                                        <Col md={4}>
                                                            <Label className="fw-semibold">
                                                                Autre handicap (à préciser){' '}
                                                                <span className="text-danger">*</span>
                                                            </Label>
                                                            <Input
                                                                type="text"
                                                                {...champ('autre_handicap')}
                                                                onChange={e =>
                                                                    setData(
                                                                        'autre_handicap',
                                                                        e.target.value
                                                                            .toUpperCase()
                                                                            .replace(
                                                                                /[^A-Z\s\-']/g,
                                                                                '',
                                                                            ),
                                                                    )
                                                                }
                                                            />
                                                            {erreur('autre_handicap')}
                                                        </Col>
                                                    )}
                                                </>
                                            )}
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── PAIEMENT ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-danger-subtle">
                                        <h5 className="card-title mb-0 text-danger fw-bold">
                                            <i className="ri-bank-card-line me-1" />
                                            COORDONNÉES DE PAIEMENT
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Type de paiement{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="type_paiement_id"
                                                    value={data.type_paiement_id}
                                                    options={references.typesPaiement.map(t => ({
                                                        value: String(t.id),
                                                        label: t.nom,
                                                    }))}
                                                    onChange={val =>
                                                        setData('type_paiement_id', val)
                                                    }
                                                    invalid={!!errors.type_paiement_id}
                                                />
                                                {erreur('type_paiement_id')}
                                            </Col>
                                            {showTresorMoney && (
                                                <Col md={4}>
                                                    <Label className="fw-semibold">
                                                        N° Trésor Money{' '}
                                                        <span className={requiresYup ? 'text-danger' : ''}>
                                                            {requiresYup && '*'}
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        type="text"
                                                        {...champ('numero_tresor_money')}
                                                        onChange={e =>
                                                            setData(
                                                                'numero_tresor_money',
                                                                e.target.value
                                                                    .replace(/[^0-9]/g, '')
                                                                    .slice(0, 10),
                                                            )
                                                        }
                                                        maxLength={10}
                                                    />
                                                    {erreur('numero_tresor_money')}
                                                </Col>
                                            )}
                                            {showWave && (
                                                <Col md={4}>
                                                    <Label className="fw-semibold">
                                                        N° Wave{' '}
                                                        <span className={requiresWave ? 'text-danger' : ''}>
                                                            {requiresWave && '*'}
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        type="text"
                                                        {...champ('numero_wave')}
                                                        onChange={e =>
                                                            setData(
                                                                'numero_wave',
                                                                e.target.value
                                                                    .replace(/[^0-9]/g, '')
                                                                    .slice(0, 10),
                                                            )
                                                        }
                                                        maxLength={10}
                                                    />
                                                    {erreur('numero_wave')}
                                                </Col>
                                            )}
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── STAGE ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-primary-subtle">
                                        <h5 className="card-title mb-0 text-primary fw-bold">
                                            <i className="ri-briefcase-line me-1" />
                                            INFORMATIONS DU STAGE
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={6}>
                                                <Label className="fw-semibold">
                                                    Entreprise d'accueil{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="entreprise_id"
                                                    value={data.entreprise_id}
                                                    options={entrepriseOptions}
                                                    onChange={val => setData('entreprise_id', val)}
                                                    invalid={!!errors.entreprise_id}
                                                />
                                                {erreur('entreprise_id')}
                                            </Col>
                                            <Col md={3}>
                                                <Label className="fw-semibold">
                                                    Service d'affectation{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('service_affectation')}
                                                    onChange={e =>
                                                        setData(
                                                            'service_affectation',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('service_affectation')}
                                            </Col>
                                            <Col md={3}>
                                                <Label className="fw-semibold">
                                                    Intitulé du poste
                                                </Label>
                                                <Input type="text" {...champ('intitule_poste')} />
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Localité du stage{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('localite_stage')}
                                                    onChange={e =>
                                                        setData(
                                                            'localite_stage',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('localite_stage')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Commune du stage{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="commune_stage"
                                                    value={data.commune_stage}
                                                    options={references.communes.map(c => ({
                                                        value: c.nom,
                                                        label: c.nom,
                                                    }))}
                                                    onChange={val => setData('commune_stage', val)}
                                                    invalid={!!errors.commune_stage}
                                                />
                                                {erreur('commune_stage')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Sous-préfecture du stage{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="sous_prefecture_stage"
                                                    value={data.sous_prefecture_stage}
                                                    options={references.communes.map(c => ({
                                                        value: c.nom,
                                                        label: c.nom,
                                                    }))}
                                                    onChange={val =>
                                                        setData('sous_prefecture_stage', val)
                                                    }
                                                    invalid={!!errors.sous_prefecture_stage}
                                                />
                                                {erreur('sous_prefecture_stage')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Nom de l'encadreur{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('nom_encadreur')}
                                                    onChange={e =>
                                                        setData(
                                                            'nom_encadreur',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('nom_encadreur')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Fonction de l'encadreur{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('fonction_encadreur')}
                                                    onChange={e =>
                                                        setData(
                                                            'fonction_encadreur',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('fonction_encadreur')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Contact de l'encadreur{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="text"
                                                    {...champ('contact_encadreur')}
                                                    onChange={e =>
                                                        setData(
                                                            'contact_encadreur',
                                                            e.target.value
                                                                .replace(/[^0-9]/g, '')
                                                                .slice(0, 10),
                                                        )
                                                    }
                                                    maxLength={10}
                                                />
                                                {erreur('contact_encadreur')}
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Statut du stage
                                                </Label>
                                                <RsSelect
                                                    id="statut_stage"
                                                    value={data.statut_stage}
                                                    options={references.statutsStage.map(ss => ({
                                                        value: String(ss.code),
                                                        label: ss.nom,
                                                    }))}
                                                    onChange={val => setData('statut_stage', val)}
                                                />
                                            </Col>
                                            <Col md={4}>
                                                <Label className="fw-semibold">
                                                    Situation du stage{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="situation_stage"
                                                    value={data.situation_stage}
                                                    options={references.situationsStage.map(ss => ({
                                                        value: String(ss.code),
                                                        label: ss.nom,
                                                    }))}
                                                    onChange={val =>
                                                        setData('situation_stage', val)
                                                    }
                                                    invalid={!!errors.situation_stage}
                                                />
                                                {erreur('situation_stage')}
                                            </Col>
                                            {showDateDebut && (
                                                <Col md={3}>
                                                    <Label className="fw-semibold">
                                                        Date de début{' '}
                                                        <span className="text-danger">*</span>
                                                    </Label>
                                                    <Input
                                                        type="date"
                                                        {...champ('date_debut')}
                                                        max={todayString()}
                                                    />
                                                    {erreur('date_debut')}
                                                </Col>
                                            )}
                                            <Col md={3}>
                                                <Label className="fw-semibold">
                                                    Date de fin prévue{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="date"
                                                    {...champ('date_fin_prevue')}
                                                    readOnly
                                                />
                                                <small className="text-muted">
                                                    Calculée automatiquement
                                                </small>
                                                {erreur('date_fin_prevue')}
                                            </Col>
                                            {showCapitalisationPejedec && (
                                                <>
                                                    <Col md={2}>
                                                        <Label className="fw-semibold">
                                                            Mois capitalisés{' '}
                                                            <span className="text-danger">*</span>
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            {...champ('nbr_mois_capitaliser')}
                                                            onChange={e =>
                                                                setData(
                                                                    'nbr_mois_capitaliser',
                                                                    e.target.value,
                                                                )
                                                            }
                                                        />
                                                        {erreur('nbr_mois_capitaliser')}
                                                    </Col>
                                                    <Col md={4}>
                                                        <Label className="fw-semibold">
                                                            Démarrage capitalisation
                                                        </Label>
                                                        <Input
                                                            type="date"
                                                            value={
                                                                data.date_demarrage_capitalisation
                                                            }
                                                            readOnly
                                                        />
                                                        <small className="text-muted">
                                                            Calculée automatiquement
                                                        </small>
                                                        {erreur('date_demarrage_capitalisation')}
                                                    </Col>
                                                </>
                                            )}
                                            {showCapitalisationSansFinanciere && (
                                                <Col md={4}>
                                                    <Label className="fw-semibold">
                                                        Démarrage capitalisation (sans prise en
                                                        charge){' '}
                                                        <span className="text-danger">*</span>
                                                    </Label>
                                                    <Input
                                                        type="date"
                                                        {...champ(
                                                            'date_demarrage_capitalisation_sans_financiere',
                                                        )}
                                                    />
                                                    {erreur(
                                                        'date_demarrage_capitalisation_sans_financiere',
                                                    )}
                                                </Col>
                                            )}
                                            <Col md={12}>
                                                <Label className="fw-semibold">
                                                    Observations{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <Input
                                                    type="textarea"
                                                    rows={3}
                                                    {...champ('observations')}
                                                    onChange={e =>
                                                        setData(
                                                            'observations',
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                />
                                                {erreur('observations')}
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── PIÈCES JUSTIFICATIVES ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-primary-subtle">
                                        <h5 className="card-title mb-0 text-primary fw-bold">
                                            <i className="ri-file-upload-line me-1" />
                                            PIÈCES JUSTIFICATIVES
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <p className="text-muted">
                                            Ne redéposez que les pièces à corriger : chaque dépôt
                                            ajoute une nouvelle version, l'ancienne reste
                                            consultable.
                                        </p>
                                        <Row className="g-3">
                                            {/* Toujours affichés */}
                                            {['FICHIER_CMU', 'PIECE_IDENTITE', 'FICHIER_RIB'].map(
                                                code => {
                                                    const type = typesDocument.find(
                                                        t => t.code === code,
                                                    );
                                                    if (!type) return null;
                                                    const existant =
                                                        documentsDeposes[type.code];
                                                    return (
                                                        <Col md={6} key={type.code}>
                                                            <Label className="fw-semibold">
                                                                {type.nom}
                                                            </Label>
                                                            {existant?.derniere_version && (
                                                                <div className="mb-1">
                                                                    <a
                                                                        href={`/storage/${existant.derniere_version.chemin}`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="link-primary"
                                                                    >
                                                                        <i className="ri-attachment-2 me-1" />
                                                                        {
                                                                            existant
                                                                                .derniere_version
                                                                                .nom_original
                                                                        }
                                                                    </a>
                                                                    <Badge
                                                                        color="light"
                                                                        className="text-body ms-2"
                                                                    >
                                                                        v
                                                                        {
                                                                            existant
                                                                                .derniere_version
                                                                                .numero_version
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                            )}
                                                            <Input
                                                                type="file"
                                                                accept=".pdf,.jpg,.jpeg,.png"
                                                                invalid={
                                                                    !!errors[
                                                                        `documents.${type.code}`
                                                                    ]
                                                                }
                                                                onChange={e => {
                                                                    const fichier = (
                                                                        e.target as HTMLInputElement,
                                                                    ).files?.[0];
                                                                    const suivant = {
                                                                        ...data.documents,
                                                                    };
                                                                    if (fichier) {
                                                                        suivant[type.code] =
                                                                            fichier;
                                                                    } else {
                                                                        delete suivant[
                                                                            type.code,
                                                                        ];
                                                                    }
                                                                    setData(
                                                                        'documents',
                                                                        suivant,
                                                                    );
                                                                }}
                                                            />
                                                            {erreur(
                                                                `documents.${type.code}`,
                                                            )}
                                                        </Col>
                                                    );
                                                },
                                            )}

                                            {/* Attestation + Certificat : seulement STAGE ECOLE */}
                                            {isStageEcole &&
                                                ['FICHIER_ATTESTATION', 'FICHIER_CERTIFICAT_FREQUENTATION'].map(
                                                    code => {
                                                        const type = typesDocument.find(
                                                            t => t.code === code,
                                                        );
                                                        if (!type) return null;
                                                        const existant =
                                                            documentsDeposes[type.code];
                                                        return (
                                                            <Col md={6} key={type.code}>
                                                                <Label className="fw-semibold">
                                                                    {type.nom}{' '}
                                                                    <span className="text-danger">
                                                                        *
                                                                    </span>
                                                                </Label>
                                                                {existant?.derniere_version && (
                                                                    <div className="mb-1">
                                                                        <a
                                                                            href={`/storage/${existant.derniere_version.chemin}`}
                                                                            target="_blank"
                                                                            rel="noreferrer"
                                                                            className="link-primary"
                                                                        >
                                                                            <i className="ri-attachment-2 me-1" />
                                                                            {
                                                                                existant
                                                                                    .derniere_version
                                                                                    .nom_original
                                                                            }
                                                                        </a>
                                                                        <Badge
                                                                            color="light"
                                                                            className="text-body ms-2"
                                                                        >
                                                                            v
                                                                            {
                                                                                existant
                                                                                    .derniere_version
                                                                                    .numero_version
                                                                            }
                                                                        </Badge>
                                                                    </div>
                                                                )}
                                                                <Input
                                                                    type="file"
                                                                    accept=".pdf,.doc,.docx"
                                                                    invalid={
                                                                        !!errors[
                                                                            `documents.${type.code}`,
                                                                        ]
                                                                    }
                                                                    onChange={e => {
                                                                        const fichier = (
                                                                            e.target as HTMLInputElement,
                                                                        ).files?.[0];
                                                                        const suivant = {
                                                                            ...data.documents,
                                                                        };
                                                                        if (fichier) {
                                                                            suivant[type.code] =
                                                                                fichier;
                                                                        } else {
                                                                            delete suivant[
                                                                                type.code,
                                                                            ];
                                                                        }
                                                                        setData(
                                                                            'documents',
                                                                            suivant,
                                                                        );
                                                                    }}
                                                                />
                                                                {erreur(
                                                                    `documents.${type.code}`,
                                                                )}
                                                            </Col>
                                                        );
                                                    },
                                                )}

                                            {/* Diplôme : seulement STAGE DE QUALIFICATION */}
                                            {isStageQualification && (() => {
                                                const type = typesDocument.find(
                                                    t => t.code === 'FICHIER_DIPLOME',
                                                );
                                                if (!type) return null;
                                                const existant =
                                                    documentsDeposes[type.code];
                                                return (
                                                    <Col md={6} key={type.code}>
                                                        <Label className="fw-semibold">
                                                            {type.nom}{' '}
                                                            <span className="text-danger">*</span>
                                                        </Label>
                                                        {existant?.derniere_version && (
                                                            <div className="mb-1">
                                                                <a
                                                                    href={`/storage/${existant.derniere_version.chemin}`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="link-primary"
                                                                >
                                                                    <i className="ri-attachment-2 me-1" />
                                                                    {
                                                                        existant
                                                                            .derniere_version
                                                                            .nom_original
                                                                    }
                                                                </a>
                                                                <Badge
                                                                    color="light"
                                                                    className="text-body ms-2"
                                                                >
                                                                    v
                                                                    {
                                                                        existant
                                                                            .derniere_version
                                                                            .numero_version
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        )}
                                                        <Input
                                                            type="file"
                                                            accept=".pdf,.doc,.docx"
                                                            invalid={
                                                                !!errors[
                                                                    `documents.${type.code}`,
                                                                ]
                                                            }
                                                            onChange={e => {
                                                                const fichier = (
                                                                    e.target as HTMLInputElement,
                                                                ).files?.[0];
                                                                const suivant = {
                                                                    ...data.documents,
                                                                };
                                                                if (fichier) {
                                                                    suivant[type.code] =
                                                                        fichier;
                                                                } else {
                                                                    delete suivant[
                                                                        type.code,
                                                                    ];
                                                                }
                                                                setData(
                                                                    'documents',
                                                                    suivant,
                                                                );
                                                            }}
                                                        />
                                                        {erreur(
                                                            `documents.${type.code}`,
                                                        )}
                                                    </Col>
                                                );
                                            })()}

                                            {/* Type de paiement */}
                                            <Col md={6}>
                                                <Label className="fw-semibold">
                                                    Type de paiement{' '}
                                                    <span className="text-danger">*</span>
                                                </Label>
                                                <RsSelect
                                                    id="type_paiement_id"
                                                    value={data.type_paiement_id}
                                                    options={references.typesPaiement.map(t => ({
                                                        value: String(t.id),
                                                        label: t.nom,
                                                    }))}
                                                    onChange={val =>
                                                        setData('type_paiement_id', val)
                                                    }
                                                    invalid={!!errors.type_paiement_id}
                                                />
                                                {erreur('type_paiement_id')}
                                            </Col>

                                            {/* Fiche Trésor Money : si showTresorMoney */}
                                            {showTresorMoney && (() => {
                                                const type = typesDocument.find(
                                                    t => t.code === 'TRESOR_MONEY',
                                                );
                                                return (
                                                    <>
                                                        <Col md={6}>
                                                            <Label className="fw-semibold">
                                                                Fiche Trésor Money{' '}
                                                                {requiresYup && (
                                                                    <span className="text-danger">*</span>
                                                                )}
                                                            </Label>
                                                            {type &&
                                                                documentsDeposes[type.code]
                                                                    ?.derniere_version && (
                                                                <div className="mb-1">
                                                                    <a
                                                                        href={`/storage/${documentsDeposes[type.code].derniere_version!.chemin}`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="link-primary"
                                                                    >
                                                                        <i className="ri-attachment-2 me-1" />
                                                                        {
                                                                            documentsDeposes[
                                                                                type.code,
                                                                            ].derniere_version!
                                                                                .nom_original
                                                                        }
                                                                    </a>
                                                                </div>
                                                            )}
                                                            <Input
                                                                type="file"
                                                                accept=".pdf,.jpg,.jpeg,.png"
                                                                onChange={e => {
                                                                    const fichier = (
                                                                        e.target as HTMLInputElement,
                                                                    ).files?.[0];
                                                                    const suivant = {
                                                                        ...data.documents,
                                                                    };
                                                                    if (fichier) {
                                                                        suivant.TRESOR_MONEY =
                                                                            fichier;
                                                                    } else {
                                                                        delete suivant[
                                                                            'TRESOR_MONEY',
                                                                        ];
                                                                    }
                                                                    setData(
                                                                        'documents',
                                                                        suivant,
                                                                    );
                                                                }}
                                                            />
                                                        </Col>
                                                        <Col md={6}>
                                                            <Label className="fw-semibold">
                                                                Numéro Trésor Money{' '}
                                                                {requiresYup && (
                                                                    <span className="text-danger">*</span>
                                                                )}
                                                            </Label>
                                                            <Input
                                                                type="text"
                                                                value={
                                                                    data.numero_tresor_money
                                                                }
                                                                onChange={e =>
                                                                    setData(
                                                                        'numero_tresor_money',
                                                                        e.target.value
                                                                            .replace(/[^0-9]/g, '')
                                                                            .slice(0, 10),
                                                                    )
                                                                }
                                                                maxLength={10}
                                                                invalid={
                                                                    !!errors.numero_tresor_money
                                                                }
                                                            />
                                                            {erreur('numero_tresor_money')}
                                                        </Col>
                                                    </>
                                                );
                                            })()}

                                            {/* Fiche Wave : si showWave (BMZ) */}
                                            {showWave && (() => {
                                                const type = typesDocument.find(
                                                    t => t.code === 'FICHE_WAVE',
                                                );
                                                return (
                                                    <>
                                                        <Col md={6}>
                                                            <Label className="fw-semibold">
                                                                Attestation de reconnaissance de numéro Mobile Money{' '}
                                                                <span className="text-danger">*</span>
                                                            </Label>
                                                            {type &&
                                                                documentsDeposes[type.code]
                                                                    ?.derniere_version && (
                                                                <div className="mb-1">
                                                                    <a
                                                                        href={`/storage/${documentsDeposes[type.code].derniere_version!.chemin}`}
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                        className="link-primary"
                                                                    >
                                                                        <i className="ri-attachment-2 me-1" />
                                                                        {
                                                                            documentsDeposes[
                                                                                type.code,
                                                                            ].derniere_version!
                                                                                .nom_original
                                                                        }
                                                                    </a>
                                                                </div>
                                                            )}
                                                            <Input
                                                                type="file"
                                                                accept=".pdf,.doc,.docx"
                                                                onChange={e => {
                                                                    const fichier = (
                                                                        e.target as HTMLInputElement,
                                                                    ).files?.[0];
                                                                    const suivant = {
                                                                        ...data.documents,
                                                                    };
                                                                    if (fichier) {
                                                                        suivant.FICHE_WAVE =
                                                                            fichier;
                                                                    } else {
                                                                        delete suivant[
                                                                            'FICHE_WAVE',
                                                                        ];
                                                                    }
                                                                    setData(
                                                                        'documents',
                                                                        suivant,
                                                                    );
                                                                }}
                                                            />
                                                        </Col>
                                                        <Col md={6}>
                                                            <Label className="fw-semibold">
                                                                Numéro Wave{' '}
                                                                <span className="text-danger">*</span>
                                                            </Label>
                                                            <Input
                                                                type="text"
                                                                value={data.numero_wave}
                                                                onChange={e =>
                                                                    setData(
                                                                        'numero_wave',
                                                                        e.target.value
                                                                            .replace(/[^0-9]/g, '')
                                                                            .slice(0, 10),
                                                                    )
                                                                }
                                                                maxLength={10}
                                                                invalid={!!errors.numero_wave}
                                                            />
                                                            {erreur('numero_wave')}
                                                        </Col>
                                                    </>
                                                );
                                            })()}

                                            {/* Contrat + Fiche AEJ : toujours affichés */}
                                            {['CONTRAT', 'FICHE_AEJ'].map(code => {
                                                const type = typesDocument.find(
                                                    t => t.code === code,
                                                );
                                                if (!type) return null;
                                                const existant =
                                                    documentsDeposes[type.code];
                                                return (
                                                    <Col md={6} key={type.code}>
                                                        <Label className="fw-semibold">
                                                            {type.nom}
                                                        </Label>
                                                        {existant?.derniere_version && (
                                                            <div className="mb-1">
                                                                <a
                                                                    href={`/storage/${existant.derniere_version.chemin}`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="link-primary"
                                                                >
                                                                    <i className="ri-attachment-2 me-1" />
                                                                    {
                                                                        existant
                                                                            .derniere_version
                                                                            .nom_original
                                                                    }
                                                                </a>
                                                                <Badge
                                                                    color="light"
                                                                    className="text-body ms-2"
                                                                >
                                                                    v
                                                                    {
                                                                        existant
                                                                            .derniere_version
                                                                            .numero_version
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        )}
                                                        <Input
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            invalid={
                                                                !!errors[
                                                                    `documents.${type.code}`,
                                                                ]
                                                            }
                                                            onChange={e => {
                                                                const fichier = (
                                                                    e.target as HTMLInputElement,
                                                                ).files?.[0];
                                                                const suivant = {
                                                                    ...data.documents,
                                                                };
                                                                if (fichier) {
                                                                    suivant[type.code] =
                                                                        fichier;
                                                                } else {
                                                                    delete suivant[
                                                                        type.code,
                                                                    ];
                                                                }
                                                                setData(
                                                                    'documents',
                                                                    suivant,
                                                                );
                                                            }}
                                                        />
                                                        {erreur(
                                                            `documents.${type.code}`,
                                                        )}
                                                    </Col>
                                                );
                                            })}
                                                                }
                                                                setData('documents', suivant);
                                                            }}
                                                        />
                                                        {erreur(`documents.${type.code}`)}
                                                    </Col>
                                                );
                                            })}
                                        </Row>
                                    </CardBody>
                                </Card>

                                {/* ─────────────── TRANSMISSION ─────────────── */}
                                <Card>
                                    <CardHeader className="bg-success-subtle">
                                        <h5 className="card-title mb-0 text-success fw-bold">
                                            <i className="ri-send-plane-line me-1" />
                                            TRANSMISSION AU CHEF D'AGENCE
                                        </h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col md={12}>
                                                <Label className="fw-semibold">
                                                    Commentaire de correction (facultatif)
                                                </Label>
                                                <Input
                                                    type="textarea"
                                                    rows={2}
                                                    placeholder="Ce qui a été corrigé suite au rejet de la DMG…"
                                                    {...champ('motif')}
                                                />
                                                {erreur('motif')}
                                            </Col>
                                        </Row>

                                        <div className="d-flex flex-wrap gap-2 justify-content-end mt-4">
                                            <Button
                                                type="button"
                                                color="light"
                                                disabled={occupe}
                                                onClick={() => window.history.back()}
                                            >
                                                Annuler
                                            </Button>

                                            {/* Geste 1 : corriger sans transmettre */}
                                            <Button
                                                type="submit"
                                                color="primary"
                                                disabled={occupe}
                                            >
                                                <i className="ri-save-line me-1" />
                                                Enregistrer la correction
                                            </Button>

                                            {/* Geste 2 : transmettre une fiche déjà corrigée */}
                                            <Button
                                                type="button"
                                                color="info"
                                                outline
                                                disabled={occupe || !peutTransmettre}
                                                onClick={transmettreSeulement}
                                            >
                                                {transmissionEnCours ? (
                                                    <Spinner size="sm" className="me-1" />
                                                ) : (
                                                    <i className="ri-send-plane-line me-1" />
                                                )}
                                                Transmettre au Chef d'Agence
                                            </Button>

                                            {/* Geste 3 : les deux d'un coup */}
                                            <Button
                                                type="button"
                                                color="success"
                                                disabled={occupe || !peutTransmettre}
                                                onClick={e => soumettre(e, 'enregistrer_transmettre')}
                                            >
                                                {processing ? (
                                                    <Spinner size="sm" className="me-1" />
                                                ) : (
                                                    <i className="ri-check-double-line me-1" />
                                                )}
                                                Enregistrer et transmettre
                                            </Button>
                                        </div>

                                        {!peutTransmettre && (
                                            <p className="text-muted text-end mb-0 mt-2">
                                                La transmission est indisponible : aucun pointage
                                                ajourné par la DMG n'est rattaché à ce stagiaire.
                                            </p>
                                        )}
                                    </CardBody>
                                </Card>
                            </Col>
                        </Row>
                    </Form>
                </Container>
            </div>
        </React.Fragment>
    );
}
