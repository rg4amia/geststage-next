import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Table, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

const CbPaiementsIndex = ({
    dossiersControle = [],
    etatsAjournes = []
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

    const validerDossier = (id: number) => {
        if (confirm('Valider ce dossier de paiement pour élaboration de l\'OP ?')) {
            router.post(`/cb/paiements/valider/${id}`);
        }
    };

    return (
        <React.Fragment>
            <Head title="Espace Chef de Bureau - Paiements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Contrôle des Dossiers" pageTitle="Chef de Bureau (CB)" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Vérification des États de Paiement</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Contrôle des Dossiers <Badge color="primary" className="ms-1">{dossiersControle.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        États Ajournés (Retours DMG) <Badge color="danger" className="ms-1">{etatsAjournes.length}</Badge>
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
                                                <th>Nombre Stagiaires</th>
                                                <th>Montant Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dossiersControle.map((d: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-primary">{d.numero}</td>
                                                    <td>{d.agence?.nom}</td>
                                                    <td>{d.source_financement?.libelle}</td>
                                                    <td>{d.nombre_stagiaires || 0}</td>
                                                    <td className="fw-bold">{d.montant_total} FCFA</td>
                                                    <td>
                                                        <Button color="success" size="sm" className="me-2" onClick={() => validerDossier(d.id)} disabled={processing}>
                                                            <i className="ri-check-line align-bottom me-1"></i> Valider
                                                        </Button>
                                                        <Button color="danger" size="sm" onClick={() => handleAjourner(d)}>
                                                            <i className="ri-close-line align-bottom me-1"></i> Ajourner
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {dossiersControle.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun dossier en attente de contrôle.</td></tr>
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
                                                <th>Motif Ajournement</th>
                                                <th>Date Ajournement</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {etatsAjournes.map((e: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td className="fw-medium text-danger">{e.numero}</td>
                                                    <td>{e.agence?.nom}</td>
                                                    <td>{e.motif_ajournement}</td>
                                                    <td>{e.date_ajournement}</td>
                                                </tr>
                                            ))}
                                            {etatsAjournes.length === 0 && (
                                                <tr><td colSpan={4} className="text-center p-3">Aucun état de paiement ajourné.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* Modal Ajournement CB */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Ajourner l'état de paiement</ModalHeader>
                <ModalBody>
                    <p>En ajournant cet état, il retournera à la DMG pour correction (Corbeille: Etats de paiement ajournés / CB).</p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif de l'ajournement</label>
                        <Input type="textarea" id="motif" rows={3} placeholder="Ex: Montant incohérent, bénéficiaire inéligible..." />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjournement} disabled={processing}>Confirmer l'Ajournement</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CbPaiementsIndex;
