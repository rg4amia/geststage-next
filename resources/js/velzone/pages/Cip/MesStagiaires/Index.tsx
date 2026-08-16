import React, { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Form, Input, Modal, ModalHeader, ModalBody, ModalFooter, Badge } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const MesStagiaires = ({ 
    instances, 
    agences, 
    entreprises, 
    typesfinancements, 
    typestages, 
    typestructures, 
    etapes, 
    situationstages, 
    filters 
}: any) => {
    const data = instances || [];
    const [modalAnalyse, setModalAnalyse] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);

    const { data: formData, setData, get } = useForm({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        etape_id: filters?.etape_id || '',
        situationstage_id: filters?.situationstage_id || '',
        date_debut: filters?.date_debut || '',
        date_fin: filters?.date_fin || '',
        search: filters?.search || '',
    });

    const handleSearch = (e?: any) => {
        if(e) e.preventDefault();
        get('/cip/mes-stagiaires');
    };

    const handleReset = () => {
        setData({
            agence_id: '',
            entreprise_id: '',
            typesfinancement_id: '',
            typestage_id: '',
            type_structure_id: '',
            etape_id: '',
            situationstage_id: '',
            date_debut: '',
            date_fin: '',
            search: '',
        });
        setTimeout(() => get('/cip/mes-stagiaires'), 50);
    };

    const toggleAnalyse = (stagiaire: any = null) => {
        setSelectedStagiaire(stagiaire);
        setModalAnalyse(!modalAnalyse);
    };

    // Calculate some basic stats
    const totalStagiaires = data.length;
    const avecContrat = data.filter((d: any) => d.stage?.contrats?.length > 0).length;
    const sansContrat = totalStagiaires - avecContrat;
    const enAttente = data.filter((d: any) => (d.etape_courante || '').toLowerCase().includes('attente')).length;

    const columns = useMemo(
        () => [
            {
                header: 'Situation Stage',
                accessorKey: 'stage.situation_stage',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Date',
                accessorKey: 'created_at',
                cell: (cell: any) => {
                    const val = cell.getValue();
                    return val ? new Date(val).toLocaleDateString('fr-FR') : '-';
                },
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
                header: 'Source de financement',
                accessorKey: 'stage.source_financement.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type de stage',
                accessorKey: 'stage.type_stage.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type Structure',
                accessorKey: 'stage.entreprise.type_structure.nom',
                cell: (cell: any) => {
                    const type = cell.getValue();
                    if (!type) return '-';
                    if (type.toUpperCase().includes('NEANT')) {
                        return <Badge color="warning">{type}</Badge>;
                    }
                    if (type.toUpperCase().includes('PRIVE')) {
                        return <Badge color="primary">{type}</Badge>;
                    }
                    return <Badge color="success">{type}</Badge>;
                },
            },
            {
                header: 'Date debut',
                accessorKey: 'stage.date_debut',
                cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
            },
            {
                header: 'Date fin',
                accessorKey: 'stage.date_fin_prevue',
                cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
            },
            {
                header: 'Numéro AEJ',
                accessorKey: 'stage.beneficiaire.numero_aej',
                cell: (cell: any) => <span className="fw-medium">{cell.getValue() || '-'}</span>,
            },
            {
                header: 'Nom et prénoms',
                accessorKey: 'stage.beneficiaire.nom',
                cell: (cell: any) => {
                    const row = cell.row.original.stage?.beneficiaire;
                    return row ? <span className="fw-semibold text-primary">{row.nom} {row.prenoms}</span> : '-';
                },
            },
            {
                header: 'Sexe',
                accessorKey: 'stage.beneficiaire.sexe',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Contrat',
                accessorKey: 'stage.contrats',
                cell: (cell: any) => {
                    const contrats = cell.getValue();
                    const hasContrat = contrats && contrats.length > 0;
                    return hasContrat ? (
                        <Badge color="success"><i className="ri-check-line me-1"></i> Avec Contrat</Badge>
                    ) : (
                        <Badge color="warning">Sans Contrat</Badge>
                    );
                },
            },
            {
                header: 'Etape Traitement',
                accessorKey: 'etape_courante',
                cell: (cell: any) => {
                    return <Badge color="info">{cell.getValue() || '-'}</Badge>;
                },
            },
            {
                header: 'Action',
                cell: (cell: any) => {
                    return (
                        <div className="d-flex gap-1">
                            <Button color="light" size="sm" className="btn-icon" title="Voir Stagiaire">
                                <i className="ri-eye-line text-info"></i>
                            </Button>
                            <Button color="light" size="sm" className="btn-icon" title="Pointage" onClick={() => toggleAnalyse(cell.row.original)}>
                                <i className="ri-folder-open-line text-primary"></i>
                            </Button>
                            <Button color="light" size="sm" className="btn-icon" title="PDF">
                                <i className="ri-file-pdf-line text-danger"></i>
                            </Button>
                            <Button color="light" size="sm" className="btn-icon" title="Envoyer">
                                <i className="ri-send-plane-line text-warning"></i>
                            </Button>
                        </div>
                    );
                },
            },
        ].map(col => ({ ...col, enableColumnFilter: false })),
        []
    );

    const activeFiltersCount = [
        formData.agence_id, formData.entreprise_id, formData.typesfinancement_id,
        formData.typestage_id, formData.type_structure_id, formData.etape_id,
        formData.situationstage_id, formData.date_debut, formData.date_fin, formData.search
    ].filter(Boolean).length;

    return (
        <React.Fragment>
            <Head title="Mes Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    {/* Page Title */}
                    <div className="row">
                        <div className="col-12">
                            <div className="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 className="mb-sm-0">Mes Stagiaires</h4>
                                <div className="page-title-right">
                                    <ol className="breadcrumb m-0">
                                        <li className="breadcrumb-item">
                                            <Link href="/dashboard">Accueil</Link>
                                        </li>
                                        <li className="breadcrumb-item active">
                                            Mes Stagiaires
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <Row className="g-3 mb-3">
                        {[
                            { label: 'Total Stagiaires', value: totalStagiaires, icon: 'ri-team-line', bg: '#e8f4fd', iconColor: '#0dcaf0', valColor: '#0dcaf0' },
                            { label: 'Avec Contrat', value: avecContrat, icon: 'ri-file-list-3-line', bg: '#e8f8f0', iconColor: '#198754', valColor: '#198754' },
                            { label: 'En Attente', value: enAttente, icon: 'ri-time-line', bg: '#fff8e6', iconColor: '#fd7e14', valColor: '#fd7e14' },
                            { label: 'Sans Contrat', value: sansContrat, icon: 'ri-close-circle-line', bg: '#fde8e8', iconColor: '#dc3545', valColor: '#dc3545' },
                        ].map((s, i) => (
                            <Col xl={3} md={6} key={i}>
                                <Card className="card-animate mb-0 h-100 border-0 shadow-sm">
                                    <CardBody className="p-3">
                                        <div className="d-flex align-items-center gap-3">
                                            <div className="avatar-sm flex-shrink-0">
                                                <span className="avatar-title rounded fs-3" style={{ background: s.bg, color: s.iconColor }}>
                                                    <i className={s.icon}></i>
                                                </span>
                                            </div>
                                            <div>
                                                <p className="text-uppercase fw-medium mb-0 fs-11" style={{ color: '#878a99' }}>{s.label}</p>
                                                <h4 className="fs-22 fw-bold mb-0" style={{ color: s.valColor }}>{s.value}</h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    {/* Filters Card */}
                    <Card className="mb-3 border-0 shadow-sm">
                        <CardBody className="pb-2">
                            <Form onSubmit={handleSearch}>
                                {/* Ligne 1 : recherche + filtres principaux */}
                                <Row className="g-2 align-items-end mb-2">
                                    <Col xs={12} sm={6} md={3}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-search-line me-1"></i>Recherche
                                        </label>
                                        <Input 
                                            type="text" 
                                            className="form-control-sm" 
                                            placeholder="Nom, Prénom, Numéro AEJ..."
                                            value={formData.search} 
                                            onChange={(e) => setData('search', e.target.value)} 
                                        />
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-building-4-line me-1"></i>Agence
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                            <option value="">Toutes les agences</option>
                                            {Object.entries(agences || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-building-line me-1"></i>Entreprise
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.entreprise_id} onChange={e => setData('entreprise_id', e.target.value)}>
                                            <option value="">Toutes les entreprises</option>
                                            {Object.entries(entreprises || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-bank-card-line me-1"></i>Financement
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.typesfinancement_id} onChange={e => setData('typesfinancement_id', e.target.value)}>
                                            <option value="">Tous</option>
                                            {Object.entries(typesfinancements || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={3}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-macbook-line me-1"></i>Type de Stage
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.typestage_id} onChange={e => setData('typestage_id', e.target.value)}>
                                            <option value="">Tous les types</option>
                                            {Object.entries(typestages || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                </Row>

                                {/* Ligne 2 : filtres secondaires + boutons */}
                                <Row className="g-2 align-items-end">
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-community-line me-1"></i>Structure
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.type_structure_id} onChange={e => setData('type_structure_id', e.target.value)}>
                                            <option value="">Toutes</option>
                                            {Object.entries(typestructures || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-git-merge-line me-1"></i>Étape
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.etape_id} onChange={e => setData('etape_id', e.target.value)}>
                                            <option value="">Toutes les étapes</option>
                                            {Object.entries(etapes || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-flag-line me-1"></i>Situation
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.situationstage_id} onChange={e => setData('situationstage_id', e.target.value)}>
                                            <option value="">Toutes les situations</option>
                                            {Object.entries(situationstages || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-calendar-line me-1"></i>Du
                                        </label>
                                        <Input type="date" className="form-control-sm" value={formData.date_debut} onChange={e => setData('date_debut', e.target.value)} />
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-calendar-line me-1"></i>Au
                                        </label>
                                        <Input type="date" className="form-control-sm" value={formData.date_fin} onChange={e => setData('date_fin', e.target.value)} />
                                    </Col>

                                    {/* Boutons */}
                                    <Col xs={12} sm={4} md={2} className="d-flex gap-2 align-items-end">
                                        <button
                                            type="submit"
                                            className="btn btn-primary btn-sm flex-fill"
                                            title="Appliquer les filtres"
                                        >
                                            <i className="ri-search-line me-1"></i>Filtrer
                                            {activeFiltersCount > 0 && (
                                                <span className="badge ms-1 fs-10" style={{ background: '#405189', color: '#fff' }}>{activeFiltersCount}</span>
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary btn-sm"
                                            onClick={handleReset}
                                            title="Réinitialiser"
                                        >
                                            <i className="ri-refresh-line"></i>
                                        </button>
                                    </Col>
                                </Row>

                                {/* Barre d'actions optionnelle (si besoin d'actions globales) */}
                                <div className="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                                    <div className="d-flex gap-2">
                                        <button type="button" className="btn btn-outline-success btn-sm">
                                            <i className="ri-file-excel-line me-1"></i> Exporter Excel
                                        </button>
                                    </div>
                                    {activeFiltersCount > 0 && (
                                        <small className="text-muted">
                                            <i className="ri-filter-3-line me-1"></i>
                                            {activeFiltersCount} filtre{activeFiltersCount > 1 ? 's' : ''} actif{activeFiltersCount > 1 ? 's' : ''}
                                            {' — '}
                                            <button type="button" className="btn btn-link btn-sm p-0 text-danger" onClick={handleReset}>
                                                Tout effacer
                                            </button>
                                        </small>
                                    )}
                                </div>
                            </Form>
                        </CardBody>
                    </Card>

                    {/* Main Content: Table */}
                    <Row>
                        <Col lg={12}>
                            <Card className="border-0 shadow-sm">
                                <CardHeader>
                                    <div className="d-flex justify-content-between align-items-center">
                                        <h5 className="card-title mb-0" style={{ color: '#495057' }}>
                                            Liste des Stagiaires
                                            <span className="badge ms-2 fs-12" style={{ background: '#e8f0fe', color: '#405189' }}>
                                                {totalStagiaires}
                                            </span>
                                        </h5>
                                    </div>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <TableContainerReactTable
                                        columns={columns}
                                        data={data}
                                        isGlobalFilter={false}
                                        customPageSize={10}
                                        divClass="table-responsive"
                                        tableClass="table table-striped table-hover align-middle mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher dans le tableau..."
                                    />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            <Modal isOpen={modalAnalyse} toggle={() => toggleAnalyse()} size="xl">
                <ModalHeader toggle={() => toggleAnalyse()} className="bg-light border-bottom">
                    <h5 className="modal-title mb-0" style={{ color: '#495057' }}>
                        <i className="ri-folder-open-line me-2 align-middle text-primary"></i>
                        Analyse du Pointage - {selectedStagiaire?.stage?.beneficiaire?.nom} {selectedStagiaire?.stage?.beneficiaire?.prenoms}
                    </h5>
                </ModalHeader>
                <ModalBody className="bg-white">
                    <div className="alert alert-info border-0 rounded-3 mb-4 d-flex">
                        <i className="ri-information-line fs-18 me-3"></i>
                        <div>
                            <h6 className="alert-heading fw-semibold mb-1">Flux de traitement</h6>
                            <p className="mb-0 fs-13">
                                <strong>1. Agence</strong> → <strong>2. DESSE</strong> → <strong>3. DMG</strong> → <strong>4. CB</strong> → <strong>5. DMG-OP</strong> → <strong>6. AC</strong>
                            </p>
                        </div>
                    </div>

                    <Card className="border shadow-none mb-0">
                        <CardBody className="p-0">
                            <div className="table-responsive">
                                <table className="table align-middle table-nowrap table-striped mb-0">
                                    <thead className="table-light text-muted fs-12">
                                        <tr>
                                            <th>Mois</th>
                                            <th>Étape Traitement</th>
                                            <th>Étape DESSE</th>
                                            <th>N° OP</th>
                                            <th>N° Bordereau</th>
                                            <th>Dossier</th>
                                            <th>Position</th>
                                            <th>Situation</th>
                                            <th>Date Pointage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {/* Example empty state row */}
                                        {selectedStagiaire?.stage?.pointages?.length > 0 ? (
                                            selectedStagiaire.stage.pointages.map((pointage: any, index: number) => (
                                                <tr key={index}>
                                                    <td>{pointage.periode?.code || '-'}</td>
                                                    <td><Badge color="info">{pointage.statut || '-'}</Badge></td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>
                                                        {pointage.version_courante?.jours_presents !== undefined 
                                                            ? `${pointage.version_courante.jours_presents} J` 
                                                            : '-'}
                                                    </td>
                                                    <td>{pointage.version_courante?.presence || '-'}</td>
                                                    <td>
                                                        {pointage.version_courante?.saisi_le 
                                                            ? new Date(pointage.version_courante.saisi_le).toLocaleDateString('fr-FR') 
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={9} className="text-center text-muted py-5">
                                                    <div className="d-flex flex-column align-items-center justify-content-center">
                                                        <i className="ri-file-search-line display-5 text-muted opacity-50 mb-3"></i>
                                                        <h6>Aucun pointage disponible</h6>
                                                        <p className="mb-0 fs-13">Les données de pointage de ce stagiaire s'afficheront ici.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardBody>
                    </Card>
                </ModalBody>
                <ModalFooter className="border-top">
                    <Button color="light" onClick={() => toggleAnalyse()}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default MesStagiaires;
