import React from 'react';
import { Head, router } from '@inertiajs/react';
import { Badge, Button, Card, CardBody, CardHeader, Col, Container, Input, Row, Table } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

type Statistiques = {
    stages_total: number;
    pointages_attente: number;
    pointages_valides: number;
    pointages_ajournes_ca: number;
    pointages_ajournes_dmg: number;
    dossiers_transmis_ac: number;
    dossiers_vises_ac: number;
    dossiers_ajournes_dmg: number;
    droits_ouverts: number;
    montant_droits_ouverts: string;
    paiements_a_traiter: number;
    montant_paiements_a_traiter: string;
    audits_recents: number;
};

type Props = {
    moisActuel: string;
    periode?: { id: number; code?: string; nom?: string } | null;
    sourceFinancement?: { id: number; code?: string; nom?: string } | null;
    sourcesFinancement: Array<{ id: number; code?: string; nom?: string }>;
    filters: {
        mois?: string;
        source_financement_id?: string;
    };
    statistiques: Statistiques;
    repartitionSourcesFinancement: Array<{
        source?: { id?: number | null; code?: string | null; nom?: string | null } | null;
        total_droits: number;
        montant_total: string;
    }>;
    journalActivite: Array<{
        id: number;
        action: string;
        modele: string;
        modele_id: number;
        utilisateur: string;
        email?: string | null;
        date?: string | null;
    }>;
    alertes: Array<{
        niveau: 'info' | 'success' | 'warning' | 'danger';
        titre: string;
        message: string;
    }>;
};

const moneyFormatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('fr-FR');

const cards = [
    {
        key: 'stages_total',
        label: 'Stages suivis',
        icon: 'ri-team-line',
        color: 'primary',
        suffix: 'stage(s)',
    },
    {
        key: 'pointages_attente',
        label: 'Pointages en attente',
        icon: 'ri-time-line',
        color: 'info',
        suffix: 'à traiter',
    },
    {
        key: 'dossiers_transmis_ac',
        label: 'Dossiers transmis AC',
        icon: 'ri-file-list-3-line',
        color: 'warning',
        suffix: 'dossier(s)',
    },
    {
        key: 'paiements_a_traiter',
        label: 'Paiements à traiter',
        icon: 'ri-money-dollar-circle-line',
        color: 'success',
        suffix: 'paiement(s)',
    },
    {
        key: 'droits_ouverts',
        label: 'Droits ouverts',
        icon: 'ri-bank-card-line',
        color: 'secondary',
        suffix: 'droit(s)',
    },
    {
        key: 'audits_recents',
        label: 'Entrées audit',
        icon: 'ri-history-line',
        color: 'dark',
        suffix: 'évènement(s)',
    },
] as const;

const ReportingIndex = ({
    moisActuel,
    periode,
    sourceFinancement,
    sourcesFinancement,
    filters,
    statistiques,
    repartitionSourcesFinancement,
    journalActivite,
    alertes,
}: Props) => {
    const selectedMonth = filters?.mois || moisActuel || '';
    const selectedSource = filters?.source_financement_id || '';

    const runSearch = () => {
        router.get(
            '/reporting',
            {
                mois: selectedMonth,
                source_financement_id: selectedSource,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const downloadCsv = () => {
        const params = new URLSearchParams({
            mois: selectedMonth,
            source_financement_id: selectedSource,
        });

        window.location.href = `/reporting/export/kpi.csv?${params.toString()}`;
    };

    const formatCurrency = (value: string | number) => {
        const normalized = typeof value === 'string' ? Number(value) : value;
        return moneyFormatter.format(Number.isFinite(normalized) ? normalized : 0);
    };

    return (
        <React.Fragment>
            <Head title="Tableau de bord" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Tableau de bord" pageTitle="GestStage" />

                    <Card className="mb-3">
                        <CardHeader className="d-flex flex-wrap gap-2 align-items-center">
                            <div className="flex-grow-1">
                                <h4 className="card-title mb-1">Pilotage reporting</h4>
                                <p className="text-muted mb-0">
                                    Vue d’ensemble du mois {periode?.code || moisActuel} {sourceFinancement?.nom ? `— ${sourceFinancement.nom}` : ''}
                                </p>
                            </div>
                            <Button color="primary" outline onClick={downloadCsv}>
                                Export CSV
                            </Button>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3 align-items-end">
                                <Col md={3}>
                                    <label className="form-label">Mois</label>
                                    <Input
                                        type="month"
                                        value={selectedMonth}
                                        onChange={(event) => {
                                            router.get(
                                                '/reporting',
                                                {
                                                    mois: event.target.value,
                                                    source_financement_id: selectedSource,
                                                },
                                                { preserveState: true, preserveScroll: true },
                                            );
                                        }}
                                    />
                                </Col>
                                <Col md={5}>
                                    <label className="form-label">Source de financement</label>
                                    <Input
                                        type="select"
                                        value={selectedSource}
                                        onChange={(event) => {
                                            router.get(
                                                '/reporting',
                                                {
                                                    mois: selectedMonth,
                                                    source_financement_id: event.target.value,
                                                },
                                                { preserveState: true, preserveScroll: true },
                                            );
                                        }}
                                    >
                                        <option value="">Toutes les sources</option>
                                        {sourcesFinancement.map((source) => (
                                            <option key={source.id} value={source.id}>
                                                {source.nom} {source.code ? `(${source.code})` : ''}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={4} className="d-flex gap-2 justify-content-md-end">
                                    <Button color="light" onClick={runSearch}>
                                        Appliquer
                                    </Button>
                                    <Button color="secondary" outline onClick={() => router.get('/reporting')}>
                                        Réinitialiser
                                    </Button>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    <Row className="g-3">
                        {cards.map((card) => (
                            <Col xxl={2} lg={4} md={6} key={card.key}>
                                <Card className="card-animate h-100">
                                    <CardBody>
                                        <div className="d-flex align-items-start">
                                            <div className={`avatar-sm flex-shrink-0 me-3 bg-${card.color}-subtle rounded`}>
                                                <span className={`avatar-title text-${card.color} rounded fs-16`}>
                                                    <i className={card.icon}></i>
                                                </span>
                                            </div>
                                            <div className="flex-grow-1">
                                                <p className="text-muted mb-1">{card.label}</p>
                                                <h4 className="mb-1">
                                                    {numberFormatter.format(Number(statistiques[card.key as keyof Statistiques] ?? 0))}
                                                </h4>
                                                <span className="text-muted">{card.suffix}</span>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <Row className="mt-1 g-3">
                        <Col xxl={7}>
                            <Card className="h-100">
                                <CardHeader>
                                    <h4 className="card-title mb-0">Répartition par source de financement</h4>
                                </CardHeader>
                                <CardBody>
                                    <div className="table-responsive">
                                        <Table className="align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Source</th>
                                                    <th className="text-end">Droits</th>
                                                    <th className="text-end">Montant</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {repartitionSourcesFinancement.length > 0 ? (
                                                    repartitionSourcesFinancement.map((item, index) => (
                                                        <tr key={`${item.source?.id ?? index}`}>
                                                            <td>
                                                                <div className="fw-medium">
                                                                    {item.source?.nom || 'Source inconnue'}
                                                                </div>
                                                                <small className="text-muted">{item.source?.code || '—'}</small>
                                                            </td>
                                                            <td className="text-end">{numberFormatter.format(item.total_droits)}</td>
                                                            <td className="text-end">{formatCurrency(item.montant_total)}</td>
                                                        </tr>
                                                    ))
                                                ) : (
                                                    <tr>
                                                        <td colSpan={3} className="text-center text-muted py-4">
                                                            Aucune donnée disponible pour les filtres courants.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                        <Col xxl={5}>
                            <Card className="h-100">
                                <CardHeader>
                                    <h4 className="card-title mb-0">Alertes et points de vigilance</h4>
                                </CardHeader>
                                <CardBody>
                                    {alertes.length > 0 ? (
                                        <div className="d-flex flex-column gap-3">
                                            {alertes.map((alerte, index) => (
                                                <div key={`${alerte.titre}-${index}`} className={`border rounded p-3 border-${alerte.niveau}`}>
                                                    <div className="d-flex justify-content-between align-items-center">
                                                        <h6 className="mb-1">{alerte.titre}</h6>
                                                        <Badge color={alerte.niveau}>{alerte.niveau}</Badge>
                                                    </div>
                                                    <p className="text-muted mb-0">{alerte.message}</p>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-4 text-muted">
                                            Aucun point de vigilance sur la période filtrée.
                                        </div>
                                    )}
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Card className="mt-3">
                        <CardHeader>
                            <h4 className="card-title mb-0">Journal d’activité récent</h4>
                        </CardHeader>
                        <CardBody>
                            <div className="table-responsive">
                                <Table className="align-middle table-nowrap mb-0">
                                    <thead className="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Utilisateur</th>
                                            <th>Action</th>
                                            <th>Modèle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {journalActivite.length > 0 ? (
                                            journalActivite.map((item) => (
                                                <tr key={item.id}>
                                                    <td>{item.date || '—'}</td>
                                                    <td>
                                                        <div className="fw-medium">{item.utilisateur}</div>
                                                        {item.email ? <small className="text-muted">{item.email}</small> : null}
                                                    </td>
                                                    <td>
                                                        <Badge color="info-subtle" className="text-info">
                                                            {item.action}
                                                        </Badge>
                                                    </td>
                                                    <td>
                                                        {item.modele}
                                                        <span className="text-muted ms-1">#{item.modele_id}</span>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={4} className="text-center text-muted py-4">
                                                    Aucun log d’audit récent.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </Table>
                            </div>
                        </CardBody>
                    </Card>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default ReportingIndex;
