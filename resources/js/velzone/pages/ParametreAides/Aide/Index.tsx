import { Head, Link } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    Col,
    Container,
    Input,
    Nav,
    NavItem,
    NavLink,
    Offcanvas,
    OffcanvasBody,
    OffcanvasHeader,
    Row,
    TabContent,
    Table,
    TabPane,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Lien {
    libelle: string;
    href: string;
}

interface Detail {
    titre: string;
    description: string;
}

interface FaqItem {
    module?: string;
    question: string;
    reponse: string;
}

interface Rubrique {
    id: string;
    titre: string;
    icone: string;
    resume: string;
    utilisateurs: string[];
    routes: string[];
    ecrans: string[];
    details: Detail[];
    actions: string[];
    workflow: string[];
    statuts: string[];
    contraintes: string[];
    erreurs: string[];
    faq: FaqItem[];
    liens: Lien[];
    sources: string[];
    confiance: string;
}

interface RoleGuide {
    nom: string;
    mission: string;
    peut: string[];
    nePeutPas: string[];
    modules: string[];
    sources: string[];
}

interface WorkflowGuide {
    nom: string;
    objectif: string;
    etapes: {
        acteur: string;
        action: string;
        avant: string;
        apres: string;
        condition: string;
    }[];
    sources: string[];
}

interface Props {
    synthese: {
        titre: string;
        description: string;
        modules: number;
        roles: number;
        workflows: number;
        articles: number;
        regles: number;
        sources: number;
    };
    architecture: {
        technologies: string[];
        navigation: string[];
        dossiersAnalyses: string[];
    };
    rubriques: Rubrique[];
    roles: RoleGuide[];
    workflows: WorkflowGuide[];
    matriceFonctionnelle: Record<string, string>[];
    matricePermissions: Record<string, string>[];
    matriceWorkflows: Record<string, string>[];
    faq: FaqItem[];
    erreurs: {
        symptome: string;
        cause: string;
        condition: string;
        solution: string;
    }[];
    reglesMetier: { regle: string; confiance: string; sources: string }[];
    statuts: { domaine: string; statuts: string[] }[];
    glossaire: { terme: string; definition: string; module: string }[];
    aideContextuelle: unknown[];
    baseConnaissances: unknown[];
    intents: unknown[];
    indexRecherche: unknown[];
    audit: {
        incoherences: {
            probleme: string;
            impact: string;
            fichiers: string;
            confiance: string;
            recommandation: string;
        }[];
        nonDocumentables: {
            sujet: string;
            ceQueLeCodeMontre: string;
            ambiguite: string;
            informationNecessaire: string;
        }[];
        tracabilite: { regle: string; sources: string }[];
        sourcesAnalysees: string[];
    };
}

const tabs = [
    { id: 'guides', label: 'Guides', icon: 'ri-book-open-line' },
    { id: 'roles', label: 'Rôles', icon: 'ri-shield-user-line' },
    { id: 'workflows', label: 'Workflows', icon: 'ri-git-branch-line' },
    { id: 'faq', label: 'FAQ', icon: 'ri-question-answer-line' },
    { id: 'erreurs', label: 'Erreurs', icon: 'ri-error-warning-line' },
    { id: 'ia', label: 'Base IA', icon: 'ri-brain-line' },
    { id: 'audit', label: 'Audit', icon: 'ri-search-eye-line' },
];

const pillClass = (confiance: string) => {
    if (confiance === 'elevee') {
        return 'success';
    }

    if (confiance === 'moyenne') {
        return 'warning';
    }

    return 'secondary';
};

const textSearch = (rubrique: Rubrique) =>
    [
        rubrique.titre,
        rubrique.resume,
        ...rubrique.utilisateurs,
        ...rubrique.routes,
        ...rubrique.ecrans,
        ...rubrique.actions,
        ...rubrique.workflow,
        ...rubrique.statuts,
        ...rubrique.contraintes,
        ...rubrique.erreurs,
        ...rubrique.details.flatMap((detail) => [
            detail.titre,
            detail.description,
        ]),
        ...rubrique.faq.flatMap((item) => [item.question, item.reponse]),
        ...rubrique.sources,
    ].join(' ');

const MiniList = ({
    title,
    items,
    icon = 'ri-checkbox-circle-line',
}: {
    title: string;
    items: string[];
    icon?: string;
}) => (
    <div>
        <h6 className="text-uppercase small fw-semibold mb-2 text-muted">
            {title}
        </h6>
        <div className="vstack gap-2">
            {items.map((item) => (
                <div className="d-flex align-items-start gap-2" key={item}>
                    <i className={`${icon} text-success mt-1`} />
                    <span className="small text-dark">{item}</span>
                </div>
            ))}
        </div>
    </div>
);

const CodePreview = ({ title, data }: { title: string; data: unknown }) => (
    <Card className="h-100 border-0 shadow-sm">
        <CardBody>
            <h6 className="mb-3">{title}</h6>
            <pre className="bg-light small mb-0 overflow-auto rounded p-3">
                {JSON.stringify(data, null, 2)}
            </pre>
        </CardBody>
    </Card>
);

const Index = ({
    synthese,
    architecture,
    rubriques,
    roles,
    workflows,
    matriceFonctionnelle,
    matricePermissions,
    matriceWorkflows,
    faq,
    erreurs,
    reglesMetier,
    statuts,
    glossaire,
    aideContextuelle,
    baseConnaissances,
    intents,
    indexRecherche,
    audit,
}: Props) => {
    const [recherche, setRecherche] = useState('');
    const [categorieActive, setCategorieActive] = useState<string | null>(null);
    const [tabActive, setTabActive] = useState('guides');
    const [rubriqueOuverte, setRubriqueOuverte] = useState<Rubrique | null>(
        null,
    );

    const terme = recherche.trim().toLocaleLowerCase();
    const rubriquesFiltrees = useMemo(
        () =>
            rubriques.filter((rubrique) => {
                if (
                    categorieActive !== null &&
                    rubrique.id !== categorieActive
                ) {
                    return false;
                }

                if (!terme) {
                    return true;
                }

                return textSearch(rubrique).toLocaleLowerCase().includes(terme);
            }),
        [categorieActive, rubriques, terme],
    );

    return (
        <React.Fragment>
            <Head title="Aide / Guide utilisateur" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb
                        title="Aide / Guide utilisateur"
                        pageTitle="Parametre & Aides"
                    />

                    <Card className="bg-primary-subtle mb-4 border-0">
                        <CardBody className="p-4">
                            <div className="d-flex align-items-start justify-content-between flex-wrap gap-3">
                                <div>
                                    <h4 className="mb-1">{synthese.titre}</h4>
                                    <p className="mb-0 text-muted">
                                        {synthese.description}
                                    </p>
                                </div>
                                {(recherche || categorieActive) && (
                                    <Button
                                        color="soft-primary"
                                        onClick={() => {
                                            setRecherche('');
                                            setCategorieActive(null);
                                        }}
                                    >
                                        <i className="ri-restart-line me-1 align-bottom" />
                                        Réinitialiser
                                    </Button>
                                )}
                            </div>

                            <Row className="g-3 mt-3">
                                {[
                                    ['Modules', synthese.modules],
                                    ['Rôles', synthese.roles],
                                    ['Workflows', synthese.workflows],
                                    ['Articles', synthese.articles],
                                    ['Règles', synthese.regles],
                                    ['Sources', synthese.sources],
                                ].map(([label, value]) => (
                                    <Col md={2} sm={4} xs={6} key={label}>
                                        <div className="rounded border bg-white p-3 text-center">
                                            <div className="fs-4 fw-semibold text-primary">
                                                {value}
                                            </div>
                                            <div className="small text-muted">
                                                {label}
                                            </div>
                                        </div>
                                    </Col>
                                ))}
                            </Row>

                            <div className="position-relative mt-3">
                                <Input
                                    id="recherche-aide"
                                    type="search"
                                    bsSize="lg"
                                    className="ps-5"
                                    placeholder="Rechercher une route, un rôle, un statut, une erreur..."
                                    aria-label="Rechercher dans le centre d aide"
                                    value={recherche}
                                    onChange={(event) =>
                                        setRecherche(event.target.value)
                                    }
                                />
                                <i className="ri-search-line position-absolute translate-middle-y fs-18 start-0 top-50 ms-3 text-muted" />
                            </div>
                        </CardBody>
                    </Card>

                    <Nav tabs className="nav-tabs-custom mb-4">
                        {tabs.map((tab) => (
                            <NavItem key={tab.id}>
                                <NavLink
                                    className={
                                        tabActive === tab.id ? 'active' : ''
                                    }
                                    role="button"
                                    onClick={() => setTabActive(tab.id)}
                                >
                                    <i className={`${tab.icon} me-1`} />
                                    {tab.label}
                                </NavLink>
                            </NavItem>
                        ))}
                    </Nav>

                    <TabContent activeTab={tabActive}>
                        <TabPane tabId="guides">
                            <Row className="g-4">
                                <Col lg={3}>
                                    <Card
                                        className="position-sticky border-0 shadow-sm"
                                        style={{ top: '5rem' }}
                                    >
                                        <CardBody className="p-0">
                                            <div className="list-group list-group-flush">
                                                <button
                                                    type="button"
                                                    className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${categorieActive === null ? 'active' : ''}`}
                                                    onClick={() =>
                                                        setCategorieActive(null)
                                                    }
                                                >
                                                    <span>
                                                        <i className="ri-apps-2-line me-2" />
                                                        Toutes les rubriques
                                                    </span>
                                                    <span className="badge bg-light text-body">
                                                        {rubriques.length}
                                                    </span>
                                                </button>
                                                {rubriques.map((rubrique) => (
                                                    <button
                                                        type="button"
                                                        key={rubrique.id}
                                                        className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${categorieActive === rubrique.id ? 'active' : ''}`}
                                                        onClick={() =>
                                                            setCategorieActive(
                                                                rubrique.id,
                                                            )
                                                        }
                                                    >
                                                        <span className="text-start">
                                                            <i
                                                                className={`${rubrique.icone} me-2`}
                                                            />
                                                            {rubrique.titre}
                                                        </span>
                                                        <span className="badge bg-light text-body">
                                                            {
                                                                rubrique.details
                                                                    .length
                                                            }
                                                        </span>
                                                    </button>
                                                ))}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>

                                <Col lg={9}>
                                    <Row className="g-4">
                                        {rubriquesFiltrees.map((rubrique) => (
                                            <Col xl={6} key={rubrique.id}>
                                                <Card className="h-100 border-0 shadow-sm">
                                                    <CardBody className="p-4">
                                                        <div className="d-flex align-items-start mb-4 gap-3">
                                                            <span className="avatar-md rounded-3 bg-primary-subtle d-inline-flex align-items-center justify-content-center fs-24 flex-shrink-0 text-primary">
                                                                <i
                                                                    className={
                                                                        rubrique.icone
                                                                    }
                                                                />
                                                            </span>
                                                            <div className="flex-grow-1">
                                                                <div className="d-flex align-items-center justify-content-between gap-2">
                                                                    <h5 className="mb-1">
                                                                        {
                                                                            rubrique.titre
                                                                        }
                                                                    </h5>
                                                                    <Badge
                                                                        color={pillClass(
                                                                            rubrique.confiance,
                                                                        )}
                                                                        pill
                                                                    >
                                                                        {
                                                                            rubrique.confiance
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                                <p className="small mb-0 text-muted">
                                                                    {
                                                                        rubrique.resume
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div className="d-flex mb-3 flex-wrap gap-2">
                                                            {rubrique.utilisateurs.map(
                                                                (role) => (
                                                                    <Badge
                                                                        color="light"
                                                                        className="text-body border"
                                                                        key={
                                                                            role
                                                                        }
                                                                    >
                                                                        {role}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>

                                                        <div className="vstack mb-4 gap-3">
                                                            {rubrique.details
                                                                .slice(0, 3)
                                                                .map(
                                                                    (
                                                                        detail,
                                                                    ) => (
                                                                        <div
                                                                            className="d-flex align-items-start gap-2"
                                                                            key={
                                                                                detail.titre
                                                                            }
                                                                        >
                                                                            <i className="ri-checkbox-circle-line text-success mt-1" />
                                                                            <div>
                                                                                <div className="small fw-semibold text-dark">
                                                                                    {
                                                                                        detail.titre
                                                                                    }
                                                                                </div>
                                                                                <div className="small text-muted">
                                                                                    {
                                                                                        detail.description
                                                                                    }
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    ),
                                                                )}
                                                        </div>

                                                        <div className="border-top d-flex flex-wrap gap-2 pt-3">
                                                            <Button
                                                                color="soft-primary"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setRubriqueOuverte(
                                                                        rubrique,
                                                                    )
                                                                }
                                                            >
                                                                Voir le détail
                                                                <i className="ri-layout-right-line ms-1 align-bottom" />
                                                            </Button>
                                                            {rubrique.liens.map(
                                                                (lien) => (
                                                                    <Link
                                                                        key={
                                                                            lien.href
                                                                        }
                                                                        href={
                                                                            lien.href
                                                                        }
                                                                        className="btn btn-soft-primary btn-sm"
                                                                    >
                                                                        {
                                                                            lien.libelle
                                                                        }
                                                                        <i className="ri-arrow-up-right-line ms-1 align-bottom" />
                                                                    </Link>
                                                                ),
                                                            )}
                                                        </div>
                                                    </CardBody>
                                                </Card>
                                            </Col>
                                        ))}
                                        {rubriquesFiltrees.length === 0 && (
                                            <Col lg={12}>
                                                <Alert color="warning">
                                                    Aucun guide ne correspond à
                                                    la recherche.
                                                </Alert>
                                            </Col>
                                        )}
                                    </Row>
                                </Col>
                            </Row>
                        </TabPane>

                        <TabPane tabId="roles">
                            <Row className="g-4">
                                {roles.map((role) => (
                                    <Col xl={6} key={role.nom}>
                                        <Card className="h-100 border-0 shadow-sm">
                                            <CardBody>
                                                <h5 className="mb-1">
                                                    {role.nom}
                                                </h5>
                                                <p className="text-muted">
                                                    {role.mission}
                                                </p>
                                                <Row className="g-3">
                                                    <Col md={6}>
                                                        <MiniList
                                                            title="Peut faire"
                                                            items={role.peut}
                                                        />
                                                    </Col>
                                                    <Col md={6}>
                                                        <MiniList
                                                            title="Ne peut pas"
                                                            items={
                                                                role.nePeutPas
                                                            }
                                                            icon="ri-close-circle-line"
                                                        />
                                                    </Col>
                                                </Row>
                                                <div className="d-flex mt-3 flex-wrap gap-2">
                                                    {role.modules.map(
                                                        (module) => (
                                                            <Badge
                                                                color="primary"
                                                                pill
                                                                key={module}
                                                            >
                                                                {module}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                                <div className="small mt-3 text-muted">
                                                    Sources :{' '}
                                                    {role.sources.join(', ')}
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>

                            <Card className="mt-4 border-0 shadow-sm">
                                <CardBody>
                                    <h5 className="mb-3">
                                        Matrice des permissions
                                    </h5>
                                    <div className="table-responsive">
                                        <Table hover className="align-middle">
                                            <thead>
                                                <tr>
                                                    {Object.keys(
                                                        matricePermissions[0] ||
                                                            {},
                                                    ).map((key) => (
                                                        <th key={key}>{key}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {matricePermissions.map(
                                                    (row, index) => (
                                                        <tr key={index}>
                                                            {Object.values(
                                                                row,
                                                            ).map(
                                                                (
                                                                    value,
                                                                    cellIndex,
                                                                ) => (
                                                                    <td
                                                                        key={
                                                                            cellIndex
                                                                        }
                                                                        className="small"
                                                                    >
                                                                        {value}
                                                                    </td>
                                                                ),
                                                            )}
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </TabPane>

                        <TabPane tabId="workflows">
                            <Row className="g-4">
                                {workflows.map((workflow) => (
                                    <Col lg={12} key={workflow.nom}>
                                        <Card className="border-0 shadow-sm">
                                            <CardBody>
                                                <h5 className="mb-1">
                                                    {workflow.nom}
                                                </h5>
                                                <p className="text-muted">
                                                    {workflow.objectif}
                                                </p>
                                                <div className="table-responsive">
                                                    <Table className="align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>Acteur</th>
                                                                <th>Action</th>
                                                                <th>
                                                                    Statut avant
                                                                </th>
                                                                <th>
                                                                    Statut après
                                                                </th>
                                                                <th>
                                                                    Condition
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {workflow.etapes.map(
                                                                (
                                                                    etape,
                                                                    index,
                                                                ) => (
                                                                    <tr
                                                                        key={
                                                                            index
                                                                        }
                                                                    >
                                                                        <td>
                                                                            {
                                                                                etape.acteur
                                                                            }
                                                                        </td>
                                                                        <td>
                                                                            {
                                                                                etape.action
                                                                            }
                                                                        </td>
                                                                        <td>
                                                                            <Badge
                                                                                color="light"
                                                                                className="text-body border"
                                                                            >
                                                                                {
                                                                                    etape.avant
                                                                                }
                                                                            </Badge>
                                                                        </td>
                                                                        <td>
                                                                            <Badge color="primary">
                                                                                {
                                                                                    etape.apres
                                                                                }
                                                                            </Badge>
                                                                        </td>
                                                                        <td className="small">
                                                                            {
                                                                                etape.condition
                                                                            }
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )}
                                                        </tbody>
                                                    </Table>
                                                </div>
                                                <div className="small text-muted">
                                                    Sources :{' '}
                                                    {workflow.sources.join(
                                                        ', ',
                                                    )}
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>

                            <Card className="mt-4 border-0 shadow-sm">
                                <CardBody>
                                    <h5 className="mb-3">
                                        Matrice fonctionnelle
                                    </h5>
                                    <div className="table-responsive">
                                        <Table hover className="align-middle">
                                            <thead>
                                                <tr>
                                                    {Object.keys(
                                                        matriceFonctionnelle[0] ||
                                                            {},
                                                    ).map((key) => (
                                                        <th key={key}>{key}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {matriceFonctionnelle.map(
                                                    (row, index) => (
                                                        <tr key={index}>
                                                            {Object.values(
                                                                row,
                                                            ).map(
                                                                (
                                                                    value,
                                                                    cellIndex,
                                                                ) => (
                                                                    <td
                                                                        key={
                                                                            cellIndex
                                                                        }
                                                                        className="small"
                                                                    >
                                                                        {value}
                                                                    </td>
                                                                ),
                                                            )}
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>

                            <CodePreview
                                title="Matrice workflow structurée"
                                data={matriceWorkflows}
                            />
                        </TabPane>

                        <TabPane tabId="faq">
                            <Row className="g-4">
                                {faq.map((item) => (
                                    <Col lg={6} key={item.question}>
                                        <Card className="h-100 border-0 shadow-sm">
                                            <CardBody>
                                                <Badge
                                                    color="primary"
                                                    className="mb-2"
                                                >
                                                    {item.module}
                                                </Badge>
                                                <h6>{item.question}</h6>
                                                <p className="mb-0 text-muted">
                                                    {item.reponse}
                                                </p>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>
                        </TabPane>

                        <TabPane tabId="erreurs">
                            <Row className="g-4">
                                <Col lg={7}>
                                    <Card className="border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Erreurs fréquentes
                                            </h5>
                                            <div className="table-responsive">
                                                <Table className="align-middle">
                                                    <thead>
                                                        <tr>
                                                            <th>Symptôme</th>
                                                            <th>Cause</th>
                                                            <th>Solution</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {erreurs.map(
                                                            (erreur) => (
                                                                <tr
                                                                    key={
                                                                        erreur.symptome
                                                                    }
                                                                >
                                                                    <td className="fw-semibold">
                                                                        {
                                                                            erreur.symptome
                                                                        }
                                                                    </td>
                                                                    <td className="small">
                                                                        {
                                                                            erreur.cause
                                                                        }
                                                                        <div className="mt-1 text-muted">
                                                                            {
                                                                                erreur.condition
                                                                            }
                                                                        </div>
                                                                    </td>
                                                                    <td className="small">
                                                                        {
                                                                            erreur.solution
                                                                        }
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col lg={5}>
                                    <Card className="border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Règles métier
                                            </h5>
                                            <div className="vstack gap-3">
                                                {reglesMetier.map((regle) => (
                                                    <div
                                                        className="border-bottom pb-3"
                                                        key={regle.regle}
                                                    >
                                                        <div className="fw-semibold">
                                                            {regle.regle}
                                                        </div>
                                                        <div className="small mt-1 text-muted">
                                                            {regle.sources}
                                                        </div>
                                                        <Badge
                                                            color={pillClass(
                                                                regle.confiance,
                                                            )}
                                                            className="mt-2"
                                                        >
                                                            {regle.confiance}
                                                        </Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                            </Row>

                            <Row className="g-4 mt-1">
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">Statuts</h5>
                                            <div className="vstack gap-3">
                                                {statuts.map((groupe) => (
                                                    <div key={groupe.domaine}>
                                                        <div className="small fw-semibold mb-2">
                                                            {groupe.domaine}
                                                        </div>
                                                        <div className="d-flex flex-wrap gap-2">
                                                            {groupe.statuts.map(
                                                                (statut) => (
                                                                    <Badge
                                                                        color="light"
                                                                        className="text-body border"
                                                                        key={
                                                                            statut
                                                                        }
                                                                    >
                                                                        {statut}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">Glossaire</h5>
                                            <div className="table-responsive">
                                                <Table className="align-middle">
                                                    <tbody>
                                                        {glossaire.map(
                                                            (item) => (
                                                                <tr
                                                                    key={
                                                                        item.terme
                                                                    }
                                                                >
                                                                    <td className="fw-semibold">
                                                                        {
                                                                            item.terme
                                                                        }
                                                                    </td>
                                                                    <td className="small">
                                                                        {
                                                                            item.definition
                                                                        }
                                                                        <div className="text-muted">
                                                                            {
                                                                                item.module
                                                                            }
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                            </Row>
                        </TabPane>

                        <TabPane tabId="ia">
                            <Row className="g-4">
                                <Col lg={6}>
                                    <CodePreview
                                        title="Base de connaissances"
                                        data={baseConnaissances}
                                    />
                                </Col>
                                <Col lg={6}>
                                    <CodePreview
                                        title="Aide contextuelle"
                                        data={aideContextuelle}
                                    />
                                </Col>
                                <Col lg={6}>
                                    <CodePreview
                                        title="Intentions chatbot"
                                        data={intents}
                                    />
                                </Col>
                                <Col lg={6}>
                                    <CodePreview
                                        title="Index de recherche"
                                        data={indexRecherche}
                                    />
                                </Col>
                            </Row>
                        </TabPane>

                        <TabPane tabId="audit">
                            <Row className="g-4">
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Architecture fonctionnelle
                                            </h5>
                                            <MiniList
                                                title="Technologies"
                                                items={
                                                    architecture.technologies
                                                }
                                            />
                                            <div className="mt-4">
                                                <MiniList
                                                    title="Navigation"
                                                    items={
                                                        architecture.navigation
                                                    }
                                                    icon="ri-compass-3-line"
                                                />
                                            </div>
                                            <div className="mt-4">
                                                <MiniList
                                                    title="Dossiers analysés"
                                                    items={
                                                        architecture.dossiersAnalyses
                                                    }
                                                    icon="ri-folder-search-line"
                                                />
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Incohérences détectées
                                            </h5>
                                            <div className="vstack gap-3">
                                                {audit.incoherences.map(
                                                    (item) => (
                                                        <Alert
                                                            color="warning"
                                                            className="mb-0"
                                                            key={item.probleme}
                                                        >
                                                            <div className="fw-semibold">
                                                                {item.probleme}
                                                            </div>
                                                            <div className="small">
                                                                {item.impact}
                                                            </div>
                                                            <div className="small mt-2 text-muted">
                                                                {item.fichiers}
                                                            </div>
                                                            <div className="small mt-2">
                                                                {
                                                                    item.recommandation
                                                                }
                                                            </div>
                                                        </Alert>
                                                    ),
                                                )}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Points à confirmer
                                            </h5>
                                            <div className="vstack gap-3">
                                                {audit.nonDocumentables.map(
                                                    (item) => (
                                                        <div
                                                            className="border-bottom pb-3"
                                                            key={item.sujet}
                                                        >
                                                            <div className="fw-semibold">
                                                                {item.sujet}
                                                            </div>
                                                            <div className="small text-muted">
                                                                {
                                                                    item.ceQueLeCodeMontre
                                                                }
                                                            </div>
                                                            <div className="small mt-2">
                                                                {item.ambiguite}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col lg={6}>
                                    <Card className="h-100 border-0 shadow-sm">
                                        <CardBody>
                                            <h5 className="mb-3">
                                                Traçabilité technique
                                            </h5>
                                            <div className="vstack gap-2">
                                                {audit.tracabilite.map(
                                                    (item) => (
                                                        <div
                                                            className="rounded border p-3"
                                                            key={item.regle}
                                                        >
                                                            <div className="fw-semibold">
                                                                {item.regle}
                                                            </div>
                                                            <div className="small text-muted">
                                                                {item.sources}
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                            </Row>
                        </TabPane>
                    </TabContent>

                    <Offcanvas
                        isOpen={rubriqueOuverte !== null}
                        toggle={() => setRubriqueOuverte(null)}
                        direction="end"
                        className="offcanvas-width-xxl"
                    >
                        <OffcanvasHeader
                            toggle={() => setRubriqueOuverte(null)}
                        >
                            {rubriqueOuverte && (
                                <div className="d-flex align-items-center gap-2">
                                    <span className="avatar-sm rounded-3 bg-primary-subtle d-inline-flex align-items-center justify-content-center text-primary">
                                        <i className={rubriqueOuverte.icone} />
                                    </span>
                                    <span>{rubriqueOuverte.titre}</span>
                                </div>
                            )}
                        </OffcanvasHeader>
                        <OffcanvasBody>
                            {rubriqueOuverte && (
                                <div className="vstack gap-4">
                                    <p className="mb-0 text-muted">
                                        {rubriqueOuverte.resume}
                                    </p>
                                    <MiniList
                                        title="Écrans"
                                        items={rubriqueOuverte.ecrans}
                                        icon="ri-window-line"
                                    />
                                    <MiniList
                                        title="Actions"
                                        items={rubriqueOuverte.actions}
                                    />
                                    <MiniList
                                        title="Workflow"
                                        items={rubriqueOuverte.workflow}
                                        icon="ri-git-branch-line"
                                    />
                                    <MiniList
                                        title="Contraintes"
                                        items={rubriqueOuverte.contraintes}
                                        icon="ri-pushpin-line"
                                    />
                                    <MiniList
                                        title="Erreurs possibles"
                                        items={rubriqueOuverte.erreurs}
                                        icon="ri-error-warning-line"
                                    />
                                    <div>
                                        <h6 className="text-uppercase small fw-semibold mb-2 text-muted">
                                            Statuts
                                        </h6>
                                        <div className="d-flex flex-wrap gap-2">
                                            {rubriqueOuverte.statuts.map(
                                                (statut) => (
                                                    <Badge
                                                        color="light"
                                                        className="text-body border"
                                                        key={statut}
                                                    >
                                                        {statut}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <h6 className="text-uppercase small fw-semibold mb-2 text-muted">
                                            FAQ
                                        </h6>
                                        <div className="vstack gap-3">
                                            {rubriqueOuverte.faq.map((item) => (
                                                <div
                                                    className="rounded border p-3"
                                                    key={item.question}
                                                >
                                                    <div className="fw-semibold">
                                                        {item.question}
                                                    </div>
                                                    <div className="small mt-1 text-muted">
                                                        {item.reponse}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <h6 className="text-uppercase small fw-semibold mb-2 text-muted">
                                            Accès directs
                                        </h6>
                                        <div className="d-flex flex-wrap gap-2">
                                            {rubriqueOuverte.liens.map(
                                                (lien) => (
                                                    <Link
                                                        key={lien.href}
                                                        href={lien.href}
                                                        className="btn btn-primary btn-sm"
                                                    >
                                                        {lien.libelle}
                                                        <i className="ri-arrow-up-right-line ms-1 align-bottom" />
                                                    </Link>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                    <div className="small text-muted">
                                        Sources :{' '}
                                        {rubriqueOuverte.sources.join(', ')}
                                    </div>
                                </div>
                            )}
                        </OffcanvasBody>
                    </Offcanvas>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Index;
