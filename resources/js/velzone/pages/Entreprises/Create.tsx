import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Label, Form } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface Props {
    agences: any[];
    communes: any[];
    typesStructure: any[];
}

const Create = ({ agences, communes, typesStructure }: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        agence_id: '',
        commune_id: '',
        type_structure_id: '',
        raison_sociale: '',
        sigle: '',
        numero_contribuable: '',
        registre_commerce: '',
        adresse: '',
        telephone: '',
        email: '',
        actif: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/entreprises');
    };

    return (
        <React.Fragment>
            <Head title="Ajouter une entreprise" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Ajouter une entreprise" pageTitle="Entreprises" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Nouvelle entreprise</h5>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={handleSubmit}>
                                        <Row className="g-3">
                                            <Col md={6}>
                                                <Label htmlFor="raison_sociale" className="form-label">Raison Sociale <span className="text-danger">*</span></Label>
                                                <Input type="text" id="raison_sociale" value={data.raison_sociale} onChange={e => setData('raison_sociale', e.target.value)} invalid={!!errors.raison_sociale} />
                                                {errors.raison_sociale && <div className="invalid-feedback">{errors.raison_sociale}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="sigle" className="form-label">Sigle</Label>
                                                <Input type="text" id="sigle" value={data.sigle} onChange={e => setData('sigle', e.target.value)} invalid={!!errors.sigle} />
                                                {errors.sigle && <div className="invalid-feedback">{errors.sigle}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="agence_id" className="form-label">Agence <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors.agence_id ? 'is-invalid' : ''}`} id="agence_id" value={data.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                                    <option value="">Sélectionner une agence</option>
                                                    {agences.map(a => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                                </select>
                                                {errors.agence_id && <div className="invalid-feedback">{errors.agence_id}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="type_structure_id" className="form-label">Type de Structure</Label>
                                                <select className={`form-select ${errors.type_structure_id ? 'is-invalid' : ''}`} id="type_structure_id" value={data.type_structure_id} onChange={e => setData('type_structure_id', e.target.value)}>
                                                    <option value="">Sélectionner un type</option>
                                                    {typesStructure.map(t => <option key={t.id} value={t.id}>{t.libelle}</option>)}
                                                </select>
                                                {errors.type_structure_id && <div className="invalid-feedback">{errors.type_structure_id}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="numero_contribuable" className="form-label">Numéro Contribuable (NCC)</Label>
                                                <Input type="text" id="numero_contribuable" value={data.numero_contribuable} onChange={e => setData('numero_contribuable', e.target.value)} invalid={!!errors.numero_contribuable} />
                                                {errors.numero_contribuable && <div className="invalid-feedback">{errors.numero_contribuable}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="registre_commerce" className="form-label">Registre de Commerce (RC)</Label>
                                                <Input type="text" id="registre_commerce" value={data.registre_commerce} onChange={e => setData('registre_commerce', e.target.value)} invalid={!!errors.registre_commerce} />
                                                {errors.registre_commerce && <div className="invalid-feedback">{errors.registre_commerce}</div>}
                                            </Col>
                                            <Col md={12}>
                                                <div className="text-end mt-4">
                                                    <Link href="/entreprises" className="btn btn-light me-2">Annuler</Link>
                                                    <Button color="primary" type="submit" disabled={processing}>Enregistrer</Button>
                                                </div>
                                            </Col>
                                        </Row>
                                    </Form>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Create;
