import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Table } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../Components/Common/ServerPagination';

interface Props {
    entreprises: any;
    filters: any;
}

const Index = ({ entreprises, filters }: Props) => {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/entreprises', { search }, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette entreprise ?')) {
            router.delete(`/entreprises/${id}`);
        }
    };

    return (
        <React.Fragment>
            <Head title="Entreprises" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Entreprises" pageTitle="Référentiels" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Liste des entreprises</h5>
                                    <div className="flex-shrink-0">
                                        <Link href="/entreprises/create" className="btn btn-success add-btn">
                                            <i className="ri-add-line align-bottom me-1"></i> Ajouter une entreprise
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    <Row className="g-4 mb-3">
                                        <Col sm="auto">
                                            <form onSubmit={handleSearch} className="d-flex gap-2">
                                                <Input
                                                    type="text"
                                                    placeholder="Rechercher..."
                                                    value={search}
                                                    onChange={e => setSearch(e.target.value)}
                                                />
                                                <Button color="primary" type="submit">Rechercher</Button>
                                            </form>
                                        </Col>
                                    </Row>

                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Raison Sociale</th>
                                                    <th>Sigle</th>
                                                    <th>Agence</th>
                                                    <th>Type Structure</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {entreprises.data.map((entreprise: any) => (
                                                    <tr key={entreprise.id}>
                                                        <td>{entreprise.raison_sociale}</td>
                                                        <td>{entreprise.sigle || '-'}</td>
                                                        <td>{entreprise.agence?.nom || '-'}</td>
                                                        <td>{entreprise.type_structure?.nom || '-'}</td>
                                                        <td>
                                                            <span className={`badge bg-${entreprise.actif ? 'success' : 'danger'}`}>
                                                                {entreprise.actif ? 'Actif' : 'Inactif'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                <Link href={`/entreprises/${entreprise.id}/edit`} className="btn btn-sm btn-soft-info">
                                                                    <i className="ri-pencil-fill align-bottom" />
                                                                </Link>
                                                                <Button size="sm" color="soft-danger" onClick={() => handleDelete(entreprise.id)}>
                                                                    <i className="ri-delete-bin-fill align-bottom" />
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {entreprises.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={6} className="text-center">Aucune entreprise trouvée.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination
                                        pagination={normalizePagination(entreprises)}
                                        itemLabel="entreprises"
                                    />
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
