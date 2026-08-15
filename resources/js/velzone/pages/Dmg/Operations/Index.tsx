import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DmgOperationsIndex = ({
    elaborationOp = [],
    bordereaux = [],
    fichierCut = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const commonColumns = [
        {
            header: 'Numéro',
            accessorKey: 'numero',
            cell: (cell: any) => <span className="fw-medium text-primary">{cell.getValue()}</span>,
        },
        {
            header: 'Date',
            accessorKey: 'date_creation',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Montant (FCFA)',
            accessorKey: 'montant',
            cell: (cell: any) => <span className="fw-bold text-success">{cell.getValue() || '0'}</span>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="secondary" size="sm" outline>
                    Détails / Actions
                </Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DMG - Opérations Financières" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Opérations Financières" pageTitle="DMG" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Gestion des OPs, Bordereaux et CUT</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Élaboration OP <Badge color="primary" className="ms-1">{elaborationOp.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Bordereaux en attente <Badge color="primary" className="ms-1">{bordereaux.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Fichier CUT <Badge color="success" className="ms-1">{fichierCut.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={elaborationOp || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="2">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={bordereaux || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={fichierCut || []}
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
        </React.Fragment>
    );
};

export default DmgOperationsIndex;
