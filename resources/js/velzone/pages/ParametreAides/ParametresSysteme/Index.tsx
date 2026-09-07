import { Head, router, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Button, Card, CardBody, CardHeader, Col, Container, Form, Input, Label, Modal, ModalBody,
    ModalFooter, ModalHeader, Row, Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

interface Parametre {
    id: number;
    cle: string;
    libelle: string;
    description: string | null;
    type: 'booleen' | 'entier' | 'chaine';
    valeur: boolean | number | string | null;
}

interface Props {
    parametres: Parametre[];
    regles: any;
    sources: { id: number; nom: string }[];
    typesStage: { id: number; nom: string }[];
    typesPrelevement: string[];
    typesPaiement: string[];
    peutGerer: boolean;
}

/** `2026-09-01` -> `2026-09`, format attendu par l'input `month` et par le backend. */
const versMois = (date: string | null): string => (date ? date.slice(0, 7) : '');

const formaterMontant = (montant: string | number): string =>
    new Intl.NumberFormat('fr-FR').format(Number(montant));

const Index = ({ parametres, regles, sources, typesStage, typesPrelevement, typesPaiement, peutGerer }: Props) => {
    const [regleEditee, setRegleEditee] = useState<any | null>(null);
    const [modaleOuverte, setModaleOuverte] = useState(false);

    const general = useForm({
        parametres: parametres.map((parametre) => ({ cle: parametre.cle, valeur: parametre.valeur })),
    });

    const regle = useForm({
        nom: '',
        type_prelevement: typesPrelevement[0] ?? 'CMU',
        source_financement_id: '',
        type_stage_id: '',
        type_paiement: typesPaiement[0] ?? 'DEMARRAGE',
        montant: '',
        effet_du: '',
        effet_au: '',
        actif: true,
    });

    const majParametre = (cle: string, valeur: any) => {
        general.setData(
            'parametres',
            general.data.parametres.map((parametre) =>
                parametre.cle === cle ? { ...parametre, valeur } : parametre,
            ),
        );
    };

    const soumettreGeneral = (e: React.FormEvent) => {
        e.preventDefault();
        general.post('/parametre-aides/parametres-systeme/general', { preserveScroll: true });
    };

    const ouvrirCreation = () => {
        regle.reset();
        regle.clearErrors();
        setRegleEditee(null);
        setModaleOuverte(true);
    };

    const ouvrirEdition = (source: any) => {
        regle.clearErrors();
        regle.setData({
            nom: source.nom,
            type_prelevement: source.type_prelevement,
            source_financement_id: String(source.source_financement_id),
            type_stage_id: source.type_stage_id ? String(source.type_stage_id) : '',
            type_paiement: source.type_paiement,
            montant: String(source.montant),
            effet_du: versMois(source.effet_du),
            effet_au: versMois(source.effet_au),
            actif: source.actif,
        });
        setRegleEditee(source);
        setModaleOuverte(true);
    };

    const soumettreRegle = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setModaleOuverte(false) };

        if (regleEditee) {
            regle.put(`/parametre-aides/parametres-systeme/prelevements/${regleEditee.id}`, options);
        } else {
            regle.post('/parametre-aides/parametres-systeme/prelevements', options);
        }
    };

    const desactiverRegle = (source: any) => {
        if (
            confirm(
                `Désactiver la règle « ${source.nom} » ? Elle ne sera plus appliquée aux nouveaux paiements, mais reste attachée aux paiements déjà calculés.`,
            )
        ) {
            router.delete(`/parametre-aides/parametres-systeme/prelevements/${source.id}`, { preserveScroll: true });
        }
    };

    return (
        <React.Fragment>
            <Head title="Paramètres système" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Paramètres système" pageTitle="Parametre & Aides" />

                    <Row>
                        <Col lg={5}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Paramètres généraux</h5>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={soumettreGeneral}>
                                        {general.data.parametres.map((saisie) => {
                                            const definition = parametres.find((p) => p.cle === saisie.cle)!;

                                            return (
                                                <div className="mb-3" key={saisie.cle}>
                                                    {definition.type === 'booleen' ? (
                                                        <div className="form-check form-switch">
                                                            <Input
                                                                type="checkbox"
                                                                className="form-check-input"
                                                                id={saisie.cle}
                                                                disabled={!peutGerer}
                                                                checked={!!saisie.valeur}
                                                                onChange={(e) => majParametre(saisie.cle, e.target.checked)}
                                                            />
                                                            <Label className="form-check-label" htmlFor={saisie.cle}>
                                                                {definition.libelle}
                                                            </Label>
                                                        </div>
                                                    ) : (
                                                        <>
                                                            <Label htmlFor={saisie.cle} className="form-label">
                                                                {definition.libelle}
                                                            </Label>
                                                            <Input
                                                                type={definition.type === 'entier' ? 'number' : 'text'}
                                                                id={saisie.cle}
                                                                disabled={!peutGerer}
                                                                value={String(saisie.valeur ?? '')}
                                                                onChange={(e) => majParametre(saisie.cle, e.target.value)}
                                                            />
                                                        </>
                                                    )}
                                                    {definition.description && (
                                                        <small className="text-muted d-block">{definition.description}</small>
                                                    )}
                                                </div>
                                            );
                                        })}

                                        {peutGerer && (
                                            <div className="text-end">
                                                <Button color="primary" type="submit" disabled={general.processing}>
                                                    Enregistrer
                                                </Button>
                                            </div>
                                        )}
                                    </Form>
                                </CardBody>
                            </Card>
                        </Col>

                        <Col lg={7}>
                            <Card>
                                <CardHeader className="d-flex align-items-center">
                                    <h5 className="card-title mb-0 flex-grow-1">Règles de prélèvement</h5>
                                    {peutGerer && (
                                        <Button color="success" size="sm" onClick={ouvrirCreation}>
                                            <i className="ri-add-line align-bottom me-1" /> Nouvelle règle
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardBody>
                                    <p className="text-muted">
                                        Deux règles actives de même portée — source de financement, type de stage et type
                                        de paiement — ne peuvent pas couvrir un même mois.
                                    </p>

                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Règle</th>
                                                    <th>Portée</th>
                                                    <th>Montant</th>
                                                    <th>Période</th>
                                                    <th>Statut</th>
                                                    {peutGerer && <th>Actions</th>}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {regles.data.map((ligne: any) => (
                                                    <tr key={ligne.id}>
                                                        <td>
                                                            {ligne.nom}
                                                            <div>
                                                                <small className="text-muted">{ligne.type_prelevement}</small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div>{ligne.source_financement?.nom || '-'}</div>
                                                            <small className="text-muted">
                                                                {ligne.type_stage?.nom || 'Tous types de stage'} · {ligne.type_paiement}
                                                            </small>
                                                        </td>
                                                        <td>{formaterMontant(ligne.montant)} F</td>
                                                        <td>
                                                            {versMois(ligne.effet_du)} →{' '}
                                                            {ligne.effet_au ? versMois(ligne.effet_au) : 'sans fin'}
                                                        </td>
                                                        <td>
                                                            <span className={`badge bg-${ligne.actif ? 'success' : 'danger'}`}>
                                                                {ligne.actif ? 'Active' : 'Désactivée'}
                                                            </span>
                                                        </td>
                                                        {peutGerer && (
                                                            <td>
                                                                <div className="d-flex gap-2">
                                                                    <Button
                                                                        size="sm"
                                                                        color="soft-info"
                                                                        onClick={() => ouvrirEdition(ligne)}
                                                                        title="Modifier"
                                                                    >
                                                                        <i className="ri-pencil-fill align-bottom" />
                                                                    </Button>
                                                                    {ligne.actif && (
                                                                        <Button
                                                                            size="sm"
                                                                            color="soft-danger"
                                                                            onClick={() => desactiverRegle(ligne)}
                                                                            title="Désactiver"
                                                                        >
                                                                            <i className="ri-forbid-2-line align-bottom" />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                                {regles.data.length === 0 && (
                                                    <tr>
                                                        <td colSpan={peutGerer ? 6 : 5} className="text-center">
                                                            Aucune règle de prélèvement enregistrée.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    <ServerPagination pagination={normalizePagination(regles)} itemLabel="règles" />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            <Modal isOpen={modaleOuverte} toggle={() => setModaleOuverte(false)} centered size="lg">
                <ModalHeader toggle={() => setModaleOuverte(false)}>
                    {regleEditee ? 'Modifier la règle' : 'Nouvelle règle de prélèvement'}
                </ModalHeader>
                <Form onSubmit={soumettreRegle}>
                    <ModalBody>
                        <Row className="g-3">
                            <Col md={8}>
                                <Label htmlFor="nom" className="form-label">
                                    Intitulé <span className="text-danger">*</span>
                                </Label>
                                <Input
                                    type="text"
                                    id="nom"
                                    value={regle.data.nom}
                                    onChange={(e) => regle.setData('nom', e.target.value)}
                                    invalid={!!regle.errors.nom}
                                />
                                {regle.errors.nom && <div className="invalid-feedback">{regle.errors.nom}</div>}
                            </Col>
                            <Col md={4}>
                                <Label htmlFor="type_prelevement" className="form-label">Type de prélèvement</Label>
                                <select
                                    className="form-select"
                                    id="type_prelevement"
                                    value={regle.data.type_prelevement}
                                    onChange={(e) => regle.setData('type_prelevement', e.target.value)}
                                >
                                    {typesPrelevement.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                            </Col>
                            <Col md={6}>
                                <Label htmlFor="source_financement_id" className="form-label">
                                    Source de financement <span className="text-danger">*</span>
                                </Label>
                                <select
                                    className={`form-select ${regle.errors.source_financement_id ? 'is-invalid' : ''}`}
                                    id="source_financement_id"
                                    value={regle.data.source_financement_id}
                                    onChange={(e) => regle.setData('source_financement_id', e.target.value)}
                                >
                                    <option value="">Sélectionner une source</option>
                                    {sources.map((source) => (
                                        <option key={source.id} value={source.id}>{source.nom}</option>
                                    ))}
                                </select>
                                {regle.errors.source_financement_id && (
                                    <div className="invalid-feedback">{regle.errors.source_financement_id}</div>
                                )}
                            </Col>
                            <Col md={6}>
                                <Label htmlFor="type_stage_id" className="form-label">Type de stage</Label>
                                <select
                                    className="form-select"
                                    id="type_stage_id"
                                    value={regle.data.type_stage_id}
                                    onChange={(e) => regle.setData('type_stage_id', e.target.value)}
                                >
                                    <option value="">Tous les types de stage</option>
                                    {typesStage.map((type) => (
                                        <option key={type.id} value={type.id}>{type.nom}</option>
                                    ))}
                                </select>
                            </Col>
                            <Col md={4}>
                                <Label htmlFor="type_paiement" className="form-label">Type de paiement</Label>
                                <select
                                    className="form-select"
                                    id="type_paiement"
                                    value={regle.data.type_paiement}
                                    onChange={(e) => regle.setData('type_paiement', e.target.value)}
                                >
                                    {typesPaiement.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                            </Col>
                            <Col md={8}>
                                <Label htmlFor="montant" className="form-label">
                                    Montant (F CFA) <span className="text-danger">*</span>
                                </Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    id="montant"
                                    value={regle.data.montant}
                                    onChange={(e) => regle.setData('montant', e.target.value)}
                                    invalid={!!regle.errors.montant}
                                />
                                {regle.errors.montant && <div className="invalid-feedback">{regle.errors.montant}</div>}
                            </Col>
                            <Col md={6}>
                                <Label htmlFor="effet_du" className="form-label">
                                    Applicable à partir de <span className="text-danger">*</span>
                                </Label>
                                <Input
                                    type="month"
                                    id="effet_du"
                                    value={regle.data.effet_du}
                                    onChange={(e) => regle.setData('effet_du', e.target.value)}
                                    invalid={!!regle.errors.effet_du}
                                />
                                {regle.errors.effet_du && <div className="invalid-feedback">{regle.errors.effet_du}</div>}
                            </Col>
                            <Col md={6}>
                                <Label htmlFor="effet_au" className="form-label">Jusqu’à (inclus)</Label>
                                <Input
                                    type="month"
                                    id="effet_au"
                                    value={regle.data.effet_au}
                                    onChange={(e) => regle.setData('effet_au', e.target.value)}
                                    invalid={!!regle.errors.effet_au}
                                />
                                {regle.errors.effet_au && <div className="invalid-feedback">{regle.errors.effet_au}</div>}
                                <small className="text-muted">Laisser vide pour une règle sans date de fin.</small>
                            </Col>
                            <Col md={12}>
                                <div className="form-check form-switch">
                                    <Input
                                        type="checkbox"
                                        className="form-check-input"
                                        id="actif"
                                        checked={regle.data.actif}
                                        onChange={(e) => regle.setData('actif', e.target.checked)}
                                    />
                                    <Label className="form-check-label" htmlFor="actif">Règle active</Label>
                                </div>
                            </Col>
                        </Row>
                    </ModalBody>
                    <ModalFooter>
                        <Button color="light" type="button" onClick={() => setModaleOuverte(false)}>Annuler</Button>
                        <Button color="primary" type="submit" disabled={regle.processing}>Enregistrer</Button>
                    </ModalFooter>
                </Form>
            </Modal>
        </React.Fragment>
    );
};

export default Index;
