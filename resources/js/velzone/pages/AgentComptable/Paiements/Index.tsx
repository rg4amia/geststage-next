import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Table, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

const AcPaiementsIndex = ({
    bordereauxAttente = [],
    ordresRejetes = [],
    statutPaiements = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [processing, setProcessing] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedDossier, setSelectedDossier] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const handleAjourner = (dossier: any) => {
        setSelectedDossier(dossier);
        setModalOpen(true);
    };

    const confirmAjournement = () => {
        setProcessing(true);
        // Api call simulation
        setTimeout(() => {
            setProcessing(false);
            setModalOpen(false);
        }, 500);
    };

    const validerPaiement = (id: number) => {
        if (confirm('Valider ce bordereau pour paiement ?')) {
            router.post(`/ac/paiements/valider/${id}`);
        }
    };

    return (
        <React.Fragment>
            <Head title="Espace Agent Comptable - Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Gestion des Paiements" pageTitle="Agent Comptable" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Bordereaux et Ordres de Paiement</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Bordereaux en Attente <Badge color="primary" className="ms-1">{bordereauxAttente.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Ordres Rejetés / Différés <Badge color="danger" className="ms-1">{ordresRejetes.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Statut des Paiements <Badge color="info" className="ms-1">{statutPaiements.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro BORD</th>
                                                <th>Agence</th>
                                                <th>Source Financement</th>
                                                <th>Montant Total</th>
                                                <th>Date Transmission</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {bordereauxAttente.map((b: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-primary">{b.numero}</td>
                                                    <td>{b.agence?.nom}</td>
                                                    <td>{b.source_financement?.libelle}</td>
                                                    <td className="fw-bold text-success">{b.montant_total} FCFA</td>
                                                    <td>{b.date_transmission}</td>
                                                    <td>
                                                        <Button color="success" size="sm" className="me-2" onClick={() => validerPaiement(b.id)} disabled={processing}>
                                                            <i className="ri-check-line align-bottom me-1"></i> Valider
                                                        </Button>
                                                        <Button color="danger" size="sm" onClick={() => handleAjourner(b)}>
                                                            <i className="ri-close-line align-bottom me-1"></i> Rejeter/Différer
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {bordereauxAttente.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun bordereau en attente de validation.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro OP</th>
                                                <th>Motif Rejet</th>
                                                <th>Date Rejet</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {ordresRejetes.map((o: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-danger">{o.numero}</td>
                                                    <td>{o.motif_rejet}</td>
                                                    <td>{o.date_rejet}</td>
                                                    <td><Badge color="danger">Rejeté</Badge></td>
                                                    <td>
                                                        <Button color="primary" size="sm" outline>
                                                            Consulter
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {ordresRejetes.length === 0 && (
                                                <tr><td colSpan={5} className="text-center p-3">Aucun ordre de paiement rejeté.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="3">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro BORD</th>
                                                <th>Date Validation</th>
                                                <th>Montant Total</th>
                                                <th>Statut Banque</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {statutPaiements.map((s: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium">{s.numero}</td>
                                                    <td>{s.date_validation}</td>
                                                    <td className="fw-bold">{s.montant_total} FCFA</td>
                                                    <td><Badge color="success">Payé</Badge></td>
                                                </tr>
                                            ))}
                                            {statutPaiements.length === 0 && (
                                                <tr><td colSpan={4} className="text-center p-3">Aucun historique de paiement disponible.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* Modal Rejet / Différé AC */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Rejeter ou Différer le Bordereau</ModalHeader>
                <ModalBody>
                    <p>En rejetant ce dossier, il retournera à la DMG. Précisez si c'est un rejet définitif ou différé.</p>
                    <div className="mb-3">
                        <label htmlFor="typeAction" className="form-label">Type d'action</label>
                        <Input type="select" id="typeAction" defaultValue="differe">
                            <option value="differe">Différer</option>
                            <option value="rejet">Rejet Définitif</option>
                        </Input>
                    </div>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif</label>
                        <Input type="textarea" id="motif" rows={3} placeholder="Ex: Problème de signature, fonds insuffisants..." />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjournement} disabled={processing}>Confirmer le Retour</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default AcPaiementsIndex;
