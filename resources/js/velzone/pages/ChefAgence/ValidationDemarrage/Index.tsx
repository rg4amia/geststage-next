import { Head, router, useForm } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    Form,
    Input,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Nav,
    NavItem,
    NavLink,
    Row,
    TabContent,
    TabPane,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

type RowData = {
    id: number;
    date: string;
    agence: string;
    entreprise: string;
    source_financement: string;
    type_stage: string;
    type_structure: string;
    numero_aej: string;
    nom_prenoms: string;
    date_naissance: string;
    sexe: string;
    contrat_label: string;
    incidence_financiere: string;
    date_debut: string;
    date_fin: string;
};

const emptySelection = {
    ids: [] as string[],
    motif: '',
    type: 'demarrage',
};

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
    filters,
}: any) => {
    const [activeTab, setActiveTab] = useState(filters?.tab || '1');
    const [selectedRows, setSelectedRows] = useState<string[]>([]);
    const [modalAjournerOpen, setModalAjournerOpen] = useState(false);
    const [modalValiderOpen, setModalValiderOpen] = useState(false);
    const [previewRow, setPreviewRow] = useState<RowData | null>(null);

    const {
        data: formData,
        setData: setFormData,
        get,
        reset: resetFilters,
    } = useForm({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        created_begin: filters?.created_begin || '',
        created_end: filters?.created_end || '',
        periode_id: filters?.periode_id || '',
        tab: filters?.tab || '1',
    });

    const {
        data: actionData,
        setData: setActionData,
        post,
        processing: actionProcessing,
        reset: resetAction,
    } = useForm(emptySelection);

    const currentRows: RowData[] = useMemo(() => {
        if (activeTab === '2') {
            return demarrageOmis || [];
        }

        if (activeTab === '3') {
            return retourAjournement || [];
        }

        return demarrage || [];
    }, [activeTab, demarrage, demarrageOmis, retourAjournement]);

    useEffect(() => {
        setFormData('tab', activeTab);
    }, [activeTab, setFormData]);

    const currentTabLabel =
        activeTab === '2'
            ? 'Liste des démarrages omis'
            : activeTab === '3'
              ? "Liste des retours d'ajournement"
              : 'Liste des démarrages';

    const toggleTab = (tab: string) => {
        if (tab !== activeTab) {
            setActiveTab(tab);
            setSelectedRows([]);
            setModalAjournerOpen(false);
            setModalValiderOpen(false);
        }
    };

    const handleFilter = (event: React.FormEvent) => {
        event.preventDefault();
        get(route('chefagence.validations'), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleReset = () => {
        resetFilters();
        setSelectedRows([]);
        setActiveTab('1');
        router.visit(route('chefagence.validations'));
    };

    const handleRowSelect = useCallback((id: string) => {
        setSelectedRows((current) =>
            current.includes(id)
                ? current.filter((rowId) => rowId !== id)
                : [...current, id],
        );
    }, []);

    const handleSelectAll = useCallback(() => {
        const allIds = currentRows.map((row) => row.id.toString());
        setSelectedRows((current) =>
            current.length === allIds.length ? [] : allIds,
        );
    }, [currentRows]);

    const openValiderModal = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');

            return;
        }

        setActionData('ids', selectedRows);
        setActionData(
            'type',
            activeTab === '2'
                ? 'demarrageOmis'
                : activeTab === '3'
                  ? 'retourAjournement'
                  : 'demarrage',
        );
        setModalValiderOpen(true);
    };

    const openAjournerModal = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');

            return;
        }

        setActionData('ids', selectedRows);
        setModalAjournerOpen(true);
    };

    const confirmValider = () => {
        post(route('chefagence.validations.validerGroup'), {
            preserveScroll: true,
            onSuccess: () => {
                setModalValiderOpen(false);
                setSelectedRows([]);
                resetAction();
            },
        });
    };

    const confirmAjourner = () => {
        post(route('chefagence.validations.ajournerGroup'), {
            preserveScroll: true,
            onSuccess: () => {
                setModalAjournerOpen(false);
                setSelectedRows([]);
                resetAction();
            },
        });
    };

    const handleValidationListeEntiere = () => {
        if (activeTab === '2' && !formData.periode_id) {
            alert('Veuillez sélectionner une période pour le démarrage omis.');

            return;
        }

        const allIds = currentRows.map((row) => row.id.toString());

        if (allIds.length === 0) {
            alert('Aucun dossier à traiter dans cet onglet.');

            return;
        }

        setActionData('ids', allIds);
        setActionData(
            'type',
            activeTab === '2'
                ? 'demarrageOmis'
                : activeTab === '3'
                  ? 'retourAjournement'
                  : 'demarrage',
        );
        post(route('chefagence.validations.validerGroup'), {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                resetAction();
            },
        });
    };

    const handleGenererAddSelection = () => {
        if (selectedRows.length === 0) {
            alert('Veuillez sélectionner au moins un dossier.');

            return;
        }

        setActionData('ids', selectedRows);
        setActionData(
            'type',
            activeTab === '2'
                ? 'demarrageOmis'
                : activeTab === '3'
                  ? 'retourAjournement'
                  : 'demarrage',
        );
        post(route('chefagence.validations.genererAddGroup'), {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                resetAction();
            },
        });
    };

    const handleGenererAddGlobal = () => {
        if (activeTab === '2' && !formData.periode_id) {
            alert('Veuillez sélectionner une période pour le démarrage omis.');

            return;
        }

        const allIds = currentRows.map((row) => row.id.toString());

        if (allIds.length === 0) {
            alert('Aucun dossier à traiter dans cet onglet.');

            return;
        }

        setActionData('ids', allIds);
        setActionData(
            'type',
            activeTab === '2'
                ? 'demarrageOmis'
                : activeTab === '3'
                  ? 'retourAjournement'
                  : 'demarrage',
        );
        post(route('chefagence.validations.genererAddGroup'), {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedRows([]);
                resetAction();
            },
        });
    };

    const columns = useMemo(
        () => [
            {
                header: (
                    <div className="form-check">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            checked={
                                currentRows.length > 0 &&
                                selectedRows.length === currentRows.length
                            }
                            onChange={handleSelectAll}
                        />
                    </div>
                ),
                accessorKey: 'select',
                enableSorting: false,
                cell: (cell: any) => (
                    <div className="form-check">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            checked={selectedRows.includes(
                                cell.row.original.id.toString(),
                            )}
                            onChange={() =>
                                handleRowSelect(cell.row.original.id.toString())
                            }
                        />
                    </div>
                ),
            },
            { header: 'Date', accessorKey: 'date' },
            { header: 'Agence', accessorKey: 'agence' },
            { header: 'Entreprise', accessorKey: 'entreprise' },
            {
                header: 'Source de financement',
                accessorKey: 'source_financement',
            },
            { header: 'Type de stage', accessorKey: 'type_stage' },
            {
                header: 'Type de structure',
                accessorKey: 'type_structure',
                cell: (cell: any) => {
                    const value = cell.getValue();

                    if (!value || value === '-') {
                        return '-';
                    }

                    return <Badge color="success">{value}</Badge>;
                },
            },
            { header: 'Numéro AEJ', accessorKey: 'numero_aej' },
            { header: 'Nom et prénoms', accessorKey: 'nom_prenoms' },
            { header: 'Date de naissance', accessorKey: 'date_naissance' },
            { header: 'Sexe', accessorKey: 'sexe' },
            {
                header: 'Contrat',
                accessorKey: 'contrat_label',
                cell: (cell: any) => {
                    const value = cell.getValue();

                    return value === 'Avec Contrat' ? (
                        <Badge color="success">{value}</Badge>
                    ) : (
                        <Badge color="warning">{value}</Badge>
                    );
                },
            },
            {
                header: 'Incidence financière',
                accessorKey: 'incidence_financiere',
                cell: (cell: any) => {
                    const value = cell.getValue();

                    return value === 'Oui' ? (
                        <Badge color="success">{value}</Badge>
                    ) : (
                        <Badge color="danger">{value}</Badge>
                    );
                },
            },
            { header: 'Date début', accessorKey: 'date_debut' },
            { header: 'Date de fin', accessorKey: 'date_fin' },
            {
                header: 'Action',
                accessorKey: 'actions',
                enableSorting: false,
                cell: (cell: any) => {
                    const row = cell.row.original as RowData;

                    return (
                        <div className="d-flex gap-1">
                            <Button
                                color="info"
                                size="sm"
                                className="btn-icon"
                                onClick={() => setPreviewRow(row)}
                            >
                                <i className="ri-eye-line"></i>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [currentRows, selectedRows, handleRowSelect, handleSelectAll],
    );

    return (
        <React.Fragment>
            <Head title="Validation Démarrage" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb
                        title="Validations des Démarrages"
                        pageTitle="Chef d'Agence"
                    />

                    <Row>
                        <Col lg={12}>
                            <Card className="border-0 shadow-sm">
                                <CardBody className="pb-2">
                                    <Form onSubmit={handleFilter}>
                                        <Row className="g-3 align-items-end">
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    AGENCE
                                                </label>
                                                <Input
                                                    type="select"
                                                    className="form-select-sm"
                                                    value={formData.agence_id}
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'agence_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Tout
                                                    </option>
                                                    {Object.entries(
                                                        agences || {},
                                                    ).map(([id, label]) => (
                                                        <option
                                                            key={id}
                                                            value={id}
                                                        >
                                                            {String(label)}
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    ENTREPRISE
                                                </label>
                                                <Input
                                                    type="select"
                                                    className="form-select-sm"
                                                    value={
                                                        formData.entreprise_id
                                                    }
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'entreprise_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Tout
                                                    </option>
                                                    {Object.entries(
                                                        entreprises || {},
                                                    ).map(([id, label]) => (
                                                        <option
                                                            key={id}
                                                            value={id}
                                                        >
                                                            {String(label)}
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    FINANCEMENT
                                                </label>
                                                <Input
                                                    type="select"
                                                    className="form-select-sm"
                                                    value={
                                                        formData.typesfinancement_id
                                                    }
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'typesfinancement_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Tout
                                                    </option>
                                                    {Object.entries(
                                                        typesfinancements || {},
                                                    ).map(([id, label]) => (
                                                        <option
                                                            key={id}
                                                            value={id}
                                                        >
                                                            {String(label)}
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    TYPE STAGE
                                                </label>
                                                <Input
                                                    type="select"
                                                    className="form-select-sm"
                                                    value={
                                                        formData.typestage_id
                                                    }
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'typestage_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Tout
                                                    </option>
                                                    {Object.entries(
                                                        typestages || {},
                                                    ).map(([id, label]) => (
                                                        <option
                                                            key={id}
                                                            value={id}
                                                        >
                                                            {String(label)}
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    TYPE STRUCTURE
                                                </label>
                                                <Input
                                                    type="select"
                                                    className="form-select-sm"
                                                    value={
                                                        formData.type_structure_id
                                                    }
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'type_structure_id',
                                                            e.target.value,
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        Tout
                                                    </option>
                                                    {Object.entries(
                                                        typestructures || {},
                                                    ).map(([id, label]) => (
                                                        <option
                                                            key={id}
                                                            value={id}
                                                        >
                                                            {String(label)}
                                                        </option>
                                                    ))}
                                                </Input>
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    DATE CREATION DEBUT
                                                </label>
                                                <Input
                                                    type="date"
                                                    className="form-control-sm"
                                                    value={
                                                        formData.created_begin
                                                    }
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'created_begin',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Col>
                                            <Col xs={6} sm={4} md={2}>
                                                <label className="form-label fs-12 mb-1 text-muted">
                                                    DATE CREATION FIN
                                                </label>
                                                <Input
                                                    type="date"
                                                    className="form-control-sm"
                                                    value={formData.created_end}
                                                    onChange={(e) =>
                                                        setFormData(
                                                            'created_end',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </Col>
                                            <Col
                                                xs={12}
                                                sm={12}
                                                md={2}
                                                className="d-flex align-items-end gap-2"
                                            >
                                                <Button
                                                    type="submit"
                                                    color="success"
                                                    size="sm"
                                                    className="flex-fill"
                                                >
                                                    <i className="ri-search-line me-1"></i>
                                                    Rechercher
                                                </Button>
                                                <Button
                                                    type="button"
                                                    color="light"
                                                    size="sm"
                                                    onClick={handleReset}
                                                >
                                                    <i className="ri-refresh-line"></i>
                                                </Button>
                                            </Col>
                                        </Row>
                                    </Form>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <div className="d-flex my-3 flex-wrap gap-2">
                        <Button
                            color="primary"
                            className="btn-label"
                            onClick={openValiderModal}
                        >
                            <i className="ri-check-double-line label-icon fs-16 me-2 align-middle"></i>
                            Sélectionner et validation
                        </Button>
                        <Button
                            color="info"
                            className="btn-label"
                            onClick={handleValidationListeEntiere}
                        >
                            <i className="ri-folder-line label-icon fs-16 me-2 align-middle"></i>
                            Validation de la liste
                        </Button>
                        <Button
                            color="secondary"
                            className="btn-label"
                            onClick={handleGenererAddGlobal}
                        >
                            <i className="ri-file-text-line label-icon fs-16 me-2 align-middle"></i>
                            Générer ADD
                        </Button>
                        <Button
                            color="success"
                            className="btn-label"
                            onClick={handleGenererAddSelection}
                        >
                            <i className="ri-file-list-3-line label-icon fs-16 me-2 align-middle"></i>
                            Sélectionner Générer ADD
                        </Button>
                        <Button
                            color="danger"
                            className="btn-label"
                            onClick={openAjournerModal}
                        >
                            <i className="ri-close-circle-line label-icon fs-16 me-2 align-middle"></i>
                            Sélectionner et ajourner
                        </Button>
                    </div>

                    <div className="alert alert-info rounded-3 d-flex mb-4 border-0">
                        <i className="ri-information-line fs-18 me-3 mt-1"></i>
                        <div className="w-100">
                            Chaque action [Validation Groupé & Générer
                            Attestation Présence] s'applique à l'onglet actif.
                            Pour l'onglet Présence, sélectionner la période
                            d'attestation de démarrage.
                        </div>
                    </div>

                    <Row>
                        <Col lg={12}>
                            <Card className="border-0 shadow-sm">
                                <CardHeader className="bg-success">
                                    <div className="d-flex justify-content-between align-items-center">
                                        <h5 className="card-title mb-0 text-white">
                                            {currentTabLabel}
                                            <span
                                                className="badge fs-12 ms-2"
                                                style={{
                                                    background: '#e8f0fe',
                                                    color: '#405189',
                                                }}
                                            >
                                                {currentRows.length}
                                            </span>
                                        </h5>
                                    </div>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <Nav
                                        tabs
                                        className="nav-tabs-custom nav-success border-bottom-0 ms-3 mt-3 mb-0"
                                    >
                                        <NavItem>
                                            <NavLink
                                                className={classnames({
                                                    active: activeTab === '1',
                                                })}
                                                onClick={() => toggleTab('1')}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                DEMARRAGE
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                className={classnames({
                                                    active: activeTab === '2',
                                                })}
                                                onClick={() => toggleTab('2')}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                DEMARRAGE OMIS
                                            </NavLink>
                                        </NavItem>
                                        <NavItem>
                                            <NavLink
                                                className={classnames({
                                                    active: activeTab === '3',
                                                })}
                                                onClick={() => toggleTab('3')}
                                                style={{ cursor: 'pointer' }}
                                            >
                                                RETOUR D'AJOURNEMENT
                                            </NavLink>
                                        </NavItem>
                                    </Nav>

                                    <TabContent activeTab={activeTab}>
                                        <TabPane tabId="1">
                                            <TableContainerReactTable
                                                columns={columns}
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
                                            <div className="bg-light border-bottom p-3">
                                                <Row>
                                                    <Col md={4}>
                                                        <label className="form-label text-danger mb-2 font-medium">
                                                            PÉRIODE *
                                                        </label>
                                                        <Input
                                                            type="select"
                                                            className="form-select-sm"
                                                            value={
                                                                formData.periode_id
                                                            }
                                                            onChange={(e) =>
                                                                setFormData(
                                                                    'periode_id',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        >
                                                            <option value="">
                                                                Sélectionner une
                                                                période
                                                            </option>
                                                            {Object.entries(
                                                                periodes || {},
                                                            ).map(
                                                                ([
                                                                    id,
                                                                    label,
                                                                ]) => (
                                                                    <option
                                                                        key={id}
                                                                        value={
                                                                            id
                                                                        }
                                                                    >
                                                                        {String(
                                                                            label,
                                                                        )}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </Input>
                                                    </Col>
                                                </Row>
                                            </div>
                                            <TableContainerReactTable
                                                columns={columns}
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
                                                columns={columns}
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

            <Modal
                isOpen={modalAjournerOpen}
                toggle={() => setModalAjournerOpen((value) => !value)}
            >
                <ModalHeader
                    toggle={() => setModalAjournerOpen((value) => !value)}
                    className="bg-danger text-white"
                >
                    Ajourner la sélection
                </ModalHeader>
                <ModalBody>
                    <p>
                        Vous êtes sur le point d'ajourner{' '}
                        <strong>{selectedRows.length}</strong> dossier(s).
                        Veuillez spécifier le motif d'ajournement.
                    </p>
                    <div className="mb-3">
                        <label htmlFor="motif" className="form-label">
                            Motif d'ajournement
                        </label>
                        <Input
                            type="textarea"
                            id="motif"
                            rows={3}
                            placeholder="Ex: Contrat illisible, dates incorrectes..."
                            value={actionData.motif}
                            onChange={(e) =>
                                setActionData('motif', e.target.value)
                            }
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        onClick={() => setModalAjournerOpen(false)}
                    >
                        Fermer
                    </Button>
                    <Button
                        color="danger"
                        onClick={confirmAjourner}
                        disabled={actionProcessing || !actionData.motif}
                    >
                        Confirmer l'ajournement
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal
                isOpen={modalValiderOpen}
                toggle={() => setModalValiderOpen((value) => !value)}
            >
                <ModalHeader
                    toggle={() => setModalValiderOpen((value) => !value)}
                    className="bg-primary text-white"
                >
                    Validation de stagiaires
                </ModalHeader>
                <ModalBody>
                    <p>
                        Validation de <strong>{selectedRows.length}</strong>{' '}
                        dossier(s) sélectionné(s).
                    </p>
                    <div className="alert alert-secondary border-0">
                        Onglet actif: <strong>{currentTabLabel}</strong>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button
                        color="light"
                        onClick={() => setModalValiderOpen(false)}
                    >
                        Fermer
                    </Button>
                    <Button
                        color="primary"
                        onClick={confirmValider}
                        disabled={actionProcessing}
                    >
                        Enregistrer
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal
                isOpen={Boolean(previewRow)}
                toggle={() => setPreviewRow(null)}
                size="lg"
            >
                <ModalHeader
                    toggle={() => setPreviewRow(null)}
                    className="bg-light"
                >
                    Détail du dossier
                </ModalHeader>
                <ModalBody>
                    {previewRow && (
                        <div className="table-responsive">
                            <table className="table-sm table align-middle">
                                <tbody>
                                    <tr>
                                        <th>Date</th>
                                        <td>{previewRow.date}</td>
                                    </tr>
                                    <tr>
                                        <th>Agence</th>
                                        <td>{previewRow.agence}</td>
                                    </tr>
                                    <tr>
                                        <th>Entreprise</th>
                                        <td>{previewRow.entreprise}</td>
                                    </tr>
                                    <tr>
                                        <th>Numéro AEJ</th>
                                        <td>{previewRow.numero_aej}</td>
                                    </tr>
                                    <tr>
                                        <th>Nom et prénoms</th>
                                        <td>{previewRow.nom_prenoms}</td>
                                    </tr>
                                    <tr>
                                        <th>Contrat</th>
                                        <td>{previewRow.contrat_label}</td>
                                    </tr>
                                    <tr>
                                        <th>Incidence financière</th>
                                        <td>
                                            {previewRow.incidence_financiere}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setPreviewRow(null)}>
                        Fermer
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default ValidationDemarrageIndex;
