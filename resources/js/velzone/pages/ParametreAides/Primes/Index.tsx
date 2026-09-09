import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, Col, Container, Form, Input, Label, Row, Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Strategie {
    key: string;
    classe: string;
    label: string;
    description: string;
    priority: number;
    fallback: boolean;
    config: string;
}

type Valeur = string | number | null;
type Noeud = Valeur | Noeud[] | { [cle: string]: Noeud };
type Bareme = Record<string, Noeud>;

interface Props {
    configuration: Bareme;
    defauts: Bareme;
    strategies: Strategie[];
    personnalise: boolean;
    derniereModification: { le: string | null; par: string | null } | null;
    peutGerer: boolean;
}

interface Simulation {
    montant?: number;
    duree_mois?: number;
    mois?: string;
    strategie?: string | null;
    erreur?: string;
}

/**
 * Libellés des sections et des clés du barème. Les grilles sont indexées par
 * des clés techniques héritées du legacy (`45000`, `1-5`, `one_point_five`) :
 * on les traduit à l'affichage sans les renommer en base, le moteur de calcul
 * lisant toujours les clés d'origine.
 */
const LIBELLES: Record<string, string> = {
    smig: 'Repli SMIG',
    qualification: 'Stage de qualification',
    pae: 'Grilles PAE (stage de qualification)',
    stage_ecole: 'Stage école',
    default: 'Par défaut',
    budget_aej_public: 'Budget État — structure publique',
    budget_aej_prive: 'Budget État — structure privée',
    budget_aej_scad: 'Budget État — SCAD',
    budget_aej_effective_date: 'Entrée en vigueur des grilles Budget État',
    budget_aej_periods: 'Périodes des grilles Budget État',
    rules: 'Prorata des mois de bord',
    full: 'Mois plein',
    base: 'Mois plein',
    first_month: 'Mois de démarrage',
    last_month: 'Mois de fin',
    middle_adjustment_1_5: 'Mois intermédiaire (stage d’1,5 mois)',
    one_point_five: 'Stage d’1,5 mois',
    start: 'Début',
    end: 'Fin',
    prive: 'Structure privée',
    public: 'Structure publique',
    '1-5': 'Démarrage du 1er au 5',
    '10-19': 'Démarrage du 10 au 19',
    '10': 'Démarrage le 10',
    '20': 'Démarrage à partir du 20',
    '45000': 'Grille 45 000 FCFA',
    '75000': 'Grille 75 000 FCFA',
};

const libelle = (cle: string): string => LIBELLES[cle] ?? cle.replace(/_/g, ' ');

const estObjet = (valeur: Noeud): valeur is { [cle: string]: Noeud } =>
    typeof valeur === 'object' && valeur !== null && !Array.isArray(valeur);

/** Une clé de date : saisie au format AAAA-MM-JJ plutôt qu'en montant. */
const estDate = (cle: string, chemin: string[]): boolean =>
    cle.toLowerCase().includes('date') ||
    (chemin.includes('budget_aej_periods') && (cle === 'start' || cle === 'end'));

/** Recopie immuable de l'arbre en remplaçant la feuille désignée par `chemin`. */
const remplacer = (racine: Noeud, chemin: string[], valeur: Valeur): Noeud => {
    if (chemin.length === 0) {
        return valeur;
    }

    const [cle, ...reste] = chemin;

    if (Array.isArray(racine)) {
        return racine.map((item, index) => (String(index) === cle ? remplacer(item, reste, valeur) : item));
    }

    return { ...(estObjet(racine) ? racine : {}), [cle]: remplacer(estObjet(racine) ? racine[cle] : null, reste, valeur) };
};

const lire = (racine: Noeud, chemin: string[]): Noeud => {
    let courant: Noeud = racine;

    for (const cle of chemin) {
        if (Array.isArray(courant)) {
            courant = courant[Number(cle)] ?? null;
        } else if (estObjet(courant)) {
            courant = courant[cle] ?? null;
        } else {
            return null;
        }
    }

    return courant;
};

const formaterMontant = (montant: number): string => new Intl.NumberFormat('fr-FR').format(montant);

interface EditeurProps {
    valeur: Noeud;
    defaut: Noeud;
    chemin: string[];
    modifiable: boolean;
    onChange: (chemin: string[], valeur: Valeur) => void;
}

/**
 * Éditeur générique du barème : il descend la structure telle qu'elle est
 * livrée par le backend plutôt que de figer les sections connues, afin qu'une
 * grille ajoutée dans `config/primes.php` reste éditable sans toucher à
 * l'écran.
 */
const EditeurNoeud = ({ valeur, defaut, chemin, modifiable, onChange }: EditeurProps) => {
    if (Array.isArray(valeur)) {
        return (
            <Row className="g-3">
                {valeur.map((item, index) => (
                    <Col md={6} key={index}>
                        <div className="border rounded p-3 h-100">
                            <h6 className="text-muted text-uppercase fs-11 mb-3">Période {index + 1}</h6>
                            <EditeurNoeud
                                valeur={item}
                                defaut={lire(defaut, [String(index)])}
                                chemin={[...chemin, String(index)]}
                                modifiable={modifiable}
                                onChange={onChange}
                            />
                        </div>
                    </Col>
                ))}
            </Row>
        );
    }

    if (estObjet(valeur)) {
        const entrees = Object.entries(valeur);
        const feuilles = entrees.filter(([, v]) => !estObjet(v) && !Array.isArray(v));
        const branches = entrees.filter(([, v]) => estObjet(v) || Array.isArray(v));

        return (
            <React.Fragment>
                {feuilles.length > 0 && (
                    <Row className="g-3">
                        {feuilles.map(([cle, v]) => (
                            <Col md={4} key={cle}>
                                <EditeurNoeud
                                    valeur={v}
                                    defaut={lire(defaut, [cle])}
                                    chemin={[...chemin, cle]}
                                    modifiable={modifiable}
                                    onChange={onChange}
                                />
                            </Col>
                        ))}
                    </Row>
                )}
                {branches.map(([cle, v]) => (
                    <div key={cle} className={chemin.length === 0 ? 'mb-4' : 'mt-3 ps-3 border-start'}>
                        <h6 className="fs-13 mb-2">{libelle(cle)}</h6>
                        <EditeurNoeud
                            valeur={v}
                            defaut={lire(defaut, [cle])}
                            chemin={[...chemin, cle]}
                            modifiable={modifiable}
                            onChange={onChange}
                        />
                    </div>
                ))}
            </React.Fragment>
        );
    }

    const cle = chemin[chemin.length - 1] ?? '';
    const date = estDate(cle, chemin);
    const modifie = defaut !== undefined && String(valeur ?? '') !== String(defaut ?? '');

    return (
        <div>
            <Label className="form-label fs-12 mb-1 d-flex align-items-center gap-2">
                {libelle(cle)}
                {modifie && (
                    <Badge color="warning" className="bg-warning-subtle text-warning">
                        modifié
                    </Badge>
                )}
            </Label>
            <Input
                type={date ? 'date' : 'number'}
                bsSize="sm"
                value={valeur === null ? '' : String(valeur)}
                disabled={!modifiable}
                onChange={(e) => {
                    const brut = e.target.value;
                    onChange(chemin, brut === '' ? null : date ? brut : Number(brut));
                }}
            />
            {!date && typeof defaut === 'number' && (
                <span className="text-muted fs-11">Défaut : {formaterMontant(defaut)} FCFA</span>
            )}
        </div>
    );
};

const TYPES_STAGE = [
    { id: 1, nom: 'Stage de qualification' },
    { id: 2, nom: 'Stage école' },
];

const FINANCEMENTS = [
    { id: 1, nom: 'PAPS-GOUV' },
    { id: 2, nom: 'Budget AEJ' },
    { id: 3, nom: 'Budget État' },
    { id: 4, nom: 'Financement 4' },
    { id: 5, nom: 'Financement 5' },
];

const STRUCTURES = [
    { id: '', nom: 'Non renseignée' },
    { id: '1', nom: 'Publique' },
    { id: '2', nom: 'Privée' },
    { id: '3', nom: 'SCAD' },
];

const Index = ({ configuration, defauts, strategies, personnalise, derniereModification, peutGerer }: Props) => {
    const bareme = useForm<{ configuration: Bareme }>({ configuration });

    const [simulation, setSimulation] = useState<Simulation | null>(null);
    const [simulationEnCours, setSimulationEnCours] = useState(false);
    const [saisie, setSaisie] = useState({
        type_stage_legacy_id: '1',
        source_financement_legacy_id: '1',
        type_structure_legacy_id: '',
        date_debut: '',
        date_fin: '',
        mois: '',
        nom_entreprise: '',
    });

    const majValeur = (chemin: string[], valeur: Valeur) => {
        bareme.setData('configuration', remplacer(bareme.data.configuration, chemin, valeur) as Bareme);
    };

    const enregistrer = (e: React.FormEvent) => {
        e.preventDefault();
        bareme.put('/parametre-aides/primes', { preserveScroll: true });
    };

    const reinitialiser = () => {
        if (
            confirm(
                'Rétablir le barème livré avec l’application ? Les montants personnalisés seront perdus. Les paiements déjà calculés ne sont pas modifiés.',
            )
        ) {
            router.post('/parametre-aides/primes/reinitialiser', {}, { preserveScroll: true });
        }
    };

    const simuler = async (e: React.FormEvent) => {
        e.preventDefault();
        setSimulationEnCours(true);
        setSimulation(null);

        try {
            const reponse = await fetch('/parametre-aides/primes/simuler', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    ...saisie,
                    type_structure_legacy_id: saisie.type_structure_legacy_id || null,
                    nom_entreprise: saisie.nom_entreprise || null,
                }),
            });

            const resultat = await reponse.json();

            setSimulation(
                reponse.ok
                    ? resultat
                    : { erreur: resultat.erreur ?? Object.values(resultat.errors ?? {}).flat().join(' ') },
            );
        } catch {
            setSimulation({ erreur: 'La simulation n’a pas abouti.' });
        } finally {
            setSimulationEnCours(false);
        }
    };

    return (
        <React.Fragment>
            <Head title="Barème des primes" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Barème des primes" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={12}>
                            <Alert color="info" className="d-flex align-items-start gap-2">
                                <i className="ri-information-line fs-16" />
                                <div>
                                    Ces montants alimentent la <strong>prime du mois</strong> calculée pour chaque
                                    paiement : montant plein pour un mois entier, prorata pour le mois de démarrage et
                                    le mois de fin selon le jour de démarrage du contrat. Les règles d’enchaînement
                                    restent portées par le code ; seules leurs valeurs sont modifiables ici. Une
                                    modification ne recalcule pas les paiements déjà émis.
                                </div>
                            </Alert>
                        </Col>
                    </Row>

                    <Row>
                        <Col xxl={8}>
                            <Form onSubmit={enregistrer}>
                                <Card>
                                    <CardHeader className="d-flex align-items-center justify-content-between">
                                        <div>
                                            <h5 className="card-title mb-0">Grilles de primes</h5>
                                            {personnalise ? (
                                                <span className="text-muted fs-12">
                                                    Barème personnalisé
                                                    {derniereModification?.par
                                                        ? ` — dernière modification par ${derniereModification.par}`
                                                        : ''}
                                                </span>
                                            ) : (
                                                <span className="text-muted fs-12">
                                                    Barème livré avec l’application
                                                </span>
                                            )}
                                        </div>
                                        {peutGerer && (
                                            <div className="d-flex gap-2">
                                                {personnalise && (
                                                    <Button color="light" type="button" onClick={reinitialiser}>
                                                        Réinitialiser
                                                    </Button>
                                                )}
                                                <Button color="primary" type="submit" disabled={bareme.processing}>
                                                    Enregistrer
                                                </Button>
                                            </div>
                                        )}
                                    </CardHeader>
                                    <CardBody>
                                        {bareme.errors.configuration && (
                                            <Alert color="danger">{bareme.errors.configuration}</Alert>
                                        )}
                                        {Object.entries(bareme.data.configuration).map(([section, valeur]) => (
                                            <div key={section} className="mb-4">
                                                <h5 className="fs-14 text-uppercase text-muted mb-3">
                                                    {libelle(section)}
                                                </h5>
                                                <EditeurNoeud
                                                    valeur={valeur}
                                                    defaut={lire(defauts, [section])}
                                                    chemin={[section]}
                                                    modifiable={peutGerer}
                                                    onChange={majValeur}
                                                />
                                            </div>
                                        ))}
                                    </CardBody>
                                </Card>
                            </Form>
                        </Col>

                        <Col xxl={4}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Simulateur</h5>
                                    <span className="text-muted fs-12">
                                        Rejoue le barème enregistré sur une situation saisie à la main. Les
                                        modifications non enregistrées ne sont pas prises en compte.
                                    </span>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={simuler}>
                                        <div className="mb-3">
                                            <Label className="form-label">Type de stage</Label>
                                            <Input
                                                type="select"
                                                value={saisie.type_stage_legacy_id}
                                                onChange={(e) =>
                                                    setSaisie({ ...saisie, type_stage_legacy_id: e.target.value })
                                                }
                                            >
                                                {TYPES_STAGE.map((type) => (
                                                    <option key={type.id} value={type.id}>
                                                        {type.nom}
                                                    </option>
                                                ))}
                                            </Input>
                                        </div>
                                        <div className="mb-3">
                                            <Label className="form-label">Source de financement</Label>
                                            <Input
                                                type="select"
                                                value={saisie.source_financement_legacy_id}
                                                onChange={(e) =>
                                                    setSaisie({
                                                        ...saisie,
                                                        source_financement_legacy_id: e.target.value,
                                                    })
                                                }
                                            >
                                                {FINANCEMENTS.map((source) => (
                                                    <option key={source.id} value={source.id}>
                                                        {source.nom}
                                                    </option>
                                                ))}
                                            </Input>
                                        </div>
                                        <div className="mb-3">
                                            <Label className="form-label">Type de structure</Label>
                                            <Input
                                                type="select"
                                                value={saisie.type_structure_legacy_id}
                                                onChange={(e) =>
                                                    setSaisie({ ...saisie, type_structure_legacy_id: e.target.value })
                                                }
                                            >
                                                {STRUCTURES.map((structure) => (
                                                    <option key={structure.id} value={structure.id}>
                                                        {structure.nom}
                                                    </option>
                                                ))}
                                            </Input>
                                        </div>
                                        <Row className="g-2 mb-3">
                                            <Col md={6}>
                                                <Label className="form-label">Début du contrat</Label>
                                                <Input
                                                    type="date"
                                                    value={saisie.date_debut}
                                                    onChange={(e) =>
                                                        setSaisie({ ...saisie, date_debut: e.target.value })
                                                    }
                                                    required
                                                />
                                            </Col>
                                            <Col md={6}>
                                                <Label className="form-label">Fin du contrat</Label>
                                                <Input
                                                    type="date"
                                                    value={saisie.date_fin}
                                                    onChange={(e) => setSaisie({ ...saisie, date_fin: e.target.value })}
                                                    required
                                                />
                                            </Col>
                                        </Row>
                                        <div className="mb-3">
                                            <Label className="form-label">Mois payé</Label>
                                            <Input
                                                type="month"
                                                value={saisie.mois}
                                                onChange={(e) => setSaisie({ ...saisie, mois: e.target.value })}
                                                required
                                            />
                                        </div>
                                        <div className="mb-3">
                                            <Label className="form-label">Entreprise (optionnel)</Label>
                                            <Input
                                                type="text"
                                                value={saisie.nom_entreprise}
                                                placeholder="Nom de la structure d’accueil"
                                                onChange={(e) =>
                                                    setSaisie({ ...saisie, nom_entreprise: e.target.value })
                                                }
                                            />
                                        </div>
                                        <Button color="secondary" type="submit" disabled={simulationEnCours}>
                                            {simulationEnCours ? 'Calcul…' : 'Calculer la prime du mois'}
                                        </Button>
                                    </Form>

                                    {simulation?.erreur && (
                                        <Alert color="danger" className="mt-3 mb-0">
                                            {simulation.erreur}
                                        </Alert>
                                    )}

                                    {simulation && simulation.montant !== undefined && (
                                        <Alert color="success" className="mt-3 mb-0">
                                            <div className="fs-18 fw-semibold">
                                                {formaterMontant(simulation.montant)} FCFA
                                            </div>
                                            <div className="fs-12">
                                                Prime de {simulation.mois} — durée du contrat :{' '}
                                                {simulation.duree_mois} mois
                                                {simulation.strategie ? ` — règle : ${simulation.strategie}` : ''}
                                            </div>
                                        </Alert>
                                    )}
                                </CardBody>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Règles appliquées</h5>
                                    <span className="text-muted fs-12">
                                        La première règle qui reconnaît le stage l’emporte, par priorité décroissante.
                                    </span>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <Table className="table-sm align-middle mb-0">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Règle</th>
                                                <th className="text-center">Priorité</th>
                                                <th>Valeurs</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {strategies.map((strategie) => (
                                                <tr key={strategie.key}>
                                                    <td>
                                                        <div className="fw-medium">
                                                            {strategie.label}
                                                            {strategie.fallback && (
                                                                <Badge
                                                                    color="secondary"
                                                                    className="bg-secondary-subtle text-secondary ms-1"
                                                                >
                                                                    repli
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="text-muted fs-11">{strategie.description}</div>
                                                    </td>
                                                    <td className="text-center">{strategie.priority}</td>
                                                    <td className="text-muted fs-12">{strategie.config}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </Table>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Index;
