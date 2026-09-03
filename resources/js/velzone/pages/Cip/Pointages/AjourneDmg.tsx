import { Head, router, usePage } from '@inertiajs/react';
import React, { useCallback, useMemo, useState } from 'react';
import {
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    Row,
    Button,
    Input,
    Label,
    Alert,
    Pagination,
    PaginationItem,
    PaginationLink,
    Badge,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';

/* ─── Helpers ─── */
const formatDateFr = (dateStr: string | null | undefined) => {
    if (!dateStr) return '-';
    try {
        return new Date(dateStr).toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

const formatMontant = (montant: number | null | undefined) => {
    if (montant == null) return '-';
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        minimumFractionDigits: 0,
    }).format(montant);
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

interface PaginatedData {
    data: AjourneDmgRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
    periode_id?: string;
    agence_id?: string;
    entreprise_id?: string;
    source_financement_id?: string;
    type_stage_id?: string;
    search?: string;
}

interface Props {
    paiements: PaginatedData;
    periodes: { id: number; code: string }[];
    agences: { id: number; nom: string }[];
    entreprises: { id: number; raison_sociale: string }[];
    sourcesFinancement: { id: number; nom: string }[];
    typesStage: { id: number; nom: string }[];
    filters: Filters;
}

/* ─── Composant ─── */
const AjourneDmgIndex = ({ paiements, periodes, agences, entreprises, sourcesFinancement, typesStage, filters }: Props) => {
    const { flash } = usePage().props as any;
    const [selectedFilters, setSelectedFilters] = useState<Filters>({
        periode_id: filters?.periode_id || '',
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || '',
        type_stage_id: filters?.type_stage_id || '',
        search: filters?.search || '',
    });

    const handleFilterChange = useCallback((key: string, value: string) => {
        setSelectedFilters((prev) => ({ ...prev, [key]: value }));
        const params: Record<string, string> = {};
        if (key !== 'search') {
            Object.entries({ ...selectedFilters, [key]: value }).forEach(([k, v]) => {
                if (v) params[k] = v;
            });
        }
        router.get('/cip/pointage/ajourne-dmg', params, { preserveState: true, replace: true });
    }, [selectedFilters]);

    const handleSearch = useCallback(() => {
        const params: Record<string, string> = {};
        Object.entries(selectedFilters).forEach(([k, v]) => {
            if (v) params[k] = v;
        });
        router.get('/cip/pointage/ajourne-dmg', params, { preserveState: true, replace: true });
    }, [selectedFilters]);

    const columns = useMemo(
        () => [
            {
                header: '#',
                cell: (cell: any) => cell.row.index + 1,
                size: 50,
            },
            {
                header: 'Période',
                accessorFn: (row: AjourneDmgRow) => row.periode?.code,
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Date ajournement',
                accessorFn: (row: AjourneDmgRow) => row.date_ajournement,
                cell: (cell: any) => formatDateFr(cell.getValue()),
            },
            {
                header: 'Motif DMG',
                accessorFn: (row: AjourneDmgRow) => row.observation_dmg,
                cell: (cell: any) => (
                    <span className="text-danger">
                        <i className="ri-error-warning-line me-1"></i>
                        {cell.getValue() || 'Ajourné par la DMG'}
                    </span>
                ),
            },
            {
                header: 'Agence',
                accessorFn: (row: AjourneDmgRow) => row.stage?.agence?.nom,
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Entreprise',
                accessorFn: (row: AjourneDmgRow) => row.stage?.entreprise?.raison_sociale,
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Financement',
                accessorFn: (row: AjourneDmgRow) => row.stage?.sourceFinancement?.nom,
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type Stage',
                accessorFn: (row: AjourneDmgRow) => row.stage?.typeStage?.nom,
                cell: (cell: any) => cell.getValue() || 'Stage de qualification',
            },
            {
                header: 'N° AEJ',
                accessorFn: (row: AjourneDmgRow) => row.stage?.beneficiaire?.numero_aej,
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Nom et prénoms',
                cell: (cell: any) => {
                    const b = cell.row.original.stage?.beneficiaire;
                    return (
                        <div>
                            <span className="fw-medium">{b?.nom || ''} {b?.prenoms || ''}</span>
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
                accessorFn: (row: AjourneDmgRow) => row.stage?.beneficiaire?.typePaiement?.nom,
                cell: (cell: any) => {
                    const val = cell.getValue();
                    if (!val) return '-';
                    return <Badge color="info-subtle" className="text-info">{val}</Badge>;
                },
            },
            {
                header: 'Date début',
                accessorFn: (row: AjourneDmgRow) => row.stage?.date_debut,
                cell: (cell: any) => formatDateFr(cell.getValue()),
            },
            {
                header: 'Date fin',
                accessorFn: (row: AjourneDmgRow) => row.stage?.date_fin_prevue,
                cell: (cell: any) => formatDateFr(cell.getValue()),
            },
            {
                header: 'Montant',
                accessorFn: (row: AjourneDmgRow) => row.montant,
                cell: (cell: any) => <span className="fw-semibold">{formatMontant(cell.getValue())}</span>,
            },
            {
                header: 'Statut',
                cell: () => <span className="badge bg-danger-subtle text-danger">AJOURNÉ DMG</span>,
            },
            {
                header: 'Actions',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const stageId = row.stage_id || row.stage?.id;
                    const editHref = stageId
                        ? `/cip/pointages/edit-stagiaire/${stageId}?return_tab=ajourne_dmg&mois=${encodeURIComponent(filters?.periode_id || '')}`
                        : '#';

                    return (
                        <div className="d-flex gap-2">
                            <Button
                                color="primary"
                                size="sm"
                                href={editHref}
                                disabled={!stageId}
                                title="Traiter le stagiaire"
                            >
                                <i className="ri-edit-line me-1"></i>Traiter
                            </Button>
                        </div>
                    );
                },
            },
        ],
        [filters],
    );

    return (
        <React.Fragment>
            <Head title="Pointages Ajournés (DMG)" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages Ajournés par la DMG" pageTitle="Espace CIP" />

                    {/* ─── Flash Messages ─── */}
                    {flash?.success && (
                        <Alert color="success" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-check-double-line me-2 align-middle"></i>{flash.success}
                        </Alert>
                    )}
                    {flash?.error && (
                        <Alert color="danger" className="border-0 alert-dismissible fade show" role="alert">
                            <i className="ri-error-warning-line me-2 align-middle"></i>{flash.error}
                        </Alert>
                    )}

                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <div className="d-flex justify-content-between align-items-center">
                                        <h4 className="card-title mb-0">
                                            <i className="ri-arrow-go-back-fill text-danger me-2"></i>
                                            Pointages rejetés par la DMG
                                            <span className="badge bg-danger-subtle text-danger ms-2">{paiements?.total || 0}</span>
                                        </h4>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    {/* ─── Filtres ─── */}
                                    <Row className="mb-3 g-3">
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Période</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.periode_id || ''}
                                                onChange={(e) => handleFilterChange('periode_id', e.target.value)}
                                            >
                                                <option value="">Toutes les périodes</option>
                                                {periodes.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.code}</option>
                                                ))}
                                            </Input>
                                        </Col>
                                        <Col md={2}>
                                            <Label className="form-label text-uppercase fs-12 text-muted fw-semibold">Agence</Label>
                                            <Input
                                                type="select"
                                                value={selectedFilters.agence_id || ''}
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
                                                value={selectedFilters.entreprise_id || ''}
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
                                                value={selectedFilters.source_financement_id || ''}
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
                                                value={selectedFilters.type_stage_id || ''}
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
                                            <div className="input-group">
                                                <Input
                                                    type="text"
                                                    placeholder="Nom, prénoms, N° AEJ..."
                                                    value={selectedFilters.search || ''}
                                                    onChange={(e) => setSelectedFilters((prev) => ({ ...prev, search: e.target.value }))}
                                                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                                                />
                                                <Button color="primary" onClick={handleSearch}>
                                                    <i className="ri-search-line"></i>
                                                </Button>
                                            </div>
                                        </Col>
                                    </Row>

                                    {/* ─── Tableau ─── */}
                                    <div className="table-responsive table-card mb-3">
                                        <table className="align-middle table-nowrap table-hover mb-0 table">
                                            <thead className="table-light">
                                                <tr>
                                                    {columns.map((col, idx) => (
                                                        <th key={idx} style={col.size ? { width: col.size } : undefined}>
                                                            {col.header}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {paiements?.data?.length > 0 ? (
                                                    paiements.data.map((row, rowIdx) => (
                                                        <tr key={row.id}>
                                                            {columns.map((col, colIdx) => (
                                                                <td key={colIdx}>
                                                                    {col.cell
                                                                        ? col.cell({
                                                                              getValue: () => {
                                                                                  const accessorFn = (col as any).accessorFn;
                                                                                  return accessorFn ? accessorFn(row) : row;
                                                                              },
                                                                              row: { original: row, index: rowIdx },
                                                                          })
                                                                        : '-'}
                                                                </td>
                                                            ))}
                                                        </tr>
                                                    ))
                                                ) : (
                                                    <tr>
                                                        <td colSpan={columns.length} className="text-center text-muted py-4">
                                                            <i className="ri-inbox-line fs-24 d-block mb-2"></i>
                                                            Aucun pointage ajourné par la DMG trouvé.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* ─── Pagination ─── */}
                                    {paiements?.last_page > 1 && (
                                        <div className="d-flex justify-content-between align-items-center mt-3">
                                            <span className="text-muted fs-13">
                                                Affichage de {((paiements.current_page - 1) * paiements.per_page) + 1} à{' '}
                                                {Math.min(paiements.current_page * paiements.per_page, paiements.total)} sur{' '}
                                                {paiements.total} résultat(s)
                                            </span>
                                            <Pagination className="mb-0">
                                                {paiements.links.map((link, idx) => (
                                                    <PaginationItem key={idx} active={link.active} disabled={!link.url}>
                                                        <PaginationLink
                                                            onClick={() => {
                                                                if (link.url) {
                                                                    router.get(link.url, {}, { preserveState: true, replace: true });
                                                                }
                                                            }}
                                                        >
                                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                                        </PaginationLink>
                                                    </PaginationItem>
                                                ))}
                                            </Pagination>
                                        </div>
                                    )}
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default AjourneDmgIndex;
