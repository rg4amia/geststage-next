import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireAgence, { DonneesAgence } from './FormulaireAgence';

interface Props {
    agence: {
        id: number;
        code: string;
        nom: string;
        region_id: number | null;
        commune_id: number | null;
        adresse: string | null;
        actif: boolean;
    };
    regions: { id: number; nom: string }[];
    communes: { id: number; nom: string; region_id: number | null }[];
}

const Edit = ({ agence, regions, communes }: Props) => {
    const { data, setData, put, processing, errors } = useForm<DonneesAgence>({
        code: agence.code,
        nom: agence.nom,
        region_id: agence.region_id ?? '',
        commune_id: agence.commune_id ?? '',
        adresse: agence.adresse || '',
        actif: agence.actif,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/parametre-aides/agences/${agence.id}`);
    };

    return (
        <React.Fragment>
            <Head title={`Modifier ${agence.nom}`} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Modifier une agence" pageTitle="Agences" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">{agence.nom}</h5>
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

export default Edit;
