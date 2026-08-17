import { Head, router } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useState } from 'react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DmgValidationIndex = ({
    attenteVerification = [],
    valides = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
setActiveTab(tab);
}
    };

    const handleAjourner = (stagiaire: any) => {
        setSelectedStagiaire(stagiaire);
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

    const validerStagiaire = (id: number) => {
        if (confirm('Marquer ce stagiaire comme vérifié et valide ?')) {
            router.post(`/dmg/validation/valider/${id}`);
        }
    };

    const verificationColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1">
                            {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{cell.row.original.beneficiaire?.matricule}</p>
                    </div>
                </div>
            ),
        },
        {
            header: 'Entreprise',
            accessorKey: 'entreprise.raison_sociale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Agence Régionale',
            accessorKey: 'agence.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="success" size="sm" onClick={() => validerStagiaire(cell.row.original.id)} disabled={processing}>
                        <i className="ri-check-line align-bottom me-1"></i> Valider
                    </Button>
                    <Button color="danger" size="sm" onClick={() => handleAjourner(cell.row.original)}>
                        <i className="ri-close-line align-bottom me-1"></i> Ajourner
                    </Button>
                </div>
            ),
        },
    ];

    const validesColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1">
                            {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                        </h5>
                    </div>
                </div>
            ),
        },
        {
            header: 'Date Validation',
            accessorKey: 'date_validation',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => <Badge color="success">Vérifié</Badge>,
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DMG - Validation" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Vérification des Stagiaires" pageTitle="DMG" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Validation des nouveaux stagiaires</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Attente de vérification <Badge color="primary" className="ms-1">{attenteVerification.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Stagiaires Validés <Badge color="success" className="ms-1">{valides.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={verificationColumns}
                                        data={attenteVerification || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="2">
                                    <TableContainerReactTable
                                        columns={validesColumns}
                                        data={valides || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            {/* Modal Ajournement */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Ajourner le dossier</ModalHeader>
                <ModalBody>
                    <p>En ajournant ce dossier, il sera renvoyé à l'Agence Régionale pour correction.</p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif de l'ajournement</label>
                        <Input type="textarea" id="motif" rows={3} placeholder="Ex: RIB illisible..." />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="danger" onClick={confirmAjournement} disabled={processing}>Confirmer l'ajournement</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DmgValidationIndex;
