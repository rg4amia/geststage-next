import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useCallback, useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    Input,
    Label,
    Row,
    Spinner,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

/* ─── Helpers ─── */
const formatDateFr = (dateStr: string | null | undefined) => {
    if (!dateStr) {
return '-';
}

    try {
        return new Date(dateStr).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

const formatMontant = (montant: number | null | undefined) => {
    if (montant == null) {
return '-';
}

    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(montant);
};

/* ─── Types ─── */
interface AjourneDmgRow {
    id: number;
    stage_id: number | null;
    statut: string;
    montant: number | null;
    date_ajournement: string | null;
    observation_dmg: string;
    decisions: any[];
    periode: { code: string | null };
    stage: {
        id: number;
        date_debut: string | null;
        date_fin_prevue: string | null;
        beneficiaire: {
            numero_aej: string | null;
            nom: string | null;
            prenoms: string | null;
            telephone_principal: string | null;
            telephone_secondaire: string | null;
            typePaiement: { nom: string | null; code: string | null } | null;
            numero_tresor_money: string | null;
            numero_wave: string | null;
        };
        entreprise: { id: number; raison_sociale: string | null };
        agence: { id: number; nom: string | null };
        sourceFinancement: { id: number; nom: string | null };
        typeStage: { id: number; nom: string | null };
    };
}

interface Props {
    paiements: { data: AjourneDmgRow[]; current_page: number; last_page: number; per_page: number; total: number; links: any[] };
    periodes: { id: number; code: string }[];
    agences: { id: number; nom: string }[];
    entreprises: { id: number; raison_sociale: string }[];
    sourcesFinancement: { id: number; nom: string }[];
    typesStage: { id: number; nom: string }[];
    filters: Record<string, string>;
}

/* ═══════════════════════════════════════════════════════════
   COMPOSANT PRINCIPAL
   ═══════════════════════════════════════════════════════════ */
const AjourneDmgIndex = ({ paiements, periodes, agences, entreprises, sourcesFinancement, typesStage, filters }: Props) => {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const data = paiements;

    const [selectedFilters, setSelectedFilters] = useState({
        periode_id: filters?.periode_id || '',
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        search: filters?.search || '',
    });

    const handleFilterChange = useCallback((key: string, value: string) => {
        setSelectedFilters((prev) => ({ ...prev, [key]: value }));
    }, []);

    const applyFilters = useCallback(() => {
        const params: Record<string, string> = {};
        Object.entries(selectedFilters).forEach(([k, v]) => {
            if (v) {
params[k] = v;
}
        });
        router.get('/cip/pointage/ajourne-dmg', params, { preserveState: true, preserveScroll: true });
    }, [selectedFilters]);

    const resetFilters = useCallback(() => {
        setSelectedFilters({ periode_id: '', agence_id: '', entreprise_id: '', source_financement_id: '', type_stage_id: '', search: '' });
        router.get('/cip/pointage/ajourne-dmg', {}, { preserveState: false });
    }, []);

    /* ─── getStageData —(Index pattern) ─── */
    const getStageData = useCallback((row: any) => row.stage || row, []);

    /* ─── Colonnes ─── */
    const columns = useMemo(
        () => [
            { header: '#', cell: (cell: any) => cell.row.index + 1, size: 50 },
            {
                header: 'Période',
                cell: (cell: any) => {
                    const code = cell.row.original.periode?.code;

                    return code ? <Badge color="secondary-subtle" className="text-secondary">{code}</Badge> : '-';
                },
            },
            {
                header: 'Date rejet',
                cell: (cell: any) => <span className="text-muted">{formatDateFr(cell.row.original.date_ajournement)}</span>,
            },
            {
                header: 'Motif DMG',
                cell: (cell: any) => {
                    const motif = cell.row.original.observation_dmg || 'Ajourné par la DMG';

                    return (
                        <span
                            className="text-danger fw-medium"
                            title={motif}
                            style={{ maxWidth: '200px', display: 'inline-block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                        >
                            <i className="ri-error-warning-line me-1"></i>{motif}
                        </span>
                    );
                },
            },
            {
                header: 'Agence',
                cell: (cell: any) => <span className="fw-medium">{getStageData(cell.row.original)?.agence?.nom || '-'}</span>,
            },
            {
                header: 'Entreprise',
                cell: (cell: any) => getStageData(cell.row.original)?.entreprise?.raison_sociale || '-',
            },
            {
                header: 'Financement',
                cell: (cell: any) => {
                    const val = getStageData(cell.row.original)?.sourceFinancement?.nom;

                    return val ? <Badge color="info-subtle" className="text-info">{val}</Badge> : '-';
                },
            },
            {
                header: 'N° AEJ',
                cell: (cell: any) => <span className="text-muted">{getStageData(cell.row.original)?.beneficiaire?.numero_aej || '-'}</span>,
            },
            {
                header: 'Nom et Prénoms',
                cell: (cell: any) => {
                    const stage = getStageData(cell.row.original);
                    const b = stage?.beneficiaire;

                    return (
                        <div>
                            <span className="fw-semibold">{b?.nom || ''} {b?.prenoms || ''}</span>
                            {b?.telephone_principal && (
                                <div className="text-muted fs-12">
                                    <i className="ri-phone-line me-1"></i>{b.telephone_principal}
                                </div>
                            )}
                        </div>
                    );
                },
            },
            {
                header: 'Type paiement',
                cell: (cell: any) => {
                    const tp = getStageData(cell.row.original)?.beneficiaire?.typePaiement;

                    if (!tp?.nom) {
return '-';
}

                    const isTresor = tp.code === 'TRESOR_MONEY';

                    return (
                        <Badge color={isTresor ? 'warning-subtle' : 'success-subtle'} className={`text-${isTresor ? 'warning' : 'success'}`}>
                            <i className={`${isTresor ? 'ri-bank-line' : 'ri-smartphone-line'} me-1`}></i>{tp.nom}
                        </Badge>
                    );
                },
            },
            {
                header: 'Date début',
                cell: (cell: any) => <span className="text-muted">{formatDateFr(getStageData(cell.row.original)?.date_debut)}</span>,
            },
            {
                header: 'Date fin',
                cell: (cell: any) => <span className="text-muted">{formatDateFr(getStageData(cell.row.original)?.date_fin_prevue)}</span>,
            },
            {
                header: 'Montant',
                cell: (cell: any) => <span className="fw-semibold">{formatMontant(cell.row.original.montant)}</span>,
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const stageId = row.stage_id || getStageData(row)?.id;
                    const editHref = stageId
                        ? `/cip/pointages/edit-stagiaire/${stageId}?return_tab=ajourne_dmg&mois=${encodeURIComponent(selectedFilters.periode_id || '')}`
                        : '#';

                    return (
                        <div className="d-flex gap-1">
                            <Button color="primary" size="sm" href={editHref} disabled={!stageId} title="Traiter le stagiaire">
                                <i className="ri-edit-line me-1"></i>Traiter
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [selectedFilters.periode_id],
    );

    return (
        <React.Fragment>
            <Head title="Pointages Ajournés (DMG)" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages Ajournés par la DMG" pageTitle="Espace CIP" />

                    {/* ─── Flash Messages ─── */}
                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show">
                            <i className="ri-check-double-line me-2 align-middle"></i>{flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show">
                            <i className="ri-error-warning-line me-2 align-middle"></i>{flash.error}
                        </Alert>
                    )}

                    {/* ─── Alerte contextuelle ─── */}
                    <Alert color="danger" className="border-0 border-start border-4 border-danger mb-4">
                        <div className="d-flex align-items-center gap-2">
                            <i className="ri-arrow-go-back-fill fs-24"></i>
                            <div>
                                <strong>Pointages rejetés par la DMG</strong>
                                <p className="mb-0 fs-13">
                                    Ces paiements ont été ajournés par la DMG. Le CIP doit corriger les informations du stagiaire
                                    (type de paiement, coordonnées) puis renvoyer au Chef d'Agence pour re-validation.
                                </p>
                            </div>
                        </div>
                    </Alert>

                    {/* ─── Filtres (même modèle que Index.tsx) ─── */}
                    <Card className="mb-3">
                        <CardBody>
                            <Row className="g-3 align-items-end">
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.periode_id}
                                        onChange={(e) => handleFilterChange('periode_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {periodes.map((p) => (
                                            <option key={p.id} value={p.id}>{p.code}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.agence_id}
                                        onChange={(e) => handleFilterChange('agence_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {agences.map((a) => (
                                            <option key={a.id} value={a.id}>{a.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Entreprise</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.entreprise_id}
                                        onChange={(e) => handleFilterChange('entreprise_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {entreprises.map((e) => (
                                            <option key={e.id} value={e.id}>{e.raison_sociale}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Financement</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.source_financement_id}
                                        onChange={(e) => handleFilterChange('source_financement_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {sourcesFinancement.map((s) => (
                                            <option key={s.id} value={s.id}>{s.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Type Stage</Label>
                                    <Input
                                        type="select"
                                        value={selectedFilters.type_stage_id}
                                        onChange={(e) => handleFilterChange('type_stage_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {typesStage.map((t) => (
                                            <option key={t.id} value={t.id}>{t.nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={2}>
                                    <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Recherche</Label>
                                    <Input
                                        type="text"
                                        placeholder="Nom, N° AEJ..."
                                        value={selectedFilters.search}
                                        onChange={(e) => handleFilterChange('search', e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    />
                                </Col>
                                <Col md={12}>
                                    <div className="d-flex gap-2">
                                        <Button color="success" onClick={applyFilters}>
                                            <i className="ri-search-line me-1"></i>Rechercher
                                        </Button>
                                        <Button color="secondary" onClick={resetFilters}>
                                            <i className="ri-refresh-line me-1"></i>Réinitialiser
                                        </Button>
                                        <Link href="/cip/pointages?tab=ajourne_dmg" className="btn btn-outline-secondary ms-auto">
                                            <i className="ri-arrow-left-line me-1"></i>Voir l'onglet pointages
                                        </Link>
                                    </div>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {/* ─── Tableau (même modèle que Index.tsx) ─── */}
                    <Card>
                        <CardHeader>
                            <h4 className="card-title mb-0">
                                <i className="ri-arrow-go-back-fill text-danger me-2"></i>
                                Pointages rejetés par la DMG
                                <Badge color="danger" className="ms-2">{data?.total || 0}</Badge>
                            </h4>
                        </CardHeader>
                        <CardBody>
                            <TableContainerReactTable
                                columns={columns}
                                data={data?.data || []}
                                isGlobalFilter={false}
                                customPageSize={data?.data?.length || 20}
                                divClass="table-responsive table-card mt-1 mb-1"
                                tableClass="align-middle table-nowrap table-hover"
                                theadClass="table-light"
                                SearchPlaceholder="Recherche..."
                                isServerPagination={true}
                                serverPagination={data}
                                onPageChange={(page: number) => {
                                    const params: Record<string, string> = {};
                                    Object.entries(selectedFilters).forEach(([k, v]) => {
                                        if (v) {
params[k] = v;
}
                                    });
                                    params.page = String(page);
                                    router.get('/cip/pointage/ajourne-dmg', params, { preserveState: true, preserveScroll: true });
                                }}
                            />
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default AjourneDmgIndex;
