import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const ValidationDemarrageIndex = ({ attenteValidationDemarrage, attenteValidationOmis }: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedInstance, setSelectedInstance] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const handleAjourner = (instance: any) => {
        setSelectedInstance(instance);
        setModalOpen(true);
    };

    const getColumns = (isOmis: boolean) => [
        {
            header: 'Bénéficiaire',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1">
                            {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Entreprise',
            accessorKey: 'stage.entreprise.raison_sociale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Date de Début',
            accessorKey: 'stage.date_debut',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Statut',
            accessorKey: 'corbeille_actuelle',
            cell: (cell: any) => (
                <span className="badge bg-warning-subtle text-warning">
                    {isOmis ? 'Attente Validation Omis' : 'Attente Validation'}
                </span>
            ),
        },
        {
            header: 'Actions',
            cell: (cell: any) => {
                return (
                    <div className="d-flex gap-2">
                        <Button color="success" size="sm" title="Valider">
                            <i className="ri-check-line align-bottom me-1"></i> Valider
                        </Button>
                        <Button color="danger" size="sm" onClick={() => handleAjourner(cell.row.original)} title="Ajourner">
                            <i className="ri-close-line align-bottom me-1"></i> Ajourner
                        </Button>
                    </div>
                );
            },
        },
    ];

    const demarrageColumns = useMemo(() => getColumns(false), []);
    const omisColumns = useMemo(() => getColumns(true), []);

    return (
        <React.Fragment>
            <Head title="Validation Démarrages" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validations des Démarrages" pageTitle="Chef d'Agence" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader className="align-items-center d-flex">
                                    <h4 className="card-title mb-0 flex-grow-1">Stagiaires en attente de validation</h4>
                                </CardHeader>
                                <CardBody>
                                    <Nav tabs className="nav-tabs-custom nav-success mb-3">
                                        <NavItem>
                                            <NavLink
                                                className={classnames({ active: activeTab === '1' })}
                                                onClick={() => { toggleTab('1'); }}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                Démarrage ({attenteValidationDemarrage?.length || 0})
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                className={classnames({ active: activeTab === '2' })}
                                                onClick={() => { toggleTab('2'); }}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                Démarrage Omis ({attenteValidationOmis?.length || 0})
                                            </NavLink>
                                        </NavItem>
                                    </Nav>
                                    
                                    <TabContent activeTab={activeTab} className="text-muted">
                                        <TabPane tabId="1">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={attenteValidationDemarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>
                                        <TabPane tabId="2">
                                            <TableContainerReactTable
                                                columns={omisColumns}
                                                data={attenteValidationOmis || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="align-middle table-nowrap mb-0"
                                                theadClass="table-light"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>
                                    </TabContent>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            {/* Modal Ajournement */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Ajourner le dossier</ModalHeader>
                <ModalBody>
                    <p>Veuillez spécifier le motif d'ajournement. Le dossier retournera à l'espace du CIP.</p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif</label>
                        <Input type="textarea" id="motif" rows={3} placeholder="Ex: Contrat illisible, dates incorrectes..." />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Fermer</Button>
                    <Button color="danger" onClick={() => setModalOpen(!modalOpen)}>Confirmer l'Ajournement</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default ValidationDemarrageIndex;
