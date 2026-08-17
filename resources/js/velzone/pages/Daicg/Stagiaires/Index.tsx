import { Head, router } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useState } from 'react';
import { Card, CardBody, CardHeader, Container, Nav, NavItem, NavLink, TabContent, TabPane, Badge } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const DaicgStagiairesIndex = ({
    validesCA = [],
    validesDESSE = [],
    sansContrat = []
}: any) => {
    const [activeTab, setActiveTab] = useState('1');

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
setActiveTab(tab);
}
    };

    const commonColumns = [
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
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => {
                let badgeClass = 'primary';

                if (cell.getValue() === 'Valide DESSE') {
badgeClass = 'success';
} else if (cell.getValue() === 'Sans Contrat') {
badgeClass = 'danger';
}

                return <Badge color={badgeClass}>{cell.getValue() || 'Consultation'}</Badge>;
            },
        },
    ];

    return (
        <React.Fragment>
            <Head title="Espace DAICG - Consultation Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Consultation des Dossiers" pageTitle="Direction DAICG" />

                    <Card>
                        <CardHeader className="d-flex align-items-center">
                            <h4 className="card-title mb-0 flex-grow-1">Vue globale des stagiaires</h4>
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '1' })} onClick={() => toggleTab('1')}>
                                        Validés par CA <Badge color="primary" className="ms-1">{validesCA.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '2' })} onClick={() => toggleTab('2')}>
                                        Validés par DESSE <Badge color="success" className="ms-1">{validesDESSE.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink style={{ cursor: 'pointer' }} className={classnames({ active: activeTab === '3' })} onClick={() => toggleTab('3')}>
                                        Sans Contrat <Badge color="danger" className="ms-1">{sansContrat.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="text-muted">
                                <TabPane tabId="1">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={validesCA || []}
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
                                        columns={commonColumns}
                                        data={validesDESSE || []}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </TabPane>

                                <TabPane tabId="3">
                                    <TableContainerReactTable
                                        columns={commonColumns}
                                        data={sansContrat || []}
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
                </Container>
            </div>
        </React.Fragment>
    );
};

export default DaicgStagiairesIndex;
