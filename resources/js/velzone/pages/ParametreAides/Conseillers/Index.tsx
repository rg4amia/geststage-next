import { Head, Link, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import Select from 'react-select';
import {
    Button, Card, CardBody, CardHeader, Col, Container, Form, Input, Label, Modal, ModalBody,
    ModalFooter, ModalHeader, Row, Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Props {
    conseillers: any;
    agences: { id: number; nom: string }[];
    utilisateursDisponibles: { id: number; nom: string; email: string; telephone?: string }[];
    filters: Record<string, string | undefined>;
    peutGerer: boolean;
    peutGererComptes: boolean;
}

const Index = ({ conseillers, agences, utilisateursDisponibles = [], filters, peutGerer, peutGererComptes }: Props) => {
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
                                                <Select
                                                    isSearchable
                                                    placeholder="Toutes les agences"
                                                    noOptionsMessage={() => 'Aucune agence'}
                                                    options={agences.map((a) => ({ value: String(a.id), label: a.nom }))}
                                                    value={agenceId ? { value: agenceId, label: agences.find((a) => String(a.id) === agenceId)?.nom || '' } : null}
                                                    onChange={(selected) => setAgenceId(selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                />
                                            </Col>
                                            <Col md={3}>
                                                <Select
                                                    isSearchable={false}
                                                    placeholder="Tous les statuts"
                                                    noOptionsMessage={() => 'Aucun statut'}
                                                    options={[
                                                        { value: '1', label: 'Actifs' },
                                                        { value: '0', label: 'Inactifs' },
                                                    ]}
                                                    value={actif !== '' ? { value: actif, label: actif === '1' ? 'Actifs' : 'Inactifs' } : null}
                                                    onChange={(selected) => setActif(selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                />
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
                        <Select
                            isSearchable
                            className="mb-3"
                            classNamePrefix="react-select"
                            placeholder="Sélectionner le mode"
                            noOptionsMessage={() => 'Aucun mode'}
                            options={[
                                { value: 'creer', label: 'Créer un nouveau compte' },
                                { value: 'rattacher', label: 'Rattacher un compte existant' },
                            ]}
                            value={
                                compte.data.mode
                                    ? {
                                        value: compte.data.mode,
                                        label: compte.data.mode === 'creer'
                                            ? 'Créer un nouveau compte'
                                            : 'Rattacher un compte existant',
                                    }
                                    : null
                            }
                            onChange={(selected) => compte.setData('mode', selected?.value || 'creer')}
                        />

                        {compte.data.mode === 'rattacher' ? (
                            <>
                                <Label className="form-label">
                                    Compte utilisateur <span className="text-danger">*</span>
                                </Label>
                                <Select
                                    isSearchable
                                    classNamePrefix="react-select"
                                    placeholder="Rechercher un utilisateur..."
                                    noOptionsMessage={() => 'Aucun utilisateur disponible'}
                                    options={utilisateursDisponibles.map((u) => ({
                                        value: String(u.id),
                                        label: `${u.nom} — ${u.email}`,
                                    }))}
                                    value={
                                        compte.data.user_id
                                            ? {
                                                value: compte.data.user_id,
                                                label: utilisateursDisponibles.find((u) => String(u.id) === compte.data.user_id)
                                                    ? `${utilisateursDisponibles.find((u) => String(u.id) === compte.data.user_id)!.nom} — ${utilisateursDisponibles.find((u) => String(u.id) === compte.data.user_id)!.email}`
                                                    : `ID ${compte.data.user_id}`,
                                            }
                                            : null
                                    }
                                    onChange={(selected) => compte.setData('user_id', selected?.value || '')}
                                    className={compte.errors.user_id ? 'is-invalid' : ''}
                                />
                                {compte.errors.user_id && (
                                    <div className="text-danger small mt-1">{compte.errors.user_id}</div>
                                )}
                                <small className="text-muted">
                                    {compte.data.mode === 'rattacher' && (
                                        <>
                                            {utilisateursDisponibles.length} compte(s) disponible(s) dans votre périmètre.
                                        </>
                                    )}
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
