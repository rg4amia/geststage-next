import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import { Button, Card, CardBody, CardHeader, Col, Container, Input, Row, Table } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Props {
    utilisateurs: any;
    roles: string[];
    agences: { id: number; nom: string }[];
    filters: Record<string, string | undefined>;
    peutGerer: boolean;
    peutUsurper: boolean;
}

const Index = ({ utilisateurs, roles, agences, filters, peutGerer, peutUsurper }: Props) => {
    const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');
    const [agenceId, setAgenceId] = useState(filters.agence_id || '');
    const [actif, setActif] = useState(filters.actif ?? '');

    const appliquerFiltres = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/parametre-aides/comptes',
            { search, role, agence_id: agenceId, actif },
            { preserveState: true, replace: true },
        );
    };

    const basculerActivation = (utilisateur: any) => {
        const action = utilisateur.actif ? 'désactiver' : 'réactiver';
        if (confirm(`Voulez-vous ${action} le compte de ${utilisateur.nom} ?`)) {
            router.post(`/parametre-aides/comptes/${utilisateur.id}/activation`, {}, { preserveScroll: true });
        }
    };

    const usurper = (utilisateur: any) => {
        if (
            confirm(
                `Vous allez naviguer sous l’identité de ${utilisateur.nom}. Cette action est journalisée. Continuer ?`,
            )
        ) {
            router.post(`/parametre-aides/comptes/${utilisateur.id}/usurper`);
        }
    };

    return (
        <React.Fragment>
            <Head title="Comptes utilisateurs" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Comptes utilisateurs" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Liste des comptes</h5>
                                    {peutGerer && (
                                        <div className="flex-shrink-0">
                                            <Link href="/parametre-aides/comptes/creer" className="btn btn-success add-btn">
                                                <i className="ri-add-line align-bottom me-1" /> Nouveau compte
                                            </Link>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardBody>
                                    <form onSubmit={appliquerFiltres}>
                                        <Row className="g-2 mb-3">
                                            <Col md={4}>
                                                <Input
                                                    type="text"
                                                    placeholder="Nom, e-mail ou téléphone..."
                                                    value={search}
                                                    onChange={(e) => setSearch(e.target.value)}
                                                />
                                            </Col>
                                            <Col md={2}>
                                                <select className="form-select" value={role} onChange={(e) => setRole(e.target.value)}>
                                                    <option value="">Tous les rôles</option>
                                                    {roles.map((r) => (
                                                        <option key={r} value={r}>{r}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={3}>
                                                <select className="form-select" value={agenceId} onChange={(e) => setAgenceId(e.target.value)}>
                                                    <option value="">Toutes les agences</option>
                                                    {agences.map((a) => (
                                                        <option key={a.id} value={a.id}>{a.nom}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={2}>
                                                <select className="form-select" value={actif} onChange={(e) => setActif(e.target.value)}>
                                                    <option value="">Tous les statuts</option>
                                                    <option value="1">Actifs</option>
                                                    <option value="0">Désactivés</option>
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
                                                    <th>Nom</th>
                                                    <th>Contact</th>
                                                    <th>Rôles</th>
                                                    <th>Agences</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {utilisateurs.data.map((utilisateur: any) => (
                                                    <tr key={utilisateur.id}>
                                                        <td>
                                                            {utilisateur.nom}
                                                            {utilisateur.est_conseiller && (
                                                                <span className="badge bg-info-subtle text-info ms-2">Conseiller</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <div>{utilisateur.email}</div>
                                                            <small className="text-muted">{utilisateur.telephone || '-'}</small>
                                                        </td>
                                                        <td>
                                                            {utilisateur.roles?.length
                                                                ? utilisateur.roles.map((r: any) => (
                                                                      <span key={r.id} className="badge bg-primary-subtle text-primary me-1">
                                                                          {r.name}
                                                                      </span>
                                                                  ))
                                                                : '-'}
                                                        </td>
                                                        <td>
                                                            {utilisateur.perimetres_agences?.length
                                                                ? utilisateur.perimetres_agences.map((a: any) => a.nom).join(', ')
                                                                : '-'}
                                                        </td>
                                                        <td>
                                                            <span className={`badge bg-${utilisateur.actif ? 'success' : 'danger'}`}>
                                                                {utilisateur.actif ? 'Actif' : 'Désactivé'}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                {peutGerer && (
                                                                    <>
                                                                        <Link
                                                                            href={`/parametre-aides/comptes/${utilisateur.id}/modifier`}
                                                                            className="btn btn-sm btn-soft-info"
                                                                            title="Modifier"
                                                                        >
                                                                            <i className="ri-pencil-fill align-bottom" />
                                                                        </Link>
                                                                        <Button
                                                                            size="sm"
                                                                            color={utilisateur.actif ? 'soft-danger' : 'soft-success'}
                                                                            onClick={() => basculerActivation(utilisateur)}
                                                                            title={utilisateur.actif ? 'Désactiver' : 'Réactiver'}
                                                                        >
                                                                            <i
                                                                                className={`align-bottom ${
                                                                                    utilisateur.actif ? 'ri-forbid-2-line' : 'ri-check-line'
                                                                                }`}
                                                                            />
                                                                        </Button>
                                                                    </>
                                                                )}
                                                                {/* Le legacy réserve `login-as` aux comptes non conseillers. */}
                                                                {peutUsurper && !utilisateur.est_conseiller && utilisateur.actif && (
                                                                    <Button
                                                                        size="sm"
                                                                        color="soft-warning"
                                                                        onClick={() => usurper(utilisateur)}
                                                                        title="Se connecter en tant que"
                                                                    >
                                                                        <i className="ri-spy-line align-bottom" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {utilisateurs.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={6} className="text-center">Aucun compte trouvé.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination pagination={normalizePagination(utilisateurs)} itemLabel="comptes" />
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
