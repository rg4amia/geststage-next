import React, { useMemo, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Card, CardBody, Col, Container, Row, Button, Nav, NavItem, NavLink, TabContent, TabPane, Modal, ModalHeader, ModalBody, ModalFooter, Input, Form, Collapse, UncontrolledTooltip } from 'reactstrap';
import classnames from 'classnames';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const ValidationDemarrageIndex = ({ 
    demarrage, 
    demarrageOmis, 
    retourAjournement,
    agences,
    entreprises,
    typesfinancements,
    typestages,
    typestructures,
    periodes,
    filters 
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [isFilterOpen, setIsFilterOpen] = useState(false);
    
    // Selection state
    const [selectedRows, setSelectedRows] = useState<string[]>([]);

    // Column picker state
    const ALL_COLUMNS = [
        { key: 'created_at', label: 'Date', default: true },
        { key: 'agence', label: 'Agence', default: true },
        { key: 'entreprise', label: 'Entreprise', default: true },
        { key: 'source_financement', label: 'Source de Financement', default: false },
        { key: 'type_stage', label: 'Type de Stage', default: false },
        { key: 'type_structure', label: 'Type de Structure', default: false },
        { key: 'matricule', label: 'Numero AEJ', default: true },
        { key: 'nom', label: 'Nom et Prénoms', default: true },
        { key: 'date_naissance', label: 'Date de naissance', default: false },
        { key: 'sexe', label: 'Sexe', default: true },
        { key: 'contrat', label: 'Contrat', default: true },
        { key: 'incidence', label: 'Incidence Financière', default: true },
        { key: 'date_debut', label: 'Date de Debut', default: true },
        { key: 'date_fin', label: 'Date de Fin', default: true },
        { key: 'actions', label: 'Action', default: true },
    ];
    const [visibleColumns, setVisibleColumns] = useState(() => ALL_COLUMNS.filter(c => c.default).map(c => c.key));
    const [showColumnPicker, setShowColumnPicker] = useState(false);

    const toggleColumn = (key: string) => {
        setVisibleColumns(prev => prev.includes(key) ? prev.filter(k => k !== key) : [...prev, key]);
    };
    
    const { data: formData, setData, get, processing: filterProcessing } = useForm({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        created_begin: filters?.created_begin || '',
        created_end: filters?.created_end || '',
        periode_id: filters?.periode_id || '',
    });

    const { data: actionData, setData: setActionData, post, processing: actionProcessing, reset: resetAction } = useForm({
        ids: [] as string[],
        type: '',
        motif: '',
        etat_validation_id: '',
        commentaire: ''
    });

    const toggleTab = (tab: string) => {
        if (activeTab !== tab) {
            setActiveTab(tab);
            setSelectedRows([]); // reset selection when changing tabs
        }
    };

    const handleFilter = (e: any) => {
        e.preventDefault();
        get(route('chefagence.validations'));
    };

    const handleReset = () => {
        router.visit(route('chefagence.validations'));
    };

    const handleRowSelect = (id: string) => {
        if (selectedRows.includes(id)) {
            setSelectedRows(selectedRows.filter(rowId => rowId !== id));
        } else {
            setSelectedRows([...selectedRows, id]);
        }
    };

    const handleSelectAll = (dataset: any[]) => {
        if (selectedRows.length === dataset.length && dataset.length > 0) {
            setSelectedRows([]);
        } else {
            setSelectedRows(dataset.map(item => item.id.toString()));
        }
    };

    const openAjournerModal = () => {
        if (selectedRows.length === 0) {
            alert("Veuillez sélectionner au moins un dossier.");
            return;
        }
        setActionData('ids', selectedRows);
        setModalAjournerOpen(true);
    };

    const openValiderModal = () => {
        if (selectedRows.length === 0) {
            alert("Veuillez sélectionner au moins un dossier.");
            return;
        }
        setActionData('ids', selectedRows);
        
        let currentType = 'demarrage';
        if(activeTab === '2') currentType = 'demarrageOmis';
        if(activeTab === '3') currentType = 'retourAjournement';
        setActionData('type', currentType);
        
        setModalValiderOpen(true);
    };

    const confirmAjourner = () => {
        post(route('chefagence.validations.ajournerGroup'), {
            onSuccess: () => {
                setModalAjournerOpen(false);
                setSelectedRows([]);
                resetAction();
            }
        });
    };

    const confirmValider = () => {
        post(route('chefagence.validations.validerGroup'), {
            onSuccess: () => {
                setModalValiderOpen(false);
                setSelectedRows([]);
                resetAction();
            }
        });
    };

    const handleValidationListeEntiere = () => {
        let currentDataset = [];
        if (activeTab === '1') currentDataset = demarrage || [];
        if (activeTab === '2') currentDataset = demarrageOmis || [];
        if (activeTab === '3') currentDataset = retourAjournement || [];

        if (currentDataset.length === 0) {
            alert("La liste est vide.");
            return;
        }

        const allIds = currentDataset.map((item: any) => item.id.toString());
        setActionData('ids', allIds);
        
        let currentType = 'demarrage';
        if(activeTab === '2') currentType = 'demarrageOmis';
        if(activeTab === '3') currentType = 'retourAjournement';
        setActionData('type', currentType);
        
        setModalValiderOpen(true);
    };

    const handleGenererAddGlobal = () => {
        let currentDataset = [];
        if (activeTab === '1') currentDataset = demarrage || [];
        if (activeTab === '2') currentDataset = demarrageOmis || [];
        if (activeTab === '3') currentDataset = retourAjournement || [];

        if (currentDataset.length === 0) {
            alert("La liste est vide.");
            return;
        }

        const allIds = currentDataset.map((item: any) => item.id.toString());
        
        post(route('chefagence.validations.genererAddGroup'), {
            data: { ids: allIds },
            onSuccess: () => {
                alert("Génération ADD globale lancée.");
            }
        });
    };

    const handleGenererAddSelection = () => {
        if (selectedRows.length === 0) {
            alert("Veuillez sélectionner au moins un dossier.");
            return;
        }

        post(route('chefagence.validations.genererAddGroup'), {
            data: { ids: selectedRows },
            onSuccess: () => {
                alert("Génération ADD lancée pour la sélection.");
                setSelectedRows([]);
            }
        });
    };

    const getColumns = (dataset: any[]) => [
        {
            header: (
                <div className="form-check">
                    <input 
                        className="form-check-input" 
                        type="checkbox" 
                        onChange={() => handleSelectAll(dataset)}
                        checked={selectedRows.length === dataset.length && dataset.length > 0}
                    />
                </div>
            ),
            accessorKey: 'id',
            enableSorting: false,
            cell: (cell: any) => (
                <div className="form-check">
                    <input 
                        className="form-check-input" 
                        type="checkbox" 
                        value={cell.row.original.id}
                        checked={selectedRows.includes(cell.row.original.id.toString())}
                        onChange={() => handleRowSelect(cell.row.original.id.toString())}
                    />
                </div>
            )
        },
        {
            header: 'Date',
            accessorKey: 'created_at',
            cell: (cell: any) => new Date(cell.getValue()).toLocaleDateString('fr-FR'),
        },
        {
            header: 'Agence',
            accessorKey: 'stage.agence.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Entreprise',
            accessorKey: 'stage.entreprise.raison_sociale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Source de Financement',
            accessorKey: 'stage.contrats[0].source_financement_id',
            cell: (cell: any) => cell.row.original.stage?.contrats?.[0]?.source_financement?.nom || '-',
        },
        {
            header: 'Type de Stage',
            accessorKey: 'stage.type_stage.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Type de Structure',
            accessorKey: 'stage.entreprise.type_structure.nom',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Numero AEJ',
            accessorKey: 'stage.beneficiaire.matricule',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Nom et Prénoms',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => `${cell.row.original.stage?.beneficiaire?.nom} ${cell.row.original.stage?.beneficiaire?.prenoms}`,
        },
        {
            header: 'Date de naissance',
            accessorKey: 'stage.beneficiaire.date_naissance',
            cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
        },
        {
            header: 'Sexe',
            accessorKey: 'stage.beneficiaire.sexe',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Contrat',
            accessorKey: 'stage.contrats[0].reference',
            cell: (cell: any) => cell.row.original.stage?.contrats?.[0]?.reference || cell.row.original.stage?.contrats?.[0]?.numero || '-',
        },
        {
            header: 'Incidence Financière',
            accessorKey: 'stage.contrats[0].montant',
            cell: (cell: any) => {
                const montant = cell.row.original.stage?.contrats?.[0]?.montant || cell.row.original.stage?.incidence_financiere;
                return montant ? `${montant} FCFA` : '-';
            },
        },
        {
            header: 'Date de Debut',
            accessorKey: 'stage.date_debut',
            cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
        },
        {
            header: 'Date de Fin',
            accessorKey: 'stage.date_fin',
            cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
        },
        {
            header: 'Action',
            cell: (cell: any) => {
                return (
                    <div className="d-flex gap-2">
                        <Button color="success" size="sm" onClick={() => {
                            setSelectedRows([cell.row.original.id.toString()]);
                            openValiderModal();
                        }} title="Valider">
                            <i className="ri-check-line align-bottom"></i>
                        </Button>
                        <Button color="danger" size="sm" onClick={() => {
                            setSelectedRows([cell.row.original.id.toString()]);
                            openAjournerModal();
                        }} title="Ajourner">
                            <i className="ri-close-line align-bottom"></i>
                        </Button>
                    </div>
                );
            },
        },
    ].filter(col => {
        if (!col.accessorKey && col.header !== 'Actions') return true; // Checkbox column
        if (col.header === 'Actions') return visibleColumns.includes('actions');
        if (col.accessorKey === 'created_at') return visibleColumns.includes('created_at');
        if (col.accessorKey === 'stage.agence.nom') return visibleColumns.includes('agence');
        if (col.accessorKey === 'stage.entreprise.raison_sociale') return visibleColumns.includes('entreprise');
        if (col.accessorKey === 'stage.contrats[0].source_financement_id') return visibleColumns.includes('source_financement');
        if (col.accessorKey === 'stage.type_stage.nom') return visibleColumns.includes('type_stage');
        if (col.accessorKey === 'stage.entreprise.type_structure.nom') return visibleColumns.includes('type_structure');
        if (col.accessorKey === 'stage.beneficiaire.matricule') return visibleColumns.includes('matricule');
        if (col.accessorKey === 'stage.beneficiaire.nom') return visibleColumns.includes('nom');
        if (col.accessorKey === 'stage.beneficiaire.date_naissance') return visibleColumns.includes('date_naissance');
        if (col.accessorKey === 'stage.beneficiaire.sexe') return visibleColumns.includes('sexe');
        if (col.accessorKey === 'stage.date_debut') return visibleColumns.includes('date_debut');
        if (col.accessorKey === 'stage.date_fin') return visibleColumns.includes('date_fin');
        return true;
    });

    const demarrageColumns = useMemo(() => getColumns(demarrage || []), [demarrage, selectedRows, visibleColumns]);
    const omisColumns = useMemo(() => getColumns(demarrageOmis || []), [demarrageOmis, selectedRows, visibleColumns]);
    const retourColumns = useMemo(() => getColumns(retourAjournement || []), [retourAjournement, selectedRows, visibleColumns]);

    return (
        <React.Fragment>
            <Head title="Validation Démarrages" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Validations des Démarrages" pageTitle="Chef d'Agence" />
                         <Row>
                        <Col lg={12}>
                            <Card id="validationList">
                                <div className="card-header border-0 bg-success">
                                    <div className="d-flex align-items-center">
                                        <h5 className="card-title mb-0 flex-grow-1 text-white">
                                            {activeTab === '1' && `Liste des Démarrages`}
                                            {activeTab === '2' && `Liste des Démarrages Omis`}
                                            {activeTab === '3' && `Liste des Retours d'ajournement`}
                                            <span className="badge ms-2 fs-12 bg-white text-success">
                                                {activeTab === '1' ? demarrage?.length || 0 : (activeTab === '2' ? demarrageOmis?.length || 0 : retourAjournement?.length || 0)}
                                            </span>
                                            <i className="ri-information-fill text-white ms-2" id="infoTooltip" style={{ cursor: 'pointer' }}></i>
                                            <UncontrolledTooltip placement="right" target="infoTooltip">
                                                Les actions s'appliquent à l'onglet actif. Pour Présence, sélectionnez la période d'attestation de démarrage.
                                            </UncontrolledTooltip>
                                        </h5>
                                        <div className="flex-shrink-0 d-flex gap-2">
                                            <Button color="light" size="sm" onClick={() => setIsFilterOpen(!isFilterOpen)}>
                                                <i className="ri-filter-2-line align-bottom me-1"></i> Filtres
                                            </Button>
                                            <div className="position-relative">
                                                <Button color="light" size="sm" onClick={() => setShowColumnPicker(!showColumnPicker)}>
                                                    <i className="ri-settings-3-line me-1"></i> Colonnes
                                                </Button>
                                                {showColumnPicker && (
                                                    <div
                                                        className="position-absolute end-0 mt-1 bg-white border rounded shadow p-3"
                                                        style={{ zIndex: 1050, minWidth: '210px' }}
                                                    >
                                                        <p style={{ color: '#6c757d', fontSize: '11px', textTransform: 'uppercase', fontWeight: 600, marginBottom: '8px' }}>
                                                            Colonnes visibles
                                                        </p>
                                                        {ALL_COLUMNS.map(col => (
                                                            <div key={col.key} className="form-check mb-1">
                                                                <input
                                                                    type="checkbox"
                                                                    className="form-check-input"
                                                                    id={`col-${col.key}`}
                                                                    checked={visibleColumns.includes(col.key)}
                                                                    onChange={() => toggleColumn(col.key)}
                                                                />
                                                                <label
                                                                    className="form-check-label text-dark fs-13"
                                                                    htmlFor={`col-${col.key}`}
                                                                    style={{ cursor: 'pointer' }}
                                                                >
                                                                    {col.label}
                                                                </label>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div className="card-header p-0 border-0 bg-success-subtle">
                                    <Nav className="nav-tabs-custom rounded card-header-tabs border-bottom-0 mx-0" role="tablist">
                                        <NavItem>
                                            <NavLink
                                                className={classnames({ active: activeTab === '1' }, 'fw-semibold')}
                                                onClick={() => { toggleTab('1'); }}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                DÉMARRAGE ({demarrage?.length || 0})
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                className={classnames({ active: activeTab === '2' }, 'fw-semibold')}
                                                onClick={() => { toggleTab('2'); }}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                DÉMARRAGE OMIS ({demarrageOmis?.length || 0})
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                className={classnames({ active: activeTab === '3' }, 'fw-semibold')}
                                                onClick={() => { toggleTab('3'); }}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                RETOUR D'AJOURNEMENT ({retourAjournement?.length || 0})
                                            </NavLink>
                                        </NavItem>
                                    </Nav>
                                </div>

                                {/* Collapse for Filters */}
                                <Collapse isOpen={isFilterOpen}>
                                    <CardBody className="bg-light border-bottom border-top">
                                        <Form onSubmit={handleFilter}>
                                            <Row className="g-3 align-items-end">
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">AGENCE</label>
                                                    <Input type="select" className="form-select-sm" value={formData.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                                        <option value="">Tout</option>
                                                        {Object.entries(agences || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">ENTREPRISE</label>
                                                    <Input type="select" className="form-select-sm" value={formData.entreprise_id} onChange={e => setData('entreprise_id', e.target.value)}>
                                                        <option value="">Tout</option>
                                                        {Object.entries(entreprises || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">FINANCEMENT</label>
                                                    <Input type="select" className="form-select-sm" value={formData.typesfinancement_id} onChange={e => setData('typesfinancement_id', e.target.value)}>
                                                        <option value="">Tout</option>
                                                        {Object.entries(typesfinancements || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">TYPE STAGE</label>
                                                    <Input type="select" className="form-select-sm" value={formData.typestage_id} onChange={e => setData('typestage_id', e.target.value)}>
                                                        <option value="">Tout</option>
                                                        {Object.entries(typestages || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">TYPE STRUCTURE</label>
                                                    <Input type="select" className="form-select-sm" value={formData.type_structure_id} onChange={e => setData('type_structure_id', e.target.value)}>
                                                        <option value="">Tout</option>
                                                        {Object.entries(typestructures || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">CRÉATION DU</label>
                                                    <Input type="date" className="form-control-sm" value={formData.created_begin} onChange={e => setData('created_begin', e.target.value)} />
                                                </Col>
                                                <Col xs={6} sm={4} md={2}>
                                                    <label className="form-label fs-12 text-muted mb-1">CRÉATION AU</label>
                                                    <Input type="date" className="form-control-sm" value={formData.created_end} onChange={e => setData('created_end', e.target.value)} />
                                                </Col>
                                                <Col xs={12} sm={12} md={2} className="d-flex gap-2 align-items-end">
                                                    <Button type="submit" color="success" size="sm" className="flex-fill" disabled={filterProcessing}>
                                                        <i className="ri-search-line me-1"></i>Rechercher
                                                    </Button>
                                                    <Button type="button" color="light" size="sm" onClick={handleReset}>
                                                        <i className="ri-refresh-line"></i>
                                                    </Button>
                                                </Col>
                                            </Row>
                                        </Form>
                                    </CardBody>
                                </Collapse>

                                {selectedRows.length > 0 && (
                                    <div className="bg-success-subtle p-3 border-bottom d-flex align-items-center justify-content-between">
                                        <div className="text-success fw-semibold">
                                            {selectedRows.length} dossier(s) sélectionné(s)
                                        </div>
                                        <div className="d-flex flex-wrap gap-2">
                                            <Button color="success" size="sm" className="btn-label" onClick={openValiderModal}>
                                                <i className="ri-check-double-line label-icon align-middle fs-16 me-2"></i> Valider Sélection
                                            </Button>
                                            <Button color="primary" size="sm" className="btn-label" onClick={handleGenererAddSelection}>
                                                <i className="ri-file-list-3-line label-icon align-middle fs-16 me-2"></i> Générer ADD
                                            </Button>
                                            <Button color="danger" size="sm" className="btn-label" onClick={openAjournerModal}>
                                                <i className="ri-close-circle-line label-icon align-middle fs-16 me-2"></i> Ajourner
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {selectedRows.length === 0 && (
                                    <div className="bg-light p-2 border-bottom d-flex justify-content-end gap-2">
                                        <Button color="info" size="sm" className="btn-label" onClick={handleValidationListeEntiere}>
                                            <i className="ri-folder-line label-icon align-middle fs-16 me-2"></i> Valider la liste complète
                                        </Button>
                                        <Button color="secondary" size="sm" className="btn-label" onClick={handleGenererAddGlobal}>
                                            <i className="ri-file-text-line label-icon align-middle fs-16 me-2"></i> Générer ADD Global
                                        </Button>
                                    </div>
                                )}

                                <CardBody className="p-0">
                                    <TabContent activeTab={activeTab} className="text-muted">
                                        <TabPane tabId="1">
                                            <TableContainerReactTable
                                                columns={demarrageColumns}
                                                data={demarrage || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>
                                        <TabPane tabId="2">
                                            {/* Période filter for Omis */}
                                            <div className="p-3 bg-light border-bottom">
                                                <Row>
                                                    <Col md={4}>
                                                        <label className="form-label font-medium mb-2 text-danger">PÉRIODE *</label>
                                                        <Input type="select" className="form-select-sm" value={formData.periode_id} onChange={e => setData('periode_id', e.target.value)}>
                                                            <option value="">Sélectionner une période</option>
                                                            {Object.entries(periodes || {}).map(([id, label]) => (
                                                                <option key={id} value={id}>{String(label)}</option>
                                                            ))}
                                                        </Input>
                                                    </Col>
                                                </Row>
                                            </div>
                                            <TableContainerReactTable
                                                columns={omisColumns}
                                                data={demarrageOmis || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
                                                SearchPlaceholder="Rechercher..."
                                            />
                                        </TabPane>
                                        <TabPane tabId="3">
                                            <TableContainerReactTable
                                                columns={retourColumns}
                                                data={retourAjournement || []}
                                                isGlobalFilter={true}
                                                customPageSize={10}
                                                divClass="table-responsive table-card mb-3"
                                                tableClass="table align-middle table-nowrap mb-0"
                                                theadClass="bg-success text-white text-uppercase fw-semibold fs-11"
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
            <Modal isOpen={modalAjournerOpen} toggle={() => setModalAjournerOpen(!modalAjournerOpen)}>
                <ModalHeader toggle={() => setModalAjournerOpen(!modalAjournerOpen)} className="bg-danger text-white">
                    Ajourner la sélection
                </ModalHeader>
                <ModalBody>
                    <p>Vous êtes sur le point d'ajourner <strong>{selectedRows.length}</strong> dossier(s). Veuillez spécifier le motif d'ajournement. Les dossiers retourneront à l'espace du CIP.</p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">Motif d'ajournement</label>
                        <Input 
                            type="textarea" 
                            id="motif" 
                            rows={3} 
                            placeholder="Ex: Contrat illisible, dates incorrectes..." 
                            value={actionData.motif}
                            onChange={e => setActionData('motif', e.target.value)}
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalAjournerOpen(false)}>Fermer</Button>
                    <Button color="danger" onClick={confirmAjourner} disabled={actionProcessing || !actionData.motif}>
                        Confirmer l'Ajournement
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Modal Validation */}
            <Modal isOpen={modalValiderOpen} toggle={() => setModalValiderOpen(!modalValiderOpen)}>
                <ModalHeader toggle={() => setModalValiderOpen(!modalValiderOpen)} className="bg-primary text-white">
                    Validation de stagiaires
                </ModalHeader>
                <ModalBody>
                    <p>Validation de <strong>{selectedRows.length}</strong> dossier(s) sélectionné(s).</p>
                    <div className="form-group mb-3">
                        <label htmlFor="statut">Statut</label>
                        <Input type="select" className="form-control" value={actionData.etat_validation_id} onChange={e => setActionData('etat_validation_id', e.target.value)}>
                            <option value="">Sélectionner</option>
                            <option value="1">Validé</option>
                        </Input>
                    </div>
                    <div className="form-group mb-3">
                        <label htmlFor="fichier-justif">Joindre Attestation signée (Optionnel)</label>
                        <Input type="file" className="form-control" />
                    </div>
                    <div className="form-group mb-3">
                        <label htmlFor="commentaire">Commentaire</label>
                        <Input type="textarea" className="form-control" rows={3} value={actionData.commentaire} onChange={e => setActionData('commentaire', e.target.value)} />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalValiderOpen(false)}>Fermer</Button>
                    <Button color="primary" onClick={confirmValider} disabled={actionProcessing}>
                        Enregistrer
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default ValidationDemarrageIndex;
