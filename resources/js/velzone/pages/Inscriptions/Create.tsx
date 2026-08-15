import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Label, Form, FormFeedback } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';
import Select from 'react-select';

interface Props {
    offres: any[];
}

const Create = ({ offres }: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        beneficiaire: {
            numero_aej: '',
            nom: '',
            prenoms: '',
            date_naissance: '',
            sexe: '',
        },
        stage: {
            entreprise_id: '',
            agence_id: '',
            type_stage_id: '',
            source_financement_id: '',
            offre_emploi_id: '',
            intitule_poste: '',
            date_debut: '',
            date_fin_prevue: '',
        },
        contrat: {
            numero: '',
            date_debut: '',
            date_fin: '',
            prime_mensuelle: '',
        }
    });

    const offresOptions = offres.map((offre) => ({
        value: offre.id,
        label: `${offre.numero} - ${offre.intitule} (${offre.entreprise?.raison_sociale})`,
        offre: offre
    }));

    const handleOffreSelect = (selectedOption: any) => {
        if (!selectedOption) return;
        
        const offre = selectedOption.offre;
        setData('stage', {
            ...data.stage,
            offre_emploi_id: offre.id,
            entreprise_id: offre.entreprise_id,
            agence_id: offre.agence_id,
            type_stage_id: offre.type_stage_id,
            source_financement_id: offre.source_financement_id,
            intitule_poste: offre.intitule,
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/inscriptions');
    };

    return (
        <React.Fragment>
            <Head title="Nouvelle Inscription" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouvelle Inscription" pageTitle="Dossiers" />
                    
                    <Form onSubmit={submit}>
                        <Row>
                            {/* BÉNÉFICIAIRE */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">1. Informations du Bénéficiaire</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={6}>
                                                <Label>Numéro AEJ <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.numero_aej} onChange={e => setData('beneficiaire', {...data.beneficiaire, numero_aej: e.target.value})} invalid={!!errors['beneficiaire.numero_aej']} />
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Sexe <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors['beneficiaire.sexe'] ? 'is-invalid' : ''}`} value={data.beneficiaire.sexe} onChange={e => setData('beneficiaire', {...data.beneficiaire, sexe: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    <option value="M">Masculin</option>
                                                    <option value="F">Féminin</option>
                                                </select>
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Nom <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.nom} onChange={e => setData('beneficiaire', {...data.beneficiaire, nom: e.target.value})} invalid={!!errors['beneficiaire.nom']} />
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Prénoms <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.prenoms} onChange={e => setData('beneficiaire', {...data.beneficiaire, prenoms: e.target.value})} invalid={!!errors['beneficiaire.prenoms']} />
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Date de naissance <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.beneficiaire.date_naissance} onChange={e => setData('beneficiaire', {...data.beneficiaire, date_naissance: e.target.value})} invalid={!!errors['beneficiaire.date_naissance']} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* STAGE */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">2. Lier à une Offre (Stage)</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={12}>
                                                <Label>Offre d'emploi associée <span className="text-danger">*</span></Label>
                                                <Select
                                                    options={offresOptions}
                                                    onChange={handleOffreSelect}
                                                    placeholder="Sélectionner une offre..."
                                                    className={errors['stage.offre_emploi_id'] ? 'is-invalid' : ''}
                                                />
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Intitulé du poste retenu <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.stage.intitule_poste} onChange={e => setData('stage', {...data.stage, intitule_poste: e.target.value})} invalid={!!errors['stage.intitule_poste']} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Date de début <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.stage.date_debut} onChange={e => setData('stage', {...data.stage, date_debut: e.target.value})} invalid={!!errors['stage.date_debut']} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Date de fin prévue <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.stage.date_fin_prevue} onChange={e => setData('stage', {...data.stage, date_fin_prevue: e.target.value})} invalid={!!errors['stage.date_fin_prevue']} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* CONTRAT INITIAL */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">3. Contrat initial</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={4}>
                                                <Label>Numéro de contrat</Label>
                                                <Input type="text" value={data.contrat.numero} onChange={e => setData('contrat', {...data.contrat, numero: e.target.value})} invalid={!!errors['contrat.numero']} placeholder="Généré automatiquement si vide" />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Date de début <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.contrat.date_debut} onChange={e => setData('contrat', {...data.contrat, date_debut: e.target.value})} invalid={!!errors['contrat.date_debut']} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Date de fin <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.contrat.date_fin} onChange={e => setData('contrat', {...data.contrat, date_fin: e.target.value})} invalid={!!errors['contrat.date_fin']} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Prime mensuelle (FCFA) <span className="text-danger">*</span></Label>
                                                <Input type="number" value={data.contrat.prime_mensuelle} onChange={e => setData('contrat', {...data.contrat, prime_mensuelle: e.target.value})} invalid={!!errors['contrat.prime_mensuelle']} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            <Col lg={12}>
                                <div className="text-end mb-4">
                                    <Link href="/inscriptions" className="btn btn-light me-2">Annuler</Link>
                                    <Button color="primary" type="submit" disabled={processing}>
                                        <i className="ri-save-line align-bottom me-1"></i> Valider l'inscription
                                    </Button>
                                </div>
                            </Col>
                        </Row>
                    </Form>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Create;
