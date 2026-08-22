import { Head, Link, router, usePage } from '@inertiajs/react';
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
    UncontrolledTooltip,
} from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

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

interface Props {
    offres: OffreItem[];
    agences: RefItem[];
    communes: RefItem[];
    typesStage: RefItem[];
    originesStagiaire: RefItem[];
    liensParente: RefItem[];
    niveauxEtude: RefItem[];
    diplomes: RefItem[];
    typesEnseignement: RefItem[];
    handicaps: RefItem[];
    typesHandicap: RefItem[];
    typesPaiement: RefItem[];
    sourcesFinancement: RefItem[];
    statutsStage: RefItem[];
    situationsStage: RefItem[];
    typesStructure: RefItem[];
    conseillers: ConseillerItem[];
    authUserAgenceIds: number[];
}

interface DemandeurAej {
    numero_aej?: string;
    nom?: string;
    prenom?: string;
    prenoms?: string;
    date_naissance?: string;
    lieu_naissance?: string;
    telephone?: string;
    sexe?: string;
    type_piece_identite?: string;
    numero_identite?: string;
    specialite?: string;
    etablissement_frequente?: string;
    type_enseignement?: string;
    handicap?: string;
    commune_residence?: string;
    personne_urgence?: string;
    prsurgent_tel1?: string;
    numero_cmu?: string;
    numero_piece_identite?: string;
    contact_urgence_1?: string;
    lien_parente?: string;
    specialite_diplome?: string;
    specialite_preciser?: string;
}

interface FormErrors {
    [key: string]: string;
}

/* ═══════════════════════════════════════════════════════════════════════════
   CONSTANTES
   ═══════════════════════════════════════════════════════════════════════════ */
const ALLOWED_COHORT_DAYS = [1, 2, 3, 4, 5, 10, 20];

const TYPE_PIECE_OPTIONS = [
    { value: "ATTESTATION D'IDENTITÉ", label: "Attestation d'identite" },
    { value: "AUTRES", label: "Autres" },
    { value: "RÉCIPISSÉ CNI", label: "Recepisse CNI" },
    { value: "CARTE CMU", label: "Carte CMU" },
    { value: "CARTE NATIONALE D'IDENTITÉ (BLANC)", label: "CNI blanc" },
    { value: "CARTE NATIONALE D'IDENTITÉ (ORANGE)", label: "CNI orange" },
    { value: "PASSEPORT", label: "Passeport" },
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

/** Extensions autorisées pour les fichiers */
const ALLOWED_DOC_EXTENSIONS = ['pdf', 'doc', 'docx'];
const ALLOWED_IMAGE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
const MAX_FILE_SIZE_KB = 10240; // 10 MB

/** Valider l'extension d'un fichier */
const validateFileExtension = (file: File, allowedExts: string[]): boolean => {
    const ext = file.name.split('.').pop()?.toLowerCase() || '';
    return allowedExts.includes(ext);
};

/** Valider la taille d'un fichier (max en KB) */
const validateFileSize = (file: File, maxKB: number): boolean => {
    return file.size <= maxKB * 1024;
};

/* ═══════════════════════════════════════════════════════════════════════════
   WRAPPER REACT-SELECT
   ═══════════════════════════════════════════════════════════════════════════ */
interface RsSelectOption {
    value: string;
    label: string;
}

/** Wrapper react-select compatible avec les states string existants */
const RsSelect = ({
    value, options, onChange, placeholder, isDisabled, className, id,
}: {
    value: string;
    options: RsSelectOption[];
    onChange: (val: string) => void;
    placeholder?: string;
    isDisabled?: boolean;
    className?: string;
    id?: string;
}) => {
    const selected = options.find(o => o.value === value) || null;
    return (
        <Select<RsSelectOption>
            inputId={id}
            className={className}
            classNamePrefix="select2"
            options={options}
            value={selected}
            onChange={opt => onChange(opt?.value ?? '')}
            placeholder={placeholder || 'Sélectionner...'}
            isDisabled={isDisabled}
            isClearable
            noOptionsMessage={() => 'Aucune option'}
            formatOptionLabel={(opt) => opt.label}
        />
    );
};

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANTS D'AIDE
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * Composant Tooltip d'aide contextuelle pour les champs complexes
 */
const FieldTooltip: React.FC<{ id: string; children: React.ReactNode }> = ({ id, children }) => {
    return (
        <>
            <i
                id={`tooltip-${id}`}
                className="ri-information-line text-primary ms-1"
                style={{ cursor: 'help', fontSize: '16px' }}
            />
            <UncontrolledTooltip placement="top" target={`tooltip-${id}`}>
                {children}
            </UncontrolledTooltip>
        </>
    );
};

/**
 * Composant Label amélioré avec support tooltip optionnel
 */
const EnhancedLabel: React.FC<{
    label: string;
    required?: boolean;
    tooltipId?: string;
    tooltipContent?: React.ReactNode;
}> = ({ label, required, tooltipId, tooltipContent }) => {
    return (
        <Label className="fw-semibold">
            {label}
            {required && <span className="text-danger">*</span>}
            {tooltipId && tooltipContent && <FieldTooltip id={tooltipId}>{tooltipContent}</FieldTooltip>}
        </Label>
    );
};

/**
 * Composant de récapitulatif avant validation finale
 */
interface RecapitulatifProps {
    beneficiaire: Partial<BenefData>;
    stage: Partial<StageData>;
    documents: Record<string, File | null>;
    refData: {
        agences: RefItem[];
        communes: RefItem[];
        typesStage: RefItem[];
        sourcesFinancement: RefItem[];
        originesStagiaire: RefItem[];
        niveauxEtude: RefItem[];
        diplomes: RefItem[];
        entreprises: RefItem[];
        typesPaiement: RefItem[];
    };
}

const RecapitulatifInscription: React.FC<RecapitulatifProps> = ({ beneficiaire, stage, documents, refData }) => {
    const getRefLabel = (items: RefItem[], id: string | undefined) => {
        if (!id) return '—';
        const item = items.find(i => String(i.id) === id);
        return item?.nom || '—';
    };

    const formatDate = (date: string | undefined) => {
        if (!date) return '—';
        const d = new Date(date);
        return d.toLocaleDateString('fr-FR');
    };

    return (
        <Card className="shadow border-0">
            <CardHeader className="bg-gradient-danger text-white">
                <h5 className="mb-0 fw-bold">
                    <i className="ri-file-list-3-line me-2" />
                    RÉCAPITULATIF DE L'INSCRIPTION
                </h5>
                <small>Veuillez vérifier toutes les informations avant de valider</small>
            </CardHeader>
            <CardBody>
                <Row className="g-4">
                    {/* Section Agence */}
                    <Col md={6}>
                        <div className="border-start border-3 border-danger ps-3">
                            <h6 className="text-danger fw-bold mb-3">
                                <i className="ri-building-2-line me-1" />Informations Agence
                            </h6>
                            <div className="mb-2">
                                <strong>Agence :</strong> {getRefLabel(refData.agences, stage.agence_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Conseiller :</strong> {stage.conseiller_id || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Origine :</strong> {getRefLabel(refData.originesStagiaire, stage.origine_stagiaire_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Financement :</strong> {getRefLabel(refData.sourcesFinancement, stage.source_financement_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Type de stage :</strong> {getRefLabel(refData.typesStage, stage.type_stage_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Durée :</strong> {stage.duree_stage ? `${stage.duree_stage} mois` : '—'}
                            </div>
                        </div>
                    </Col>

                    {/* Section Bénéficiaire */}
                    <Col md={6}>
                        <div className="border-start border-3 border-info ps-3">
                            <h6 className="text-info fw-bold mb-3">
                                <i className="ri-user-3-line me-1" />Identité Stagiaire
                            </h6>
                            <div className="mb-2">
                                <strong>N° AEJ :</strong> {beneficiaire.numero_aej || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Nom :</strong> {beneficiaire.nom || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Prénoms :</strong> {beneficiaire.prenoms || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Date de naissance :</strong> {formatDate(beneficiaire.date_naissance)}
                            </div>
                            <div className="mb-2">
                                <strong>Sexe :</strong> {beneficiaire.sexe || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Téléphone :</strong> {beneficiaire.telephone_principal || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>N° CMU :</strong> {beneficiaire.numero_cmu || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Pièce d'identité :</strong> {beneficiaire.numero_piece_identite || '—'}
                            </div>
                        </div>
                    </Col>

                    {/* Section Formation */}
                    <Col md={6}>
                        <div className="border-start border-3 border-success ps-3">
                            <h6 className="text-success fw-bold mb-3">
                                <i className="ri-graduation-cap-line me-1" />Formation
                            </h6>
                            <div className="mb-2">
                                <strong>Niveau d'étude :</strong> {getRefLabel(refData.niveauxEtude, beneficiaire.niveau_etude_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Diplôme :</strong> {getRefLabel(refData.diplomes, beneficiaire.diplome_id)}
                            </div>
                            {beneficiaire.specialite && (
                                <div className="mb-2">
                                    <strong>Spécialité :</strong> {beneficiaire.specialite}
                                </div>
                            )}
                            {beneficiaire.annee_diplome && (
                                <div className="mb-2">
                                    <strong>Année :</strong> {beneficiaire.annee_diplome}
                                </div>
                            )}
                        </div>
                    </Col>

                    {/* Section Entreprise */}
                    <Col md={6}>
                        <div className="border-start border-3 border-warning ps-3">
                            <h6 className="text-warning fw-bold mb-3">
                                <i className="ri-building-4-line me-1" />Entreprise d'accueil
                            </h6>
                            <div className="mb-2">
                                <strong>Entreprise :</strong> {getRefLabel(refData.entreprises, stage.entreprise_id)}
                            </div>
                            <div className="mb-2">
                                <strong>Service :</strong> {stage.service_affectation || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Encadreur :</strong> {stage.nom_encadreur || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Fonction encadreur :</strong> {stage.fonction_encadreur || '—'}
                            </div>
                            <div className="mb-2">
                                <strong>Contact encadreur :</strong> {stage.contact_encadreur || '—'}
                            </div>
                        </div>
                    </Col>

                    {/* Section Dates */}
                    <Col md={6}>
                        <div className="border-start border-3 border-primary ps-3">
                            <h6 className="text-primary fw-bold mb-3">
                                <i className="ri-calendar-line me-1" />Dates
                            </h6>
                            <div className="mb-2">
                                <strong>Date début :</strong> {formatDate(stage.date_debut)}
                            </div>
                            <div className="mb-2">
                                <strong>Date fin prévue :</strong> {formatDate(stage.date_fin_prevue)}
                            </div>
                            {stage.date_demarrage_capitalisation && (
                                <div className="mb-2">
                                    <strong>Début capitalisation :</strong> {formatDate(stage.date_demarrage_capitalisation)}
                                </div>
                            )}
                        </div>
                    </Col>

                    {/* Section Documents */}
                    <Col md={6}>
                        <div className="border-start border-3 border-secondary ps-3">
                            <h6 className="text-secondary fw-bold mb-3">
                                <i className="ri-file-upload-line me-1" />Documents joints
                            </h6>
                            {Object.entries(documents).map(([key, file]) => {
                                if (!file) return null;
                                return (
                                    <div key={key} className="mb-2">
                                        <i className="ri-file-3-line text-success me-1" />
                                        <small>{file.name}</small>
                                    </div>
                                );
                            })}
                            {Object.values(documents).every(f => !f) && (
                                <div className="text-muted">
                                    <i className="ri-file-warning-line me-1" />
                                    Aucun document joint
                                </div>
                            )}
                        </div>
                    </Col>

                    {/* Observations */}
                    {stage.observations && (
                        <Col md={12}>
                            <Alert color="info" className="mb-0">
                                <strong>Observations :</strong>
                                <div className="mt-2">{stage.observations}</div>
                            </Alert>
                        </Col>
                    )}
                </Row>
            </CardBody>
        </Card>
    );
};

/* ═══════════════════════════════════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════════════════════════════════ */
const todayString = () => new Date().toISOString().slice(0, 10);

const normalizeLabel = (v: string) =>
    v.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

const addMonths = (d: Date, m: number) => { const n = new Date(d); n.setMonth(n.getMonth() + m); return n; };
const addDays = (d: Date, days: number) => { const n = new Date(d); n.setDate(n.getDate() + days); return n; };
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

/** Calcul âge exact à partir de la date de naissance */
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

/** Date de capitalisation = date_debut + nbr_mois */
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
        "RÉCIPISSÉ CNI": 11,
        "CARTE CMU": 13,
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

/** Obtient les types de stage filtrés selon l'origine et la financement (par CODE) */
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

    // C2D ou PEJEDEC (BMZ) → uniquement QUALIFICATION
    if (fCode.includes('C2D') || fCode.includes('PEJEDEC')) {
        return typesStage.filter(ts => normalizeLabel(ts.nom).includes('QUALIFICATION')).map(ts => String(ts.id));
    }
    // BUDGET AEJ + PEJEDEC (capitalisation avec) → uniquement ECOLE
    if (fCode.includes('BUDGET AEJ') && oCode.includes('CAPITALISATION AVEC')) {
        return typesStage.filter(ts => normalizeLabel(ts.nom).includes('ECOLE')).map(ts => String(ts.id));
    }
    // BUDGET AEJ + NON CAPITALISATION (AEJ) → ECOLE + QUALIFICATION
    if (fCode.includes('BUDGET AEJ') && oCode.includes('NON CAPITALISATION')) {
        return typesStage
            .filter(ts => normalizeLabel(ts.nom).includes('ECOLE') || normalizeLabel(ts.nom).includes('QUALIFICATION'))
            .map(ts => String(ts.id));
    }
    // Par défaut → tous
    return typesStage.map(ts => String(ts.id));
};

/** Durées selon financement et type stage (replication exacte du legacy getTypeStage)
 *
 * Legacy: Le serveur retourne les durées configurées pour chaque type stage.
 * - type_stage.duree=6 → ajoute "6 mois"
 * - type_stage.duree=3 → si BMZ(PEJEDEC): [3]; sinon: ["", 1, 1.5, 2, 3]
 *
 * Logique durée par type de stage:
 * - STAGE ECOLE → [1, 1.5, 2, 3, 6] mois
 * - STAGE DE QUALIFICATION → strictement 6 mois
 */
const getDurationOptions = (financementCode: string, typeStageLabel: string): { value: string; label: string }[] => {
    const isEcole = normalizeLabel(typeStageLabel).includes('ECOLE');
    const isQualif = normalizeLabel(typeStageLabel).includes('QUALIFICATION');

    // STAGE ECOLE → [1, 1.5, 2, 3, 6] mois
    if (isEcole) {
        return [
            { value: '1', label: '1 mois' },
            { value: '1.5', label: '1.5 mois' },
            { value: '2', label: '2 mois' },
            { value: '3', label: '3 mois' },
            { value: '6', label: '6 mois' },
        ];
    }
    // STAGE DE QUALIFICATION → strictement 6 mois
    if (isQualif) {
        return [{ value: '6', label: '6 mois' }];
    }
    // Par défaut → toutes les durées
    return [
        { value: '1', label: '1 mois' },
        { value: '1.5', label: '1.5 mois' },
        { value: '2', label: '2 mois' },
        { value: '3', label: '3 mois' },
        { value: '6', label: '6 mois' },
    ];
};

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANT
   ═══════════════════════════════════════════════════════════════════════════ */
const Create = ({
    offres, agences, communes, typesStage, originesStagiaire,
    liensParente, niveauxEtude, diplomes, typesEnseignement,
    handicaps, typesHandicap, typesPaiement, sourcesFinancement,
    statutsStage, situationsStage, typesStructure,
    conseillers, authUserAgenceIds,
}: Props) => {
    const { flash, auth } = usePage<{ flash: { success?: string; error?: string }; auth: { user?: { type_user_id?: number } } }>().props;

    /* ─── État formulaire ─── */
    const [beneficiaire, setBeneficiaire] = useState({
        numero_aej: '', nom: '', prenoms: '', sexe: '', date_naissance: '',
        lieu_naissance: '', sous_prefecture_naissance: '', custom_sous_prefecture: '',
        commune_residence_id: '', commune_residence_text: '',
        sous_prefecture_residence: '', nature_piece_identite: '', numero_piece_identite: '',
        numero_cmu: '', telephone_principal: '', telephone_secondaire: '',
        personne_urgence: '', lien_parente_id: '', contact_urgence_1: '', contact_urgence_2: '',
        niveau_etude_id: '', diplome_id: '', autre_diplome: '', specialite: '',
        annee_diplome: '', etablissement_frequente: '', type_enseignement_id: '',
        handicap_id: '', type_handicap_id: '', autre_handicap: '',
        type_paiement_id: '', numero_tresor_money: '', numero_wave: '',
    });

    const [stage, setStage] = useState({
        agence_id: '', conseiller_id: '', origine_stagiaire_id: '',
        source_financement_id: '', type_stage_id: '', offre_emploi_id: '',
        entreprise_id: '', sigle_entreprise: '',
        date_entree_portefeuille: todayString(),
        service_affectation: '', intitule_poste: '', localite_stage: '',
        commune_stage: '', sous_prefecture_stage: '', nom_encadreur: '',
        fonction_encadreur: '', contact_encadreur: '', statut_stage: '1',
        situation_stage: '1', date_debut: '', date_fin_prevue: '',
        duree_stage: '', nbr_mois_capitaliser: 0,
        date_demarrage_capitalisation: '', date_demarrage_capitalisation_sans_financiere: '',
        observations: '', type_structure_id: '',
        // Offre detail fields
        intitule_offre: '', nombre_de_place: 0,
    });

    const [contrat, setContrat] = useState({ numero: '', date_debut: '', date_fin: '', prime_mensuelle: '' });
    const [documents, setDocuments] = useState<Record<string, File | null>>({});

    /* ─── Wizard stepper ─── */
    const STEPS = [
        { key: 'agence', label: 'Agence Régionale', icon: 'ri-building-2-line' },
        { key: 'identification', label: 'Identification', icon: 'ri-user-3-line' },
        { key: 'stage', label: 'Mise en Stage', icon: 'ri-briefcase-line' },
        { key: 'documents', label: 'Documents', icon: 'ri-file-upload-line' },
        { key: 'recapitulatif', label: 'Récapitulatif', icon: 'ri-check-double-line' },
    ] as const;
    type StepKey = typeof STEPS[number]['key'];
    const [currentStep, setCurrentStep] = useState<number>(0);

    /* ─── UI states ─── */
    const [errors, setErrors] = useState<FormErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const [demandeurLoading, setDemandeurLoading] = useState(false);
    const [demandeurError, setDemandeurError] = useState('');
    const [demandeurLoaded, setDemandeurLoaded] = useState(false);

    /* ─── Derived ─── */
    const origineId = String(stage.origine_stagiaire_id);
    const financementId = String(stage.source_financement_id);
    const age = calculAge(beneficiaire.date_naissance);

    // Comparaison par CODE (les IDs diffèrent entre legacy et nouveau système)
    const selectedOrigine = originesStagiaire.find(o => String(o.id) === origineId);
    const selectedFinancement = sourcesFinancement.find(s => String(s.id) === financementId);
    const origineCode = normalizeLabel(selectedOrigine?.code || selectedOrigine?.nom || '');
    const financementCode = normalizeLabel(selectedFinancement?.code || selectedFinancement?.nom || '');

    // Origine: NON_CAPITALISATION (AEJ), CAPITALISATION_AVEC (PEJEDEC), CAPITALISATION_SANS, PRE_FINANCE
    const isAEJ = origineCode.includes('NON CAPITALISATION');
    const isPEJEDEC = origineCode.includes('CAPITALISATION AVEC');
    const isSpontaneOuDAICG = origineCode.includes('CAPITALISATION SANS') || origineCode.includes('PRE FINANCE');
    // Financement: BUDGET_AEJ (ancien ID=3), C2D (ancien ID=4), PEJEDEC (ancien ID=5)
    const isFinancementBMZ = financementCode.includes('PEJEDEC');
    const isFinancementBailleurs = financementCode.includes('BUDGET AEJ');
    const isFinancementC2d = financementCode.includes('C2D');

    /** Type de stage sélectionné → label */
    function getStageLabel(): string {
        const ts = typesStage.find(t => String(t.id) === stage.type_stage_id);
        return ts?.nom || '';
    }
    const stageLabel = normalizeLabel(getStageLabel());
    const isStageEcole = stageLabel.includes('ECOLE');
    const isStageQualification = stageLabel.includes('QUALIFICATION');

    /** Flags d'affichage */
    const showOffre = isAEJ || isPEJEDEC;
    const showOffreDetail = showOffre && stage.offre_emploi_id;
    // Date debut : masquée pour Spontané/DAICG (capitalisation) — legacy updateOrigineStagiaire()
    const showDateDebut = isAEJ || isPEJEDEC;
    const showCapitalisationPejedec = isPEJEDEC;
    const showCapitalisationSansFinanciere = isSpontaneOuDAICG;
    const showWave = isFinancementBMZ;
    const showTresorMoney = !isFinancementBMZ;
    const showTypeStructure = isFinancementBailleurs && (isStageEcole || isStageQualification);
    // numero_yup requis uniquement si financement C2D — legacy js_validation: numero_yup.required = financement==4
    const requiresYup = isFinancementC2d;
    const requiresWave = isFinancementBMZ;

    /* ─── Options filtrées ─── */
    const filteredTypesStageIds = useMemo(
        () => getFilteredTypeStageIds(typesStage, originesStagiaire, sourcesFinancement, origineId, financementId),
        [typesStage, originesStagiaire, sourcesFinancement, origineId, financementId],
    );

    const filteredTypesStage = useMemo(
        () => typesStage.filter(ts => filteredTypesStageIds.includes(String(ts.id))),
        [typesStage, filteredTypesStageIds],
    );

    const durationOptions = useMemo(
        () => getDurationOptions(financementCode, getStageLabel()),
        [financementCode, stage.type_stage_id],
    );

    const filteredDiplomes = useMemo(() => {
        if (!stage.niveau_etude_id) return diplomes;
        return diplomes.filter(d => !d.niveau_id || String(d.niveau_id) === stage.niveau_etude_id);
    }, [diplomes, stage.niveau_etude_id]);

    const entrepriseOptions = useMemo(() => {
        if (!stage.agence_id) return [];
        const unique = new Map<number, OffreItem>();
        offres
            .filter(o => o.agence_id === Number(stage.agence_id))
            .forEach(o => { if (!unique.has(o.entreprise_id)) unique.set(o.entreprise_id, o); });
        return Array.from(unique.values()).map(o => ({
            value: o.entreprise_id,
            label: o.entreprise?.raison_sociale || `Entreprise #${o.entreprise_id}`,
        }));
    }, [offres, stage.agence_id]);

    const piecePrefix = getPiecePrefix(beneficiaire.nature_piece_identite);
    const pieceMaxLength = getPieceMaxLength(beneficiaire.nature_piece_identite);
    const pieceNumberDisplay = beneficiaire.numero_piece_identite.replace(new RegExp(`^${piecePrefix}`), '');

    const isNiveauAucun = String(stage.niveau_etude_id) === '1';
    const isDiplomeAutre = String(beneficiaire.diplome_id) === '42';

    /* ═══════════════════════════════════════════════════════════════════════
       AUTO-EFFECTS
       ═══════════════════════════════════════════════════════════════════════ */

    /** Auto-sélectionner l'agence si l'utilisateur n'en a qu'une seule dans son périmètre */
    useEffect(() => {
        if (authUserAgenceIds.length === 1 && !stage.agence_id) {
            setStage(s => ({ ...s, agence_id: String(authUserAgenceIds[0]) }));
        }
    }, [authUserAgenceIds]);

    /** Origine PEJEDEC → forcer financement=3, stage=ECOLE, durée=6, date_debut visible */
    useEffect(() => {
        if (isPEJEDEC) {
            const ecole = typesStage.find(ts => normalizeLabel(ts.nom).includes('ECOLE'));
            setStage(s => ({
                ...s,
                source_financement_id: '3',
                type_stage_id: ecole ? String(ecole.id) : s.type_stage_id,
                duree_stage: '6',
            }));
        }
    }, [origineId]);

    /** Origine AEJ → afficher offre,stage libre | Autres → masquer offre */
    useEffect(() => {
        if (!isAEJ && !isPEJEDEC) {
            setStage(s => ({
                ...s,
                offre_emploi_id: '',
                intitule_offre: '',
                nombre_de_place: 0,
                entreprise_id: '',
                sigle_entreprise: '',
            }));
        }
    }, [origineId]);

    /** Origine Spontané/DAICG → date_debut masquée (capitalisation) */
    useEffect(() => {
        if (isSpontaneOuDAICG) {
            setStage(s => ({ ...s, date_debut: '' }));
        }
    }, [origineId]);

    /** Type financement → auto-sets type paiement */
    useEffect(() => {
        if (isFinancementBMZ) {
            const waveType = typesPaiement.find(p => normalizeLabel(p.nom).includes('WAVE'));
            setBeneficiaire(b => ({ ...b, type_paiement_id: waveType ? String(waveType.id) : '2' }));
        } else if (financementId) {
            const yupType = typesPaiement.find(p => normalizeLabel(p.nom).includes('TRESOR') || normalizeLabel(p.nom).includes('YUP'));
            setBeneficiaire(b => ({ ...b, type_paiement_id: yupType ? String(yupType.id) : '1' }));
        }
    }, [financementId]);

    /** Type financement → filtre type stage + durée + reset dates */
    useEffect(() => {
        if (isFinancementBMZ || isFinancementC2d) {
            const qualif = typesStage.find(ts => normalizeLabel(ts.nom).includes('QUALIFICATION'));
            setStage(s => ({
                ...s,
                type_stage_id: qualif ? String(qualif.id) : '',
                duree_stage: '6',
                date_debut: '',
                date_fin_prevue: '',
            }));
        } else if (isFinancementBailleurs && isPEJEDEC) {
            const ecole = typesStage.find(ts => normalizeLabel(ts.nom).includes('ECOLE'));
            setStage(s => ({
                ...s,
                type_stage_id: ecole ? String(ecole.id) : '',
                duree_stage: '6',
                date_debut: '',
                date_fin_prevue: '',
            }));
        } else if (isFinancementBailleurs && isAEJ) {
            setStage(s => ({ ...s, date_debut: '', date_fin_prevue: '' }));
        }
    }, [financementId]);

    /** Niveau étude = 1 (AUCUN) → auto-fill */
    useEffect(() => {
        if (isNiveauAucun) {
            const diplomeAucun = diplomes.find(d => normalizeLabel(d.nom) === 'AUCUN');
            const typeEnsAucun = typesEnseignement.find(t => normalizeLabel(t.nom) === 'AUCUN');
            setBeneficiaire(b => ({
                ...b,
                diplome_id: diplomeAucun ? String(diplomeAucun.id) : '',
                specialite: 'AUCUN',
                etablissement_frequente: 'AUCUN',
                type_enseignement_id: typeEnsAucun ? String(typeEnsAucun.id) : '',
                annee_diplome: '',
            }));
        } else {
            setBeneficiaire(b => ({
                ...b,
                diplome_id: '',
                specialite: '',
                etablissement_frequente: '',
                type_enseignement_id: '',
                annee_diplome: '',
            }));
        }
    }, [stage.niveau_etude_id]);

    /** Capitalisation PEJEDEC → calcul date_demarrage */
    useEffect(() => {
        if (isPEJEDEC && stage.date_debut && stage.nbr_mois_capitaliser > 0) {
            const d = calculDateDemarrageCap(stage.date_debut, stage.nbr_mois_capitaliser);
            setStage(s => ({ ...s, date_demarrage_capitalisation: d }));
        }
    }, [stage.date_debut, stage.nbr_mois_capitaliser]);

    /** Calcul date fin auto */
    useEffect(() => {
        const base = (isSpontaneOuDAICG && stage.date_demarrage_capitalisation_sans_financiere)
            ? stage.date_demarrage_capitalisation_sans_financiere
            : stage.date_debut;
        const fin = calculateDateFin(base, stage.duree_stage);
        if (fin && fin !== stage.date_fin_prevue) {
            setStage(s => ({ ...s, date_fin_prevue: fin }));
        }
    }, [stage.date_debut, stage.duree_stage, stage.date_demarrage_capitalisation_sans_financiere]);

    /* ═══════════════════════════════════════════════════════════════════════
       HANDLERS
       ═══════════════════════════════════════════════════════════════════════ */

    /** Chargement demandeur AEJ */
    const handleLoadDemandeur = useCallback(async () => {
        if (beneficiaire.numero_aej.length < 10) {
            setDemandeurError('Renseignez au moins 10 caracteres.');
            return;
        }
        setDemandeurLoading(true);
        setDemandeurError('');
        try {
            const resp = await fetch(`/api/stagiaires/demandeur/${encodeURIComponent(beneficiaire.numero_aej)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!resp.ok) throw new Error('Demandeur non trouvé');
            const { data }: { data: DemandeurAej } = await resp.json();

            // Déterminer nature piece et numéro
            const ncni = (data.numero_identite || data.numero_piece_identite || '').replace(/\s/g, '');
            const prefix2 = ncni.substring(0, 2).toUpperCase();
            let naturePiece = "ATTESTATION D'IDENTITÉ";
            let numPiece = ncni;
            let maxLen = 20;

            if (prefix2 === 'C0') {
                naturePiece = "CARTE NATIONALE D'IDENTITÉ (ORANGE)";
                numPiece = ncni.substring(1);
                maxLen = 10;
            } else if (prefix2 === 'CI') {
                naturePiece = "CARTE NATIONALE D'IDENTITÉ (BLANC)";
                numPiece = ncni.substring(2);
                maxLen = 9;
            } else {
                const typePieceLib = (data.type_piece_identite || '').toUpperCase();
                if (typePieceLib.includes('PASSEPORT')) {
                    naturePiece = 'PASSEPORT';
                    maxLen = 10;
                } else if (typePieceLib.includes('CNI') || typePieceLib.includes('CARTE NATIONALE')) {
                    naturePiece = "CARTE NATIONALE D'IDENTITÉ (BLANC)";
                    maxLen = 9;
                }
            }

            const sexeText = data.sexe === 'M' || data.sexe === 'MASCULIN' ? 'Homme' : 'Femme';

            setBeneficiaire(b => ({
                ...b,
                nom: (data.nom || '').toUpperCase(),
                prenoms: (data.prenoms || data.prenom || '').toUpperCase(),
                telephone_principal: data.telephone || b.telephone_principal,
                numero_aej: (data.numero_aej || b.numero_aej).toUpperCase(),
                numero_cmu: String(data.numero_cmu ?? '').toUpperCase(),
                date_naissance: data.date_naissance || b.date_naissance,
                sexe: sexeText,
                lieu_naissance: (data.lieu_naissance || '').toUpperCase(),
                nature_piece_identite: naturePiece,
                numero_piece_identite: numPiece,
                specialite: (data.specialite || data.specialite_diplome || '').toUpperCase(),
                etablissement_frequente: (data.etablissement_frequente || data.specialite_preciser || '').toUpperCase(),
                handicap_id: data.handicap || '',
                type_enseignement_id: data.type_enseignement || '',
                commune_residence_text: (data.commune_residence || '').toUpperCase(),
                personne_urgence: (data.personne_urgence || '').toUpperCase(),
                contact_urgence_1: data.prsurgent_tel1 || data.contact_urgence_1 || '',
            }));

            setDemandeurLoaded(true);
        } catch (e: any) {
            setDemandeurError(e.message || 'Impossible de charger le demandeur.');
        } finally {
            setDemandeurLoading(false);
        }
    }, [beneficiaire.numero_aej]);

    /** Restart → réinitialiser les champs identification */
    const handleRestart = useCallback(() => {
        const aej = beneficiaire.numero_aej;
        setBeneficiaire(b => ({
            ...b,
            numero_aej: aej,
            nom: '', prenoms: '', sexe: '', date_naissance: '',
            lieu_naissance: '', sous_prefecture_naissance: '',
            nature_piece_identite: '', numero_piece_identite: '',
            numero_cmu: '', telephone_principal: '', telephone_secondaire: '',
            personne_urgence: '', lien_parente_id: '', contact_urgence_1: '', contact_urgence_2: '',
            niveau_etude_id: '', diplome_id: '', autre_diplome: '', specialite: '',
            annee_diplome: '', etablissement_frequente: '', type_enseignement_id: '',
            handicap_id: '', type_handicap_id: '', autre_handicap: '',
        }));
        setDemandeurLoaded(false);
        setDemandeurError('');
    }, [beneficiaire.numero_aej]);

    /** Sélection offre → remplir entreprise, sigle, intitulé, nb places */
    const handleOffreChange = useCallback((sel: any) => {
        if (!sel) return;
        const o = sel.offre as OffreItem;
        setStage(s => ({
            ...s,
            offre_emploi_id: String(o.id),
            entreprise_id: String(o.entreprise_id),
            type_stage_id: String(o.type_stage_id),
            source_financement_id: String(o.source_financement_id),
            intitule_poste: o.intitule,
            intitule_offre: o.intitule,
            nombre_de_place: o.nombre_de_place,
            sigle_entreprise: o.entreprise?.sigle || '',
        }));
    }, []);

    /* ═══════════════════════════════════════════════════════════════════════
       VALIDATION
       ═══════════════════════════════════════════════════════════════════════ */
    const validate = useCallback((): FormErrors => {
        const e: FormErrors = {};
        const req = (f: string, v: string, label: string) => { if (!v?.trim()) e[f] = `${label} est obligatoire.`; };
        const reqNum = (f: string, v: string, len: number, label: string) => {
            if (v && v.length !== len) e[f] = `${label} doit avoir exactement ${len} chiffres.`;
        };

        // ─── AGENCE ───
        req('agence_id', stage.agence_id, 'Agence');
        req('conseiller_id', stage.conseiller_id, 'Conseiller');
        req('origine_stagiaire_id', stage.origine_stagiaire_id, 'Origine du stagiaire');
        req('source_financement_id', stage.source_financement_id, 'Source de financement');
        req('type_stage_id', stage.type_stage_id, 'Type de stage');
        req('duree_stage', stage.duree_stage, 'Durée prévisionnelle');

        if (showOffre && isAEJ) {
            req('offre_emploi_id', stage.offre_emploi_id, "Numéro de l'offre");
        }

        // ─── IDENTIFICATION ───
        req('numero_aej', beneficiaire.numero_aej, 'Numero AEJ');
        if (beneficiaire.numero_aej && (beneficiaire.numero_aej.length < 10 || beneficiaire.numero_aej.length > 14)) {
            e.numero_aej = 'Le numéro AEJ doit être entre 10 et 14 caractères.';
        }

        req('nom', beneficiaire.nom, 'Nom');
        req('prenoms', beneficiaire.prenoms, 'Prénoms');
        req('date_naissance', beneficiaire.date_naissance, 'Date de naissance');
        if (beneficiaire.date_naissance && new Date(beneficiaire.date_naissance) >= new Date()) {
            e.date_naissance = 'La date de naissance doit être antérieure à aujourd\'hui.';
        }

        // Vérification âge min et max (legacy: min 17, max 40; BUDGET AEJ+ECOLE → min 14)
        if (beneficiaire.date_naissance) {
            const minimumAge = isFinancementBailleurs && isStageEcole ? 14 : 17;
            if (age < minimumAge) {
                e.date_naissance = `Le stagiaire doit avoir au moins ${minimumAge} ans.`;
            }
            if (age > 40) {
                e.date_naissance = 'Le stagiaire ne doit pas dépasser 40 ans.';
            }
        }

        req('sexe', beneficiaire.sexe, 'Sexe');
        req('lieu_naissance', beneficiaire.lieu_naissance, 'Lieu de naissance');
        req('nature_piece_identite', beneficiaire.nature_piece_identite, 'Nature pièce');
        req('numero_piece_identite', beneficiaire.numero_piece_identite, 'Numéro pièce');
        req('numero_cmu', beneficiaire.numero_cmu, 'Numéro CMU');
        req('telephone_principal', beneficiaire.telephone_principal, 'Contact téléphonique 1');
        if (beneficiaire.telephone_principal && beneficiaire.telephone_principal.length !== 10) {
            e.telephone_principal = 'Le contact doit avoir exactement 10 chiffres.';
        }
        if (beneficiaire.telephone_secondaire && beneficiaire.telephone_secondaire.length !== 10 && beneficiaire.telephone_secondaire.length > 0) {
            e.telephone_secondaire = 'Le contact doit avoir exactement 10 chiffres.';
        }

        req('personne_urgence', beneficiaire.personne_urgence, 'Personne à contacter en cas d\'urgence');
        req('lien_parente_id', beneficiaire.lien_parente_id, 'Lien de parenté');
        req('contact_urgence_1', beneficiaire.contact_urgence_1, 'Contact parent 1');
        if (beneficiaire.contact_urgence_1 && beneficiaire.contact_urgence_1.length !== 10) {
            e.contact_urgence_1 = 'Le contact doit avoir exactement 10 chiffres.';
        }
        if (beneficiaire.contact_urgence_2 && beneficiaire.contact_urgence_2.length !== 0 && beneficiaire.contact_urgence_2.length !== 10) {
            e.contact_urgence_2 = 'Le contact doit avoir exactement 10 chiffres.';
        }

        // ─── FORMATION ───
        req('niveau_etude_id', beneficiaire.niveau_etude_id, 'Niveau d\'études');
        req('diplome_id', beneficiaire.diplome_id, 'Diplôme');
        if (isDiplomeAutre) req('autre_diplome', beneficiaire.autre_diplome, 'Préciser le diplôme');
        req('specialite', beneficiaire.specialite, 'Spécialité');

        // Annee diplome required si niveau ≠ 1
        if (!isNiveauAucun) {
            req('annee_diplome', beneficiaire.annee_diplome, 'Année du diplôme');
            if (beneficiaire.annee_diplome && beneficiaire.annee_diplome.length !== 4) {
                e.annee_diplome = 'L\'année doit avoir 4 chiffres.';
            }
            // Validation: année diplôme > année naissance
            if (beneficiaire.annee_diplome && beneficiaire.date_naissance) {
                const yearBirth = new Date(beneficiaire.date_naissance).getFullYear();
                const yearDiplome = parseInt(beneficiaire.annee_diplome, 10);
                if (yearDiplome < yearBirth) {
                    e.annee_diplome = 'L\'année du diplôme est antérieure à la date de naissance.';
                }
            }
        }

        req('etablissement_frequente', beneficiaire.etablissement_frequente, 'Établissement fréquenté');
        req('type_enseignement_id', beneficiaire.type_enseignement_id, 'Type d\'enseignement');
        req('handicap_id', beneficiaire.handicap_id, 'Handicap');
        if (beneficiaire.handicap_id === 'HANDICAP') req('type_handicap_id', beneficiaire.type_handicap_id, 'Type de handicap');
        if (beneficiaire.type_handicap_id === 'AUTRE') req('autre_handicap', beneficiaire.autre_handicap, 'Autre handicap');

        // ─── PAIEMENT ───
        req('type_paiement_id', beneficiaire.type_paiement_id, 'Type de paiement');
        if (requiresYup) {
            req('numero_tresor_money', beneficiaire.numero_tresor_money, 'Numéro Trésor Money');
            if (beneficiaire.numero_tresor_money && beneficiaire.numero_tresor_money.length !== 10) {
                e.numero_tresor_money = 'Le numéro doit avoir exactement 10 chiffres.';
            }
            // fiche_yup obligatoire si C2D — legacy: fiche_yup.required = financement==4
            if (!documents.fiche_tresor_money) {
                e.fiche_tresor_money = 'La fiche Trésor Money est obligatoire pour ce type de financement.';
            }
        }
        if (requiresWave) {
            req('numero_wave', beneficiaire.numero_wave, 'Numéro Wave');
            if (beneficiaire.numero_wave && beneficiaire.numero_wave.length !== 10) {
                e.numero_wave = 'Le numéro doit avoir exactement 10 chiffres.';
            }
            // fiche_wave obligatoire si BMZ — legacy: fiche_wave.required = financement==5
            if (!documents.fiche_wave) {
                e.fiche_wave = 'La fiche Wave est obligatoire pour ce type de financement.';
            }
        }

        // ─── MISE EN STAGE ───
        req('entreprise_id', stage.entreprise_id, 'Entreprise');
        req('service_affectation', stage.service_affectation, 'Service d\'affectation');
        // intitule_poste: NON requis dans le legacy (pas dans les règles jQuery)
        req('localite_stage', stage.localite_stage, 'Localité / Lieu de stage');
        req('commune_stage', stage.commune_stage, 'Commune du lieu de stage');
        req('sous_prefecture_stage', stage.sous_prefecture_stage, 'S/P du lieu de stage');
        req('nom_encadreur', stage.nom_encadreur, 'Nom et prénom de l\'encadreur');
        req('fonction_encadreur', stage.fonction_encadreur, 'Fonction de l\'encadreur');
        req('contact_encadreur', stage.contact_encadreur, 'Numéro de l\'encadreur');
        if (stage.contact_encadreur && stage.contact_encadreur.length !== 10) {
            e.contact_encadreur = 'Le contact doit avoir exactement 10 chiffres.';
        }
        req('situation_stage', stage.situation_stage, 'Situation du stage');

        // ─── DATES ───
        if (showDateDebut) {
            req('date_debut', stage.date_debut, 'Date de début de stage');
            if (stage.date_debut) {
                const d = new Date(`${stage.date_debut}T00:00:00`);
                const now = new Date();
                // maxDate: aujourd'hui — legacy: maxDate: moment().format('YYYY-MM-DD')
                if (d > now) {
                    e.date_debut = 'La date de début ne peut pas être dans le futur.';
                }
                // minDate: il y a 5 ans — legacy: minDate: moment().subtract(5, 'years').format('YYYY-MM-DD')
                const fiveYearsAgo = new Date();
                fiveYearsAgo.setFullYear(fiveYearsAgo.getFullYear() - 5);
                if (d < fiveYearsAgo) {
                    e.date_debut = 'La date de début ne peut pas être antérieure à 5 ans.';
                }
                // Jours de cohorte — legacy: validateDateCohorte
                if (!isFinancementBMZ) {
                    const day = d.getDate();
                    if (!ALLOWED_COHORT_DAYS.includes(day)) {
                        e.date_debut = 'Veuillez choisir le 1 à 5, 10 ou 20 du mois.';
                    }
                }
            }
        }
        if (isSpontaneOuDAICG) {
            req('date_demarrage_capitalisation_sans_financiere', stage.date_demarrage_capitalisation_sans_financiere, 'Date démarrage capitalisation');
        }
        if (isPEJEDEC) {
            // nbr_mois_capitaliser required when origin==2 — legacy: nbr_mois_captaliser.required = origin==2
            if (!stage.nbr_mois_capitaliser || stage.nbr_mois_capitaliser <= 0) {
                e.nbr_mois_capitaliser = 'Le nombre de mois à capitaliser est obligatoire.';
            }
            if (stage.nbr_mois_capitaliser && stage.duree_stage && stage.nbr_mois_capitaliser >= Number(stage.duree_stage)) {
                e.nbr_mois_capitaliser = 'Le mois de capitalisation est supérieur à la durée du stage !';
            }
            // date_demarrage_capitalisation required when origin==2 (auto-calculated but must exist)
            if (!stage.date_demarrage_capitalisation) {
                e.date_demarrage_capitalisation = 'La date de démarrage de la capitalisation est obligatoire.';
            }
        }

        req('date_fin_prevue', stage.date_fin_prevue, 'Date fin prévisionnelle');
        if (stage.date_debut && stage.date_fin_prevue && stage.date_fin_prevue < stage.date_debut) {
            e.date_fin_prevue = 'La date de fin doit être postérieure à la date de début.';
        }

        // ─── OBSERVATIONS ───
        req('observations', stage.observations, 'Observations');

        // ─── FICHIERS OBLIGATOIRES ───
        // Fichier CMU — toujours obligatoire (legacy: HTML required + label *)
        if (!documents.fichier_cmu) {
            e.fichier_cmu = 'Le fichier CMU est obligatoire.';
        } else {
            if (!validateFileExtension(documents.fichier_cmu, ALLOWED_IMAGE_EXTENSIONS)) {
                e.fichier_cmu = 'Le fichier doit être au format PDF, JPG ou PNG.';
            }
            if (!validateFileSize(documents.fichier_cmu, MAX_FILE_SIZE_KB)) {
                e.fichier_cmu = 'La taille du fichier ne doit pas dépasser 10 MB.';
            }
        }

        // Pièce d'identité — toujours obligatoire
        if (!documents.piece_identite) {
            e.piece_identite = 'La pièce d\'identité est obligatoire.';
        } else {
            if (!validateFileExtension(documents.piece_identite, ALLOWED_DOC_EXTENSIONS)) {
                e.piece_identite = 'Le fichier doit être au format PDF, DOC ou DOCX.';
            }
            if (!validateFileSize(documents.piece_identite, MAX_FILE_SIZE_KB)) {
                e.piece_identite = 'La taille du fichier ne doit pas dépasser 10 MB.';
            }
        }

        // Attestation d'admissibilité — obligatoire si STAGE ECOLE
        if (isStageEcole) {
            if (!documents.fichier_attestation) {
                e.fichier_attestation = 'L\'attestation d\'admissibilité est obligatoire pour ce type de stage.';
            } else {
                if (!validateFileExtension(documents.fichier_attestation, ALLOWED_DOC_EXTENSIONS)) {
                    e.fichier_attestation = 'Le fichier doit être au format PDF, DOC ou DOCX.';
                }
                if (!validateFileSize(documents.fichier_attestation, MAX_FILE_SIZE_KB)) {
                    e.fichier_attestation = 'La taille du fichier ne doit pas dépasser 10 MB.';
                }
            }
            // Certificat de fréquentation — obligatoire si STAGE ECOLE
            if (!documents.fichier_certificat_frequentation) {
                e.fichier_certificat_frequentation = 'Le certificat de fréquentation est obligatoire pour ce type de stage.';
            } else {
                if (!validateFileExtension(documents.fichier_certificat_frequentation, ALLOWED_DOC_EXTENSIONS)) {
                    e.fichier_certificat_frequentation = 'Le fichier doit être au format PDF, DOC ou DOCX.';
                }
                if (!validateFileSize(documents.fichier_certificat_frequentation, MAX_FILE_SIZE_KB)) {
                    e.fichier_certificat_frequentation = 'La taille du fichier ne doit pas dépasser 10 MB.';
                }
            }
        }

        // Diplôme — obligatoire si STAGE DE QUALIFICATION
        if (isStageQualification) {
            if (!documents.fichier_diplome) {
                e.fichier_diplome = 'Le diplôme est obligatoire pour ce type de stage.';
            } else {
                if (!validateFileExtension(documents.fichier_diplome, ALLOWED_DOC_EXTENSIONS)) {
                    e.fichier_diplome = 'Le fichier doit être au format PDF, DOC ou DOCX.';
                }
                if (!validateFileSize(documents.fichier_diplome, MAX_FILE_SIZE_KB)) {
                    e.fichier_diplome = 'La taille du fichier ne doit pas dépasser 10 MB.';
                }
            }
        }

        // Fiche Trésor Money — obligatoire si C2D
        if (requiresYup && documents.fiche_tresor_money) {
            if (!validateFileExtension(documents.fiche_tresor_money, ALLOWED_DOC_EXTENSIONS)) {
                e.fiche_tresor_money = 'Le fichier doit être au format PDF, DOC ou DOCX.';
            }
            if (!validateFileSize(documents.fiche_tresor_money, MAX_FILE_SIZE_KB)) {
                e.fiche_tresor_money = 'La taille du fichier ne doit pas dépasser 10 MB.';
            }
        }

        // Fiche Wave — obligatoire si PEJEDEC/BMZ
        if (requiresWave && documents.fiche_wave) {
            if (!validateFileExtension(documents.fiche_wave, ALLOWED_DOC_EXTENSIONS)) {
                e.fiche_wave = 'Le fichier doit être au format PDF, DOC ou DOCX.';
            }
            if (!validateFileSize(documents.fiche_wave, MAX_FILE_SIZE_KB)) {
                e.fiche_wave = 'La taille du fichier ne doit pas dépasser 10 MB.';
            }
        }

        return e;
    }, [beneficiaire, stage, documents, requiresYup, requiresWave, showOffre, isAEJ, isPEJEDEC, isSpontaneOuDAICG,
        isFinancementBMZ, isStageEcole, isStageQualification, isNiveauAucun, isDiplomeAutre, showDateDebut, age]);

    /** Valide uniquement les champs de l'étape n */
    const validateStep = useCallback((step: number): FormErrors => {
        const all = validate();
        const stepFields: Record<number, string[]> = {
            0: ['agence_id', 'conseiller_id', 'origine_stagiaire_id', 'source_financement_id', 'type_stage_id', 'duree_stage', 'offre_emploi_id', 'type_structure_id'],
            1: ['numero_aej', 'nom', 'prenoms', 'date_naissance', 'sexe', 'lieu_naissance', 'sous_prefecture_naissance',
                'commune_residence_text', 'sous_prefecture_residence', 'nature_piece_identite', 'numero_piece_identite',
                'numero_cmu', 'telephone_principal', 'contact_urgence_1', 'personne_urgence', 'lien_parente_id',
                'niveau_etude_id', 'diplome_id', 'specialite', 'annee_diplome', 'etablissement_frequente',
                'type_enseignement_id', 'handicap_id', 'type_handicap_id', 'autre_handicap',
                'autre_diplome', 'telephone_secondaire', 'contact_urgence_2'],
            2: ['entreprise_id', 'service_affectation', 'localite_stage', 'commune_stage', 'sous_prefecture_stage',
                'nom_encadreur', 'fonction_encadreur', 'contact_encadreur', 'situation_stage',
                'date_debut', 'date_fin_prevue', 'observations',
                'date_demarrage_capitalisation_sans_financiere', 'nbr_mois_capitaliser', 'date_demarrage_capitalisation'],
            3: ['fichier_cmu', 'piece_identite', 'fichier_attestation', 'fichier_certificat_frequentation',
                'fichier_diplome', 'fiche_tresor_money', 'fiche_wave', 'type_paiement_id'],
            4: [], // Étape récapitulatif - pas de validation spécifique
        };
        const fields = stepFields[step] || [];
        const stepErrors: FormErrors = {};
        fields.forEach(f => { if (all[f]) stepErrors[f] = all[f]; });
        return stepErrors;
    }, [validate]);

    /** Navigation étapes */
    const handleNext = useCallback(() => {
        const stepErrors = validateStep(currentStep);
        setErrors(stepErrors);
        if (Object.keys(stepErrors).length === 0 && currentStep < STEPS.length - 1) {
            setCurrentStep(s => s + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [currentStep, validateStep]);

    const handlePrev = useCallback(() => {
        if (currentStep > 0) {
            setErrors({});
            setCurrentStep(s => s - 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }, [currentStep]);

    const handleStepClick = useCallback((idx: number) => {
        // On ne peut cliquer que sur les étapes déjà visitées ou la suivante
        if (idx < currentStep) {
            setErrors({});
            setCurrentStep(idx);
        }
    }, [currentStep]);

    /** Compteur d'erreurs par étape */
    const getStepErrorCount = useCallback((step: number): number => {
        return Object.keys(validateStep(step)).length;
    }, [validateStep]);

    /* ═══════════════════════════════════════════════════════════════════════
       DOUBLON CHECK + SUBMIT
       ═══════════════════════════════════════════════════════════════════════ */
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        // Sur les étapes 0-2, Enter ne doit pas soumettre — avancer à la place
        if (currentStep < STEPS.length - 1) {
            handleNext();
            return;
        }
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length > 0) return;

        setSubmitting(true);
        try {
            // 1. Vérification doublons
            const checkData = {
                numero_aej: beneficiaire.numero_aej,
                num_piece: piecePrefix + pieceNumberDisplay,
                contact_stage_full: `${beneficiaire.telephone_principal || ''} ${beneficiaire.telephone_secondaire || ''}`,
                doublon_full: `${beneficiaire.nom || ''} ${beneficiaire.prenoms || ''}`,
                doublon_yup: beneficiaire.numero_tresor_money,
                doublon_wave: beneficiaire.numero_wave,
            };

            let hasDoublon = false;
            try {
                const checkResp = await fetch('/inscriptions/check-doublon', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(checkData),
                });
                if (checkResp.ok) {
                    const checkResult = await checkResp.json();
                    if (checkResult.has_doublon) {
                        const confirmed = window.confirm(
                            `Des doublons ont été détectés sur les champs suivants : ${checkResult.types?.join(', ') || 'plusieurs champs'}.\n\nVoulez-vous quand même enregistrer ce stagiaire ?`
                        );
                        if (!confirmed) {
                            setSubmitting(false);
                            return;
                        }
                        hasDoublon = true;
                    }
                }
            } catch {
                // Si l'API doublon n'existe pas encore, on continue
            }

            // 2. Soumission
            const formData = new FormData();
            Object.entries(beneficiaire).forEach(([k, v]) => { if (v) formData.append(`beneficiaire[${k}]`, String(v)); });
            Object.entries(stage).forEach(([k, v]) => {
                if (v !== '' && v !== 0) formData.append(`stage[${k}]`, String(v));
            });
            formData.append('contrat[date_debut]', stage.date_debut || todayString());
            formData.append('contrat[date_fin]', stage.date_fin_prevue || '');

            Object.entries(documents).forEach(([k, v]) => {
                if (v instanceof File) formData.append(`documents[${k}]`, v);
            });

            const resp = await fetch('/inscriptions', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' },
            });

            if (resp.redirected || resp.ok) {
                window.location.href = '/cip/mes-stagiaires';
            } else {
                const text = await resp.text();
                try {
                    const json = JSON.parse(text);
                    setErrors(json.errors || { _form: json.message || 'Erreur serveur' });
                } catch {
                    setErrors({ _form: 'Erreur serveur inattendue' });
                }
            }
        } catch {
            setErrors({ _form: 'Erreur de connexion' });
        } finally {
            setSubmitting(false);
        }
    };

    const fieldError = (f: string) => errors[f] || '';

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER
       ═══════════════════════════════════════════════════════════════════════ */
    const canSubmit = auth?.user?.type_user_id === 1 || auth?.user?.type_user_id === 17;

    return (
        <React.Fragment>
            <Head title="Nouveau Stagiaire" />
            <style>{`
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                @keyframes pulse {
                    0%, 100% {
                        transform: scale(1);
                    }
                    50% {
                        transform: scale(1.05);
                    }
                }

                .wizard-step-enter {
                    animation: fadeInUp 0.4s ease-in-out;
                }

                .stepper-circle:hover {
                    animation: pulse 0.6s ease-in-out;
                }
            `}</style>
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouveau Stagiaire" pageTitle="CIP" />

                    {flash?.success && <Alert color="success" className="border-0"><i className="ri-check-double-line me-2" />{flash.success}</Alert>}
                    {flash?.error && <Alert color="danger" className="border-0"><i className="ri-error-warning-line me-2" />{flash.error}</Alert>}
                    {errors._form && <Alert color="danger" className="border-0"><i className="ri-error-warning-line me-2" />{errors._form}</Alert>}

                    {/* ═══ STEPPER WIZARD ═══ */}
                    <div className="d-flex justify-content-center mb-4">
                        <div className="d-flex align-items-center" style={{ gap: 0 }}>
                            {STEPS.map((step, idx) => (
                                <React.Fragment key={step.key}>
                                    {/* Cercle étape */}
                                    <div
                                        onClick={() => handleStepClick(idx)}
                                        className="text-center" style={{ cursor: idx <= currentStep ? 'pointer' : 'default' }}>
                                        <div
                                            className="stepper-circle rounded-circle d-inline-flex align-items-center justify-content-center"
                                            style={{
                                                width: 56, height: 56,
                                                backgroundColor: idx < currentStep ? '#198754' : idx === currentStep ? '#dc3545' : '#e9ecef',
                                                color: idx <= currentStep ? '#fff' : '#6c757d',
                                                transition: 'all 0.3s ease',
                                                boxShadow: idx === currentStep ? '0 0 0 4px rgba(220,53,69,0.25)' : idx < currentStep ? '0 2px 8px rgba(25,135,84,0.3)' : 'none',
                                                border: idx === currentStep ? '3px solid #fff' : 'none',
                                            }}>
                                            {idx < currentStep ? (
                                                <i className="ri-check-line" style={{ fontSize: 24, fontWeight: 'bold' }} />
                                            ) : (
                                                <i className={step.icon} style={{ fontSize: 22 }} />
                                            )}
                                        </div>
                                        <div className="mt-2" style={{
                                            fontSize: 13, fontWeight: idx === currentStep ? 700 : 500,
                                            color: idx === currentStep ? '#dc3545' : idx < currentStep ? '#198754' : '#6c757d',
                                            whiteSpace: 'nowrap',
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.5px',
                                        }}>
                                            {step.label}
                                        </div>
                                        {getStepErrorCount(idx) > 0 && idx !== currentStep && (
                                            <Badge color="danger" pill className="mt-1" style={{ fontSize: 10, fontWeight: 600 }}>
                                                {getStepErrorCount(idx)} {getStepErrorCount(idx) === 1 ? 'erreur' : 'erreurs'}
                                            </Badge>
                                        )}
                                    </div>
                                    {/* Ligne connexion */}
                                    {idx < STEPS.length - 1 && (
                                        <div style={{
                                            width: 80, height: 3, margin: '0 8px', marginBottom: 20,
                                            backgroundColor: idx < currentStep ? '#198754' : '#e9ecef',
                                            transition: 'background-color 0.3s ease',
                                            borderRadius: 2,
                                        }} />
                                    )}
                                </React.Fragment>
                            ))}
                        </div>
                    </div>

                    {/* ═══ BARRE PROGRESSION ═══ */}
                    <div className="mb-4">
                        <div className="d-flex justify-content-between align-items-center mb-2">
                            <div className="d-flex align-items-center gap-2">
                                <Badge color="danger" pill style={{ fontSize: 13, fontWeight: 600, padding: '6px 12px' }}>
                                    Étape {currentStep + 1}/{STEPS.length}
                                </Badge>
                                <span className="text-muted fw-semibold">{STEPS[currentStep].label}</span>
                            </div>
                            {Object.keys(errors).length > 0 && currentStep < STEPS.length - 1 && (
                                <small className="text-danger fw-semibold">
                                    <i className="ri-error-warning-line me-1" />
                                    {Object.keys(errors).length} {Object.keys(errors).length === 1 ? 'erreur détectée' : 'erreurs détectées'}
                                </small>
                            )}
                        </div>
                        <div className="progress" style={{ height: 8, borderRadius: 10 }}>
                            <div
                                className="progress-bar bg-gradient-danger"
                                role="progressbar"
                                style={{
                                    width: `${((currentStep + 1) / STEPS.length) * 100}%`,
                                    transition: 'width 0.5s cubic-bezier(0.4, 0, 0.2, 1)',
                                    borderRadius: 10,
                                    boxShadow: '0 2px 4px rgba(220,53,69,0.3)',
                                }}
                                aria-valuenow={(currentStep + 1) / STEPS.length * 100}
                                aria-valuemin={0}
                                aria-valuemax={100}
                            />
                        </div>
                    </div>

                    <Form onSubmit={handleSubmit} encType="multipart/form-data" id="createStagiaireForm">
                        <Row className="g-3">

                            {/* ═══ ÉTAPE 0 : AGENCE REGIONALE ═══ */}
                            {currentStep === 0 && (
                                <Col lg={12} style={{ animation: 'fadeInUp 0.4s ease-in-out' }}>
                                    <Card className="shadow-sm border-0">
                                        <CardHeader className="bg-danger-subtle">
                                            <h5 className="card-title mb-0 text-danger fw-bold">
                                                <i className="ri-building-2-line me-1" />AGENCE REGIONALE
                                            </h5>
                                        </CardHeader>
                                        <CardBody>
                                            <Row className="g-3">
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Agence <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.agence_id}
                                                        options={agences
                                                            .filter(a => authUserAgenceIds.length === 0 || authUserAgenceIds.includes(a.id))
                                                            .map(a => ({ value: String(a.id), label: a.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, agence_id: v, conseiller_id: '', entreprise_id: '' }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('agence_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('agence_id')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Conseiller référent <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.conseiller_id}
                                                        options={conseillers
                                                            .filter(c => stage.agence_id && c.agence_id === Number(stage.agence_id))
                                                            .map(c => ({ value: String(c.id), label: c.nom_complet }))}
                                                        onChange={v => setStage(s => ({ ...s, conseiller_id: v }))}
                                                        placeholder={stage.agence_id ? 'Sélectionner un conseiller' : 'Sélectionner agence d\'abord'}
                                                        isDisabled={!stage.agence_id}
                                                        className={fieldError('conseiller_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('conseiller_id')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Origine du stagiaire <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.origine_stagiaire_id}
                                                        options={originesStagiaire.map(o => ({ value: String(o.id), label: o.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, origine_stagiaire_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('origine_stagiaire_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('origine_stagiaire_id')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Source de financement <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.source_financement_id}
                                                        options={sourcesFinancement.map(s => ({ value: String(s.id), label: s.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, source_financement_id: v }))}
                                                        placeholder="Sélectionner"
                                                        isDisabled={isPEJEDEC}
                                                        className={fieldError('source_financement_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('source_financement_id')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Type de stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.type_stage_id}
                                                        options={filteredTypesStage.map(ts => ({ value: String(ts.id), label: ts.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, type_stage_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('type_stage_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('type_stage_id')}</div>
                                                </Col>
                                                {showTypeStructure && (
                                                    <Col lg={3}>
                                                        <Label className="fw-semibold">Type de structure <span className="text-danger">*</span></Label>
                                                        <RsSelect
                                                            value={stage.type_structure_id}
                                                            options={(typesStructure || []).map(ts => ({ value: String(ts.id), label: ts.nom }))}
                                                            onChange={v => setStage(s => ({ ...s, type_structure_id: v }))}
                                                            placeholder="Sélectionner"
                                                            className={fieldError('type_structure_id') ? 'is-invalid' : ''}
                                                        />
                                                        <div className="invalid-feedback">{fieldError('type_structure_id')}</div>
                                                    </Col>
                                                )}
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Durée prévisionnelle du stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.duree_stage}
                                                        options={durationOptions.map(d => ({ value: d.value, label: d.label }))}
                                                        onChange={v => setStage(s => ({ ...s, duree_stage: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('duree_stage') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('duree_stage')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Date d'entrée dans le portefeuille</Label>
                                                    <Input type="date" value={stage.date_entree_portefeuille}
                                                        onChange={e => setStage(s => ({ ...s, date_entree_portefeuille: e.target.value }))} />
                                                </Col>
                                            </Row>
                                        </CardBody>
                                    </Card>
                                </Col>
                            )}

                            {/* ═══ ÉTAPE 1 : IDENTIFICATION STAGIAIRE ═══ */}
                            {currentStep === 1 && (
                                <Col lg={6} style={{ animation: 'fadeInUp 0.4s ease-in-out' }}>
                                    <Card className="shadow-sm border-0">
                                        <CardHeader className="bg-danger-subtle">
                                            <h5 className="card-title mb-0 text-danger fw-bold">
                                                <i className="ri-user-3-line me-1" />IDENTIFICATION STAGIAIRE
                                            </h5>
                                        </CardHeader>
                                        <CardBody>
                                            <Row className="g-3">
                                                {/* Offre fields (AEJ/PEJEDEC) */}
                                                {showOffre && (
                                                    <>
                                                        <Col lg={12}>
                                                            <Label className="fw-semibold">
                                                                Numéro de l'offre {isAEJ && <span className="text-danger">*</span>}
                                                            </Label>
                                                            <Select
                                                                options={offres
                                                                    .filter(o => !stage.agence_id || o.agence_id === Number(stage.agence_id))
                                                                    .map(o => ({
                                                                        value: o.id,
                                                                        label: `${o.numero} - ${o.intitule} (${o.entreprise?.raison_sociale})`,
                                                                        offre: o,
                                                                    }))}
                                                                onChange={handleOffreChange}
                                                                placeholder="Selectionner Numéro de l'offre du stage"
                                                            />
                                                        </Col>
                                                        {showOffreDetail && (
                                                            <>
                                                                <Col lg={6}>
                                                                    <Label className="fw-semibold">Intitulé de l'offre</Label>
                                                                    <Input type="text" value={stage.intitule_offre} readOnly />
                                                                </Col>
                                                                <Col lg={6}>
                                                                    <Label className="fw-semibold">Nombre de place</Label>
                                                                    <Input type="number" value={stage.nombre_de_place} readOnly />
                                                                </Col>
                                                            </>
                                                        )}
                                                    </>
                                                )}

                                                {/* Numéro AEJ / Matricule */}
                                                <Col lg={12}>
                                                    <EnhancedLabel
                                                        label={isFinancementC2d ? 'Matricule' : 'Numéro AEJ'}
                                                        required
                                                        tooltipId="numero-aej"
                                                        tooltipContent="Numéro d'inscription à l'Agence pour l'Emploi des Jeunes (AEJ). Doit contenir entre 10 et 14 caractères."
                                                    />
                                                    <Input type="text"
                                                        className={fieldError('numero_aej') || demandeurError ? 'is-invalid' : ''}
                                                        value={beneficiaire.numero_aej}
                                                        onChange={e => {
                                                            setBeneficiaire(b => ({ ...b, numero_aej: e.target.value }));
                                                            setDemandeurLoaded(false);
                                                            setDemandeurError('');
                                                        }}
                                                        readOnly={demandeurLoaded}
                                                        minLength={10} maxLength={14}
                                                        placeholder="Numero AEJ/Téléphone"
                                                        autoComplete="off" />
                                                    <div className="invalid-feedback">{fieldError('numero_aej') || demandeurError}</div>
                                                    <div className="mt-2">
                                                        {!demandeurLoaded ? (
                                                            <Button color="info" type="button" size="sm"
                                                                disabled={demandeurLoading || beneficiaire.numero_aej.length < 10}
                                                                onClick={handleLoadDemandeur}>
                                                                {demandeurLoading ? <><Spinner size="sm" className="me-1" />Chargement...</> : 'Vérifier'}
                                                            </Button>
                                                        ) : (
                                                            <Button color="success" type="button" size="sm" onClick={handleRestart}>
                                                                <i className="ri-repeat-line me-1" />Réinitialiser
                                                            </Button>
                                                        )}
                                                    </div>
                                                </Col>

                                                {/* Nom / Prénoms */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Nom <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('nom') ? 'is-invalid' : ''}
                                                        value={beneficiaire.nom}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, nom: e.target.value.toUpperCase().replace(/[^A-Z\s\-']/g, '') }))}
                                                        readOnly={demandeurLoaded} autoComplete="off" />
                                                    <div className="invalid-feedback">{fieldError('nom')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Prénoms <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('prenoms') ? 'is-invalid' : ''}
                                                        value={beneficiaire.prenoms}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, prenoms: e.target.value.toUpperCase().replace(/[^A-Z\s\-']/g, '') }))}
                                                        readOnly={demandeurLoaded} autoComplete="off" />
                                                    <div className="invalid-feedback">{fieldError('prenoms')}</div>
                                                </Col>

                                                {/* Lieu + Date naissance */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Lieu de naissance <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('lieu_naissance') ? 'is-invalid' : ''}
                                                        value={beneficiaire.lieu_naissance}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, lieu_naissance: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('lieu_naissance')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Date de naissance <span className="text-danger">*</span></Label>
                                                    <Input type="date"
                                                        className={fieldError('date_naissance') ? 'is-invalid' : ''}
                                                        value={beneficiaire.date_naissance}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, date_naissance: e.target.value }))}
                                                        readOnly={demandeurLoaded} max={todayString()} />
                                                    {age > 0 && <small className="text-muted">{age} ans</small>}
                                                    <div className="invalid-feedback">{fieldError('date_naissance')}</div>
                                                </Col>

                                                {/* Sexe + Sous-pref nais */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Sexe <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.sexe}
                                                        options={[{ value: 'Femme', label: 'Femme' }, { value: 'Homme', label: 'Homme' }]}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, sexe: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('sexe') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('sexe')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Sous-préfecture de naissance <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.sous_prefecture_naissance}
                                                        options={[
                                                            ...communes.map(c => ({ value: c.nom, label: c.nom })),
                                                            { value: 'autre', label: 'Autre' },
                                                        ]}
                                                        onChange={v => setBeneficiaire(b => ({
                                                            ...b,
                                                            sous_prefecture_naissance: v,
                                                            custom_sous_prefecture: '',
                                                        }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('sous_prefecture_naissance') ? 'is-invalid' : ''}
                                                    />
                                                    {beneficiaire.sous_prefecture_naissance === 'autre' && (
                                                        <Input type="text" className="mt-2" placeholder="Entrez la sous-préfecture"
                                                            value={beneficiaire.custom_sous_prefecture}
                                                            onChange={e => setBeneficiaire(b => ({ ...b, custom_sous_prefecture: e.target.value.toUpperCase() }))} />
                                                    )}
                                                    <div className="invalid-feedback">{fieldError('sous_prefecture_naissance')}</div>
                                                </Col>

                                                {/* Commune + SP résidence */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Commune de résidence <span className="text-danger">*</span></Label>
                                                    <Input type="text"
                                                        className={fieldError('commune_residence_text') ? 'is-invalid' : ''}
                                                        value={beneficiaire.commune_residence_text}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, commune_residence_text: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('commune_residence_text')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">S/P de résidence <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.sous_prefecture_residence}
                                                        options={communes.map(c => ({ value: c.nom, label: c.nom }))}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, sous_prefecture_residence: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('sous_prefecture_residence') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('sous_prefecture_residence')}</div>
                                                </Col>

                                                {/* Nature + Numéro pièce */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Nature pièce d'identité <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.nature_piece_identite}
                                                        options={TYPE_PIECE_OPTIONS.map(p => ({ value: p.value, label: p.label }))}
                                                        onChange={v => setBeneficiaire(b => ({
                                                            ...b,
                                                            nature_piece_identite: v,
                                                            numero_piece_identite: '',
                                                        }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('nature_piece_identite') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('nature_piece_identite')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Numéro pièce d'identité <span className="text-danger">*</span></Label>
                                                    <div className="input-group">
                                                        {piecePrefix && <span className="input-group-text">{piecePrefix}</span>}
                                                        <Input type="text"
                                                            className={fieldError('numero_piece_identite') ? 'is-invalid' : ''}
                                                            value={pieceNumberDisplay}
                                                            maxLength={pieceMaxLength}
                                                            onChange={e => setBeneficiaire(b => ({
                                                                ...b,
                                                                numero_piece_identite: `${piecePrefix}${e.target.value.replace(/\s/g, '').toUpperCase()}`,
                                                            }))} />
                                                    </div>
                                                    <div className="invalid-feedback">{fieldError('numero_piece_identite')}</div>
                                                </Col>

                                                {/* Numéro CMU */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Numéro CMU <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('numero_cmu') ? 'is-invalid' : ''}
                                                        value={beneficiaire.numero_cmu}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, numero_cmu: e.target.value.toUpperCase() }))}
                                                        maxLength={50} autoComplete="off" />
                                                    <div className="invalid-feedback">{fieldError('numero_cmu')}</div>
                                                </Col>

                                                {/* Séparateur Contacts */}
                                                <Col lg={12}>
                                                    <hr className="my-3" style={{ borderTop: '2px solid #e9ecef' }} />
                                                    <h6 className="text-muted fw-semibold mb-3">
                                                        <i className="ri-phone-line me-2" />Coordonnées & Contact
                                                    </h6>
                                                </Col>

                                                {/* Contacts */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Contact téléphonique 1 <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('telephone_principal') ? 'is-invalid' : ''}
                                                        value={beneficiaire.telephone_principal}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, telephone_principal: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                        maxLength={10} />
                                                    <div className="invalid-feedback">{fieldError('telephone_principal')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Contact téléphonique 2</Label>
                                                    <Input type="text"
                                                        value={beneficiaire.telephone_secondaire}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, telephone_secondaire: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                        maxLength={10} />
                                                </Col>

                                                {/* Séparateur Urgence */}
                                                <Col lg={12}>
                                                    <hr className="my-3" style={{ borderTop: '2px solid #e9ecef' }} />
                                                    <h6 className="text-muted fw-semibold mb-3">
                                                        <i className="ri-alarm-warning-line me-2" />Contact d'urgence
                                                    </h6>
                                                </Col>

                                                {/* Urgence */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Personne à contacter en cas d'urgence <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('personne_urgence') ? 'is-invalid' : ''}
                                                        value={beneficiaire.personne_urgence}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, personne_urgence: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('personne_urgence')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Lien de parenté <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.lien_parente_id}
                                                        options={liensParente.map(l => ({ value: String(l.id), label: l.nom }))}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, lien_parente_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('lien_parente_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('lien_parente_id')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Contact téléphonique 1 du parent <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('contact_urgence_1') ? 'is-invalid' : ''}
                                                        value={beneficiaire.contact_urgence_1}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, contact_urgence_1: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                        maxLength={10} />
                                                    <div className="invalid-feedback">{fieldError('contact_urgence_1')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Contact téléphonique 2 du parent</Label>
                                                    <Input type="text"
                                                        value={beneficiaire.contact_urgence_2}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, contact_urgence_2: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                        maxLength={10} />
                                                </Col>

                                                {/* Séparateur Formation */}
                                                <Col lg={12}>
                                                    <hr className="my-3" style={{ borderTop: '2px solid #e9ecef' }} />
                                                    <h6 className="text-muted fw-semibold mb-3">
                                                        <i className="ri-graduation-cap-line me-2" />Formation & Diplôme
                                                    </h6>
                                                </Col>

                                                {/* Formation */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Niveau d'études <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.niveau_etude_id}
                                                        options={niveauxEtude.map(n => ({ value: String(n.id), label: n.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, niveau_etude_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('niveau_etude_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('niveau_etude_id')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Diplôme <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.diplome_id}
                                                        options={filteredDiplomes.map(d => ({ value: String(d.id), label: d.nom }))}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, diplome_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('diplome_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('diplome_id')}</div>
                                                </Col>
                                                {isDiplomeAutre && (
                                                    <Col lg={6}>
                                                        <Label className="fw-semibold">Préciser Diplôme <span className="text-danger">*</span></Label>
                                                        <Input type="text" className={fieldError('autre_diplome') ? 'is-invalid' : ''}
                                                            value={beneficiaire.autre_diplome}
                                                            onChange={e => setBeneficiaire(b => ({ ...b, autre_diplome: e.target.value }))} />
                                                        <div className="invalid-feedback">{fieldError('autre_diplome')}</div>
                                                    </Col>
                                                )}

                                                {/* Spécialité + Année */}
                                                <Col lg={9}>
                                                    <Label className="fw-semibold">Spécialité <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('specialite') ? 'is-invalid' : ''}
                                                        value={beneficiaire.specialite}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, specialite: e.target.value.toUpperCase() }))} />
                                                    <Alert color="info" className="mt-2 py-2" style={{ borderLeft: '4px solid #17a2b8' }}>
                                                        <small>Ce champ doit correspondre à la spécialité du diplôme sélectionné.</small>
                                                    </Alert>
                                                    <div className="invalid-feedback">{fieldError('specialite')}</div>
                                                </Col>
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Année du diplôme {isNiveauAucun ? '' : <span className="text-danger">*</span>}</Label>
                                                    <Input type="text" className={fieldError('annee_diplome') ? 'is-invalid' : ''}
                                                        value={beneficiaire.annee_diplome}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, annee_diplome: e.target.value.replace(/[^0-9]/g, '') }))}
                                                        maxLength={4} readOnly={isNiveauAucun} />
                                                    <div className="invalid-feedback">{fieldError('annee_diplome')}</div>
                                                </Col>

                                                {/* Établissement + Type enseignement */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Établissement fréquenté</Label>
                                                    <Input type="text" value={beneficiaire.etablissement_frequente}
                                                        onChange={e => setBeneficiaire(b => ({ ...b, etablissement_frequente: e.target.value.toUpperCase() }))} />
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Type d'enseignement <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.type_enseignement_id}
                                                        options={typesEnseignement.map(t => ({ value: String(t.id), label: t.nom }))}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, type_enseignement_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('type_enseignement_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('type_enseignement_id')}</div>
                                                </Col>

                                                {/* Handicap */}
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Handicap <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.handicap_id}
                                                        options={HANDICAP_OPTIONS.map(h => ({ value: h.value, label: h.label }))}
                                                        onChange={v => setBeneficiaire(b => ({
                                                            ...b,
                                                            handicap_id: v,
                                                            type_handicap_id: '',
                                                            autre_handicap: '',
                                                        }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('handicap_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('handicap_id')}</div>
                                                </Col>
                                                {beneficiaire.handicap_id === 'HANDICAP' && (
                                                    <>
                                                        <Col lg={4}>
                                                            <Label className="fw-semibold">Type handicap <span className="text-danger">*</span></Label>
                                                            <RsSelect
                                                                value={beneficiaire.type_handicap_id}
                                                                options={TYPE_HANDICAP_OPTIONS.map(h => ({ value: h.value, label: h.label }))}
                                                                onChange={v => setBeneficiaire(b => ({
                                                                    ...b,
                                                                    type_handicap_id: v,
                                                                    autre_handicap: '',
                                                                }))}
                                                                placeholder="Sélectionner"
                                                                className={fieldError('type_handicap_id') ? 'is-invalid' : ''}
                                                            />
                                                            <div className="invalid-feedback">{fieldError('type_handicap_id')}</div>
                                                        </Col>
                                                        {beneficiaire.type_handicap_id === 'AUTRE' && (
                                                            <Col lg={4}>
                                                                <Label className="fw-semibold">Autre handicap <span className="text-danger">*</span></Label>
                                                                <Input type="text"
                                                                    className={fieldError('autre_handicap') ? 'is-invalid' : ''}
                                                                    value={beneficiaire.autre_handicap}
                                                                    onChange={e => setBeneficiaire(b => ({ ...b, autre_handicap: e.target.value.toUpperCase().replace(/[^A-Z\s\-']/g, '') }))} />
                                                                <div className="invalid-feedback">{fieldError('autre_handicap')}</div>
                                                            </Col>
                                                        )}
                                                    </>
                                                )}
                                            </Row>
                                        </CardBody>
                                    </Card>
                                </Col>
                            )}

                            {/* ═══ ÉTAPE 2 : MISE EN STAGE ═══ */}
                            {currentStep === 2 && (
                                <Col lg={6} style={{ animation: 'fadeInUp 0.4s ease-in-out' }}>
                                    <Card className="shadow-sm border-0">
                                        <CardHeader className="bg-danger-subtle">
                                            <h5 className="card-title mb-0 text-danger fw-bold">
                                                <i className="ri-briefcase-line me-1" />MISE EN STAGE
                                            </h5>
                                        </CardHeader>
                                        <CardBody>
                                            <Row className="g-3">
                                                <Col lg={8}>
                                                    <Label className="fw-semibold">Nom de l'entreprise <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.entreprise_id}
                                                        options={entrepriseOptions.map(o => ({ value: String(o.value), label: o.label }))}
                                                        onChange={v => setStage(s => ({ ...s, entreprise_id: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('entreprise_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('entreprise_id')}</div>
                                                </Col>
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Sigle entreprise</Label>
                                                    <Input type="text" value={stage.sigle_entreprise} readOnly />
                                                </Col>

                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Service d'affectation <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('service_affectation') ? 'is-invalid' : ''}
                                                        value={stage.service_affectation}
                                                        onChange={e => setStage(s => ({ ...s, service_affectation: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('service_affectation')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Intitulé du poste de stage</Label>
                                                    <Input type="text" value={stage.intitule_poste}
                                                        onChange={e => setStage(s => ({ ...s, intitule_poste: e.target.value.toUpperCase() }))} />
                                                </Col>

                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Localité / Lieu de stage <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('localite_stage') ? 'is-invalid' : ''}
                                                        value={stage.localite_stage}
                                                        onChange={e => setStage(s => ({ ...s, localite_stage: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('localite_stage')}</div>
                                                </Col>
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Commune du lieu de stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.commune_stage}
                                                        options={communes.map(c => ({ value: c.nom, label: c.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, commune_stage: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('commune_stage') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('commune_stage')}</div>
                                                </Col>
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">S/P du lieu de stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.sous_prefecture_stage}
                                                        options={communes.map(c => ({ value: c.nom, label: c.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, sous_prefecture_stage: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('sous_prefecture_stage') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('sous_prefecture_stage')}</div>
                                                </Col>

                                                <Col lg={12}>
                                                    <Label className="fw-semibold">Nom et prénom de l'encadreur <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('nom_encadreur') ? 'is-invalid' : ''}
                                                        value={stage.nom_encadreur}
                                                        onChange={e => setStage(s => ({ ...s, nom_encadreur: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('nom_encadreur')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Fonction de l'encadreur <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('fonction_encadreur') ? 'is-invalid' : ''}
                                                        value={stage.fonction_encadreur}
                                                        onChange={e => setStage(s => ({ ...s, fonction_encadreur: e.target.value.toUpperCase() }))} />
                                                    <div className="invalid-feedback">{fieldError('fonction_encadreur')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Numéro de l'encadreur <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('contact_encadreur') ? 'is-invalid' : ''}
                                                        value={stage.contact_encadreur}
                                                        onChange={e => setStage(s => ({ ...s, contact_encadreur: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                        maxLength={10} />
                                                    <div className="invalid-feedback">{fieldError('contact_encadreur')}</div>
                                                </Col>

                                                {/* Statut + Situation */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Statut stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.statut_stage}
                                                        options={(statutsStage || []).map(ss => ({ value: String(ss.id), label: ss.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, statut_stage: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('statut_stage') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('statut_stage')}</div>
                                                </Col>
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Situation stage <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={stage.situation_stage}
                                                        options={(situationsStage || []).map(ss => ({ value: String(ss.id), label: ss.nom }))}
                                                        onChange={v => setStage(s => ({ ...s, situation_stage: v }))}
                                                        placeholder="Sélectionner"
                                                        className={fieldError('situation_stage') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('situation_stage')}</div>
                                                </Col>

                                                {/* Dates */}
                                                {showDateDebut && (
                                                    <Col lg={6}>
                                                        <EnhancedLabel
                                                            label="Date de début de stage"
                                                            required
                                                            tooltipId="date-debut"
                                                            tooltipContent="Doit être une date de cohorte valide : 1 à 5, 10 ou 20 du mois. Ne peut pas être dans le futur ni antérieure à 5 ans."
                                                        />
                                                        <Input type="date"
                                                            className={fieldError('date_debut') ? 'is-invalid' : ''}
                                                            value={stage.date_debut}
                                                            onChange={e => setStage(s => ({ ...s, date_debut: e.target.value }))}
                                                            max={todayString()} />
                                                        <div className="invalid-feedback">{fieldError('date_debut')}</div>
                                                    </Col>
                                                )}
                                                {showCapitalisationPejedec && (
                                                    <Col lg={6}>
                                                        <EnhancedLabel
                                                            label="Nombre de mois déjà effectués"
                                                            required
                                                            tooltipId="nbr-mois-capitaliser"
                                                            tooltipContent="Nombre de mois de stage déjà effectués dans une structure antérieure. Doit être inférieur à la durée totale du stage."
                                                        />
                                                        <Input type="number" min={0}
                                                            className={fieldError('nbr_mois_capitaliser') ? 'is-invalid' : ''}
                                                            value={stage.nbr_mois_capitaliser}
                                                            onChange={e => setStage(s => ({ ...s, nbr_mois_capitaliser: parseInt(e.target.value) || 0 }))} />
                                                        <div className="invalid-feedback">{fieldError('nbr_mois_capitaliser')}</div>
                                                    </Col>
                                                )}
                                                {showCapitalisationPejedec && (
                                                    <Col lg={6}>
                                                        <EnhancedLabel
                                                            label="Date démarrage capitalisation"
                                                            tooltipId="date-capitalisation"
                                                            tooltipContent="Date calculée automatiquement en fonction de la date de début et du nombre de mois à capitaliser."
                                                        />
                                                        <Input type="date" value={stage.date_demarrage_capitalisation} readOnly
                                                            className={fieldError('date_demarrage_capitalisation') ? 'is-invalid' : ''} />
                                                        <div className="invalid-feedback">{fieldError('date_demarrage_capitalisation')}</div>
                                                        <small className="text-muted">Calculée automatiquement</small>
                                                    </Col>
                                                )}
                                                {showCapitalisationSansFinanciere && (
                                                    <Col lg={6}>
                                                        <EnhancedLabel
                                                            label="Date démarrage capitalisation sans incidence financière"
                                                            required
                                                            tooltipId="date-capitalisation-sans"
                                                            tooltipContent="Pour les stages spontanés ou DAICG : date de début de la capitalisation sans compensation financière."
                                                        />
                                                        <Input type="date"
                                                            className={fieldError('date_demarrage_capitalisation_sans_financiere') ? 'is-invalid' : ''}
                                                            value={stage.date_demarrage_capitalisation_sans_financiere}
                                                            onChange={e => setStage(s => ({ ...s, date_demarrage_capitalisation_sans_financiere: e.target.value }))} />
                                                        <div className="invalid-feedback">{fieldError('date_demarrage_capitalisation_sans_financiere')}</div>
                                                    </Col>
                                                )}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Date de fin prévisionnelle <span className="text-danger">*</span></Label>
                                                    <Input type="date"
                                                        className={fieldError('date_fin_prevue') ? 'is-invalid' : ''}
                                                        value={stage.date_fin_prevue} readOnly />
                                                    <div className="invalid-feedback">{fieldError('date_fin_prevue')}</div>
                                                    <small className="text-muted">Calculée automatiquement</small>
                                                </Col>

                                                <Col lg={12}>
                                                    <Alert color="success" className="py-2" style={{ borderLeft: '4px solid #28a745' }}>
                                                        <small className="text-success">
                                                            <strong>Exemple :</strong> cohorte 1, les jours indiqués seront : 1, 2, 3, 4, 5 ; cohorte 2 : le 10 ; cohorte 3 : le 20
                                                        </small>
                                                    </Alert>
                                                </Col>
                                            </Row>
                                        </CardBody>
                                    </Card>
                                </Col>
                            )}

                            {/* ═══ ÉTAPE 3 : PIECES JUSTIFICATIVES ═══ */}
                            {currentStep === 3 && (
                                <Col lg={6} style={{ animation: 'fadeInUp 0.4s ease-in-out' }}>
                                    <Card className="shadow-sm border-0">
                                        <CardHeader className="bg-danger-subtle">
                                            <h5 className="card-title mb-0 text-danger fw-bold">
                                                <i className="ri-file-upload-line me-1" />PIECES JUSTIFICATIVES
                                            </h5>
                                        </CardHeader>
                                        <CardBody>
                                            <Row className="g-3">
                                                <Col lg={12}>
                                                    <Label className="fw-semibold">Fichier CMU <span className="text-danger">*</span></Label>
                                                    <Input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                                        className={fieldError('fichier_cmu') ? 'is-invalid' : ''}
                                                        onChange={e => setDocuments(d => ({ ...d, fichier_cmu: e.target.files?.[0] || null }))} />
                                                    <div className="invalid-feedback">{fieldError('fichier_cmu')}</div>
                                                </Col>

                                                <Col lg={12}>
                                                    <Label className="fw-semibold">Pièce d'identité <span className="text-danger">*</span></Label>
                                                    <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                        className={fieldError('piece_identite') ? 'is-invalid' : ''}
                                                        onChange={e => setDocuments(d => ({ ...d, piece_identite: e.target.files?.[0] || null }))} />
                                                    <div className="invalid-feedback">{fieldError('piece_identite')}</div>
                                                </Col>

                                                {/* Fiche RIB (optionnel, comme legacy) */}
                                                <Col lg={12}>
                                                    <Label className="fw-semibold">Fiche RIB</Label>
                                                    <Input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                                        onChange={e => setDocuments(d => ({ ...d, fichier_rib: e.target.files?.[0] || null }))} />
                                                </Col>

                                                {/* Attestation + Certificat (ECOLE) */}
                                                {isStageEcole && (
                                                    <>
                                                        <Col lg={12}>
                                                            <Label className="fw-semibold">Attestation d'admissibilité <span className="text-danger">*</span></Label>
                                                            <Input type="file" accept=".pdf,.doc,.docx"
                                                                className={fieldError('fichier_attestation') ? 'is-invalid' : ''}
                                                                onChange={e => setDocuments(d => ({ ...d, fichier_attestation: e.target.files?.[0] || null }))} />
                                                            <div className="invalid-feedback">{fieldError('fichier_attestation')}</div>
                                                        </Col>
                                                        <Col lg={12}>
                                                            <Label className="fw-semibold">Certificat de fréquentation <span className="text-danger">*</span></Label>
                                                            <Input type="file" accept=".pdf,.doc,.docx"
                                                                className={fieldError('fichier_certificat_frequentation') ? 'is-invalid' : ''}
                                                                onChange={e => setDocuments(d => ({ ...d, fichier_certificat_frequentation: e.target.files?.[0] || null }))} />
                                                            <div className="invalid-feedback">{fieldError('fichier_certificat_frequentation')}</div>
                                                        </Col>
                                                    </>
                                                )}

                                                {/* Diplôme (QUALIFICATION) */}
                                                {isStageQualification && (
                                                    <Col lg={12}>
                                                        <Label className="fw-semibold">Diplôme <span className="text-danger">*</span></Label>
                                                        <Input type="file" accept=".pdf,.doc,.docx"
                                                            className={fieldError('fichier_diplome') ? 'is-invalid' : ''}
                                                            onChange={e => setDocuments(d => ({ ...d, fichier_diplome: e.target.files?.[0] || null }))} />
                                                        <div className="invalid-feedback">{fieldError('fichier_diplome')}</div>
                                                    </Col>
                                                )}

                                                {/* Type paiement + fichiers */}
                                                <Col lg={6}>
                                                    <Label className="fw-semibold">Type de paiement <span className="text-danger">*</span></Label>
                                                    <RsSelect
                                                        value={beneficiaire.type_paiement_id}
                                                        options={typesPaiement.map(t => ({ value: String(t.id), label: t.nom }))}
                                                        onChange={v => setBeneficiaire(b => ({ ...b, type_paiement_id: v }))}
                                                        placeholder="Sélectionner"
                                                        isDisabled
                                                        className={fieldError('type_paiement_id') ? 'is-invalid' : ''}
                                                    />
                                                    <div className="invalid-feedback">{fieldError('type_paiement_id')}</div>
                                                </Col>

                                                {/* Trésor Money */}
                                                {showTresorMoney && (
                                                    <>
                                                        <Col lg={6}>
                                                            <Label className="fw-semibold">Fiche Trésor Money <span className={requiresYup ? 'text-danger' : ''}>*</span></Label>
                                                            <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                                onChange={e => setDocuments(d => ({ ...d, fiche_tresor_money: e.target.files?.[0] || null }))} />
                                                        </Col>
                                                        <Col lg={6}>
                                                            <Label className="fw-semibold">Numéro Trésor Money <span className={requiresYup ? 'text-danger' : ''}>*</span></Label>
                                                            <Input type="text"
                                                                className={fieldError('numero_tresor_money') ? 'is-invalid' : ''}
                                                                value={beneficiaire.numero_tresor_money}
                                                                onChange={e => setBeneficiaire(b => ({ ...b, numero_tresor_money: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                                maxLength={10} />
                                                            <div className="invalid-feedback">{fieldError('numero_tresor_money')}</div>
                                                        </Col>
                                                    </>
                                                )}

                                                {/* Wave */}
                                                {showWave && (
                                                    <>
                                                        <Col lg={6}>
                                                            <Label className="fw-semibold">Attestation de reconnaissance de numéro Mobile Money <span className="text-danger">*</span></Label>
                                                            <Input type="file" accept=".pdf,.doc,.docx"
                                                                onChange={e => setDocuments(d => ({ ...d, fiche_wave: e.target.files?.[0] || null }))} />
                                                        </Col>
                                                        <Col lg={6}>
                                                            <Label className="fw-semibold">Numéro Wave <span className="text-danger">*</span></Label>
                                                            <Input type="text"
                                                                className={fieldError('numero_wave') ? 'is-invalid' : ''}
                                                                value={beneficiaire.numero_wave}
                                                                onChange={e => setBeneficiaire(b => ({ ...b, numero_wave: e.target.value.replace(/[^0-9]/g, '').slice(0, 10) }))}
                                                                maxLength={10} />
                                                            <div className="invalid-feedback">{fieldError('numero_wave')}</div>
                                                        </Col>
                                                    </>
                                                )}
                                            </Row>
                                        </CardBody>
                                    </Card>
                                </Col>
                            )}

                            {/* ═══ ÉTAPE 4 : RÉCAPITULATIF ═══ */}
                            {currentStep === 4 && (
                                <Col lg={12} style={{ animation: 'fadeInUp 0.4s ease-in-out' }}>
                                    <RecapitulatifInscription
                                        beneficiaire={beneficiaire}
                                        stage={stage}
                                        documents={documents}
                                        refData={{
                                            agences,
                                            communes,
                                            typesStage,
                                            sourcesFinancement,
                                            originesStagiaire,
                                            niveauxEtude,
                                            diplomes,
                                            entreprises,
                                            typesPaiement,
                                        }}
                                    />
                                </Col>
                            )}
                        </Row>

                        {/* ═══ OBSERVATIONS (étapes 2 et 3) ═══ */}
                        {(currentStep === 2 || currentStep === 3) && (
                            <Card className="shadow-sm border-0 mt-3">
                                <CardHeader className="bg-danger-subtle">
                                    <h5 className="card-title mb-0 text-danger fw-bold">OBSERVATIONS</h5>
                                </CardHeader>
                                <CardBody>
                                    <Input type="textarea" rows={3}
                                        className={fieldError('observations') ? 'is-invalid' : ''}
                                        value={stage.observations}
                                        onChange={e => setStage(s => ({ ...s, observations: e.target.value.toUpperCase() }))} />
                                    <div className="invalid-feedback">{fieldError('observations')}</div>
                                </CardBody>
                            </Card>
                        )}

                        {/* ═══ NAVIGATION WIZARD ═══ */}
                        <Card className="shadow-sm border-0 mt-4" style={{ borderTop: '3px solid #dc3545' }}>
                            <CardBody className="p-4">
                                <div className="d-flex justify-content-between align-items-center">
                                    <div>
                                        {currentStep > 0 && (
                                            <Button
                                                color="light"
                                                type="button"
                                                onClick={handlePrev}
                                                size="lg"
                                                className="px-4"
                                                style={{
                                                    border: '2px solid #e9ecef',
                                                    fontWeight: 600,
                                                    transition: 'all 0.3s ease',
                                                }}
                                            >
                                                <i className="ri-arrow-left-line me-2" />Précédent
                                            </Button>
                                        )}
                                    </div>
                                    <div className="d-flex gap-3">
                                        {currentStep < STEPS.length - 1 ? (
                                            <Button
                                                color="danger"
                                                type="button"
                                                onClick={handleNext}
                                                size="lg"
                                                className="px-5"
                                                style={{
                                                    fontWeight: 600,
                                                    boxShadow: '0 4px 12px rgba(220,53,69,0.3)',
                                                    transition: 'all 0.3s ease',
                                                }}
                                            >
                                                Suivant <i className="ri-arrow-right-line ms-2" />
                                            </Button>
                                        ) : canSubmit ? (
                                            <>
                                                <Button
                                                    color="success"
                                                    type="submit"
                                                    disabled={submitting}
                                                    size="lg"
                                                    className="px-5"
                                                    style={{
                                                        fontWeight: 600,
                                                        boxShadow: '0 4px 12px rgba(25,135,84,0.3)',
                                                        transition: 'all 0.3s ease',
                                                    }}
                                                >
                                                    {submitting ? (
                                                        <>
                                                            <Spinner size="sm" className="me-2" />
                                                            Enregistrement...
                                                        </>
                                                    ) : (
                                                        <>
                                                            <i className="ri-save-line me-2" />Enregistrer
                                                        </>
                                                    )}
                                                </Button>
                                                <Button
                                                    color="info"
                                                    type="submit"
                                                    disabled={submitting}
                                                    size="lg"
                                                    className="px-5"
                                                    style={{
                                                        fontWeight: 600,
                                                        boxShadow: '0 4px 12px rgba(13,202,240,0.3)',
                                                        transition: 'all 0.3s ease',
                                                    }}
                                                >
                                                    {submitting ? (
                                                        <>
                                                            <Spinner size="sm" className="me-2" />
                                                            Enregistrement...
                                                        </>
                                                    ) : (
                                                        <>
                                                            <i className="ri-save-3-line me-2" />Enregistrer & Fermer
                                                        </>
                                                    )}
                                                </Button>
                                            </>
                                        ) : null}
                                    </div>
                                </div>
                            </CardBody>
                        </Card>
                    </Form>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Create;
