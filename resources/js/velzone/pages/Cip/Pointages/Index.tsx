import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Container, Row, Col, Card, CardBody, CardHeader, Nav, NavItem, NavLink, TabContent, TabPane, Table, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input, Label } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

interface CipPointageProps {
    attente: any[];
    effectues: any[];
    ajournesCA: any[];
    ajournesDMG: any[];
    moisManques: Record<number, string>;
    moisActuel: string;
}

const CipPointageIndex = ({ attente, effectues, ajournesCA, ajournesDMG, moisManques, moisActuel }: CipPointageProps) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedStage, setSelectedStage] = useState<any>(null);

    const { data, setData, post, processing, reset } = useForm({
        periode_id: '',
        jours_presents: 0,
        jours_absents: 0,
        observation: ''
    });

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const toggleModal = () => setModalOpen(!modalOpen);

    const openSoumissionModal = (stage: any) => {
        setSelectedStage(stage);
        // Find periode_id from moisManques (this logic requires matching moisActuel with the ID, simplified here)
        const periodeEntry = Object.entries(moisManques).find(([id, nom]) => nom.includes(moisActuel));
        setData({
            periode_id: periodeEntry ? periodeEntry[0] : '',
            jours_presents: 0,
            jours_absents: 0,
            observation: ''
        });
        setModalOpen(true);
    };

    const soumettrePointage = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/cip/pointages/soumettre/${selectedStage.id}`, {
            onSuccess: () => {
                setModalOpen(false);
                reset();
            }
        });
    };

    return (
        <React.Fragment>
            <Head title="Pointage Présence - CIP" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointage Mensuel" pageTitle="Saisie CIP" />

                    <Card>
                        <CardHeader>
                            <h4 className="card-title mb-0">Gestion des Pointages - {moisActuel}</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente Pointage <Badge color="danger" className="ms-1">{attente.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Pointage Effectué <Badge color="success" className="ms-1">{effectues.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Ajourné / Chef Agence <Badge color="warning" className="ms-1">{ajournesCA.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '4' })} onClick={() => toggleTab('4')}>
                                        Ajourné / DMG <Badge color="danger" className="ms-1">{ajournesDMG.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th>Numéro AEJ</th>
                                                <th>Bénéficiaire</th>
                                                <th>Sexe</th>
                                                <th>Entreprise</th>
                                                <th>Date début</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {attente.map((stage, idx) => (
                                                <tr key={idx}>
                                                    <td>{stage.beneficiaire?.numero_aej}</td>
                                                    <td>{stage.beneficiaire?.nom} {stage.beneficiaire?.prenoms}</td>
                                                    <td>{stage.beneficiaire?.sexe === 'M' ? 'Masculin' : 'Féminin'}</td>
                                                    <td>{stage.entreprise?.raison_sociale}</td>
                                                    <td>{new Date(stage.created_at).toLocaleDateString()}</td>
                                                    <td>
                                                        <Button color="primary" size="sm" onClick={() => openSoumissionModal(stage)}>Pointer</Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="2">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th style={{ backgroundColor: 'teal', color: 'white' }}>Mois</th>
                                                <th>Bénéficiaire</th>
                                                <th>Présences</th>
                                                <th>Saisi par</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {effectues.map((p, idx) => (
                                                <tr key={idx}>
                                                    <td>{moisActuel}</td>
                                                    <td>{p.stage?.beneficiaire?.nom} {p.stage?.beneficiaire?.prenoms}</td>
                                                    <td>{p.version_courante?.jours_presents} j.</td>
                                                    <td>CIP</td>
                                                    <td><Badge color={p.statut === 'VALIDE' ? 'success' : 'info'}>{p.statut}</Badge></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="3">
                                    <Table className="table-nowrap mb-0 align-middle table-striped">
                                        <thead className="table-light">
                                            <tr>
                                                <th style={{ backgroundColor: 'teal', color: 'white' }}>Mois</th>
                                                <th>Bénéficiaire</th>
                                                <th>Présences</th>
                                                <th>Motif Ajournement CA</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {ajournesCA.map((p, idx) => (
                                                <tr key={idx}>
                                                    <td>{moisActuel}</td>
                                                    <td>{p.stage?.beneficiaire?.nom} {p.stage?.beneficiaire?.prenoms}</td>
                                                    <td>{p.version_courante?.jours_presents} j.</td>
                                                    <td className="text-danger">{p.decisions?.[0]?.motif}</td>
                                                    <td>
                                                        <Button color="warning" size="sm" onClick={() => openSoumissionModal(p.stage)}>Corriger</Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </Table>
                                </TabPane>

                                <TabPane tabId="4">
                                    <p className="text-center p-3">Pointages ajournés par la DMG apparaîtront ici.</p>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>

                    <Modal isOpen={modalOpen} toggle={toggleModal}>
                        <ModalHeader toggle={toggleModal}>Saisir Présences : {selectedStage?.beneficiaire?.nom}</ModalHeader>
                        <form onSubmit={soumettrePointage}>
                            <ModalBody>
                                <div className="mb-3">
                                    <Label>Période (Mois)</Label>
                                    <Input type="select" value={data.periode_id} onChange={e => setData('periode_id', e.target.value)} required>
                                        <option value="">Sélectionnez...</option>
                                        {Object.entries(moisManques).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </div>
                                <div className="mb-3">
                                    <Label>Jours Présents</Label>
                                    <Input type="number" value={data.jours_presents} onChange={e => setData('jours_presents', Number(e.target.value))} min={0} max={31} required />
                                </div>
                                <div className="mb-3">
                                    <Label>Jours Absents</Label>
                                    <Input type="number" value={data.jours_absents} onChange={e => setData('jours_absents', Number(e.target.value))} min={0} max={31} required />
                                </div>
                                <div className="mb-3">
                                    <Label>Observation</Label>
                                    <Input type="textarea" value={data.observation} onChange={e => setData('observation', e.target.value)} />
                                </div>
                            </ModalBody>
                            <ModalFooter>
                                <Button color="light" onClick={toggleModal}>Annuler</Button>
                                <Button color="primary" type="submit" disabled={processing}>Soumettre Pointage</Button>
                            </ModalFooter>
                        </form>
                    </Modal>

                </Container>
            </div>
        </React.Fragment>
    );
};

export default CipPointageIndex;
