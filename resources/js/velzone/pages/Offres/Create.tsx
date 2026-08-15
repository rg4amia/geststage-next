import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Label, Form } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface Props {
    entreprises: any[];
    agences: any[];
    typesStage: any[];
    sourcesFinancement: any[];
    programmes: any[];
}

const Create = ({ entreprises, agences, typesStage, sourcesFinancement, programmes }: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        entreprise_id: '',
        agence_id: '',
        type_stage_id: '',
        source_financement_id: '',
        programme_id: '',
        numero: '',
        intitule: '',
        description: '',
        nombre_places: 1,
        valide_du: '',
        valide_au: '',
        statut: 'BROUILLON',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/offres');
    };

    return (
        <React.Fragment>
            <Head title="Créer une offre" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Créer une offre" pageTitle="Offres" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Nouvelle offre d'emploi / stage</h5>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={handleSubmit}>
                                        <Row className="g-3">
                                            <Col md={6}>
                                                <Label htmlFor="numero" className="form-label">Numéro Offre <span className="text-danger">*</span></Label>
                                                <Input type="text" id="numero" value={data.numero} onChange={e => setData('numero', e.target.value)} invalid={!!errors.numero} />
                                                {errors.numero && <div className="invalid-feedback">{errors.numero}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="intitule" className="form-label">Intitulé <span className="text-danger">*</span></Label>
                                                <Input type="text" id="intitule" value={data.intitule} onChange={e => setData('intitule', e.target.value)} invalid={!!errors.intitule} />
                                                {errors.intitule && <div className="invalid-feedback">{errors.intitule}</div>}
                                            </Col>
                                            
                                            <Col md={6}>
                                                <Label htmlFor="entreprise_id" className="form-label">Entreprise <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors.entreprise_id ? 'is-invalid' : ''}`} id="entreprise_id" value={data.entreprise_id} onChange={e => setData('entreprise_id', e.target.value)}>
                                                    <option value="">Sélectionner une entreprise</option>
                                                    {entreprises.map(e => <option key={e.id} value={e.id}>{e.raison_sociale}</option>)}
                                                </select>
                                                {errors.entreprise_id && <div className="invalid-feedback">{errors.entreprise_id}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="agence_id" className="form-label">Agence <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors.agence_id ? 'is-invalid' : ''}`} id="agence_id" value={data.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                                    <option value="">Sélectionner une agence</option>
                                                    {agences.map(a => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                                </select>
                                                {errors.agence_id && <div className="invalid-feedback">{errors.agence_id}</div>}
                                            </Col>

                                            <Col md={4}>
                                                <Label htmlFor="type_stage_id" className="form-label">Type de Stage <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors.type_stage_id ? 'is-invalid' : ''}`} id="type_stage_id" value={data.type_stage_id} onChange={e => setData('type_stage_id', e.target.value)}>
                                                    <option value="">Sélectionner</option>
                                                    {typesStage.map(t => <option key={t.id} value={t.id}>{t.libelle}</option>)}
                                                </select>
                                                {errors.type_stage_id && <div className="invalid-feedback">{errors.type_stage_id}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="source_financement_id" className="form-label">Source Financement <span className="text-danger">*</span></Label>
                                                <select className={`form-select ${errors.source_financement_id ? 'is-invalid' : ''}`} id="source_financement_id" value={data.source_financement_id} onChange={e => setData('source_financement_id', e.target.value)}>
                                                    <option value="">Sélectionner</option>
                                                    {sourcesFinancement.map(s => <option key={s.id} value={s.id}>{s.libelle}</option>)}
                                                </select>
                                                {errors.source_financement_id && <div className="invalid-feedback">{errors.source_financement_id}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="programme_id" className="form-label">Programme</Label>
                                                <select className={`form-select ${errors.programme_id ? 'is-invalid' : ''}`} id="programme_id" value={data.programme_id} onChange={e => setData('programme_id', e.target.value)}>
                                                    <option value="">Sélectionner (optionnel)</option>
                                                    {programmes.map(p => <option key={p.id} value={p.id}>{p.libelle}</option>)}
                                                </select>
                                                {errors.programme_id && <div className="invalid-feedback">{errors.programme_id}</div>}
                                            </Col>

                                            <Col md={4}>
                                                <Label htmlFor="nombre_places" className="form-label">Nombre de Places <span className="text-danger">*</span></Label>
                                                <Input type="number" min="1" id="nombre_places" value={data.nombre_places} onChange={e => setData('nombre_places', parseInt(e.target.value) || 1)} invalid={!!errors.nombre_places} />
                                                {errors.nombre_places && <div className="invalid-feedback">{errors.nombre_places}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="valide_du" className="form-label">Valide Du</Label>
                                                <Input type="date" id="valide_du" value={data.valide_du} onChange={e => setData('valide_du', e.target.value)} invalid={!!errors.valide_du} />
                                                {errors.valide_du && <div className="invalid-feedback">{errors.valide_du}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="valide_au" className="form-label">Valide Au</Label>
                                                <Input type="date" id="valide_au" value={data.valide_au} onChange={e => setData('valide_au', e.target.value)} invalid={!!errors.valide_au} />
                                                {errors.valide_au && <div className="invalid-feedback">{errors.valide_au}</div>}
                                            </Col>
                                            
                                            <Col md={12}>
                                                <Label htmlFor="description" className="form-label">Description</Label>
                                                <textarea className={`form-control ${errors.description ? 'is-invalid' : ''}`} id="description" rows={4} value={data.description} onChange={e => setData('description', e.target.value)}></textarea>
                                                {errors.description && <div className="invalid-feedback">{errors.description}</div>}
                                            </Col>

                                            <Col md={12}>
                                                <div className="text-end mt-4">
                                                    <Link href="/offres" className="btn btn-light me-2">Annuler</Link>
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
