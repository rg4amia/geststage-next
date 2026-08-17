import { Head, router } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useState } from 'react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const CipSuiviIndex = ({
    differesAC = [],
    doublonsDESSE = [],
    renouvellements = [],
    suspensionsAbandons = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [selectedAction, setSelectedAction] = useState<any>(null);

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
setActiveTab(tab);
}
    };

    const handleAction = (type: string, item: any) => {
        setSelectedAction({ type, item });
        setModalOpen(true);
    };

    const confirmAction = () => {
        // Handle logic based on selectedAction.type
        setModalOpen(false);
    };

    const differesColumns = [
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
            header: 'Motif (AC)',
            accessorKey: 'motif_rejet',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Date',
            accessorKey: 'date_rejet',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="primary" size="sm" onClick={() => handleAction('differe', cell.row.original)}>
                    Corriger le dossier
                </Button>
            ),
        },
    ];

    const doublonsColumns = [
        {
            header: 'Bénéficiaire (Doublon)',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => (
                <div className="d-flex align-items-center">
                    <div className="flex-grow-1">
                        <h5 className="fs-14 mb-1 text-danger">
                            {cell.row.original.beneficiaire?.nom} {cell.row.original.beneficiaire?.prenoms}
                        </h5>
                    </div>
                </div>
            ),
        },
        {
            header: 'Motif (DESSE)',
            accessorKey: 'motif',
            cell: (cell: any) => cell.getValue() || 'Ajourné pour vérification de doublon.',
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="warning" size="sm" onClick={() => handleAction('doublon', cell.row.original)}>
                    Fournir justificatif
                </Button>
            ),
        },
    ];

    const renouvellementsColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => `${cell.row.original.beneficiaire?.nom} ${cell.row.original.beneficiaire?.prenoms}`,
        },
        {
            header: 'Date Fin Initiale',
            accessorKey: 'date_fin_initiale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => <Badge color="info">Attente Renouvellement</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="success" size="sm">Générer Avenant</Button>
            ),
        },
    ];

    const suspensionsColumns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => `${cell.row.original.beneficiaire?.nom} ${cell.row.original.beneficiaire?.prenoms}`,
        },
        {
            header: 'Type',
            accessorKey: 'type_evenement',
            cell: (cell: any) => {
                const color = cell.getValue() === 'Abandon' ? 'danger' : 'warning';

                return <Badge color={color}>{cell.getValue()}</Badge>;
            },
        },
        {
            header: 'Date d\'effet',
            accessorKey: 'date_effet',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="secondary" size="sm" outline>Consulter</Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace CIP - Suivi et Anomalies" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Suivi des Dossiers & Anomalies" pageTitle="Conseiller d'Insertion (CIP)" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Gestion des cas spécifiques</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Différés (AC) <Badge color="danger" className="ms-1">{differesAC.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Doublons (DESSE) <Badge color="warning" className="ms-1">{doublonsDESSE.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Renouvellements <Badge color="info" className="ms-1">{renouvellements.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '4' })} onClick={() => toggleTab('4')}>
                                        Suspensions / Abandons
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={differesColumns}
                                        data={differesAC || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="2">
                                    <TableContainerReactTable
                                        columns={doublonsColumns}
                                        data={doublonsDESSE || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <TableContainerReactTable
                                        columns={renouvellementsColumns}
                                        data={renouvellements || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="4">
                                    <TableContainerReactTable
                                        columns={suspensionsColumns}
                                        data={suspensionsAbandons || []}
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

            {/* Modal de Traitement */}
            <Modal isOpen={modalOpen} toggle={() => setModalOpen(!modalOpen)}>
                <ModalHeader toggle={() => setModalOpen(!modalOpen)}>Traitement de l'anomalie</ModalHeader>
                <ModalBody>
                    {selectedAction?.type === 'differe' && (
                        <div>
                            <p>Ce dossier a été retourné par l'Agent Comptable. Veuillez corriger les informations bancaires ou joindre les pièces manquantes.</p>
                            <Input type="file" className="form-control" />
                        </div>
                    )}
                    {selectedAction?.type === 'doublon' && (
                        <div>
                            <p>La DESSE suspecte un doublon sur ce bénéficiaire. Veuillez fournir une note explicative.</p>
                            <Input type="textarea" rows={4} placeholder="Explication du CIP..." />
                        </div>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalOpen(!modalOpen)}>Annuler</Button>
                    <Button color="primary" onClick={confirmAction}>Soumettre la correction</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CipSuiviIndex;
