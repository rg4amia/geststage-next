import { Head, Link } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Button,
    Card,
    CardBody,
    Col,
    Container,
    Input,
    Offcanvas,
    OffcanvasBody,
    OffcanvasHeader,
    Row,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Rubrique {
    id: string;
    titre: string;
    icone: string;
    resume: string;
    details: { titre: string; description: string }[];
    etapes: string[];
    liens: { libelle: string; href: string }[];
}

interface Props {
    rubriques: Rubrique[];
}

const Index = ({ rubriques }: Props) => {
    const [recherche, setRecherche] = useState('');
    const [categorieActive, setCategorieActive] = useState<string | null>(null);
    const [rubriqueOuverte, setRubriqueOuverte] = useState<Rubrique | null>(
        null,
    );
    const terme = recherche.trim().toLocaleLowerCase();
    const rubriquesFiltrees = rubriques.filter((rubrique) => {
        if (categorieActive !== null && rubrique.id !== categorieActive)
            return false;
        if (!terme) return true;

        return [
            rubrique.titre,
            rubrique.resume,
            ...rubrique.etapes,
            ...rubrique.details.flatMap((detail) => [
                detail.titre,
                detail.description,
            ]),
            ...rubrique.liens.map((lien) => lien.libelle),
        ]
            .join(' ')
            .toLocaleLowerCase()
            .includes(terme);
    });
    const totalGuides = rubriques.reduce(
        (total, rubrique) => total + rubrique.details.length,
        0,
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
                                    <h4 className="mb-1">Centre d’aide</h4>
                                    <p className="mb-0 text-muted">
                                        {totalGuides} fonctionnalités
                                        documentées pour vous guider dans
                                        Geststage.
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
                                        Réinitialiser les filtres
                                    </Button>
                                )}
                            </div>
                            <div className="mt-3">
                                <div className="position-relative">
                                    <Input
                                        id="recherche-aide"
                                        type="search"
                                        bsSize="lg"
                                        className="ps-5"
                                        placeholder="Rechercher une fonctionnalité..."
                                        aria-label="Rechercher une fonctionnalité"
                                        value={recherche}
                                        onChange={(event) =>
                                            setRecherche(event.target.value)
                                        }
                                    />
                                    <i className="ri-search-line position-absolute translate-middle-y fs-18 start-0 top-50 ms-3 text-muted" />
                                </div>
                            </div>
                        </CardBody>
                    </Card>

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
                                                Toutes les fonctionnalités
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
                                                    {rubrique.details.length}
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>

                        <Col lg={9}>
                            <div className="mb-3">
                                <h5 className="mb-1">Guides disponibles</h5>
                                <p className="small mb-0 text-muted">
                                    Ouvrez une fiche pour comprendre le rôle de
                                    chaque écran.
                                </p>
                            </div>
                            <Row className="g-4">
                                {rubriquesFiltrees.map((rubrique, index) => (
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
                                                                {rubrique.titre}
                                                            </h5>
                                                            <span className="small text-muted">
                                                                {String(
                                                                    index + 1,
                                                                ).padStart(
                                                                    2,
                                                                    '0',
                                                                )}
                                                            </span>
                                                        </div>
                                                        <p className="small mb-0 text-muted">
                                                            {rubrique.resume}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="vstack mb-4 gap-3">
                                                    <div className="text-uppercase small fw-semibold text-muted">
                                                        Fonctionnalités clés
                                                    </div>
                                                    {rubrique.details
                                                        .slice(0, 2)
                                                        .map((detail) => (
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
                                                        ))}
                                                    {rubrique.etapes.map(
                                                        (etape, etapeIndex) => (
                                                            <div
                                                                className="d-flex align-items-start gap-3"
                                                                key={etapeIndex}
                                                            >
                                                                <span className="badge rounded-pill bg-light border-primary-subtle border px-2 py-1 text-primary">
                                                                    {String(
                                                                        etapeIndex +
                                                                            1,
                                                                    ).padStart(
                                                                        2,
                                                                        '0',
                                                                    )}
                                                                </span>
                                                                <span className="small text-dark pt-1">
                                                                    {etape}
                                                                </span>
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
                                                        Voir le détail{' '}
                                                        <i className="ri-layout-right-line ms-1 align-bottom" />
                                                    </Button>
                                                    {rubrique.liens.map(
                                                        (lien) => (
                                                            <Link
                                                                key={lien.href}
                                                                href={lien.href}
                                                                className="btn btn-soft-primary btn-sm"
                                                            >
                                                                {lien.libelle}
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
                                        <Card className="border-0 shadow-sm">
                                            <CardBody className="py-5 text-center">
                                                <i className="ri-search-eye-line display-5 text-muted" />
                                                <h5 className="mt-3">
                                                    Aucun parcours trouvé
                                                </h5>
                                                <p className="mb-3 text-muted">
                                                    Essayez un autre mot-clé ou
                                                    effacez la recherche.
                                                </p>
                                                <button
                                                    type="button"
                                                    className="btn btn-soft-primary"
                                                    onClick={() =>
                                                        setRecherche('')
                                                    }
                                                >
                                                    Réinitialiser
                                                </button>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                )}
                            </Row>
                        </Col>
                    </Row>

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
                                <div>
                                    <p className="mb-4 text-muted">
                                        {rubriqueOuverte.resume}
                                    </p>

                                    <h6 className="text-uppercase small fw-semibold mb-3 text-muted">
                                        Fonctionnalités disponibles
                                    </h6>
                                    <div className="vstack mb-4 gap-3">
                                        {rubriqueOuverte.details.map(
                                            (detail) => (
                                                <div
                                                    className="rounded-3 bg-light-subtle border p-3"
                                                    key={detail.titre}
                                                >
                                                    <div className="fw-semibold text-dark">
                                                        <i className="ri-checkbox-circle-line text-success me-2" />
                                                        {detail.titre}
                                                    </div>
                                                    <div className="small mt-1 text-muted">
                                                        {detail.description}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>

                                    <h6 className="text-uppercase small fw-semibold mb-3 text-muted">
                                        Parcours opérationnel
                                    </h6>
                                    <div className="vstack mb-4 gap-3">
                                        {rubriqueOuverte.etapes.map(
                                            (etape, index) => (
                                                <div
                                                    className="d-flex align-items-start gap-3"
                                                    key={index}
                                                >
                                                    <span className="badge rounded-pill bg-primary-subtle text-primary">
                                                        {String(
                                                            index + 1,
                                                        ).padStart(2, '0')}
                                                    </span>
                                                    <span className="small text-dark">
                                                        {etape}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>

                                    <h6 className="text-uppercase small fw-semibold mb-3 text-muted">
                                        Accès directs
                                    </h6>
                                    <div className="d-flex flex-wrap gap-2">
                                        {rubriqueOuverte.liens.map((lien) => (
                                            <Link
                                                key={lien.href}
                                                href={lien.href}
                                                className="btn btn-primary btn-sm"
                                            >
                                                {lien.libelle}
                                                <i className="ri-arrow-up-right-line ms-1 align-bottom" />
                                            </Link>
                                        ))}
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
