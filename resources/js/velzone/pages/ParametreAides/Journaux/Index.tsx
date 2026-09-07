import { Head, router } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Button, Card, CardBody, CardHeader, Col, Container, Input, Modal, ModalBody, ModalHeader, Row, Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Props {
    journaux: any;
    actions: string[];
    modeles: { valeur: string; libelle: string }[];
    utilisateurs: { id: number; nom: string }[];
    filters: Record<string, string | undefined>;
}

const COULEUR_ACTION: Record<string, string> = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    usurpation_debut: 'warning',
    usurpation_fin: 'secondary',
};

const Index = ({ journaux, actions, modeles, utilisateurs, filters }: Props) => {
    const [search, setSearch] = useState(filters.search || '');
    const [action, setAction] = useState(filters.action || '');
    const [modeleType, setModeleType] = useState(filters.modele_type || '');
    const [userId, setUserId] = useState(filters.user_id || '');
    const [du, setDu] = useState(filters.du || '');
    const [au, setAu] = useState(filters.au || '');
    const [detail, setDetail] = useState<any | null>(null);

    const parametres = { search, action, modele_type: modeleType, user_id: userId, du, au };

    const appliquerFiltres = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/parametre-aides/journaux', parametres, { preserveState: true, replace: true });
    };

    const lienExport = `/parametre-aides/journaux/export?${new URLSearchParams(
        Object.entries(parametres).filter(([, valeur]) => valeur !== ''),
    ).toString()}`;

    return (
        <React.Fragment>
            <Head title="Journaux d’activité" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Journaux d’activité" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Traçabilité des actions</h5>
                                    <a href={lienExport} className="btn btn-soft-secondary btn-sm">
                                        <i className="ri-download-2-line align-bottom me-1" /> Exporter en CSV
                                    </a>
                                </CardHeader>
                                <CardBody>
                                    <form onSubmit={appliquerFiltres}>
                                        <Row className="g-2 mb-3">
                                            <Col md={3}>
                                                <Input
                                                    type="text"
                                                    placeholder="Action, modèle ou identifiant..."
                                                    value={search}
                                                    onChange={(e) => setSearch(e.target.value)}
                                                />
                                            </Col>
                                            <Col md={2}>
                                                <select className="form-select" value={action} onChange={(e) => setAction(e.target.value)}>
                                                    <option value="">Toutes les actions</option>
                                                    {actions.map((a) => (
                                                        <option key={a} value={a}>{a}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={2}>
                                                <select className="form-select" value={modeleType} onChange={(e) => setModeleType(e.target.value)}>
                                                    <option value="">Tous les modèles</option>
                                                    {modeles.map((m) => (
                                                        <option key={m.valeur} value={m.valeur}>{m.libelle}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={2}>
                                                <select className="form-select" value={userId} onChange={(e) => setUserId(e.target.value)}>
                                                    <option value="">Tous les utilisateurs</option>
                                                    {utilisateurs.map((u) => (
                                                        <option key={u.id} value={u.id}>{u.nom}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={1}>
                                                <Input type="date" value={du} onChange={(e) => setDu(e.target.value)} title="Du" />
                                            </Col>
                                            <Col md={1}>
                                                <Input type="date" value={au} onChange={(e) => setAu(e.target.value)} title="Au" />
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
                                                    <th>Date</th>
                                                    <th>Utilisateur</th>
                                                    <th>Action</th>
                                                    <th>Modèle</th>
                                                    <th>Adresse IP</th>
                                                    <th>Détail</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {journaux.data.map((journal: any) => (
                                                    <tr key={journal.id}>
                                                        <td>
                                                            {journal.created_at
                                                                ? new Date(journal.created_at).toLocaleString('fr-FR')
                                                                : '-'}
                                                        </td>
                                                        <td>{journal.utilisateur?.nom || 'Système'}</td>
                                                        <td>
                                                            <span
                                                                className={`badge bg-${COULEUR_ACTION[journal.action] || 'secondary'}-subtle text-${
                                                                    COULEUR_ACTION[journal.action] || 'secondary'
                                                                }`}
                                                            >
                                                                {journal.action}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            {journal.modele}
                                                            <small className="text-muted"> #{journal.modele_id}</small>
                                                        </td>
                                                        <td>{journal.adresse_ip || '-'}</td>
                                                        <td>
                                                            <Button size="sm" color="soft-secondary" onClick={() => setDetail(journal)}>
                                                                <i className="ri-eye-line align-bottom" />
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {journaux.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={6} className="text-center">Aucune action enregistrée.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination pagination={normalizePagination(journaux)} itemLabel="actions" />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            <Modal isOpen={detail !== null} toggle={() => setDetail(null)} centered size="lg">
                <ModalHeader toggle={() => setDetail(null)}>
                    {detail?.action} — {detail?.modele} #{detail?.modele_id}
                </ModalHeader>
                <ModalBody>
                    <Row>
                        <Col md={6}>
                            <h6>Avant</h6>
                            <pre className="bg-light p-2 rounded small mb-0" style={{ maxHeight: '320px', overflow: 'auto' }}>
                                {detail?.anciennes_donnees
                                    ? JSON.stringify(detail.anciennes_donnees, null, 2)
                                    : '—'}
                            </pre>
                        </Col>
                        <Col md={6}>
                            <h6>Après</h6>
                            <pre className="bg-light p-2 rounded small mb-0" style={{ maxHeight: '320px', overflow: 'auto' }}>
                                {detail?.nouvelles_donnees
                                    ? JSON.stringify(detail.nouvelles_donnees, null, 2)
                                    : '—'}
                            </pre>
                        </Col>
                    </Row>
                </ModalBody>
            </Modal>
        </React.Fragment>
    );
};

export default Index;
