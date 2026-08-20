import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import { Container, Row, Col, Card, CardBody, CardHeader, Form, Label, Input, Button, Alert } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

export default function EditStagiaire({
    stage,
    typesPaiement,
    returnTo,
}: {
    stage: any,
    typesPaiement: any[],
    returnTo?: { tab?: string; mois?: string | null },
}) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const beneficiaire = stage.beneficiaire || {};

    const { data, setData, put, processing, errors } = useForm({
        telephone_principal: beneficiaire.telephone_principal || '',
        telephone_secondaire: beneficiaire.telephone_secondaire || '',
        type_paiement_id: beneficiaire.type_paiement_id || '',
        numero_tresor_money: beneficiaire.numero_tresor_money || '',
        numero_wave: beneficiaire.numero_wave || '',
        return_tab: returnTo?.tab || 'ajourne_dmg',
        mois: returnTo?.mois || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/cip/pointages/update-stagiaire/${stage.id}`);
    };

    return (
        <React.Fragment>
            <Head title="Éditer Stagiaire" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Éditer Stagiaire" pageTitle="Pointages" />

                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show">
                            <i className="ri-check-double-line me-2"></i>{flash.success}
                        </Alert>
                    )}

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Informations de Paiement et Contact - {beneficiaire.nom} {beneficiaire.prenoms}</h5>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={handleSubmit}>
                                        <Row>
                                            <Col md={6} className="mb-3">
                                                <Label className="form-label">Téléphone Principal</Label>
                                                <Input 
                                                    type="text" 
                                                    value={data.telephone_principal} 
                                                    onChange={e => setData('telephone_principal', e.target.value)} 
                                                    invalid={!!errors.telephone_principal}
                                                />
                                            </Col>
                                            <Col md={6} className="mb-3">
                                                <Label className="form-label">Téléphone Secondaire</Label>
                                                <Input 
                                                    type="text" 
                                                    value={data.telephone_secondaire} 
                                                    onChange={e => setData('telephone_secondaire', e.target.value)} 
                                                />
                                            </Col>
                                        </Row>
                                        <Row>
                                            <Col md={4} className="mb-3">
                                                <Label className="form-label">Type de Paiement</Label>
                                                <Input 
                                                    type="select" 
                                                    value={data.type_paiement_id} 
                                                    onChange={e => setData('type_paiement_id', e.target.value)}
                                                    invalid={!!errors.type_paiement_id}
                                                >
                                                    <option value="">Sélectionner</option>
                                                    {typesPaiement.map((tp: any) => (
                                                        <option key={tp.id} value={tp.id}>{tp.nom || tp.libelle || tp.code}</option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col md={4} className="mb-3">
                                                <Label className="form-label">N° TrésorMoney</Label>
                                                <Input 
                                                    type="text" 
                                                    value={data.numero_tresor_money} 
                                                    onChange={e => setData('numero_tresor_money', e.target.value)} 
                                                />
                                            </Col>
                                            <Col md={4} className="mb-3">
                                                <Label className="form-label">N° Wave</Label>
                                                <Input 
                                                    type="text" 
                                                    value={data.numero_wave} 
                                                    onChange={e => setData('numero_wave', e.target.value)} 
                                                />
                                            </Col>
                                        </Row>
                                        <div className="text-end mt-4">
                                            <Button type="button" color="light" className="me-2" onClick={() => window.history.back()}>
                                                Annuler
                                            </Button>
                                            <Button type="submit" color="primary" disabled={processing}>
                                                Enregistrer les modifications
                                            </Button>
                                        </div>
                                    </Form>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
}
