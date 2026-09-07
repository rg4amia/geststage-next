import { Head, Link } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Rubrique {
    id: string;
    titre: string;
    icone: string;
    resume: string;
    etapes: string[];
    liens: { libelle: string; href: string }[];
}

interface Props {
    rubriques: Rubrique[];
}

const Index = ({ rubriques }: Props) => (
    <React.Fragment>
        <Head title="Aide / Guide utilisateur" />
        <div className="page-content">
            <Container fluid>
                <BreadCrumb title="Aide / Guide utilisateur" pageTitle="Parametre & Aides" />

                <Row>
                    <Col lg={12}>
                        <Card>
                            <CardBody>
                                <h5 className="card-title mb-1">Guide d’utilisation</h5>
                                <p className="text-muted mb-0">
                                    Les procédures ci-dessous suivent le parcours réel d’un dossier de stage, de
                                    l’inscription du demandeur au visa du bordereau de paiement.
                                </p>
                            </CardBody>
                        </Card>
                    </Col>
                </Row>

                <Row>
                    {rubriques.map((rubrique) => (
                        <Col lg={6} key={rubrique.id}>
                            <Card className="h-100">
                                <CardHeader className="d-flex align-items-center">
                                    <i className={`${rubrique.icone} fs-18 me-2 text-primary`} />
                                    <h5 className="card-title mb-0">{rubrique.titre}</h5>
                                </CardHeader>
                                <CardBody>
                                    <p className="text-muted">{rubrique.resume}</p>
                                    <ol className="ps-3">
                                        {rubrique.etapes.map((etape, index) => (
                                            <li className="mb-2" key={index}>{etape}</li>
                                        ))}
                                    </ol>
                                    <div className="d-flex flex-wrap gap-2 mt-3">
                                        {rubrique.liens.map((lien) => (
                                            <Link
                                                key={lien.href}
                                                href={lien.href}
                                                className="btn btn-soft-primary btn-sm"
                                            >
                                                {lien.libelle}
                                                <i className="ri-arrow-right-line align-bottom ms-1" />
                                            </Link>
                                        ))}
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    ))}
                </Row>
            </Container>
        </div>
    </React.Fragment>
);

export default Index;
