import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DmgRejetsIndex = ({
    ajournesCB = [],
    rejetesAC = [],
    differesAC = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) setActiveTab(tab);
    };

    const commonColumns = [
        {
            header: 'Numéro',
            accessorKey: 'numero',
            cell: (cell: any) => <span className="fw-medium text-danger">{cell.getValue()}</span>,
        },
        {
            header: 'Motif',
            accessorKey: 'motif_rejet',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Date Rejet',
            accessorKey: 'date_rejet',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <Button color="primary" size="sm">
                    Traiter le retour
                </Button>
            ),
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DMG - Retours et Ajournements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Retours et Ajournements" pageTitle="DMG" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Suivi des dossiers retournés (CB / AC)</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Ajournés par CB <Badge color="warning" className="ms-1">{ajournesCB.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Rejetés par AC <Badge color="danger" className="ms-1">{rejetesAC.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Différés par AC <Badge color="info" className="ms-1">{differesAC.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={ajournesCB || []}
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
                                        data={rejetesAC || []}
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
                                        data={differesAC || []}
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

export default DmgRejetsIndex;
