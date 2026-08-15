import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Table, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

const DmgPaiementIndex = ({
    moisActuel,
    attentePaiementDemarrage = [],
    attentePaiementPresence = [],
    dossiersBrouillon = [],
    dossiersTransmis = [],
    dossiersAjournes = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [processing, setProcessing] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedPaiement, setSelectedPaiement] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const handleAjourner = (paiement: any) => {
        setSelectedPaiement(paiement);
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

    const genererDossiers = () => {
        setProcessing(true);
        router.post('/dmg/paiements/generer-dossiers', {}, {
            onFinish: () => setProcessing(false)
        });
    };

    const transmettreDossier = (id: number) => {
        if (confirm('Transmettre ce dossier à l\'Agent Comptable ?')) {
            router.post(`/dmg/paiements/transmettre/${id}`);
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
                            {(activeTab === '1' || activeTab === '2') && (
                                <Button color="primary" onClick={genererDossiers} disabled={processing}>
                                    <i className="ri-folder-add-line align-middle me-1"></i> Générer Dossiers de Paiement
                                </Button>
                            )}
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente Démarrage <Badge color="primary" className="ms-1">{attentePaiementDemarrage.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Attente Présence <Badge color="primary" className="ms-1">{attentePaiementPresence.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Élaboration OP (Dossiers) <Badge color="info" className="ms-1">{dossiersBrouillon.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '4' })} onClick={() => toggleTab('4')}>
                                        Ordres Différés (AC) <Badge color="danger" className="ms-1">{dossiersAjournes.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Agence</th>
                                                <th>Bénéficiaire</th>
                                                <th>Numéro AEJ</th>
                                                <th>Montant</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {attentePaiementDemarrage.map((p: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td>{p.stage?.agence?.nom}</td>
                                                    <td>{p.stage?.beneficiaire?.nom} {p.stage?.beneficiaire?.prenoms}</td>
                                                    <td>{p.stage?.beneficiaire?.numero_aej}</td>
                                                    <td className="fw-bold text-success">45 000 FCFA</td>
                                                    <td>
                                                        <Button color="danger" size="sm" onClick={() => handleAjourner(p)}>
                                                            Ajourner
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {attentePaiementDemarrage.length === 0 && (
                                                <tr><td colSpan={5} className="text-center p-3">Aucun stagiaire en attente de paiement de démarrage.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Agence</th>
                                                <th>Bénéficiaire</th>
                                                <th>Numéro AEJ</th>
                                                <th>Jours Présence</th>
                                                <th>Montant</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {attentePaiementPresence.map((p: any, idx: number) => (
                                                <tr key={idx}>
                                                    <td>{p.stage?.agence?.nom}</td>
                                                    <td>{p.stage?.beneficiaire?.nom} {p.stage?.beneficiaire?.prenoms}</td>
                                                    <td>{p.stage?.beneficiaire?.numero_aej}</td>
                                                    <td>{p.jours_presence || 30}</td>
                                                    <td className="fw-bold text-success">45 000 FCFA</td>
                                                    <td>
                                                        <Button color="danger" size="sm" onClick={() => handleAjourner(p)}>
                                                            Ajourner
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {attentePaiementPresence.length === 0 && (
                                                <tr><td colSpan={6} className="text-center p-3">Aucun stagiaire en attente de paiement de présence.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="3">
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
                                            {dossiersBrouillon.map((d: any, idx: number) => (
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
                                                <tr><td colSpan={5} className="text-center p-3">Aucun dossier en cours d'élaboration.</td></tr>
                                            )}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="4">
                                    <p className="text-center p-3">Dossiers différés par l'Agent Comptable ({dossiersAjournes.length}).</p>
                                </TabPane>

                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* Modal Ajournement DMG */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Ajourner le paiement</ModalHeader>
                <ModalBody>
                    <p>En ajournant ce dossier, il retournera dans la corbeille "Pointage Ajourné DMG" du CIP.</p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif de l'ajournement</label>
                        <Input type="textarea" id="motif" rows={3} placeholder="Ex: RIB invalide, jours de présence incohérents..." />
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

export default DmgPaiementIndex;
