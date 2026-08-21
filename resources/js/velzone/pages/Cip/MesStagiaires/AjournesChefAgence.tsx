import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useMemo, useState } from 'react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Badge, Alert, Input, Spinner } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

interface InstanceRow {
    id: number;
    created_at: string;
    corbeille_actuelle: string;
    stage?: {
        beneficiaire?: { nom: string; prenoms: string; numero_aej: string; date_naissance?: string };
        entreprise?: { raison_sociale: string };
        agence?: { nom: string };
        sourceFinancement?: { nom: string };
        typeStage?: { nom: string };
        date_debut?: string;
        date_fin_prevue?: string;
        contrats?: any[];
    };
    etapeCourante?: { nom: string };
    evenements?: any[];
}

interface Props {
    instances: { data: InstanceRow[]; current_page: number; last_page: number; per_page: number; total: number; links: any[] };
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    typestructures: Record<string, string>;
    filters: Record<string, string>;
}

const AjournesChefAgence = ({ instances, agences, entreprises, typesfinancements, typestages, typestructures, filters }: Props) => {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const data = instances?.data || [];
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [detailRow, setDetailRow] = useState<InstanceRow | null>(null);

    const [formData, setFormData] = useState({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        search: filters?.search || '',
    });

    const handleFilterChange = (field: string, value: string) => {
        setFormData(prev => ({ ...prev, [field]: value }));
    };

    const applyFilters = () => {
        const params: Record<string, string> = {};
        Object.entries(formData).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get('/cip/mes-stagiaires/ajournes-ca', params, { preserveState: true, preserveScroll: true });
    };

    const resetFilters = () => {
        setFormData({ agence_id: '', entreprise_id: '', typesfinancement_id: '', typestage_id: '', search: '' });
        router.get('/cip/mes-stagiaires/ajournes-ca', {}, { preserveState: false });
    };

    const toggleSelectAll = () => {
        setSelectedIds(prev => prev.length === data.length ? [] : data.map(r => r.id));
    };

    const toggleSelectOne = (id: number) => {
        setSelectedIds(prev => prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]);
    };

    const handleValiderSelection = () => {
        if (selectedIds.length === 0) return;
        setIsProcessing(true);
        router.post('/cip/mes-stagiaires/valider-group', { ids: selectedIds.map(String) }, {
            preserveScroll: true,
            onFinish: () => { setIsProcessing(false); setSelectedIds([]); },
        });
    };

    const handleAjournerSelection = () => {
        if (selectedIds.length === 0) return;
        setIsProcessing(true);
        router.post('/cip/mes-stagiaires/ajourner-group', { ids: selectedIds.map(String), motif: 'Correction demandée par le Chef d\'Agence' }, {
            preserveScroll: true,
            onFinish: () => { setIsProcessing(false); setSelectedIds([]); },
        });
    };

    const getDernierMotif = (row: InstanceRow) => {
        const dernierEvenement = row.evenements?.sort((a: any, b: any) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())?.[0];
        return dernierEvenement?.motif || dernierEvenement?.description || 'Aucun motif renseigné';
    };

    const columns = useMemo(() => [
        {
            id: 'select',
            header: () => <Input type="checkbox" className="form-check-input" checked={data.length > 0 && selectedIds.length === data.length} onChange={toggleSelectAll} />,
            cell: (cell: any) => <Input type="checkbox" className="form-check-input" checked={selectedIds.includes(cell.row.original.id)} onChange={() => toggleSelectOne(cell.row.original.id)} />,
            size: 50,
        },
        { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
        { header: 'Date Création', cell: (cell: any) => cell.row.original.created_at ? new Date(cell.row.original.created_at).toLocaleDateString('fr-FR') : '-' },
        { header: 'Agence', cell: (cell: any) => <span className="fw-medium">{cell.row.original.stage?.agence?.nom || '-'}</span> },
        { header: 'Entreprise', cell: (cell: any) => cell.row.original.stage?.entreprise?.raison_sociale || '-' },
        { header: 'Financement', cell: (cell: any) => {
            const val = cell.row.original.stage?.sourceFinancement?.nom || '-';
            return <Badge color="info-subtle" className="text-info">{val}</Badge>;
        }},
        { header: 'Type Stage', cell: (cell: any) => cell.row.original.stage?.typeStage?.nom || '-' },
        { header: 'N° AEJ', cell: (cell: any) => <span className="text-muted">{cell.row.original.stage?.beneficiaire?.numero_aej || '-'}</span> },
        { header: 'Nom et Prénoms', cell: (cell: any) => {
            const b = cell.row.original.stage?.beneficiaire;
            return <span className="fw-semibold">{b ? `${b.nom} ${b.prenoms}`.trim() : '-'}</span>;
        }},
        { header: 'Date Début', cell: (cell: any) => cell.row.original.stage?.date_debut || '-' },
        { header: 'Date Fin', cell: (cell: any) => cell.row.original.stage?.date_fin_prevue || '-' },
        { header: 'Avec Contrat', cell: (cell: any) => {
            const has = (cell.row.original.stage?.contrats?.length || 0) > 0;
            return <Badge color={has ? 'success-subtle' : 'danger-subtle'} className={`text-${has ? 'success' : 'danger'}`}>
                <i className={`ri-${has ? 'check' : 'close'}-circle-line me-1`}></i>{has ? 'Oui' : 'Non'}
            </Badge>;
        }},
        { header: 'Motif Ajournement', cell: (cell: any) => (
            <span className="text-danger fw-medium" title={getDernierMotif(cell.row.original)} style={{ maxWidth: '200px', display: 'inline-block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                <i className="ri-error-warning-line me-1"></i>{getDernierMotif(cell.row.original)}
            </span>
        )},
        { header: 'Actions', cell: (cell: any) => (
            <div className="d-flex gap-1">
                <Button color="info" size="sm" outline onClick={() => setDetailRow(cell.row.original)} title="Voir le détail">
                    <i className="ri-eye-line"></i>
                </Button>
            </div>
        )},
    ], [data, selectedIds]);

    return (
        <React.Fragment>
            <Head title="Stagiaires Ajournés par le Chef d'Agence" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Ajournés Chef d'Agence" pageTitle="CIP" />

                    {flash?.success && <Alert color="success" className="border-0 alert-dismissible fade show"><i className="ri-check-double-line me-2 align-middle"></i>{flash.success}</Alert>}
                    {flash?.error && <Alert color="danger" className="border-0 alert-dismissible fade show"><i className="ri-error-warning-line me-2 align-middle"></i>{flash.error}</Alert>}

                    {/* ─── Alerte info ─── */}
                    <Alert color="warning" className="border-0 border-start border-4 border-warning mb-4">
                        <div className="d-flex align-items-center gap-2">
                            <i className="ri-error-warning-line fs-20"></i>
                            <div>
                                <strong>Stagiaires Ajournés par le Chef d'Agence</strong>
                                <p className="mb-0 fs-13">Ces dossiers ont été rejetés par le Chef d'Agence et retournés pour correction. Corbeille : <code>cip_ajourne_ca</code></p>
                            </div>
                        </div>
                    </Alert>

                    {/* ─── Filtres ─── */}
                    <Card className="mb-3 shadow-sm border-0">
                        <CardBody className="py-2">
                            <Row className="g-2 align-items-end">
                                <Col xs={6} sm={3} md={2}>
                                    <label className="form-label fs-12 text-muted mb-1">Agence</label>
                                    <select className="form-select form-select-sm" value={formData.agence_id} onChange={e => handleFilterChange('agence_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {Object.entries(agences).map(([id, nom]) => <option key={id} value={id}>{nom}</option>)}
                                    </select>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <label className="form-label fs-12 text-muted mb-1">Entreprise</label>
                                    <select className="form-select form-select-sm" value={formData.entreprise_id} onChange={e => handleFilterChange('entreprise_id', e.target.value)}>
                                        <option value="">Toutes</option>
                                        {Object.entries(entreprises).map(([id, nom]) => <option key={id} value={id}>{nom}</option>)}
                                    </select>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <label className="form-label fs-12 text-muted mb-1">Financement</label>
                                    <select className="form-select form-select-sm" value={formData.typesfinancement_id} onChange={e => handleFilterChange('typesfinancement_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {Object.entries(typesfinancements).map(([id, nom]) => <option key={id} value={id}>{nom}</option>)}
                                    </select>
                                </Col>
                                <Col xs={6} sm={3} md={2}>
                                    <label className="form-label fs-12 text-muted mb-1">Type Stage</label>
                                    <select className="form-select form-select-sm" value={formData.typestage_id} onChange={e => handleFilterChange('typestage_id', e.target.value)}>
                                        <option value="">Tous</option>
                                        {Object.entries(typestages).map(([id, nom]) => <option key={id} value={id}>{nom}</option>)}
                                    </select>
                                </Col>
                                <Col xs={6} sm={3} md={3}>
                                    <label className="form-label fs-12 text-muted mb-1">Recherche</label>
                                    <Input type="text" bsSize="sm" placeholder="Nom, N° AEJ..." value={formData.search} onChange={e => handleFilterChange('search', e.target.value)} onKeyDown={e => e.key === 'Enter' && applyFilters()} />
                                </Col>
                                <Col xs={6} sm={3} md={1}>
                                    <div className="d-flex gap-1">
                                        <Button color="success" size="sm" onClick={applyFilters}><i className="ri-search-line"></i></Button>
                                        <Button color="secondary" size="sm" onClick={resetFilters}><i className="ri-refresh-line"></i></Button>
                                    </div>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {/* ─── Actions globales ─── */}
                    {selectedIds.length > 0 && (
                        <Card className="mb-3 shadow-sm border-0">
                            <CardBody className="py-2">
                                <div className="d-flex align-items-center gap-2">
                                    <Badge color="primary">{selectedIds.length} sélectionné(s)</Badge>
                                    <Button color="success" size="sm" onClick={handleValiderSelection} disabled={isProcessing}>
                                        <i className="ri-check-double-line me-1"></i>Re-valider la sélection
                                    </Button>
                                    <Button color="danger" size="sm" onClick={handleAjournerSelection} disabled={isProcessing}>
                                        <i className="ri-close-circle-line me-1"></i>Ajourner la sélection
                                    </Button>
                                </div>
                            </CardBody>
                        </Card>
                    )}

                    {/* ─── Tableau ─── */}
                    <Card className="shadow-sm border-0">
                        <CardHeader className="bg-transparent border-bottom">
                            <div className="d-flex justify-content-between align-items-center">
                                <h5 className="card-title mb-0">
                                    <i className="ri-arrow-go-back-line me-2 text-warning"></i>
                                    Stagiaires Ajournés
                                    <Badge color="warning" className="ms-2 fs-12">{instances?.total || 0}</Badge>
                                </h5>
                                <Link href="/cip/mes-stagiaires" className="btn btn-outline-secondary btn-sm">
                                    <i className="ri-arrow-left-line me-1"></i>Retour à mes stagiaires
                                </Link>
                            </div>
                        </CardHeader>
                        <CardBody className="p-0">
                            <TableContainerReactTable
                                columns={columns}
                                data={data}
                                isGlobalFilter={false}
                                customPageSize={50}
                                isServerPagination={true}
                                serverPagination={instances}
                                onPageChange={(page) => {
                                    router.get('/cip/mes-stagiaires/ajournes-ca', { ...formData, page: String(page) }, { preserveState: true, preserveScroll: true });
                                }}
                                divClass="table-responsive table-card mb-0"
                                tableClass="table-striped align-middle table-nowrap mb-0"
                                theadClass="table-light text-uppercase fw-semibold fs-11"
                            />
                        </CardBody>
                    </Card>

                    {/* ─── Modale Détail ─── */}
                    {detailRow && (
                        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)' }} onClick={() => setDetailRow(null)}>
                            <div className="modal-dialog modal-lg" onClick={e => e.stopPropagation()}>
                                <div className="modal-content">
                                    <div className="modal-header bg-warning">
                                        <h5 className="modal-title text-dark"><i className="ri-file-user-line me-2"></i>Détail du dossier ajourné</h5>
                                        <button type="button" className="btn-close" onClick={() => setDetailRow(null)}></button>
                                    </div>
                                    <div className="modal-body">
                                        <Row>
                                            <Col md={6}>
                                                <table className="table table-sm align-middle mb-3">
                                                    <tbody>
                                                        <tr><th className="text-muted fw-medium">Agence</th><td>{detailRow.stage?.agence?.nom || '-'}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Entreprise</th><td className="fw-medium">{detailRow.stage?.entreprise?.raison_sociale || '-'}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Financement</th><td>{detailRow.stage?.sourceFinancement?.nom || '-'}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Type Stage</th><td>{detailRow.stage?.typeStage?.nom || '-'}</td></tr>
                                                    </tbody>
                                                </table>
                                            </Col>
                                            <Col md={6}>
                                                <table className="table table-sm align-middle mb-3">
                                                    <tbody>
                                                        <tr><th className="text-muted fw-medium">N° AEJ</th><td>{detailRow.stage?.beneficiaire?.numero_aej || '-'}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Nom et Prénoms</th><td className="fw-semibold">{detailRow.stage?.beneficiaire?.nom} {detailRow.stage?.beneficiaire?.prenoms}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Date Début</th><td>{detailRow.stage?.date_debut || '-'}</td></tr>
                                                        <tr><th className="text-muted fw-medium">Date Fin</th><td>{detailRow.stage?.date_fin_prevue || '-'}</td></tr>
                                                    </tbody>
                                                </table>
                                            </Col>
                                        </Row>
                                        <div className="alert alert-danger border-0">
                                            <strong><i className="ri-error-warning-line me-1"></i>Motif d'ajournement :</strong>
                                            <p className="mb-0 mt-1">{getDernierMotif(detailRow)}</p>
                                        </div>
                                    </div>
                                    <div className="modal-footer">
                                        <Button color="light" onClick={() => setDetailRow(null)}>Fermer</Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                </Container>
            </div>
        </React.Fragment>
    );
};

export default AjournesChefAgence;
