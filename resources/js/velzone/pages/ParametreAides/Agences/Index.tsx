import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Button, Card, CardBody, CardHeader, Col, Container, Input, Row, Table } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Props {
    agences: any;
    regions: { id: number; nom: string }[];
    filters: Record<string, string | undefined>;
    peutGerer: boolean;
}

const Index = ({ agences, regions, filters, peutGerer }: Props) => {
    const [search, setSearch] = useState(filters.search || '');
    const [regionId, setRegionId] = useState(filters.region_id || '');
    const [actif, setActif] = useState(filters.actif ?? '');

    const appliquerFiltres = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/parametre-aides/agences',
            { search, region_id: regionId, actif },
            { preserveState: true, replace: true },
        );
    };

    return (
        <React.Fragment>
            <Head title="Agences" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Agences" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Réseau des agences</h5>
                                    {peutGerer && (
                                        <div className="flex-shrink-0">
                                            <Link href="/parametre-aides/agences/creer" className="btn btn-success add-btn">
                                                <i className="ri-add-line align-bottom me-1" /> Nouvelle agence
                                            </Link>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardBody>
                                    {!peutGerer && (
                                        <div className="alert alert-info" role="alert">
                                            <i className="ri-information-line align-bottom me-1" />
                                            Consultation seule : la modification du réseau d’agences est réservée à
                                            l’administrateur.
                                        </div>
                                    )}

                                    <form onSubmit={appliquerFiltres}>
                                        <Row className="g-2 mb-3">
                                            <Col md={5}>
                                                <Input
                                                    type="text"
                                                    placeholder="Nom ou code de l’agence..."
                                                    value={search}
                                                    onChange={(e) => setSearch(e.target.value)}
                                                />
                                            </Col>
                                            <Col md={3}>
                                                <select className="form-select" value={regionId} onChange={(e) => setRegionId(e.target.value)}>
                                                    <option value="">Toutes les régions</option>
                                                    {regions.map((r) => (
                                                        <option key={r.id} value={r.id}>{r.nom}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={3}>
                                                <select className="form-select" value={actif} onChange={(e) => setActif(e.target.value)}>
                                                    <option value="">Tous les statuts</option>
                                                    <option value="1">Actives</option>
                                                    <option value="0">Inactives</option>
                                                </select>
                                            </Col>
                                            <Col md={1}>
                                                <Button color="primary" type="submit" className="w-100">
                                                    <i className="ri-search-line" />
                                                </Button>
                                            </Col>
                                        </Row>
                                    </form>

                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Nom Agence</th>
                                                    <th>Nom Chef Agence</th>
                                                    <th>Contact</th>
                                                    <th>Région</th>
                                                    {peutGerer && <th>Actions</th>}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {agences.data.map((agence: any) => (
                                                    <tr key={agence.id}>
                                                        <td>
                                                            {agence.nom}
                                                            <div>
                                                                <small className="text-muted">{agence.code}</small>
                                                            </div>
                                                            {!agence.actif && (
                                                                <span className="badge bg-danger-subtle text-danger">Inactive</span>
                                                            )}
                                                        </td>
                                                        <td>{agence.chef_agence}</td>
                                                        <td>{agence.adresse || '-'}</td>
                                                        <td>{agence.region?.nom || '-'}</td>
                                                        {peutGerer && (
                                                            <td>
                                                                <Link
                                                                    href={`/parametre-aides/agences/${agence.id}/modifier`}
                                                                    className="btn btn-sm btn-soft-info"
                                                                    title="Modifier"
                                                                >
                                                                    <i className="ri-pencil-fill align-bottom" />
                                                                </Link>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                                {agences.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={peutGerer ? 5 : 4} className="text-center">
                                                            Aucune agence trouvée.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination pagination={normalizePagination(agences)} itemLabel="agences" />
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
