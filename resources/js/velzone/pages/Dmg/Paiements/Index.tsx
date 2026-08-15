import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Nav, NavItem, NavLink, TabContent, TabPane, Table, Badge, Button } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface Props {
    paiementsATraiter: any[];
    dossiersBrouillon: any[];
    dossiersTransmis: any[];
    dossiersAjournes: any[];
    moisActuel: string;
    periode: any;
}

const DmgPaiementIndex = ({ paiementsATraiter, dossiersBrouillon, dossiersTransmis, dossiersAjournes, moisActuel, periode }: Props) => {
    const [activeTab, setActiveTab] = useState('1');
    const { post, processing } = useForm({
        periode_id: periode?.id
    });

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const genererDossiers = () => {
        if (confirm('Générer les dossiers pour tous les paiements en attente de cette période ?')) {
            post('/dmg/paiements/generer');
        }
    };

    const transmettreDossier = (id: number) => {
        if (confirm('Transmettre ce dossier à l\'Agent Comptable ?')) {
            post(`/dmg/paiements/transmettre/${id}`);
        }
    };

    return (
        <React.Fragment>
            <Head title="Traitement DMG - Chaîne Financière" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Traitement DMG" pageTitle="Chaîne Financière" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Gestion des Paiements - {moisActuel}</h4>
                            {activeTab === '1' && paiementsATraiter.length > 0 && (
                                <Button color="primary" onClick={genererDossiers} disabled={processing}>
                                    <i className="ri-folder-add-line align-middle me-1"></i> Générer Dossiers de Paiement
                                </Button>
                            )}
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Paiements à Traiter <Badge color="warning" className="ms-1">{paiementsATraiter.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Dossiers Brouillon <Badge color="info" className="ms-1">{dossiersBrouillon.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Transmis AC <Badge color="success" className="ms-1">{dossiersTransmis.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '4' })} onClick={() => toggleTab('4')}>
                                        Ajournés AC <Badge color="danger" className="ms-1">{dossiersAjournes.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Nature</th>
                                                <th>Agence</th>
                                                <th>Bénéficiaire</th>
                                                <th>Numéro AEJ</th>
                                                <th>Montant</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {paiementsATraiter.map((p, idx) => (
                                                <tr key={idx}>
                                                    <td>{p.droit_paiement?.nature}</td>
                                                    <td>{p.droit_paiement?.stage?.agence?.nom}</td>
                                                    <td>{p.droit_paiement?.stage?.beneficiaire?.nom} {p.droit_paiement?.stage?.beneficiaire?.prenoms}</td>
                                                    <td>{p.droit_paiement?.stage?.beneficiaire?.numero_aej}</td>
                                                    <td className="fw-bold">{p.montant} FCFA</td>
                                                    <td><Badge color="warning">{p.statut}</Badge></td>
                                                </tr>
                                            ))}
                                            {paiementsATraiter.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun paiement à traiter.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro BORD</th>
                                                <th>Agence</th>
                                                <th>Source Financement</th>
                                                <th>Montant Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dossiersBrouillon.map((d, idx) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-primary">{d.numero}</td>
                                                    <td>{d.agence?.nom}</td>
                                                    <td>{d.source_financement?.libelle}</td>
                                                    <td className="fw-bold">{d.montant_total} FCFA</td>
                                                    <td>
                                                        <Button color="success" size="sm" onClick={() => transmettreDossier(d.id)} disabled={processing}>
                                                            Transmettre AC
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {dossiersBrouillon.length === 0 && (
                                                <tr><td colSpan={5} className="text-center p-3">Aucun dossier brouillon.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                {/* Tabs 3 & 4 (Transmis et Ajournés) would similarly display dossiers... */}
                                <TabPane tabId="3">
                                    <p className="text-center p-3">Dossiers transmis à l'Agent Comptable ({dossiersTransmis.length}).</p>
                                </TabPane>
                                <TabPane tabId="4">
                                    <p className="text-center p-3">Dossiers ajournés par l'Agent Comptable ({dossiersAjournes.length}).</p>
                                </TabPane>

                            </TabContent>
                        </CardBody>
                    </Card>

                </Container>
            </div>
        </React.Fragment>
    );
};

export default DmgPaiementIndex;
