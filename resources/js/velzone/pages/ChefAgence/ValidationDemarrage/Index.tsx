import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Table, Button } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Props {
    instances: any[];
}

const ValidationDemarrageIndex = ({ instances }: Props) => {
    const { post, processing } = useForm({});

    const valider = (id: number) => {
        if(confirm('Êtes-vous sûr de vouloir valider ce démarrage de stage ?')) {
            post(`/validations/demarrage/${id}`);
        }
    };

    const ajourner = (id: number) => {
        const motif = prompt('Veuillez saisir le motif de l\'ajournement :');
        if (motif && motif.trim().length > 4) {
            import('@inertiajs/react').then(({ router }) => {
                router.post(`/validations/ajourner/${id}`, { motif });
            });
        } else if (motif) {
            alert('Le motif doit contenir au moins 5 caractères.');
        }
    };

    return (
        <React.Fragment>
            <Head title="Validation Démarrage - Chef d'Agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validation de Démarrage" pageTitle="Chef d'Agence" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">Dossiers en attente de validation de démarrage</h4>
                                </CardHeader>
                                <CardBody>
                                    <div className="table-responsive">
                                        <Table className="table-striped table-hover align-middle mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Date Saisie</th>
                                                    <th>Agence</th>
                                                    <th>Entreprise</th>
                                                    <th>Numéro AEJ</th>
                                                    <th>Nom et Prénoms</th>
                                                    <th>Incidence Financière</th>
                                                    <th>Début Prévu</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {instances.length > 0 ? instances.map((inst, idx) => (
                                                    <tr key={idx}>
                                                        <td>{new Date(inst.created_at).toLocaleDateString()}</td>
                                                        <td>{inst.stage?.agence?.nom || 'N/A'}</td>
                                                        <td>{inst.stage?.entreprise?.raison_sociale}</td>
                                                        <td>{inst.stage?.beneficiaire?.numero_aej}</td>
                                                        <td>{inst.stage?.beneficiaire?.nom} {inst.stage?.beneficiaire?.prenoms}</td>
                                                        <td>
                                                            {/* Assuming contracts logic is handled, this is mockup of "Incidence" */}
                                                            {inst.stage?.contrats?.length > 0 ? (inst.stage.contrats[0].prime_mensuelle + ' FCFA') : 'N/A'}
                                                        </td>
                                                        <td>{inst.stage?.contrats?.length > 0 ? new Date(inst.stage.contrats[0].date_debut_prevue).toLocaleDateString() : 'N/A'}</td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                <Link href={`/inscriptions/${inst.id}`} className="btn btn-sm btn-info">
                                                                    <i className="ri-eye-line"></i> Consulter
                                                                </Link>
                                                                <Button color="success" size="sm" onClick={() => valider(inst.id)} disabled={processing}>
                                                                    <i className="ri-check-line"></i> Valider
                                                                </Button>
                                                                <Button color="danger" outline size="sm" onClick={() => ajourner(inst.id)}>
                                                                    <i className="ri-close-line"></i> Ajourner
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )) : (
                                                    <tr>
                                                        <td colSpan={8} className="text-center p-4">Aucun dossier en attente de validation.</td>
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

export default ValidationDemarrageIndex;
