import { Head, Link } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface Module {
    id: string;
    titre: string;
    description: string;
    icone: string;
    couleur: string;
    href: string;
    compteur: number | null;
    libelleCompteur: string | null;
}

interface Props {
    modules: Module[];
}

const STAGGER_MS = 60;

const Index = ({ modules }: Props) => {
    return (
        <React.Fragment>
            <Head title="Parametre & Aides" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Parametre & Aides" pageTitle="Administration" />

                    <Row>
                        <Col lg={12}>
                            <Card className="border-0 shadow-sm">
                                <CardBody className="d-flex align-items-start gap-3">
                                    <div className="flex-shrink-0">
                                        <span className="avatar-title bg-primary-subtle text-primary rounded fs-3 avatar-sm">
                                            <i className="ri-settings-3-line" />
                                        </span>
                                    </div>
                                    <div>
                                        <h5 className="card-title mb-1">Centre d{"'"}administration</h5>
                                        <p className="text-muted mb-0">
                                            Comptes et habilitations, r{"\u00e9"}f{"\u00e9"}rentiels d{"'"}organisation,
                                            param{"\u00e8"}tres de calcul, tra{"\u00e7"}abilit{"\u00e9"} et guide
                                            utilisateur. Seuls les modules autoris{"\u00e9"}s par votre profil sont
                                            affich{"\u00e9"}s.
                                        </p>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row>
                        {modules.map((module, index) => (
                            <Col xl={3} md={6} key={module.id}>
                                <Link href={module.href} className="text-reset">
                                    <Card
                                        className="card-animate card-stagger-fade h-100 border-top border-2"
                                        style={{
                                            borderTopColor: `var(--bs-${module.couleur})`,
                                            animationDelay: `${index * STAGGER_MS}ms`,
                                        }}
                                    >
                                        <CardBody className="d-flex flex-column">
                                            <div className="d-flex align-items-center mb-3">
                                                <div className="flex-shrink-0">
                                                    <span
                                                        className={`avatar-title bg-${module.couleur}-subtle text-${module.couleur} rounded fs-3 avatar-sm`}
                                                    >
                                                        <i className={module.icone} />
                                                    </span>
                                                </div>
                                                <div className="flex-grow-1 ms-3">
                                                    <h6 className="mb-0 fs-15">{module.titre}</h6>
                                                    {module.compteur !== null && (
                                                        <span
                                                            className={`badge bg-${module.couleur}-subtle text-${module.couleur} mt-1`}
                                                        >
                                                            {module.compteur} {module.libelleCompteur}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="text-muted flex-grow-1 mb-3">{module.description}</p>
                                            <span
                                                className={`badge bg-${module.couleur}-subtle text-${module.couleur} fw-medium align-self-start`}
                                            >
                                                Ouvrir <i className="ri-arrow-right-line align-bottom ms-1" />
                                            </span>
                                        </CardBody>
                                    </Card>
                                </Link>
                            </Col>
                        ))}
                        {modules.length === 0 && (
                            <Col lg={12}>
                                <Card>
                                    <CardBody className="text-center text-muted">
                                        Aucun module d{"'"}administration n{"'"}est accessible avec votre profil.
                                    </CardBody>
                                </Card>
                            </Col>
                        )}
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Index;
