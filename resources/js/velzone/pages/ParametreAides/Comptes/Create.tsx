import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireCompte, { DonneesCompte, RoleAttribuable } from './FormulaireCompte';

interface Props {
    roles: RoleAttribuable[];
    agences: { id: number; nom: string }[];
}

const Create = ({ roles, agences }: Props) => {
    const { data, setData, post, processing, errors } = useForm<DonneesCompte>({
        nom: '',
        email: '',
        telephone: '',
        password: '',
        password_confirmation: '',
        actif: true,
        roles: [],
        agences: [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/parametre-aides/comptes');
    };

    return (
        <React.Fragment>
            <Head title="Nouveau compte utilisateur" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouveau compte" pageTitle="Comptes utilisateurs" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">Créer un compte utilisateur</h5>
                                </CardHeader>
                                <CardBody>
                                    <FormulaireCompte
                                        data={data}
                                        setData={setData as any}
                                        errors={errors as any}
                                        processing={processing}
                                        onSubmit={handleSubmit}
                                        roles={roles}
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
