import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Table, Badge } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface Props {
    instances: any[];
}

const Index = ({ instances }: Props) => {
    return (
        <React.Fragment>
            <Head title="Suivi des Inscriptions (CIP)" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Suivi des Inscriptions" pageTitle="Dossiers" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Dossiers et Inscriptions</h5>
                                    <div className="flex-shrink-0">
                                        <Link href="/inscriptions/create" className="btn btn-primary add-btn">
                                            <i className="ri-add-line align-bottom me-1"></i> Nouvelle Inscription
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Bénéficiaire</th>
                                                    <th>Entreprise</th>
                                                    <th>Étape Courante</th>
                                                    <th>Tâches à traiter</th>
                                                    <th>Débuté le</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {instances.map((instance: any) => (
                                                    <tr key={instance.id}>
                                                        <td>
                                                            {instance.stage?.beneficiaire?.prenoms} {instance.stage?.beneficiaire?.nom}
                                                        </td>
                                                        <td>{instance.stage?.entreprise?.raison_sociale || '-'}</td>
                                                        <td><Badge color="info">Étape {instance.etape_courante_id}</Badge></td>
                                                        <td>
                                                            {instance.taches_ouvertes?.length > 0 ? (
                                                                <Badge color="warning">{instance.taches_ouvertes.length} tâche(s)</Badge>
                                                            ) : (
                                                                <span className="text-muted">Aucune</span>
                                                            )}
                                                        </td>
                                                        <td>{new Date(instance.demarree_le).toLocaleDateString()}</td>
                                                        <td>
                                                            {instance.terminee_le ? (
                                                                <Badge color="success">Terminé</Badge>
                                                            ) : (
                                                                <Badge color="primary">En cours</Badge>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <Link href={`/instances/${instance.id}`} className="btn btn-sm btn-soft-secondary">
                                                                <i className="ri-eye-fill align-bottom" /> Voir dossier
                                                            </Link>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {instances.length === 0 && (
                                                    <tr>
                                                        <td colSpan={7} className="text-center p-4">
                                                            <div className="text-muted">
                                                                <i className="ri-folder-open-line display-4"></i>
                                                                <p className="mt-2">Aucun dossier en cours.</p>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
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
