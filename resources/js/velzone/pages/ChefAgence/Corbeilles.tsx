import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Nav, NavItem, NavLink, TabContent, TabPane, Table, Badge, Button } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface CorbeillesProps {
    pointagesAValider: any[];
    dossiersAValider: any[];
}

const Corbeilles = ({ pointagesAValider, dossiersAValider }: CorbeillesProps) => {
    const [activeTab, setActiveTab] = useState('1');

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
            setActiveTab(tab);
        }
    };

    return (
        <React.Fragment>
            <Head title="Corbeilles Chef d'Agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Mes Corbeilles" pageTitle="Validation" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="align-items-center d-flex">
                                    <h4 className="card-title mb-0 flex-grow-1">Dossiers en attente d'action</h4>
                                </CardHeader>
                                <CardBody>
                                    <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                        <NavItem>
                                            <NavLink
                                                style={{ cursor: 'pointer' }}
                                                className={classnames({ active: activeTab === '1' })}
                                                onClick={() => { toggleTab('1'); }}
                                            >
                                                <i className="ri-folder-2-line align-middle me-1"></i> Attente Démarrage
                                                <Badge color="danger" className="ms-1">{dossiersAValider.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                style={{ cursor: 'pointer' }}
                                                className={classnames({ active: activeTab === '2' })}
                                                onClick={() => { toggleTab('2'); }}
                                            >
                                                <i className="ri-calendar-check-line align-middle me-1"></i> Pointages Mensuels
                                                <Badge color="warning" className="ms-1">{pointagesAValider.length}</Badge>
                                            </NavLink>
                                        </NavItem>
                                    </Nav>

                                    <TabContent activeTab={activeTab} className="text-muted">
                                        <TabPane tabId="1" id="attente-demarrage">
                                            <div className="table-responsive">
                                                <Table className="table-nowrap mb-0 align-middle">
                                                    <thead className="table-light">
                                                        <tr>
                                                            <th>N° Dossier / AEJ</th>
                                                            <th>Bénéficiaire</th>
                                                            <th>Entreprise</th>
                                                            <th>Date de Soumission</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {dossiersAValider.length > 0 ? dossiersAValider.map((dossier, idx) => (
                                                            <tr key={idx}>
                                                                <td>{dossier.stage?.beneficiaire?.numero_aej}</td>
                                                                <td>{dossier.stage?.beneficiaire?.nom} {dossier.stage?.beneficiaire?.prenoms}</td>
                                                                <td>{dossier.stage?.entreprise?.raison_sociale}</td>
                                                                <td>{new Date(dossier.etapeCourante?.created_at).toLocaleDateString()}</td>
                                                                <td>
                                                                    <Link href={`/inscriptions/${dossier.id}`} className="btn btn-sm btn-primary">Consulter</Link>
                                                                </td>
                                                            </tr>
                                                        )) : (
                                                            <tr>
                                                                <td colSpan={5} className="text-center p-4">Aucun dossier en attente de démarrage.</td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </TabPane>

                                        <TabPane tabId="2" id="pointages">
                                            <div className="table-responsive">
                                                <Table className="table-nowrap mb-0 align-middle">
                                                    <thead className="table-light">
                                                        <tr>
                                                            <th>Période</th>
                                                            <th>Bénéficiaire</th>
                                                            <th>Présences</th>
                                                            <th>Soumis par</th>
                                                            <th>Date Soumission</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {pointagesAValider.length > 0 ? pointagesAValider.map((pointage, idx) => (
                                                            <tr key={idx}>
                                                                <td><Badge color="info">{pointage.periode?.nom || 'N/A'}</Badge></td>
                                                                <td>{pointage.stage?.beneficiaire?.nom} {pointage.stage?.beneficiaire?.prenoms}</td>
                                                                <td>
                                                                    <span className="text-success fw-medium">{pointage.version_courante_data?.jours_presents || 0} jours</span>
                                                                </td>
                                                                <td>{pointage.version_courante_data?.saisi_par?.name || 'Système'}</td>
                                                                <td>{new Date(pointage.updated_at).toLocaleDateString()}</td>
                                                                <td>
                                                                    {/* A method to validate immediately, or view details */}
                                                                    <Link method="post" href={`/pointages/valider/${pointage.id}`} as="button" className="btn btn-sm btn-success">
                                                                        Valider
                                                                    </Link>
                                                                </td>
                                                            </tr>
                                                        )) : (
                                                            <tr>
                                                                <td colSpan={6} className="text-center p-4">Aucun pointage en attente de validation.</td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </TabPane>
                                    </TabContent>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Corbeilles;
