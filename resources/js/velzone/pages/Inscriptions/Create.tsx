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
} from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

/* ─── Types ─── */
interface RefItem {
    id: number;
    nom: string;
    code?: string;
    raison_sociale?: string;
    agence_id?: number;
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
}

interface DemandeurAej {
    numero_aej?: string;
    nom?: string;
    prenom?: string;
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
}

interface FormErrors {
    [key: string]: string;
}

/* ─── Constantes ─── */
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

const DURATION_OPTIONS = [
    { value: '1', label: '1 mois' },
    { value: '1.5', label: '1.5 mois' },
    { value: '2', label: '2 mois' },
    { value: '3', label: '3 mois' },
    { value: '6', label: '6 mois' },
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

/* ─── Helpers ─── */
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

const getPieceMaxLength = (typePiece: string): number => {
    const map: Record<string, number> = {
        "ATTESTATION D'IDENTITE": 20, AUTRES: 20, "RECIPISSE CNI": 11,
        "CARTE CMU": 13, "CARTE NATIONALE D'IDENTITE (BLANC)": 9,
        "CARTE NATIONALE D'IDENTITE (ORANGE)": 10, PASSEPORT: 10,
    };
    return map[normalizeLabel(typePiece)] ?? 20;
};

const getPiecePrefix = (typePiece: string): string => {
    const n = normalizeLabel(typePiece);
    if (n === 'CARTE NATIONALE D\'IDENTITE (BLANC)') return 'CI';
    if (n === 'CARTE NATIONALE D\'IDENTITE (ORANGE)') return 'C';
    return '';
};

/* ═══════════════════════════════════════════════════════════════════════════
   COMPOSANT
   ═══════════════════════════════════════════════════════════════════════════ */
const Create = ({
    offres, agences, communes, typesStage, originesStagiaire,
    liensParente, niveauxEtude, diplomes, typesEnseignement,
    handicaps, typesHandicap, typesPaiement, sourcesFinancement,
}: Props) => {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;

    /* ─── État formulaire ─── */
    const [beneficiaire, setBeneficiaire] = useState({
        numero_aej: '', nom: '', prenoms: '', sexe: '', date_naissance: '',
        lieu_naissance: '', sous_prefecture_naissance: '', commune_residence_id: '',
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
        entreprise_id: '', date_entree_portefeuille: todayString(),
        service_affectation: '', intitule_poste: '', localite_stage: '',
        commune_stage: '', sous_prefecture_stage: '', nom_encadreur: '',
        fonction_encadreur: '', contact_encadreur: '', statut_stage: '1',
        situation_stage: '1', date_debut: '', date_fin_prevue: '',
        duree_stage: '', nbr_mois_capitaliser: 0,
        date_demarrage_capitalisation: '', date_demarrage_capitalisation_sans_financiere: '',
        observations: '',
    });

    const [contrat, setContrat] = useState({ numero: '', date_debut: '', date_fin: '', prime_mensuelle: '' });
    const [documents, setDocuments] = useState<Record<string, File | null>>({});

    /* ─── UI states ─── */
    const [errors, setErrors] = useState<FormErrors>({});
    const [submitting, setSubmitting] = useState(false);
    const [demandeurLoading, setDemandeurLoading] = useState(false);
    const [demandeurError, setDemandeurError] = useState('');
    const [demandeurLoaded, setDemandeurLoaded] = useState(false);

    /* ─── Derived ─── */
    const origineId = String(stage.origine_stagiaire_id);
    const financementId = String(stage.source_financement_id);

    const isAEJ = origineId === '1';
    const isPEJEDEC = origineId === '2';
    const isSpontaneOuDAICG = origineId === '3' || origineId === '4';
    const isFinancementBMZ = financementId === '5';
    const isFinancementBailleurs = financementId === '3';
    const isFinancementC2d = financementId === '4';
    const isFinancement4Or5 = isFinancementC2d || isFinancementBMZ;

    const showOffre = isAEJ || isPEJEDEC;
    const showDateDebutClassique = isAEJ || isPEJEDEC;
    const showCapitalisationPejedec = isPEJEDEC;
    const showCapitalisationSansFinanciere = isSpontaneOuDAICG;
    const showWave = isFinancementBMZ;
    const showTresorMoney = !isFinancementBMZ;
    const showTypeStructure = isFinancementBailleurs && (normalizeLabel(getStageLabel()).includes('ECOLE') || normalizeLabel(getStageLabel()).includes('QUALIFICATION'));

    const requiresYup = Boolean(financementId) && !isFinancementBMZ;
    const requiresWave = isFinancementBMZ;

    /* ─── Options filtrées ─── */
    const conseillerOptions = useMemo(() => {
        if (!stage.agence_id) return [];
        return []; // TODO: charger depuis API si besoin
    }, [stage.agence_id]);

    const entrepriseOptions = useMemo(() => {
        if (!stage.agence_id) return offres.map(o => ({ value: o.entreprise_id, label: o.entreprise?.raison_sociale || `Entreprise #${o.entreprise_id}` }));
        const unique = new Map<number, OffreItem>();
        offres.filter(o => o.agence_id === Number(stage.agence_id)).forEach(o => { if (!unique.has(o.entreprise_id)) unique.set(o.entreprise_id, o); });
        return Array.from(unique.values()).map(o => ({ value: o.entreprise_id, label: o.entreprise?.raison_sociale || `Entreprise #${o.entreprise_id}` }));
    }, [offres, stage.agence_id]);

    const filteredTypesStage = useMemo(() => {
        if (isFinancement4Or5) return typesStage.filter(ts => normalizeLabel(ts.nom).includes('QUALIFICATION'));
        if (isFinancementBailleurs && isPEJEDEC) return typesStage.filter(ts => normalizeLabel(ts.nom).includes('ECOLE'));
        if (isFinancementBailleurs && isAEJ) return typesStage.filter(ts => normalizeLabel(ts.nom).includes('ECOLE') || normalizeLabel(ts.nom).includes('QUALIFICATION'));
        return typesStage;
    }, [typesStage, financementId, origineId]);

    const filteredDiplomes = useMemo(() => {
        if (!stage.niveau_etude_id) return diplomes;
        return diplomes.filter(d => !d.niveau_id || String(d.niveau_id) === stage.niveau_etude_id);
    }, [diplomes, stage.niveau_etude_id]);

    const durationOptionsForSelection = useMemo(() => {
        if (isFinancement4Or5) return DURATION_OPTIONS.filter(o => o.value === '6');
        const selectedTs = typesStage.find(ts => String(ts.id) === stage.type_stage_id);
        if (selectedTs && (selectedTs as any).duree === '6') return DURATION_OPTIONS.filter(o => o.value === '6');
        return DURATION_OPTIONS;
    }, [typesStage, stage.type_stage_id, financementId]);

    function getStageLabel(): string {
        const ts = typesStage.find(t => String(t.id) === stage.type_stage_id);
        return ts?.nom || '';
    }

    const piecePrefix = getPiecePrefix(beneficiaire.nature_piece_identite);
    const pieceMaxLength = getPieceMaxLength(beneficiaire.nature_piece_identite);
    const pieceNumberDisplay = beneficiaire.numero_piece_identite.replace(new RegExp(`^${piecePrefix}`), '');

    /* ─── Auto-effects ─── */
    useEffect(() => {
        if (isPEJEDEC) setStage(s => ({ ...s, source_financement_id: '3' }));
    }, [origineId]);

    useEffect(() => {
        if (isFinancementBMZ) {
            const waveId = typesPaiement.find(p => p.nom.toLowerCase().includes('wave'))?.id || '2';
            setBeneficiaire(b => ({ ...b, type_paiement_id: String(waveId) }));
        } else if (financementId) {
            const yupId = typesPaiement.find(p => p.nom.toLowerCase().includes('yup') || p.nom.toLowerCase().includes('tresor'))?.id || '1';
            setBeneficiaire(b => ({ ...b, type_paiement_id: String(yupId) }));
        }
    }, [financementId]);

    useEffect(() => {
        if (isFinancement4Or5) {
            const autoTs = typesStage.find(ts => normalizeLabel(ts.nom).includes('QUALIFICATION'));
            setStage(s => ({ ...s, type_stage_id: autoTs ? String(autoTs.id) : '', duree_stage: '6' }));
        }
    }, [financementId]);

    useEffect(() => {
        if (isPEJEDEC) {
            setStage(s => ({ ...s, source_financement_id: '3', duree_stage: '6' }));
        }
    }, [origineId]);

    /* ─── Calcul date fin auto ─── */
    useEffect(() => {
        const base = (isSpontaneOuDAICG && stage.date_demarrage_capitalisation_sans_financiere)
            ? stage.date_demarrage_capitalisation_sans_financiere
            : stage.date_debut;
        const fin = calculateDateFin(base, stage.duree_stage);
        if (fin && fin !== stage.date_fin_prevue) {
            setStage(s => ({ ...s, date_fin_prevue: fin }));
        }
    }, [stage.date_debut, stage.duree_stage, stage.date_demarrage_capitalisation_sans_financiere]);

    /* ─── Handler Demandeur AEJ ─── */
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
            setBeneficiaire(b => ({
                ...b,
                nom: data.nom?.toUpperCase() || b.nom,
                prenoms: data.prenom?.toUpperCase() || b.prenoms,
                date_naissance: data.date_naissance || b.date_naissance,
                lieu_naissance: data.lieu_naissance?.toUpperCase() || b.lieu_naissance,
                telephone_principal: data.telephone || b.telephone_principal,
                sexe: data.sexe || b.sexe,
                nature_piece_identite: data.type_piece_identite || b.nature_piece_identite,
                numero_piece_identite: data.numero_identite?.toUpperCase() || b.numero_piece_identite,
                specialite: data.specialite?.toUpperCase() || b.specialite,
                etablissement_frequente: data.etablissement_frequente?.toUpperCase() || b.etablissement_frequente,
                type_enseignement_id: data.type_enseignement || b.type_enseignement_id,
                handicap_id: data.handicap || b.handicap_id,
                personne_urgence: data.personne_urgence?.toUpperCase() || b.personne_urgence,
                contact_urgence_1: data.prsurgent_tel1 || b.contact_urgence_1,
            }));
            setDemandeurLoaded(true);
        } catch (e: any) {
            setDemandeurError(e.message || 'Impossible de charger le demandeur.');
        } finally {
            setDemandeurLoading(false);
        }
    }, [beneficiaire.numero_aej]);

    /* ─── Validation ─── */
    const validate = useCallback((): FormErrors => {
        const e: FormErrors = {};
        const req = (f: string, v: string, label: string) => { if (!v?.trim()) e[f] = `${label} est obligatoire.`; };
        const digits = (f: string, v: string, len: number, label: string) => { if (v && !new RegExp(`^[0-9]{${len}}$`).test(v)) e[f] = `${label} doit avoir ${len} chiffres.`; };

        req('numero_aej', beneficiaire.numero_aej, 'Numero AEJ');
        req('nom', beneficiaire.nom, 'Nom');
        req('prenoms', beneficiaire.prenoms, 'Prenoms');
        req('date_naissance', beneficiaire.date_naissance, 'Date de naissance');
        req('sexe', beneficiaire.sexe, 'Sexe');
        req('nature_piece_identite', beneficiaire.nature_piece_identite, 'Nature piece');
        req('numero_piece_identite', beneficiaire.numero_piece_identite, 'Numero piece');
        req('telephone_principal', beneficiaire.telephone_principal, 'Telephone');
        req('personne_urgence', beneficiaire.personne_urgence, 'Personne a contacter');
        req('lien_parente_id', beneficiaire.lien_parente_id, 'Lien de parente');
        req('contact_urgence_1', beneficiaire.contact_urgence_1, 'Contact parent 1');
        req('niveau_etude_id', beneficiaire.niveau_etude_id, 'Niveau etude');
        req('diplome_id', beneficiaire.diplome_id, 'Diplome');
        req('specialite', beneficiaire.specialite, 'Specialite');
        req('etablissement_frequente', beneficiaire.etablissement_frequente, 'Etablissement');
        req('type_enseignement_id', beneficiaire.type_enseignement_id, 'Type enseignement');
        req('handicap_id', beneficiaire.handicap_id, 'Handicap');
        if (beneficiaire.handicap_id === 'HANDICAP') req('type_handicap_id', beneficiaire.type_handicap_id, 'Type handicap');
        if (beneficiaire.type_handicap_id === 'AUTRE') req('autre_handicap', beneficiaire.autre_handicap, 'Autre handicap');
        req('type_paiement_id', beneficiaire.type_paiement_id, 'Type paiement');

        req('agence_id', stage.agence_id, 'Agence');
        req('conseiller_id', stage.conseiller_id, 'Conseiller');
        req('origine_stagiaire_id', stage.origine_stagiaire_id, 'Origine');
        req('source_financement_id', stage.source_financement_id, 'Financement');
        req('type_stage_id', stage.type_stage_id, 'Type stage');
        req('duree_stage', stage.duree_stage, 'Duree');
        req('entreprise_id', stage.entreprise_id, 'Entreprise');
        req('service_affectation', stage.service_affectation, 'Service affectation');
        req('intitule_poste', stage.intitule_poste, 'Intitule poste');
        req('localite_stage', stage.localite_stage, 'Localite stage');
        req('commune_stage', stage.commune_stage, 'Commune stage');
        req('sous_prefecture_stage', stage.sous_prefecture_stage, 'S/P stage');
        req('nom_encadreur', stage.nom_encadreur, 'Nom encadreur');
        req('fonction_encadreur', stage.fonction_encadreur, 'Fonction encadreur');
        req('contact_encadreur', stage.contact_encadreur, 'Contact encadreur');
        req('situation_stage', stage.situation_stage, 'Situation stage');
        req('date_fin_prevue', stage.date_fin_prevue, 'Date fin');

        if (!stage.date_demarrage_capitalisation_sans_financiere) req('date_debut', stage.date_debut, 'Date debut');
        if (showTypeStructure) req('type_stage_id', stage.type_stage_id, 'Type stage');

        if (requiresYup) req('numero_tresor_money', beneficiaire.numero_tresor_money, 'N° Trésor Money');
        if (requiresWave) req('numero_wave', beneficiaire.numero_wave, 'N° Wave');

        digits('telephone_principal', beneficiaire.telephone_principal, 10, 'Telephone');
        digits('contact_urgence_1', beneficiaire.contact_urgence_1, 10, 'Contact parent 1');
        if (beneficiaire.numero_tresor_money) digits('numero_tresor_money', beneficiaire.numero_tresor_money, 10, 'N° Trésor Money');
        if (beneficiaire.numero_wave) digits('numero_wave', beneficiaire.numero_wave, 10, 'N° Wave');

        if (beneficiaire.numero_aej && (beneficiaire.numero_aej.length < 10 || beneficiaire.numero_aej.length > 14))
            e.numero_aej = 'Le numero AEJ doit etre entre 10 et 14 caracteres.';

        if (beneficiaire.date_naissance && new Date(beneficiaire.date_naissance) >= new Date())
            e.date_naissance = 'La date de naissance doit etre anterieure a aujourd\'hui.';

        if (stage.date_debut && !isFinancementBMZ) {
            const day = new Date(`${stage.date_debut}T00:00:00`).getDate();
            if (!ALLOWED_COHORT_DAYS.includes(day))
                e.date_debut = 'La date doit etre le 01-05, 10 ou 20 du mois.';
        }

        if (stage.date_debut && stage.date_fin_prevue && stage.date_fin_prevue < stage.date_debut)
            e.date_fin_prevue = 'La date de fin doit etre posterieure a la date de debut.';

        return e;
    }, [beneficiaire, stage, requiresYup, requiresWave, showTypeStructure, isFinancementBMZ]);

    /* ─── Submit ─── */
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        const clientErrors = validate();
        setErrors(clientErrors);
        if (Object.keys(clientErrors).length > 0) return;

        setSubmitting(true);
        try {
            const formData = new FormData();
            Object.entries(beneficiaire).forEach(([k, v]) => { if (v) formData.append(`beneficiaire[${k}]`, v); });
            Object.entries(stage).forEach(([k, v]) => { if (v !== '' && v !== 0) formData.append(`stage[${k}]`, String(v)); });
            Object.entries(contrat).forEach(([k, v]) => { if (v) formData.append(`contrat[${k}]`, v); });
            Object.entries(documents).forEach(([k, v]) => { if (v instanceof File) formData.append(`documents[${k}]`, v); });

            formData.append('contrat[date_debut]', stage.date_debut || todayString());
            formData.append('contrat[date_fin]', stage.date_fin_prevue || '');

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

    /* ─── Render ─── */
    return (
        <React.Fragment>
            <Head title="Nouveau Stagiaire" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouveau Stagiaire" pageTitle="CIP" />

                    {flash?.success && <Alert color="success" className="border-0"><i className="ri-check-double-line me-2"></i>{flash.success}</Alert>}
                    {flash?.error && <Alert color="danger" className="border-0"><i className="ri-error-warning-line me-2"></i>{flash.error}</Alert>}
                    {errors._form && <Alert color="danger" className="border-0"><i className="ri-error-warning-line me-2"></i>{errors._form}</Alert>}

                    <Form onSubmit={handleSubmit} encType="multipart/form-data">
                        <Row className="g-3">

                            {/* ═══ AGENCE REGIONALE ═══ */}
                            <Col lg={12}>
                                <Card className="shadow-sm border-0">
                                    <CardHeader className="bg-primary-subtle"><h5 className="card-title mb-0 text-primary"><i className="ri-building-2-line me-1"></i>Agence Regionale</h5></CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Agence <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('agence_id') ? 'is-invalid' : ''}`} value={stage.agence_id} onChange={e => setStage(s => ({ ...s, agence_id: e.target.value, conseiller_id: '', entreprise_id: '' }))}>
                                                    <option value="">Selectionner</option>
                                                    {agences.map(a => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('agence_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Conseiller referent <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('conseiller_id') ? 'is-invalid' : ''} value={stage.conseiller_id} onChange={e => setStage(s => ({ ...s, conseiller_id: e.target.value }))} placeholder={stage.agence_id ? 'ID du conseiller' : 'Selectionner agence d abord'} disabled={!stage.agence_id} />
                                                <div className="invalid-feedback">{fieldError('conseiller_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Origine du stagiaire <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('origine_stagiaire_id') ? 'is-invalid' : ''}`} value={stage.origine_stagiaire_id} onChange={e => setStage(s => ({ ...s, origine_stagiaire_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {originesStagiaire.map(o => <option key={o.id} value={o.id}>{o.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('origine_stagiaire_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Source de financement <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('source_financement_id') ? 'is-invalid' : ''}`} value={stage.source_financement_id} onChange={e => setStage(s => ({ ...s, source_financement_id: e.target.value }))} disabled={isPEJEDEC}>
                                                    <option value="">Selectionner</option>
                                                    {sourcesFinancement.map(s => <option key={s.id} value={s.id}>{s.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('source_financement_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Type de stage <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('type_stage_id') ? 'is-invalid' : ''}`} value={stage.type_stage_id} onChange={e => setStage(s => ({ ...s, type_stage_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {filteredTypesStage.map(ts => <option key={ts.id} value={ts.id}>{ts.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('type_stage_id')}</div>
                                            </Col>
                                            {showTypeStructure && (
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Type de structure <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('type_structure_id') ? 'is-invalid' : ''} value={(stage as any).type_structure_id || ''} onChange={e => setStage(s => ({ ...s, ...{ type_structure_id: e.target.value } }))} />
                                                    <div className="invalid-feedback">{fieldError('type_structure_id')}</div>
                                                </Col>
                                            )}
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Duree previsionnelle <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('duree_stage') ? 'is-invalid' : ''}`} value={stage.duree_stage} onChange={e => setStage(s => ({ ...s, duree_stage: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {durationOptionsForSelection.map(d => <option key={d.value} value={d.value}>{d.label}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('duree_stage')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Date entree portefeuille</Label>
                                                <Input type="date" value={stage.date_entree_portefeuille} onChange={e => setStage(s => ({ ...s, date_entree_portefeuille: e.target.value }))} />
                                            </Col>
                                            {showOffre && (
                                                <Col lg={12}>
                                                    <Label className="fw-semibold">Offre d emploi {isAEJ && <span className="text-danger">*</span>}</Label>
                                                    <Select options={offres.map(o => ({ value: o.id, label: `${o.numero} - ${o.intitule} (${o.entreprise?.raison_sociale})`, offre: o }))}
                                                        onChange={(sel: any) => { if (!sel) return; const o = sel.offre; setStage(s => ({ ...s, offre_emploi_id: String(o.id), entreprise_id: String(o.entreprise_id), type_stage_id: String(o.type_stage_id), source_financement_id: String(o.source_financement_id), intitule_poste: o.intitule })); }}
                                                        placeholder="Selectionner une offre..." />
                                                </Col>
                                            )}
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* ═══ IDENTIFICATION STAGIAIRE ═══ */}
                            <Col lg={12}>
                                <Card className="shadow-sm border-0">
                                    <CardHeader className="bg-info-subtle"><h5 className="card-title mb-0 text-info"><i className="ri-user-3-line me-1"></i>Identification Stagiaire</h5></CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={4}>
                                                <Label className="fw-semibold">{isFinancementC2d ? 'Matricule' : 'Numero AEJ'} <span className="text-danger">*</span></Label>
                                                <div className="input-group">
                                                    <Input type="text" className={fieldError('numero_aej') || demandeurError ? 'is-invalid' : ''} value={beneficiaire.numero_aej} onChange={e => { setBeneficiaire(b => ({ ...b, numero_aej: e.target.value })); setDemandeurLoaded(false); setDemandeurError(''); }} minLength={10} maxLength={14} />
                                                    <Button color="outline-primary" type="button" disabled={demandeurLoading || beneficiaire.numero_aej.length < 10} onClick={handleLoadDemandeur}>
                                                        {demandeurLoading ? <Spinner size="sm" /> : 'Charger'}
                                                    </Button>
                                                </div>
                                                {(fieldError('numero_aej') || demandeurError) && <div className="text-danger fs-12 mt-1">{fieldError('numero_aej') || demandeurError}</div>}
                                            </Col>
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Nom <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('nom') ? 'is-invalid' : ''} value={beneficiaire.nom} onChange={e => setBeneficiaire(b => ({ ...b, nom: e.target.value.toUpperCase() }))} readOnly={demandeurLoaded} />
                                                <div className="invalid-feedback">{fieldError('nom')}</div>
                                            </Col>
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Prenoms <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('prenoms') ? 'is-invalid' : ''} value={beneficiaire.prenoms} onChange={e => setBeneficiaire(b => ({ ...b, prenoms: e.target.value.toUpperCase() }))} readOnly={demandeurLoaded} />
                                                <div className="invalid-feedback">{fieldError('prenoms')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Sexe <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('sexe') ? 'is-invalid' : ''}`} value={beneficiaire.sexe} onChange={e => setBeneficiaire(b => ({ ...b, sexe: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    <option value="Homme">Homme</option>
                                                    <option value="Femme">Femme</option>
                                                </select>
                                                <div className="invalid-feedback">{fieldError('sexe')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Date de naissance <span className="text-danger">*</span></Label>
                                                <Input type="date" className={fieldError('date_naissance') ? 'is-invalid' : ''} value={beneficiaire.date_naissance} onChange={e => setBeneficiaire(b => ({ ...b, date_naissance: e.target.value }))} max={todayString()} />
                                                <div className="invalid-feedback">{fieldError('date_naissance')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Lieu de naissance <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('lieu_naissance') ? 'is-invalid' : ''} value={beneficiaire.lieu_naissance} onChange={e => setBeneficiaire(b => ({ ...b, lieu_naissance: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('lieu_naissance')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Nature piece <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('nature_piece_identite') ? 'is-invalid' : ''}`} value={beneficiaire.nature_piece_identite} onChange={e => setBeneficiaire(b => ({ ...b, nature_piece_identite: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {TYPE_PIECE_OPTIONS.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('nature_piece_identite')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Numero piece <span className="text-danger">*</span></Label>
                                                <div className="input-group">
                                                    {piecePrefix && <span className="input-group-text">{piecePrefix}</span>}
                                                    <Input type="text" className={fieldError('numero_piece_identite') ? 'is-invalid' : ''} value={pieceNumberDisplay} maxLength={pieceMaxLength} onChange={e => setBeneficiaire(b => ({ ...b, numero_piece_identite: `${piecePrefix}${e.target.value.replace(/\s/g, '').toUpperCase()}` }))} />
                                                </div>
                                                <div className="invalid-feedback">{fieldError('numero_piece_identite')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Telephone 1 <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('telephone_principal') ? 'is-invalid' : ''} value={beneficiaire.telephone_principal} onChange={e => setBeneficiaire(b => ({ ...b, telephone_principal: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                                <div className="invalid-feedback">{fieldError('telephone_principal')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Telephone 2</Label>
                                                <Input type="text" value={beneficiaire.telephone_secondaire} onChange={e => setBeneficiaire(b => ({ ...b, telephone_secondaire: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                            </Col>

                                            {/* Urgence */}
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Personne a contacter <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('personne_urgence') ? 'is-invalid' : ''} value={beneficiaire.personne_urgence} onChange={e => setBeneficiaire(b => ({ ...b, personne_urgence: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('personne_urgence')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Lien de parente <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('lien_parente_id') ? 'is-invalid' : ''}`} value={beneficiaire.lien_parente_id} onChange={e => setBeneficiaire(b => ({ ...b, lien_parente_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {liensParente.map(l => <option key={l.id} value={l.id}>{l.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('lien_parente_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Contact parent 1 <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('contact_urgence_1') ? 'is-invalid' : ''} value={beneficiaire.contact_urgence_1} onChange={e => setBeneficiaire(b => ({ ...b, contact_urgence_1: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                                <div className="invalid-feedback">{fieldError('contact_urgence_1')}</div>
                                            </Col>
                                            <Col lg={2}>
                                                <Label className="fw-semibold">Contact parent 2</Label>
                                                <Input type="text" value={beneficiaire.contact_urgence_2} onChange={e => setBeneficiaire(b => ({ ...b, contact_urgence_2: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                            </Col>

                                            {/* Formation */}
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Niveau etude <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('niveau_etude_id') ? 'is-invalid' : ''}`} value={beneficiaire.niveau_etude_id} onChange={e => setBeneficiaire(b => ({ ...b, niveau_etude_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {niveauxEtude.map(n => <option key={n.id} value={n.id}>{n.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('niveau_etude_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Diplome <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('diplome_id') ? 'is-invalid' : ''}`} value={beneficiaire.diplome_id} onChange={e => setBeneficiaire(b => ({ ...b, diplome_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {filteredDiplomes.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('diplome_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Specialite <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('specialite') ? 'is-invalid' : ''} value={beneficiaire.specialite} onChange={e => setBeneficiaire(b => ({ ...b, specialite: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('specialite')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Annee diplome</Label>
                                                <Input type="text" value={beneficiaire.annee_diplome} onChange={e => setBeneficiaire(b => ({ ...b, annee_diplome: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={4} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Etablissement frequenté <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('etablissement_frequente') ? 'is-invalid' : ''} value={beneficiaire.etablissement_frequente} onChange={e => setBeneficiaire(b => ({ ...b, etablissement_frequente: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('etablissement_frequente')}</div>
                                            </Col>
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Type d'enseignement <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('type_enseignement_id') ? 'is-invalid' : ''}`} value={beneficiaire.type_enseignement_id} onChange={e => setBeneficiaire(b => ({ ...b, type_enseignement_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {typesEnseignement.map(t => <option key={t.id} value={t.id}>{t.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('type_enseignement_id')}</div>
                                            </Col>

                                            {/* Handicap */}
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Handicap <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('handicap_id') ? 'is-invalid' : ''}`} value={beneficiaire.handicap_id} onChange={e => setBeneficiaire(b => ({ ...b, handicap_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {HANDICAP_OPTIONS.map(h => <option key={h.value} value={h.value}>{h.label}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('handicap_id')}</div>
                                            </Col>
                                            {beneficiaire.handicap_id === 'HANDICAP' && (
                                                <>
                                                    <Col lg={3}>
                                                        <Label className="fw-semibold">Type handicap <span className="text-danger">*</span></Label>
                                                        <select className={`form-select ${fieldError('type_handicap_id') ? 'is-invalid' : ''}`} value={beneficiaire.type_handicap_id} onChange={e => setBeneficiaire(b => ({ ...b, type_handicap_id: e.target.value }))}>
                                                            <option value="">Selectionner</option>
                                                            {TYPE_HANDICAP_OPTIONS.map(h => <option key={h.value} value={h.value}>{h.label}</option>)}
                                                        </select>
                                                        <div className="invalid-feedback">{fieldError('type_handicap_id')}</div>
                                                    </Col>
                                                    {beneficiaire.type_handicap_id === 'AUTRE' && (
                                                        <Col lg={3}>
                                                            <Label className="fw-semibold">Autre handicap <span className="text-danger">*</span></Label>
                                                            <Input type="text" className={fieldError('autre_handicap') ? 'is-invalid' : ''} value={beneficiaire.autre_handicap} onChange={e => setBeneficiaire(b => ({ ...b, autre_handicap: e.target.value.toUpperCase() }))} />
                                                            <div className="invalid-feedback">{fieldError('autre_handicap')}</div>
                                                        </Col>
                                                    )}
                                                </>
                                            )}
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* ═══ MISE EN STAGE ═══ */}
                            <Col lg={12}>
                                <Card className="shadow-sm border-0">
                                    <CardHeader className="bg-warning-subtle"><h5 className="card-title mb-0 text-warning"><i className="ri-briefcase-line me-1"></i>Mise en Stage</h5></CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Entreprise <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('entreprise_id') ? 'is-invalid' : ''}`} value={stage.entreprise_id} onChange={e => setStage(s => ({ ...s, entreprise_id: e.target.value }))}>
                                                    <option value="">Selectionner</option>
                                                    {entrepriseOptions.map(e => <option key={e.value} value={e.value}>{e.label}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('entreprise_id')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Service d'affectation <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('service_affectation') ? 'is-invalid' : ''} value={stage.service_affectation} onChange={e => setStage(s => ({ ...s, service_affectation: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('service_affectation')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Intitule du poste <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('intitule_poste') ? 'is-invalid' : ''} value={stage.intitule_poste} onChange={e => setStage(s => ({ ...s, intitule_poste: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('intitule_poste')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Localite / lieu de stage <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('localite_stage') ? 'is-invalid' : ''} value={stage.localite_stage} onChange={e => setStage(s => ({ ...s, localite_stage: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('localite_stage')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Commune du lieu de stage <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('commune_stage') ? 'is-invalid' : ''} value={stage.commune_stage} onChange={e => setStage(s => ({ ...s, commune_stage: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('commune_stage')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">S/P du lieu de stage <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('sous_prefecture_stage') ? 'is-invalid' : ''} value={stage.sous_prefecture_stage} onChange={e => setStage(s => ({ ...s, sous_prefecture_stage: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('sous_prefecture_stage')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Nom encadreur <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('nom_encadreur') ? 'is-invalid' : ''} value={stage.nom_encadreur} onChange={e => setStage(s => ({ ...s, nom_encadreur: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('nom_encadreur')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Fonction encadreur <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('fonction_encadreur') ? 'is-invalid' : ''} value={stage.fonction_encadreur} onChange={e => setStage(s => ({ ...s, fonction_encadreur: e.target.value.toUpperCase() }))} />
                                                <div className="invalid-feedback">{fieldError('fonction_encadreur')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Contact encadreur <span className="text-danger">*</span></Label>
                                                <Input type="text" className={fieldError('contact_encadreur') ? 'is-invalid' : ''} value={stage.contact_encadreur} onChange={e => setStage(s => ({ ...s, contact_encadreur: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                                <div className="invalid-feedback">{fieldError('contact_encadreur')}</div>
                                            </Col>
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Situation stage <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('situation_stage') ? 'is-invalid' : ''}`} value={stage.situation_stage} onChange={e => setStage(s => ({ ...s, situation_stage: e.target.value }))}>
                                                    <option value="1">En cours</option>
                                                </select>
                                                <div className="invalid-feedback">{fieldError('situation_stage')}</div>
                                            </Col>

                                            {/* Dates */}
                                            {showDateDebutClassique && (
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Date debut stage <span className="text-danger">*</span></Label>
                                                    <Input type="date" className={fieldError('date_debut') ? 'is-invalid' : ''} value={stage.date_debut} onChange={e => setStage(s => ({ ...s, date_debut: e.target.value }))} />
                                                    <div className="invalid-feedback">{fieldError('date_debut')}</div>
                                                </Col>
                                            )}
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Date fin prevue <span className="text-danger">*</span></Label>
                                                <Input type="date" className={fieldError('date_fin_prevue') ? 'is-invalid' : ''} value={stage.date_fin_prevue} readOnly />
                                                <div className="invalid-feedback">{fieldError('date_fin_prevue')}</div>
                                                <small className="text-muted">Calculee automatiquement</small>
                                            </Col>

                                            {showCapitalisationPejedec && (
                                                <>
                                                    <Col lg={3}>
                                                        <Label className="fw-semibold">Date demarrage capitalisation</Label>
                                                        <Input type="date" value={stage.date_demarrage_capitalisation} onChange={e => setStage(s => ({ ...s, date_demarrage_capitalisation: e.target.value }))} />
                                                    </Col>
                                                    <Col lg={3}>
                                                        <Label className="fw-semibold">Nb mois capitalises</Label>
                                                        <Input type="number" value={stage.nbr_mois_capitaliser} onChange={e => setStage(s => ({ ...s, nbr_mois_capitaliser: parseInt(e.target.value) || 0 }))} />
                                                    </Col>
                                                </>
                                            )}
                                            {showCapitalisationSansFinanciere && (
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">Date demarrage sans incidence financiere</Label>
                                                    <Input type="date" value={stage.date_demarrage_capitalisation_sans_financiere} onChange={e => setStage(s => ({ ...s, date_demarrage_capitalisation_sans_financiere: e.target.value }))} />
                                                </Col>
                                            )}
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* ═══ PAIEMENT & PIECES ═══ */}
                            <Col lg={12}>
                                <Card className="shadow-sm border-0">
                                    <CardHeader className="bg-success-subtle"><h5 className="card-title mb-0 text-success"><i className="ri-wallet-3-line me-1"></i>Paiement & Pieces Justificatives</h5></CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={3}>
                                                <Label className="fw-semibold">Type paiement <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${fieldError('type_paiement_id') ? 'is-invalid' : ''}`} value={beneficiaire.type_paiement_id} onChange={e => setBeneficiaire(b => ({ ...b, type_paiement_id: e.target.value }))} disabled>
                                                    <option value="">Selectionner</option>
                                                    {typesPaiement.map(t => <option key={t.id} value={t.id}>{t.nom}</option>)}
                                                </select>
                                                <div className="invalid-feedback">{fieldError('type_paiement_id')}</div>
                                            </Col>
                                            {showTresorMoney && (
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">N° Trésor Money {requiresYup && <span className="text-danger">*</span>}</Label>
                                                    <Input type="text" className={fieldError('numero_tresor_money') ? 'is-invalid' : ''} value={beneficiaire.numero_tresor_money} onChange={e => setBeneficiaire(b => ({ ...b, numero_tresor_money: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                                    <div className="invalid-feedback">{fieldError('numero_tresor_money')}</div>
                                                </Col>
                                            )}
                                            {showWave && (
                                                <Col lg={3}>
                                                    <Label className="fw-semibold">N° Wave <span className="text-danger">*</span></Label>
                                                    <Input type="text" className={fieldError('numero_wave') ? 'is-invalid' : ''} value={beneficiaire.numero_wave} onChange={e => setBeneficiaire(b => ({ ...b, numero_wave: e.target.value.replace(/[^0-9]/g, '') }))} maxLength={10} />
                                                    <div className="invalid-feedback">{fieldError('numero_wave')}</div>
                                                </Col>
                                            )}
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Prime mensuelle (FCFA) <span className="text-danger">*</span></Label>
                                                <Input type="number" value={contrat.prime_mensuelle} onChange={e => setContrat(c => ({ ...c, prime_mensuelle: e.target.value }))} min="0" step="1000" />
                                            </Col>
                                            <Col lg={12}>
                                                <hr />
                                                <h6 className="text-muted mb-3"><i className="ri-file-upload-line me-1"></i>Fichiers a telecharger</h6>
                                            </Col>
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Piece d'identite <Badge color="danger">Obligatoire</Badge></Label>
                                                <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onChange={e => setDocuments(d => ({ ...d, piece_identite: e.target.files?.[0] || null }))} />
                                            </Col>
                                            {showTresorMoney && (
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Fiche Trésor Money <Badge color="danger">Obligatoire</Badge></Label>
                                                    <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onChange={e => setDocuments(d => ({ ...d, fiche_tresor_money: e.target.files?.[0] || null }))} />
                                                </Col>
                                            )}
                                            {showWave && (
                                                <Col lg={4}>
                                                    <Label className="fw-semibold">Fiche Wave <Badge color="danger">Obligatoire</Badge></Label>
                                                    <Input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onChange={e => setDocuments(d => ({ ...d, fiche_wave: e.target.files?.[0] || null }))} />
                                                </Col>
                                            )}
                                            <Col lg={4}>
                                                <Label className="fw-semibold">Diplome</Label>
                                                <Input type="file" accept=".pdf,.doc,.docx" onChange={e => setDocuments(d => ({ ...d, diplome: e.target.files?.[0] || null }))} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* ═══ OBSERVATIONS ═══ */}
                            <Col lg={12}>
                                <Card className="shadow-sm border-0">
                                    <CardBody>
                                        <Label className="fw-semibold">Observations</Label>
                                        <Input type="textarea" rows={3} value={stage.observations} onChange={e => setStage(s => ({ ...s, observations: e.target.value.toUpperCase() }))} placeholder="Observations eventuelles..." />
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* ═══ ACTIONS ═══ */}
                            <Col lg={12}>
                                <div className="d-flex justify-content-end gap-2 mb-4">
                                    <Link href="/cip/mes-stagiaires" className="btn btn-light">Annuler</Link>
                                    <Button color="primary" type="submit" disabled={submitting}>
                                        {submitting ? <><Spinner size="sm" className="me-1" />Enregistrement...</> : <><i className="ri-save-line me-1"></i>Enregistrer le dossier</>}
                                    </Button>
                                </div>
                            </Col>
                        </Row>
                    </Form>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Create;
