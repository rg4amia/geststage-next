import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Button, Card, CardBody, CardHeader, Col, Container, Form, Input, Label, Modal, ModalBody,
    ModalFooter, ModalHeader, Row, Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Props {
    conseillers: any;
    agences: { id: number; nom: string }[];
    filters: Record<string, string | undefined>;
    peutGerer: boolean;
    peutGererComptes: boolean;
}

const Index = ({ conseillers, agences, filters, peutGerer, peutGererComptes }: Props) => {
    const [search, setSearch] = useState(filters.search || '');
    const [agenceId, setAgenceId] = useState(filters.agence_id || '');
    const [actif, setActif] = useState(filters.actif ?? '');
    const [conseillerCible, setConseillerCible] = useState<any | null>(null);

    const compte = useForm({
        mode: 'creer',
        user_id: '',
        email: '',
        telephone: '',
        password: '',
        password_confirmation: '',
    });

    const appliquerFiltres = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/parametre-aides/conseillers',
            { search, agence_id: agenceId, actif },
            { preserveState: true, replace: true },
        );
    };

    const ouvrirModale = (conseiller: any) => {
        compte.reset();
        compte.clearErrors();
        setConseillerCible(conseiller);
    };

    const soumettreCompte = (e: React.FormEvent) => {
        e.preventDefault();

        // Garde-fou : la modale peut recevoir un submit résiduel (ex. touche
        // Entrée) pendant sa fermeture, une fois `conseillerCible` déjà remis à null.
        if (!conseillerCible) {
            return;
        }

        compte.post(`/parametre-aides/conseillers/${conseillerCible.id}/compte`, {
            preserveScroll: true,
            onSuccess: () => setConseillerCible(null),
        });
    };

    return (
        <React.Fragment>
            <Head title="Conseillers" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Conseillers" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Liste des conseillers</h5>
                                    {peutGerer && (
                                        <div className="flex-shrink-0">
                                            <Link href="/parametre-aides/conseillers/creer" className="btn btn-success add-btn">
                                                <i className="ri-add-line align-bottom me-1" /> Nouveau conseiller
                                            </Link>
                                        </div>
                                    )}
                                </CardHeader>
                                <CardBody>
                                    <form onSubmit={appliquerFiltres}>
                                        <Row className="g-2 mb-3">
                                            <Col md={5}>
                                                <Input
                                                    type="text"
                                                    placeholder="Nom, prénoms ou matricule..."
                                                    value={search}
                                                    onChange={(e) => setSearch(e.target.value)}
                                                />
                                            </Col>
                                            <Col md={3}>
                                                <select className="form-select" value={agenceId} onChange={(e) => setAgenceId(e.target.value)}>
                                                    <option value="">Toutes les agences</option>
                                                    {agences.map((a) => (
                                                        <option key={a.id} value={a.id}>{a.nom}</option>
                                                    ))}
                                                </select>
                                            </Col>
                                            <Col md={3}>
                                                <select className="form-select" value={actif} onChange={(e) => setActif(e.target.value)}>
                                                    <option value="">Tous les statuts</option>
                                                    <option value="1">Actifs</option>
                                                    <option value="0">Inactifs</option>
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
                                                    <th>Agence</th>
                                                    <th>Nom et prénoms</th>
                                                    <th>Contact</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {conseillers.data.map((conseiller: any) => (
                                                    <tr key={conseiller.id}>
                                                        <td>{conseiller.agence?.nom || '-'}</td>
                                                        <td>
                                                            {conseiller.nom_complet}
                                                            {!conseiller.actif && (
                                                                <span className="badge bg-danger-subtle text-danger ms-2">Inactif</span>
                                                            )}
                                                            {conseiller.matricule && (
                                                                <div>
                                                                    <small className="text-muted">Mat. {conseiller.matricule}</small>
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td>
                                                            {conseiller.user ? (
                                                                <>
                                                                    <div>{conseiller.user.email}</div>
                                                                    <small className="text-muted">{conseiller.user.telephone || '-'}</small>
                                                                </>
                                                            ) : (
                                                                <span className="badge bg-warning-subtle text-warning">Aucun compte</span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <div className="d-flex gap-2">
                                                                {peutGerer && (
                                                                    <Link
                                                                        href={`/parametre-aides/conseillers/${conseiller.id}/modifier`}
                                                                        className="btn btn-sm btn-soft-info"
                                                                        title="Modifier"
                                                                    >
                                                                        <i className="ri-pencil-fill align-bottom" />
                                                                    </Link>
                                                                )}
                                                                {peutGererComptes && !conseiller.user_id && (
                                                                    <Button
                                                                        size="sm"
                                                                        color="soft-primary"
                                                                        onClick={() => ouvrirModale(conseiller)}
                                                                        title="Créer ou rattacher un compte"
                                                                    >
                                                                        <i className="ri-user-add-line align-bottom" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {conseillers.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={4} className="text-center">Aucun conseiller trouvé.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination pagination={normalizePagination(conseillers)} itemLabel="conseillers" />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            {/* Legacy `conseiller-to-user` : créer un compte ou en rattacher un existant. */}
            <Modal isOpen={conseillerCible !== null} toggle={() => setConseillerCible(null)} centered>
                <ModalHeader toggle={() => setConseillerCible(null)}>
                    Compte utilisateur — {conseillerCible?.nom_complet}
                </ModalHeader>
                <Form onSubmit={soumettreCompte}>
                    <ModalBody>
                        <Label className="form-label">Mode</Label>
                        <select
                            className="form-select mb-3"
                            value={compte.data.mode}
                            onChange={(e) => compte.setData('mode', e.target.value)}
                        >
                            <option value="creer">Créer un nouveau compte</option>
                            <option value="rattacher">Rattacher un compte existant</option>
                        </select>

                        {compte.data.mode === 'rattacher' ? (
                            <>
                                <Label htmlFor="user_id" className="form-label">
                                    Identifiant du compte <span className="text-danger">*</span>
                                </Label>
                                <Input
                                    type="number"
                                    id="user_id"
                                    value={compte.data.user_id}
                                    onChange={(e) => compte.setData('user_id', e.target.value)}
                                    invalid={!!compte.errors.user_id}
                                />
                                {compte.errors.user_id && <div className="invalid-feedback">{compte.errors.user_id}</div>}
                                <small className="text-muted">
                                    Identifiant visible depuis la liste des comptes utilisateurs.
                                </small>
                            </>
                        ) : (
                            <Row className="g-3">
                                <Col md={12}>
                                    <Label htmlFor="email" className="form-label">
                                        Adresse e-mail <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        type="email"
                                        id="email"
                                        value={compte.data.email}
                                        onChange={(e) => compte.setData('email', e.target.value)}
                                        invalid={!!compte.errors.email}
                                    />
                                    {compte.errors.email && <div className="invalid-feedback">{compte.errors.email}</div>}
                                </Col>
                                <Col md={12}>
                                    <Label htmlFor="telephone" className="form-label">Téléphone</Label>
                                    <Input
                                        type="text"
                                        id="telephone"
                                        value={compte.data.telephone}
                                        onChange={(e) => compte.setData('telephone', e.target.value)}
                                        invalid={!!compte.errors.telephone}
                                    />
                                    {compte.errors.telephone && <div className="invalid-feedback">{compte.errors.telephone}</div>}
                                </Col>
                                <Col md={6}>
                                    <Label htmlFor="password" className="form-label">
                                        Mot de passe <span className="text-danger">*</span>
                                    </Label>
                                    <Input
                                        type="password"
                                        id="password"
                                        autoComplete="new-password"
                                        value={compte.data.password}
                                        onChange={(e) => compte.setData('password', e.target.value)}
                                        invalid={!!compte.errors.password}
                                    />
                                    {compte.errors.password && <div className="invalid-feedback">{compte.errors.password}</div>}
                                </Col>
                                <Col md={6}>
                                    <Label htmlFor="password_confirmation" className="form-label">Confirmation</Label>
                                    <Input
                                        type="password"
                                        id="password_confirmation"
                                        autoComplete="new-password"
                                        value={compte.data.password_confirmation}
                                        onChange={(e) => compte.setData('password_confirmation', e.target.value)}
                                    />
                                </Col>
                            </Row>
                        )}
                    </ModalBody>
                    <ModalFooter>
                        <Button color="light" type="button" onClick={() => setConseillerCible(null)}>Annuler</Button>
                        <Button color="primary" type="submit" disabled={compte.processing}>Enregistrer</Button>
                    </ModalFooter>
                </Form>
            </Modal>
        </React.Fragment>
    );
};

export default Index;
