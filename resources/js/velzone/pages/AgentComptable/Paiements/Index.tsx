import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Nav, NavItem, NavLink, TabContent, TabPane, Table, Badge, Button } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Props {
    dossiersAttenteVisa: any[];
    dossiersVises: any[];
    moisActuel: string;
    periode: any;
}

const AcPaiementIndex = ({ dossiersAttenteVisa, dossiersVises, moisActuel, periode }: Props) => {
    const [activeTab, setActiveTab] = useState('1');
    const { post, processing } = useForm({});

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const viserDossier = (id: number) => {
        if (confirm('Êtes-vous sûr de vouloir VISER ce dossier de paiement ?')) {
            post(`/agent-comptable/paiements/viser/${id}`);
        }
    };

    const ajournerDossier = (id: number) => {
        const motif = prompt('Veuillez saisir le motif de rejet (Ajournement vers DMG) :');
        if (motif && motif.trim().length > 4) {
            import('@inertiajs/react').then(({ router }) => {
                router.post(`/agent-comptable/paiements/ajourner/${id}`, { motif });
            });
        } else if (motif) {
            alert('Le motif doit contenir au moins 5 caractères.');
        }
    };

    return (
        <React.Fragment>
            <Head title="Agent Comptable - Visas de Paiement" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Visas de Paiement" pageTitle="Agent Comptable" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Dossiers transmis par la DMG - {moisActuel}</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente de Visa <Badge color="warning" className="ms-1">{dossiersAttenteVisa.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Historique Visés <Badge color="success" className="ms-1">{dossiersVises.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro BORD</th>
                                                <th>Nature</th>
                                                <th>Agence</th>
                                                <th>Source Financement</th>
                                                <th>Montant Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dossiersAttenteVisa.map((d, idx) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-primary">{d.numero}</td>
                                                    <td>{d.nature}</td>
                                                    <td>{d.agence?.nom}</td>
                                                    <td>{d.source_financement?.libelle}</td>
                                                    <td className="fw-bold">{d.montant_total} FCFA</td>
                                                    <td>
                                                        <div className="d-flex gap-2">
                                                            <Button color="success" size="sm" onClick={() => viserDossier(d.id)} disabled={processing}>
                                                                <i className="ri-check-double-line"></i> Viser
                                                            </Button>
                                                            <Button color="danger" outline size="sm" onClick={() => ajournerDossier(d.id)}>
                                                                <i className="ri-close-circle-line"></i> Rejeter
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {dossiersAttenteVisa.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun dossier en attente de visa.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro BORD</th>
                                                <th>Nature</th>
                                                <th>Agence</th>
                                                <th>Source Financement</th>
                                                <th>Montant Total</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dossiersVises.map((d, idx) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-primary">{d.numero}</td>
                                                    <td>{d.nature}</td>
                                                    <td>{d.agence?.nom}</td>
                                                    <td>{d.source_financement?.libelle}</td>
                                                    <td className="fw-bold">{d.montant_total} FCFA</td>
                                                    <td><Badge color="success">VISÉ</Badge></td>
                                                </tr>
                                            ))}
                                            {dossiersVises.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun dossier visé historiquement.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>

                </Container>
            </div>
        </React.Fragment>
    );
};

export default AcPaiementIndex;
