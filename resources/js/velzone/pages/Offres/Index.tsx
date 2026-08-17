import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Table } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface Props {
    offres: any;
    filters: any;
}

const Index = ({ offres, filters }: Props) => {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/offres', { search }, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette offre ?')) {
            router.delete(`/offres/${id}`);
        }
    };

    const getStatutBadge = (statut: string) => {
        switch (statut) {
            case 'BROUILLON': return 'badge bg-warning';
            case 'PUBLIEE': return 'badge bg-success';
            case 'CLOTUREE': return 'badge bg-secondary';
            case 'ANNULEE': return 'badge bg-danger';
            default: return 'badge bg-primary';
        }
    };

    return (
        <React.Fragment>
            <Head title="Offres d'emploi" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Offres d'emploi" pageTitle="Référentiels" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Liste des offres</h5>
                                    <div className="flex-shrink-0">
                                        <Link href="/offres/create" className="btn btn-success add-btn">
                                            <i className="ri-add-line align-bottom me-1"></i> Créer une offre
                                        </Link>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    <Row className="g-4 mb-3">
                                        <Col sm="auto">
                                            <form onSubmit={handleSearch} className="d-flex gap-2">
                                                <Input 
                                                    type="text" 
                                                    placeholder="Rechercher par numéro ou intitulé..." 
                                                    value={search}
                                                    onChange={e => setSearch(e.target.value)}
                                                    style={{ minWidth: '300px' }}
                                                />
                                                <Button color="primary" type="submit">Rechercher</Button>
                                            </form>
                                        </Col>
                                    </Row>
                                    
                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Numéro</th>
                                                    <th>Intitulé</th>
                                                    <th>Entreprise</th>
                                                    <th>Type Stage</th>
                                                    <th>Places</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {offres.data.map((offre: any) => (
                                                    <tr key={offre.id}>
                                                        <td>{offre.numero}</td>
                                                        <td>{offre.intitule}</td>
                                                        <td>{offre.entreprise?.raison_sociale || '-'}</td>
                                                        <td>{offre.type_stage?.nom || '-'}</td>
                                                        <td>{offre.nombre_places}</td>
                                                        <td>
                                                            <span className={getStatutBadge(offre.statut)}>
                                                                {offre.statut}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                <Link href={`/offres/${offre.id}/edit`} className="btn btn-sm btn-soft-info">
                                                                    <i className="ri-pencil-fill align-bottom" />
                                                                </Link>
                                                                <Button size="sm" color="soft-danger" onClick={() => handleDelete(offre.id)}>
                                                                    <i className="ri-delete-bin-fill align-bottom" />
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {offres.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={7} className="text-center">Aucune offre trouvée.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                    
                                    <div className="mt-3">
                                        {offres.links.map((link: any, index: number) => (
                                            <Link 
                                                key={index}
                                                href={link.url || '#'}
                                                className={`btn btn-sm ${link.active ? 'btn-primary' : 'btn-light'} me-1`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
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
