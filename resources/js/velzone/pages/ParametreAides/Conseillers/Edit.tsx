import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireConseiller, { DonneesConseiller } from './FormulaireConseiller';

interface Props {
    conseiller: {
        id: number;
        agence_id: number;
        nom: string;
        prenoms: string | null;
        matricule: string | null;
        actif: boolean;
    };
    agences: { id: number; nom: string }[];
}

const Edit = ({ conseiller, agences }: Props) => {
    const { data, setData, put, processing, errors } = useForm<DonneesConseiller>({
        agence_id: conseiller.agence_id,
        nom: conseiller.nom,
        prenoms: conseiller.prenoms || '',
        matricule: conseiller.matricule || '',
        actif: conseiller.actif,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/parametre-aides/conseillers/${conseiller.id}`);
    };

    return (
        <React.Fragment>
            <Head title="Modifier un conseiller" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Modifier un conseiller" pageTitle="Conseillers" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">{conseiller.nom} {conseiller.prenoms}</h5>
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

export default Edit;
