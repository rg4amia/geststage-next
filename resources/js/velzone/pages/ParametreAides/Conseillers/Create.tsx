import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireConseiller, { DonneesConseiller } from './FormulaireConseiller';

interface Props {
    agences: { id: number; nom: string }[];
}

const Create = ({ agences }: Props) => {
    const { data, setData, post, processing, errors } = useForm<DonneesConseiller>({
        agence_id: '',
        nom: '',
        prenoms: '',
        matricule: '',
        actif: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/parametre-aides/conseillers');
    };

    return (
        <React.Fragment>
            <Head title="Nouveau conseiller" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouveau conseiller" pageTitle="Conseillers" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Créer un conseiller</h5>
                                </CardHeader>
                                <CardBody>
                                    <FormulaireConseiller
                                        data={data}
                                        setData={setData as any}
                                        errors={errors as any}
                                        processing={processing}
                                        onSubmit={handleSubmit}
                                        agences={agences}
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
