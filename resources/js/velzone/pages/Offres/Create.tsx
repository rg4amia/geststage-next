import { Head, Link, useForm } from '@inertiajs/react';
import React, { useState } from 'react';
import Select from 'react-select';
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
        publiee_le: '',
        statut: 'BROUILLON',
    });

    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupError, setLookupError] = useState('');

    const chargerOffre = async () => {
        const reference = data.numero.trim();
        if (!reference) {
            setLookupError("Saisissez d'abord le numero de l'offre.");
            return;
        }

        setLookupLoading(true);
        setLookupError('');
        try {
            const response = await fetch(`/offres/reference/${encodeURIComponent(reference)}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Offre introuvable.');

            const offre = payload.data;
            const typeStage = typesStage.find((type) =>
                String(type.nom || '').trim().toUpperCase() === String(offre.type_stage || '').trim().toUpperCase()
                || (String(offre.type_stage || '').toUpperCase().includes('ECOLE') && String(type.nom || '').toUpperCase().includes('ECOLE'))
            );
            const entreprise = entreprises.find((item) =>
                String(item.raison_sociale || '').trim().toUpperCase() === String(offre.entreprise || '').trim().toUpperCase()
            );

            setData((current) => ({
                ...current,
                numero: offre.reference || current.numero,
                intitule: offre.intitule || current.intitule,
                nombre_places: Number(offre.nombre_places) || current.nombre_places,
                type_stage_id: typeStage ? String(typeStage.id) : current.type_stage_id,
                entreprise_id: entreprise ? String(entreprise.id) : current.entreprise_id,
                publiee_le: offre.publiee_le ? String(offre.publiee_le).slice(0, 10) : current.publiee_le,
                valide_au: offre.valide_au ? String(offre.valide_au).slice(0, 10) : current.valide_au,
            }));
        } catch (error) {
            setLookupError(error instanceof Error ? error.message : "Impossible de charger l'offre.");
        } finally {
            setLookupLoading(false);
        }
    };

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
                                                <Button type="button" color="success" outline size="sm" className="mt-2" onClick={chargerOffre} disabled={lookupLoading}>
                                                    {lookupLoading ? 'Chargement...' : "Charger l'offre AEJ"}
                                                </Button>
                                                {lookupError && <div className="text-danger small mt-1">{lookupError}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="intitule" className="form-label">Intitulé <span className="text-danger">*</span></Label>
                                                <Input type="text" id="intitule" value={data.intitule} onChange={e => setData('intitule', e.target.value)} invalid={!!errors.intitule} />
                                                {errors.intitule && <div className="invalid-feedback">{errors.intitule}</div>}
                                            </Col>
                                            
                                            <Col md={6}>
                                                <Label htmlFor="entreprise_id" className="form-label">Entreprise <span className="text-danger">*</span></Label>
                                                <Select
                                                    isSearchable
                                                    placeholder="Sélectionner une entreprise"
                                                    noOptionsMessage={() => 'Aucune entreprise'}
                                                    options={entreprises.map((e) => ({ value: String(e.id), label: e.raison_sociale }))}
                                                    value={data.entreprise_id ? { value: data.entreprise_id, label: entreprises.find((e) => String(e.id) === data.entreprise_id)?.raison_sociale || '' } : null}
                                                    onChange={(selected) => setData('entreprise_id', selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                    className={errors.entreprise_id ? 'is-invalid' : ''}
                                                />
                                                {errors.entreprise_id && <div className="invalid-feedback d-block">{errors.entreprise_id}</div>}
                                            </Col>
                                            <Col md={6}>
                                                <Label htmlFor="agence_id" className="form-label">Agence <span className="text-danger">*</span></Label>
                                                <Select
                                                    isSearchable
                                                    placeholder="Sélectionner une agence"
                                                    noOptionsMessage={() => 'Aucune agence'}
                                                    options={agences.map((a) => ({ value: String(a.id), label: a.nom }))}
                                                    value={data.agence_id ? { value: data.agence_id, label: agences.find((a) => String(a.id) === data.agence_id)?.nom || '' } : null}
                                                    onChange={(selected) => setData('agence_id', selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                    className={errors.agence_id ? 'is-invalid' : ''}
                                                />
                                                {errors.agence_id && <div className="invalid-feedback d-block">{errors.agence_id}</div>}
                                            </Col>

                                            <Col md={4}>
                                                <Label htmlFor="type_stage_id" className="form-label">Type de Stage <span className="text-danger">*</span></Label>
                                                <Select
                                                    isSearchable
                                                    placeholder="Sélectionner"
                                                    noOptionsMessage={() => 'Aucun type'}
                                                    options={typesStage.map((t) => ({ value: String(t.id), label: t.nom }))}
                                                    value={data.type_stage_id ? { value: data.type_stage_id, label: typesStage.find((t) => String(t.id) === data.type_stage_id)?.nom || '' } : null}
                                                    onChange={(selected) => setData('type_stage_id', selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                    className={errors.type_stage_id ? 'is-invalid' : ''}
                                                />
                                                {errors.type_stage_id && <div className="invalid-feedback d-block">{errors.type_stage_id}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="source_financement_id" className="form-label">Source Financement <span className="text-danger">*</span></Label>
                                                <Select
                                                    isSearchable
                                                    placeholder="Sélectionner"
                                                    noOptionsMessage={() => 'Aucune source'}
                                                    options={sourcesFinancement.map((s) => ({ value: String(s.id), label: s.nom }))}
                                                    value={data.source_financement_id ? { value: data.source_financement_id, label: sourcesFinancement.find((s) => String(s.id) === data.source_financement_id)?.nom || '' } : null}
                                                    onChange={(selected) => setData('source_financement_id', selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                    className={errors.source_financement_id ? 'is-invalid' : ''}
                                                />
                                                {errors.source_financement_id && <div className="invalid-feedback d-block">{errors.source_financement_id}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="programme_id" className="form-label">Programme</Label>
                                                <Select
                                                    isSearchable
                                                    placeholder="Sélectionner (optionnel)"
                                                    noOptionsMessage={() => 'Aucun programme'}
                                                    options={programmes.map((p) => ({ value: String(p.id), label: p.nom }))}
                                                    value={data.programme_id ? { value: data.programme_id, label: programmes.find((p) => String(p.id) === data.programme_id)?.nom || '' } : null}
                                                    onChange={(selected) => setData('programme_id', selected?.value || '')}
                                                    classNamePrefix="react-select"
                                                    className={errors.programme_id ? 'is-invalid' : ''}
                                                />
                                                {errors.programme_id && <div className="invalid-feedback d-block">{errors.programme_id}</div>}
                                            </Col>

                                            <Col md={4}>
                                                <Label htmlFor="nombre_places" className="form-label">Nombre de Places <span className="text-danger">*</span></Label>
                                                <Input type="number" min="1" id="nombre_places" value={data.nombre_places} onChange={e => setData('nombre_places', parseInt(e.target.value) || 1)} invalid={!!errors.nombre_places} />
                                                {errors.nombre_places && <div className="invalid-feedback">{errors.nombre_places}</div>}
                                            </Col>
                                            <Col md={4}>
                                                <Label htmlFor="publiee_le" className="form-label">Date de publication</Label>
                                                <Input type="date" id="publiee_le" value={data.publiee_le} onChange={e => setData('publiee_le', e.target.value)} invalid={!!errors.publiee_le} />
                                                {errors.publiee_le && <div className="invalid-feedback">{errors.publiee_le}</div>}
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
