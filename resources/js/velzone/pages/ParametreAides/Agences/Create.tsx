import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireAgence, { DonneesAgence } from './FormulaireAgence';

interface Props {
    regions: { id: number; nom: string }[];
    communes: { id: number; nom: string; region_id: number | null }[];
}

const Create = ({ regions, communes }: Props) => {
    const { data, setData, post, processing, errors } = useForm<DonneesAgence>({
        code: '',
        nom: '',
        region_id: '',
        commune_id: '',
        adresse: '',
        actif: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/parametre-aides/agences');
    };

    return (
        <React.Fragment>
            <Head title="Nouvelle agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouvelle agence" pageTitle="Agences" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Créer une agence</h5>
                                </CardHeader>
                                <CardBody>
                                    <FormulaireAgence
                                        data={data}
                                        setData={setData as any}
                                        errors={errors as any}
                                        processing={processing}
                                        onSubmit={handleSubmit}
                                        regions={regions}
                                        communes={communes}
                                    />
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
