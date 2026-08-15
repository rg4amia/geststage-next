import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Table, Button, Badge } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Props {
    pointagesAValider: any[];
    moisActuel: string;
}

const PointageChefAgenceIndex = ({ pointagesAValider, moisActuel }: Props) => {
    const { post, processing } = useForm({});

    const valider = (id: number) => {
        if(confirm('Êtes-vous sûr de vouloir valider ces présences ? Cela génèrera un droit au paiement.')) {
            post(`/chefagence/pointages/valider/${id}`);
        }
    };

    const ajourner = (id: number) => {
        const motif = prompt('Veuillez saisir le motif de l\'ajournement :');
        if (motif && motif.trim().length > 4) {
            import('@inertiajs/react').then(({ router }) => {
                router.post(`/chefagence/pointages/ajourner/${id}`, { motif });
            });
        } else if (motif) {
            alert('Le motif doit contenir au moins 5 caractères.');
        }
    };

    return (
        <React.Fragment>
            <Head title="Validation Pointages - Chef d'Agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validation Pointages" pageTitle="Chef d'Agence" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">Pointages en attente de validation - {moisActuel}</h4>
                                </CardHeader>
                                <CardBody>
                                    <div className="table-responsive">
                                        <Table className="table-striped table-hover align-middle mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Date Soumission</th>
                                                    <th>Période</th>
                                                    <th>Agence</th>
                                                    <th>Entreprise</th>
                                                    <th>Bénéficiaire</th>
                                                    <th>Présences</th>
                                                    <th>Agent Saisie (CIP)</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {pointagesAValider.length > 0 ? pointagesAValider.map((pointage, idx) => (
                                                    <tr key={idx}>
                                                        <td>{new Date(pointage.updated_at).toLocaleDateString()}</td>
                                                        <td><Badge color="info">{pointage.periode?.nom}</Badge></td>
                                                        <td>{pointage.stage?.agence?.nom || 'N/A'}</td>
                                                        <td>{pointage.stage?.entreprise?.raison_sociale}</td>
                                                        <td>{pointage.stage?.beneficiaire?.nom} {pointage.stage?.beneficiaire?.prenoms}</td>
                                                        <td>
                                                            <span className="text-success fw-medium">{pointage.version_courante?.jours_presents || 0} jours</span>
                                                        </td>
                                                        <td>{pointage.version_courante?.saisi_par?.name || 'Inconnu'}</td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                <Button color="success" size="sm" onClick={() => valider(pointage.id)} disabled={processing}>
                                                                    <i className="ri-check-line"></i> Valider
                                                                </Button>
                                                                <Button color="danger" outline size="sm" onClick={() => ajourner(pointage.id)}>
                                                                    <i className="ri-close-line"></i> Ajourner
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )) : (
                                                    <tr>
                                                        <td colSpan={8} className="text-center p-4">Aucun pointage en attente de validation pour ce mois.</td>
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

export default PointageChefAgenceIndex;
