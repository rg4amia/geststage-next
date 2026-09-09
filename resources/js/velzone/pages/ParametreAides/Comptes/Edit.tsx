import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import FormulaireCompte, { DonneesCompte, RoleAttribuable } from './FormulaireCompte';

interface Props {
    utilisateur: {
        id: number;
        nom: string;
        email: string;
        telephone: string | null;
        actif: boolean;
        roles: string[];
        agences: number[];
    };
    roles: RoleAttribuable[];
    agences: { id: number; nom: string }[];
}

const Edit = ({ utilisateur, roles, agences }: Props) => {
    const { data, setData, put, processing, errors } = useForm<DonneesCompte>({
        nom: utilisateur.nom,
        email: utilisateur.email,
        telephone: utilisateur.telephone || '',
        password: '',
        password_confirmation: '',
        actif: utilisateur.actif,
        roles: utilisateur.roles,
        agences: utilisateur.agences,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/parametre-aides/comptes/${utilisateur.id}`);
    };

    return (
        <React.Fragment>
            <Head title={`Modifier ${utilisateur.nom}`} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Modifier un compte" pageTitle="Comptes utilisateurs" />
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0">{utilisateur.nom}</h5>
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
                                        motDePasseFacultatif
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
