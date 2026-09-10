import { Head, router, useForm } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useState } from 'react';
import AsyncSelect from 'react-select/async';
import {
    Alert, Badge, Button, Card, CardBody, CardHeader, Col, Container, Form, Input, InputGroup, InputGroupText,
    Label, Nav, NavItem, NavLink, Row, Table, TabContent, TabPane,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface OptionStagiaire {
    value: number;
    label: string;
    type_stage_legacy_id: number | null;
    source_financement_legacy_id: number | null;
    type_structure_legacy_id: number | null;
    date_debut: string | null;
    date_fin: string | null;
    nom_entreprise: string | null;
}

let minuterieStagiaires: number | undefined;
let controleurStagiaires: AbortController | undefined;
const chargerOptionsStagiaires = (saisie: string): Promise<OptionStagiaire[]> =>
    new Promise((resolve) => {
        window.clearTimeout(minuterieStagiaires);
        minuterieStagiaires = window.setTimeout(async () => {
            controleurStagiaires?.abort();
            controleurStagiaires = new AbortController();

            if (saisie.trim().length < 2) {
                resolve([]);
                return;
            }

            try {
                const reponse = await fetch(
                    `/parametre-aides/primes/stagiaires?q=${encodeURIComponent(saisie)}`,
                    {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: controleurStagiaires.signal,
                    },
                );
                const donnees = await reponse.json();

                resolve(
                    (donnees.data || []).map((s: Record<string, unknown>) => ({
                        value: s.id,
                        label: s.label,
                        type_stage_legacy_id: s.type_stage_legacy_id,
                        source_financement_legacy_id: s.source_financement_legacy_id,
                        type_structure_legacy_id: s.type_structure_legacy_id,
                        date_debut: s.date_debut,
                        date_fin: s.date_fin,
                        nom_entreprise: s.nom_entreprise,
                    })),
                );
            } catch {
                resolve([]);
            }
        }, 300);
    });

interface Strategie {
    key: string;
    classe: string;
    label: string;
    description: string;
    priority: number;
    fallback: boolean;
    config: string;
}

/** Grille indexée par plage de jour de démarrage, ex. `{'1-5': 45000, '10-19': 31500, '20': 16500}`. */
type GrilleJourValeur = Record<string, number>;

interface GrillePae {
    full: number;
    rules: Record<string, GrilleJourValeur>;
}

interface GrilleStageEcole {
    base: number;
    first_month: GrilleJourValeur;
    last_month: Record<string, GrilleJourValeur>;
    middle_adjustment_1_5?: GrilleJourValeur;
}

interface PeriodeBudgetAej {
    start: string;
    end: string | null;
    public: string;
    prive: string;
}

interface Bareme {
    smig: { default: number };
    qualification: { budget_aej_effective_date: string };
    pae: Record<string, GrillePae>;
    stage_ecole: {
        budget_aej_effective_date: string;
        budget_aej_periods: PeriodeBudgetAej[];
        [grille: string]: GrilleStageEcole | string | PeriodeBudgetAej[];
    };
}

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

/** Libellés des plages de jour de démarrage, communes aux deux grilles (PAE et stage école). */
const LIBELLE_PLAGE_JOUR: Record<string, string> = {
    '1-5': 'Démarrage du 1ᵉʳ au 5',
    '10-19': 'Démarrage du 10 au 19',
    '10': 'Démarrage le 10',
    '20': 'Démarrage à partir du 20',
};

/** Libellés des grilles `stage_ecole` (`default`, `budget_aej_public`, …). */
const LIBELLE_GRILLE_STAGE_ECOLE: Record<string, string> = {
    default: 'Grille historique',
    budget_aej_public: 'Budget État — structure publique',
    budget_aej_prive: 'Budget État — structure privée',
    budget_aej_scad: 'Budget État — SCAD',
};

/** Libellés des clés de durée sous `last_month` (`default`, `one_point_five`, ou un entier). */
const libelleDuree = (cle: string): string => {
    if (cle === 'default') return 'Toute autre durée';
    if (cle === 'one_point_five') return 'Stage de 1,5 mois';

    return `Stage de ${cle} mois`;
};

const libelleGrillePae = (cle: string): string => `Grille ${new Intl.NumberFormat('fr-FR').format(Number(cle))} FCFA`;

const formaterMontant = (montant: number): string => new Intl.NumberFormat('fr-FR').format(montant);

/** Recopie immuable d'un objet en remplaçant une valeur à un chemin de clés donné. */
const remplacer = <T,>(racine: T, chemin: (string | number)[], valeur: unknown): T => {
    if (chemin.length === 0) {
        return valeur as T;
    }

    const [cle, ...reste] = chemin;
    const racineObjet = (racine ?? {}) as Record<string | number, unknown>;

    if (Array.isArray(racine)) {
        return racine.map((item, index) => (index === cle ? remplacer(item, reste, valeur) : item)) as T;
    }

    return { ...racineObjet, [cle]: remplacer(racineObjet[cle], reste, valeur) } as T;
};

const lireNombre = (obj: unknown, chemin: (string | number)[]): number | undefined => {
    let courant: unknown = obj;

    for (const cle of chemin) {
        if (courant === null || typeof courant !== 'object') {
            return undefined;
        }

        courant = (courant as Record<string | number, unknown>)[cle];
    }

    return typeof courant === 'number' ? courant : undefined;
};

interface ChampMontantProps {
    label: string;
    chemin: (string | number)[];
    valeur: number;
    defaut?: number;
    modifiable: boolean;
    onChange: (chemin: (string | number)[], valeur: number) => void;
}

/**
 * Un champ montant unique, avec suffixe FCFA et retour au défaut d'un clic
 * lorsqu'il a été modifié — plutôt qu'un badge "modifié" qu'il faut relire
 * pour comprendre ce qu'il permet de faire.
 */
const ChampMontant = ({ label, chemin, valeur, defaut, modifiable, onChange }: ChampMontantProps) => {
    const modifie = defaut !== undefined && valeur !== defaut;

    return (
        <div className="mb-3">
            <Label className="form-label fs-13 mb-1">{label}</Label>
            <InputGroup className={modifie ? 'border border-warning rounded' : undefined}>
                <Input
                    type="number"
                    min={0}
                    value={valeur}
                    disabled={!modifiable}
                    className={modifie ? 'border-0' : undefined}
                    onChange={(e) => onChange(chemin, Number(e.target.value))}
                />
                <InputGroupText className={modifie ? 'border-0 bg-transparent' : undefined}>FCFA</InputGroupText>
                {modifiable && modifie && defaut !== undefined && (
                    <Button
                        color="light"
                        className="border-0"
                        title={`Revenir au défaut (${formaterMontant(defaut)} FCFA)`}
                        onClick={() => onChange(chemin, defaut)}
                    >
                        <i className="ri-arrow-go-back-line" />
                    </Button>
                )}
            </InputGroup>
        </div>
    );
};

interface GrilleJourProps {
    valeur: GrilleJourValeur;
    defaut?: GrilleJourValeur;
    chemin: (string | number)[];
    modifiable: boolean;
    onChange: (chemin: (string | number)[], valeur: number) => void;
}

/** Table « plage de jour de démarrage → montant », partagée par les deux grilles de prime. */
const GrilleJour = ({ valeur, defaut, chemin, modifiable, onChange }: GrilleJourProps) => (
    <Table className="align-middle mb-0" borderless>
        <tbody>
            {Object.keys(valeur).map((plage) => {
                const montant = valeur[plage];
                const montantDefaut = defaut?.[plage];
                const modifie = montantDefaut !== undefined && montant !== montantDefaut;

                return (
                    <tr key={plage}>
                        <td className="text-muted fs-13" style={{ width: '50%' }}>
                            {LIBELLE_PLAGE_JOUR[plage] ?? plage}
                        </td>
                        <td>
                            <InputGroup className={modifie ? 'border border-warning rounded' : undefined}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={montant}
                                    disabled={!modifiable}
                                    className={modifie ? 'border-0' : undefined}
                                    onChange={(e) => onChange([...chemin, plage], Number(e.target.value))}
                                />
                                <InputGroupText className={classnames({ 'border-0 bg-transparent': modifie })}>
                                    FCFA
                                </InputGroupText>
                                {modifiable && modifie && montantDefaut !== undefined && (
                                    <Button
                                        color="light"
                                        className="border-0"
                                        title={`Revenir au défaut (${formaterMontant(montantDefaut)} FCFA)`}
                                        onClick={() => onChange([...chemin, plage], montantDefaut)}
                                    >
                                        <i className="ri-arrow-go-back-line" />
                                    </Button>
                                )}
                            </InputGroup>
                        </td>
                    </tr>
                );
            })}
        </tbody>
    </Table>
);

interface ChampDateProps {
    label: string;
    chemin: (string | number)[];
    valeur: string;
    defaut?: string;
    modifiable: boolean;
    onChange: (chemin: (string | number)[], valeur: string) => void;
}

const ChampDate = ({ label, chemin, valeur, defaut, modifiable, onChange }: ChampDateProps) => {
    const modifie = defaut !== undefined && valeur !== defaut;

    return (
        <div className="mb-3">
            <Label className="form-label fs-13 mb-1">{label}</Label>
            <InputGroup className={modifie ? 'border border-warning rounded' : undefined}>
                <Input
                    type="date"
                    value={valeur}
                    disabled={!modifiable}
                    className={modifie ? 'border-0' : undefined}
                    onChange={(e) => onChange(chemin, e.target.value)}
                />
                {modifiable && modifie && defaut !== undefined && (
                    <Button
                        color="light"
                        className="border-0"
                        title={`Revenir au défaut (${defaut})`}
                        onClick={() => onChange(chemin, defaut)}
                    >
                        <i className="ri-arrow-go-back-line" />
                    </Button>
                )}
            </InputGroup>
        </div>
    );
};

interface CarteGrillePaeProps {
    montantCle: string;
    valeur: GrillePae;
    defaut?: GrillePae;
    modifiable: boolean;
    onChange: (chemin: (string | number)[], valeur: number) => void;
}

/** Une grille de stage de qualification : montant du mois plein + prorata des mois de bord. */
const CarteGrillePae = ({ montantCle, valeur, defaut, modifiable, onChange }: CarteGrillePaeProps) => (
    <Card className="border h-100">
        <CardHeader className="bg-light-subtle">
            <h6 className="mb-0 fs-14">{libelleGrillePae(montantCle)}</h6>
        </CardHeader>
        <CardBody>
            <ChampMontant
                label="Mois plein (mois intermédiaire)"
                chemin={['pae', montantCle, 'full']}
                valeur={valeur.full}
                defaut={defaut?.full}
                modifiable={modifiable}
                onChange={onChange}
            />
            <Label className="form-label fs-13 mb-1">Mois de démarrage et mois de fin</Label>
            <Row className="g-3">
                {Object.keys(valeur.rules).map((plage) => (
                    <Col md={6} key={plage}>
                        <div className="border rounded p-2">
                            <div className="text-muted fs-11 text-uppercase mb-1">{LIBELLE_PLAGE_JOUR[plage] ?? plage}</div>
                            <div className="d-flex align-items-center gap-2 mb-1">
                                <span className="fs-11 text-muted" style={{ width: 55 }}>
                                    Début
                                </span>
                                <ChampMontantInline
                                    chemin={['pae', montantCle, 'rules', plage, 'start']}
                                    valeur={valeur.rules[plage].start}
                                    defaut={defaut?.rules?.[plage]?.start}
                                    modifiable={modifiable}
                                    onChange={onChange}
                                />
                            </div>
                            <div className="d-flex align-items-center gap-2">
                                <span className="fs-11 text-muted" style={{ width: 55 }}>
                                    Fin
                                </span>
                                <ChampMontantInline
                                    chemin={['pae', montantCle, 'rules', plage, 'end']}
                                    valeur={valeur.rules[plage].end}
                                    defaut={defaut?.rules?.[plage]?.end}
                                    modifiable={modifiable}
                                    onChange={onChange}
                                />
                            </div>
                        </div>
                    </Col>
                ))}
            </Row>
        </CardBody>
    </Card>
);

/** Variante compacte de `ChampMontant`, sans label, pour les tables denses. */
const ChampMontantInline = ({
    chemin, valeur, defaut, modifiable, onChange,
}: Omit<ChampMontantProps, 'label'>) => {
    const modifie = defaut !== undefined && valeur !== defaut;

    return (
        <InputGroup className={modifie ? 'border border-warning rounded' : undefined}>
            <Input
                type="number"
                min={0}
                value={valeur}
                disabled={!modifiable}
                className={modifie ? 'border-0' : undefined}
                onChange={(e) => onChange(chemin, Number(e.target.value))}
            />
            {modifiable && modifie && defaut !== undefined && (
                <Button
                    color="light"
                    className="border-0"
                    title={`Revenir au défaut (${formaterMontant(defaut)} FCFA)`}
                    onClick={() => onChange(chemin, defaut)}
                >
                    <i className="ri-arrow-go-back-line" />
                </Button>
            )}
        </InputGroup>
    );
};

interface CarteGrilleStageEcoleProps {
    cle: string;
    valeur: GrilleStageEcole;
    defaut?: GrilleStageEcole;
    modifiable: boolean;
    onChange: (chemin: (string | number)[], valeur: number) => void;
}

/** Une grille de stage école : mois plein, premier mois, dernier mois (par durée) et ajustement 1,5 mois. */
const CarteGrilleStageEcole = ({ cle, valeur, defaut, modifiable, onChange }: CarteGrilleStageEcoleProps) => (
    <Card className="border">
        <CardHeader className="bg-light-subtle">
            <h6 className="mb-0 fs-14">{LIBELLE_GRILLE_STAGE_ECOLE[cle] ?? cle}</h6>
        </CardHeader>
        <CardBody>
            <Row className="g-4">
                <Col md={4}>
                    <ChampMontant
                        label="Mois plein (mois intermédiaire)"
                        chemin={['stage_ecole', cle, 'base']}
                        valeur={valeur.base}
                        defaut={defaut?.base}
                        modifiable={modifiable}
                        onChange={onChange}
                    />
                    {valeur.middle_adjustment_1_5 && (
                        <>
                            <Label className="form-label fs-13 mb-1">
                                Ajustement mois intermédiaire (stage de 1,5 mois)
                            </Label>
                            <GrilleJour
                                valeur={valeur.middle_adjustment_1_5}
                                defaut={defaut?.middle_adjustment_1_5}
                                chemin={['stage_ecole', cle, 'middle_adjustment_1_5']}
                                modifiable={modifiable}
                                onChange={onChange}
                            />
                        </>
                    )}
                </Col>
                <Col md={4}>
                    <Label className="form-label fs-13 mb-1">Premier mois (démarrage)</Label>
                    <GrilleJour
                        valeur={valeur.first_month}
                        defaut={defaut?.first_month}
                        chemin={['stage_ecole', cle, 'first_month']}
                        modifiable={modifiable}
                        onChange={onChange}
                    />
                </Col>
                <Col md={4}>
                    <Label className="form-label fs-13 mb-1">Dernier mois, selon la durée du contrat</Label>
                    {Object.keys(valeur.last_month).map((duree) => (
                        <div key={duree} className="mb-2">
                            <div className="text-muted fs-11 text-uppercase mb-1">{libelleDuree(duree)}</div>
                            <GrilleJour
                                valeur={valeur.last_month[duree]}
                                defaut={defaut?.last_month?.[duree]}
                                chemin={['stage_ecole', cle, 'last_month', duree]}
                                modifiable={modifiable}
                                onChange={onChange}
                            />
                        </div>
                    ))}
                </Col>
            </Row>
        </CardBody>
    </Card>
);

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

const ONGLETS = [
    { id: 'qualification', label: 'Stage de qualification', icone: 'ri-graduation-cap-line' },
    { id: 'stage_ecole', label: 'Stage école', icone: 'ri-book-open-line' },
    { id: 'smig', label: 'Repli SMIG', icone: 'ri-shield-line' },
] as const;

const Index = ({ configuration, defauts, strategies, personnalise, derniereModification, peutGerer }: Props) => {
    const bareme = useForm<{ configuration: Bareme }>({ configuration });
    const [ongletActif, setOngletActif] = useState<(typeof ONGLETS)[number]['id']>('qualification');

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
    const [stagiaireChoisi, setStagiaireChoisi] = useState<OptionStagiaire | null>(null);

    const choisirStagiaire = (option: OptionStagiaire | null) => {
        setStagiaireChoisi(option);

        if (!option) {
            return;
        }

        setSaisie({
            ...saisie,
            type_stage_legacy_id: option.type_stage_legacy_id ? String(option.type_stage_legacy_id) : saisie.type_stage_legacy_id,
            source_financement_legacy_id: option.source_financement_legacy_id
                ? String(option.source_financement_legacy_id)
                : saisie.source_financement_legacy_id,
            type_structure_legacy_id: option.type_structure_legacy_id ? String(option.type_structure_legacy_id) : '',
            date_debut: option.date_debut ?? saisie.date_debut,
            date_fin: option.date_fin ?? saisie.date_fin,
            nom_entreprise: option.nom_entreprise ?? saisie.nom_entreprise,
        });
    };

    const majValeur = (chemin: (string | number)[], valeur: unknown) => {
        bareme.setData('configuration', remplacer(bareme.data.configuration, chemin, valeur));
    };

    const nombreChampsModifies = (() => {
        const compter = (a: unknown, b: unknown): number => {
            if (a === null || typeof a !== 'object') {
                return a !== b ? 1 : 0;
            }

            if (Array.isArray(a)) {
                return a.reduce((total, item, index) => total + compter(item, (b as unknown[] | undefined)?.[index]), 0);
            }

            return Object.entries(a as Record<string, unknown>).reduce(
                (total, [cle, valeur]) => total + compter(valeur, (b as Record<string, unknown> | undefined)?.[cle]),
                0,
            );
        };

        return compter(bareme.data.configuration, defauts);
    })();

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

    const config = bareme.data.configuration;

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
                                    (quelle grille s’applique à quel financement) restent portées par le code ; seules
                                    leurs valeurs sont modifiables ici. Une modification ne recalcule pas les
                                    paiements déjà émis.
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
                                                    {nombreChampsModifies > 0 && (
                                                        <Badge color="warning" className="bg-warning-subtle text-warning ms-1">
                                                            {nombreChampsModifies} valeur{nombreChampsModifies > 1 ? 's' : ''} modifiée
                                                            {nombreChampsModifies > 1 ? 's' : ''}
                                                        </Badge>
                                                    )}
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

                                    <Nav tabs className="nav-tabs-custom px-3 pt-2">
                                        {ONGLETS.map((onglet) => (
                                            <NavItem key={onglet.id}>
                                                <NavLink
                                                    style={{ cursor: 'pointer' }}
                                                    className={classnames('fw-semibold', { active: ongletActif === onglet.id })}
                                                    onClick={() => setOngletActif(onglet.id)}
                                                >
                                                    <i className={`${onglet.icone} align-bottom me-1`} />
                                                    {onglet.label}
                                                </NavLink>
                                            </NavItem>
                                        ))}
                                    </Nav>

                                    <CardBody>
                                        {bareme.errors.configuration && (
                                            <Alert color="danger">{bareme.errors.configuration}</Alert>
                                        )}

                                        <TabContent activeTab={ongletActif}>
                                            <TabPane tabId="qualification">
                                                <p className="text-muted fs-13">
                                                    Grille appliquée selon le montant de référence retenu pour le
                                                    stage (financement, structure et date de démarrage) : chaque
                                                    grille reste éditable indépendamment.
                                                </p>
                                                <ChampDate
                                                    label="Entrée en vigueur des grilles Budget État"
                                                    chemin={['qualification', 'budget_aej_effective_date']}
                                                    valeur={config.qualification.budget_aej_effective_date}
                                                    defaut={defauts.qualification?.budget_aej_effective_date}
                                                    modifiable={peutGerer}
                                                    onChange={majValeur}
                                                />
                                                <Row className="g-3">
                                                    {Object.keys(config.pae).map((montantCle) => (
                                                        <Col md={6} key={montantCle}>
                                                            <CarteGrillePae
                                                                montantCle={montantCle}
                                                                valeur={config.pae[montantCle]}
                                                                defaut={defauts.pae?.[montantCle]}
                                                                modifiable={peutGerer}
                                                                onChange={majValeur}
                                                            />
                                                        </Col>
                                                    ))}
                                                </Row>
                                            </TabPane>

                                            <TabPane tabId="stage_ecole">
                                                <ChampDate
                                                    label="Entrée en vigueur des grilles Budget État"
                                                    chemin={['stage_ecole', 'budget_aej_effective_date']}
                                                    valeur={config.stage_ecole.budget_aej_effective_date}
                                                    defaut={defauts.stage_ecole?.budget_aej_effective_date as string | undefined}
                                                    modifiable={peutGerer}
                                                    onChange={majValeur}
                                                />
                                                <div className="d-flex flex-column gap-3">
                                                    {Object.keys(config.stage_ecole)
                                                        .filter((cle) => !['budget_aej_effective_date', 'budget_aej_periods'].includes(cle))
                                                        .map((cle) => (
                                                            <CarteGrilleStageEcole
                                                                key={cle}
                                                                cle={cle}
                                                                valeur={config.stage_ecole[cle] as GrilleStageEcole}
                                                                defaut={defauts.stage_ecole?.[cle] as GrilleStageEcole | undefined}
                                                                modifiable={peutGerer}
                                                                onChange={majValeur}
                                                            />
                                                        ))}
                                                </div>
                                            </TabPane>

                                            <TabPane tabId="smig">
                                                <p className="text-muted fs-13">
                                                    Montant versé lorsqu’aucune grille spécialisée ne reconnaît le
                                                    stage — un filet de sécurité, pas une règle destinée à être
                                                    déclenchée en usage normal.
                                                </p>
                                                <Col md={4}>
                                                    <ChampMontant
                                                        label="Montant du repli SMIG"
                                                        chemin={['smig', 'default']}
                                                        valeur={config.smig.default}
                                                        defaut={defauts.smig?.default}
                                                        modifiable={peutGerer}
                                                        onChange={majValeur}
                                                    />
                                                </Col>
                                            </TabPane>
                                        </TabContent>
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
                                            <Label className="form-label">Stagiaire (optionnel)</Label>
                                            <AsyncSelect
                                                loadOptions={chargerOptionsStagiaires}
                                                value={stagiaireChoisi}
                                                onChange={(option) => choisirStagiaire(option as OptionStagiaire | null)}
                                                placeholder="Rechercher un stagiaire (nom ou n° AEJ)..."
                                                noOptionsMessage={({ inputValue }) =>
                                                    inputValue.length < 2 ? 'Saisissez au moins 2 caractères' : 'Aucun stagiaire trouvé'
                                                }
                                                loadingMessage={() => 'Recherche...'}
                                                isClearable
                                                cacheOptions
                                                defaultOptions={[]}
                                                classNamePrefix="react-select"
                                            />
                                            <div className="form-text">
                                                Sélectionner un stagiaire préremplit le formulaire avec son dossier.
                                            </div>
                                        </div>
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
                                        <Button color="secondary" type="submit" disabled={simulationEnCours} className="w-100">
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
