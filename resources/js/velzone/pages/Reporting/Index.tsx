import { Head, Link, router } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import {
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    Input,
    Nav,
    NavItem,
    NavLink as BootstrapNavLink,
    Row,
    TabContent,
    Table,
    TabPane,
} from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';
import ReportingChartsGrid from './components/ReportingChartsGrid';
import type { ReportingChartData } from './components/ReportingChart';

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

type BiIndicator = {
    key: string;
    label: string;
    valeur: number;
    definition: string;
    modele: string;
    table: string;
    statut: string;
    periode: string;
    source_financement: string;
    perimetre_agence: string;
    roles: string[];
    lien: string;
    export: boolean;
    disponible: boolean;
    unite: string;
};

type PaymentSummary = Record<
    string,
    { label: string; valeur: number | string; statuts: string[] }
>;

type Props = {
    moisActuel: string;
    periode?: {
        id: number;
        code: string;
        date_debut?: string | null;
        date_fin?: string | null;
    } | null;
    sourceFinancement?: { id: number; code: string; nom: string } | null;
    sourcesFinancement: Array<{ id: number; code: string; nom: string }>;
    sourceVariants: Array<{
        slug: string;
        code?: string | null;
        source_financement_id?: number | null;
        label: string;
    }>;
    filters: {
        mois: string;
        source_financement_id: string;
        jour_reference: string;
        type_stage: string;
    };
    scope: { national: boolean; agence_ids: number[]; label: string };
    statistiques: Statistiques;
    indicateursBi: BiIndicator[];
    recapPaiements: {
        metadata: {
            annee: number;
            mois: string;
            jour_reference: string;
            type_stage: string;
            qualification_validation: string;
        };
        resume: PaymentSummary;
        statuts: Array<{ statut: string; total: number; montant: string }>;
        motifs_rejet: Array<{ motif: string; total: number }>;
        detail_daicg: Array<{
            agence_id: number;
            agence: string;
            beneficiaires: number;
            droits: number;
            montant_du: string;
            montant_paye: string;
            montant_rejete: string;
            montant_en_cours: string;
        }>;
    };
    graphiques: ReportingChartData[];
    chartsError?: string | null;
    repartitionSourcesFinancement: Array<{
        source: { id: number; code: string; nom: string };
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
        compteur: number;
        lien: string;
        message: string;
    }>;
};

const moneyFormatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
});
const numberFormatter = new Intl.NumberFormat('fr-FR');

const operationalCards: Array<{
    key: keyof Statistiques;
    label: string;
    icon: string;
    color: string;
    money?: boolean;
}> = [
    {
        key: 'stages_total',
        label: 'Stages totaux',
        icon: 'ri-team-line',
        color: 'primary',
    },
    {
        key: 'pointages_attente',
        label: 'Pointages en attente',
        icon: 'ri-time-line',
        color: 'info',
    },
    {
        key: 'pointages_valides',
        label: 'Pointages validés',
        icon: 'ri-checkbox-circle-line',
        color: 'success',
    },
    {
        key: 'pointages_ajournes_ca',
        label: 'Pointages ajournés CA',
        icon: 'ri-arrow-go-back-line',
        color: 'warning',
    },
    {
        key: 'pointages_ajournes_dmg',
        label: 'Ajournés DMG',
        icon: 'ri-error-warning-line',
        color: 'danger',
    },
    {
        key: 'dossiers_transmis_ac',
        label: 'Dossiers transmis AC',
        icon: 'ri-send-plane-line',
        color: 'primary',
    },
    {
        key: 'dossiers_vises_ac',
        label: 'Dossiers visés AC',
        icon: 'ri-shield-check-line',
        color: 'success',
    },
    {
        key: 'dossiers_ajournes_dmg',
        label: 'Dossiers ajournés DMG',
        icon: 'ri-file-warning-line',
        color: 'danger',
    },
    {
        key: 'droits_ouverts',
        label: 'Droits ouverts',
        icon: 'ri-bank-card-line',
        color: 'secondary',
    },
    {
        key: 'montant_droits_ouverts',
        label: 'Montant droits ouverts',
        icon: 'ri-coins-line',
        color: 'secondary',
        money: true,
    },
    {
        key: 'paiements_a_traiter',
        label: 'Paiements à traiter',
        icon: 'ri-money-dollar-circle-line',
        color: 'warning',
    },
    {
        key: 'montant_paiements_a_traiter',
        label: 'Montant à traiter',
        icon: 'ri-funds-line',
        color: 'warning',
        money: true,
    },
];

const tabs = [
    ['operationnel', 'Opérationnel', 'ri-dashboard-line'],
    ['bi', 'Indicateurs BI', 'ri-bar-chart-box-line'],
    ['paiements', 'Récap paiements', 'ri-money-dollar-circle-line'],
    ['graphiques', 'Graphiques', 'ri-line-chart-line'],
    ['journal', 'Journal d’audit', 'ri-history-line'],
] as const;

const ReportingIndex = (props: Props) => {
    const {
        moisActuel,
        periode,
        sourceFinancement,
        sourcesFinancement,
        sourceVariants,
        filters,
        scope,
        statistiques,
        indicateursBi,
        recapPaiements,
        graphiques,
        chartsError,
        repartitionSourcesFinancement,
        journalActivite,
        alertes,
    } = props;
    const [activeTab, setActiveTab] =
        useState<(typeof tabs)[number][0]>('operationnel');
    const [loading, setLoading] = useState(false);
    const [form, setForm] = useState({
        mois: filters.mois || moisActuel,
        source_financement_id: filters.source_financement_id || '',
        jour_reference: filters.jour_reference || '',
        type_stage: filters.type_stage || 'tous',
    });

    useEffect(() => {
        setForm({
            mois: filters.mois || moisActuel,
            source_financement_id: filters.source_financement_id || '',
            jour_reference: filters.jour_reference || '',
            type_stage: filters.type_stage || 'tous',
        });
    }, [filters, moisActuel]);

    const applyFilters = () => {
        setLoading(true);
        router.get('/reporting', form, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => setLoading(false),
        });
    };

    const resetFilters = () => {
        setLoading(true);
        router.get(
            '/reporting',
            {},
            { replace: true, onFinish: () => setLoading(false) },
        );
    };

    const downloadCsv = () => {
        const params = new URLSearchParams(form);
        window.location.assign(
            `/reporting/export/kpi.csv?${params.toString()}`,
        );
    };

    const formatMoney = (value: number | string) =>
        moneyFormatter.format(Number(value) || 0);
    const formatValue = (value: number | string, money = false) =>
        money ? formatMoney(value) : numberFormatter.format(Number(value) || 0);

    return (
        <React.Fragment>
            <Head title="Reporting" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Reporting" pageTitle="GestStage" />

                    <Card className="mb-3 border-0 shadow-sm">
                        <CardHeader className="d-flex align-items-center flex-wrap gap-3 bg-transparent">
                            <div className="flex-grow-1">
                                <div className="d-flex align-items-center mb-1 flex-wrap gap-2">
                                    <h4 className="card-title mb-0">
                                        Pilotage opérationnel et décisionnel
                                    </h4>
                                    <Badge
                                        color={
                                            scope.national ? 'primary' : 'info'
                                        }
                                    >
                                        {scope.label}
                                    </Badge>
                                </div>
                                <p className="mb-0 text-muted">
                                    {periode?.code || moisActuel} ·{' '}
                                    {sourceFinancement?.nom ||
                                        'Toutes les sources de financement'}
                                </p>
                            </div>
                            <Button
                                color="primary"
                                outline
                                onClick={downloadCsv}
                                disabled={loading}
                            >
                                <i className="ri-download-2-line me-1" /> Export
                                KPI CSV
                            </Button>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-3 align-items-end">
                                <Col xl={2} md={4}>
                                    <label
                                        htmlFor="reporting-month"
                                        className="form-label"
                                    >
                                        Mois
                                    </label>
                                    <Input
                                        id="reporting-month"
                                        type="month"
                                        value={form.mois}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                mois: event.target.value,
                                            })
                                        }
                                    />
                                </Col>
                                <Col xl={3} md={8}>
                                    <label
                                        htmlFor="reporting-source"
                                        className="form-label"
                                    >
                                        Source de financement
                                    </label>
                                    <Input
                                        id="reporting-source"
                                        type="select"
                                        value={form.source_financement_id}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                source_financement_id:
                                                    event.target.value,
                                            })
                                        }
                                    >
                                        <option value="">
                                            Toutes les sources
                                        </option>
                                        {sourcesFinancement.map((source) => (
                                            <option
                                                key={source.id}
                                                value={source.id}
                                            >
                                                {source.nom} ({source.code})
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col xl={2} md={4}>
                                    <label
                                        htmlFor="reporting-day"
                                        className="form-label"
                                    >
                                        Jour de référence
                                    </label>
                                    <Input
                                        id="reporting-day"
                                        type="date"
                                        value={form.jour_reference}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                jour_reference:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                </Col>
                                <Col xl={2} md={4}>
                                    <label
                                        htmlFor="reporting-type"
                                        className="form-label"
                                    >
                                        Qualification / validation
                                    </label>
                                    <Input
                                        id="reporting-type"
                                        type="select"
                                        value={form.type_stage}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                type_stage: event.target.value,
                                            })
                                        }
                                    >
                                        <option value="tous">
                                            Tous les types
                                        </option>
                                        <option value="qualification">
                                            Qualification
                                        </option>
                                        <option value="validation">
                                            Validation / stage école
                                        </option>
                                    </Input>
                                </Col>
                                <Col xl={3} md={4} className="d-flex gap-2">
                                    <Button
                                        color="primary"
                                        onClick={applyFilters}
                                        disabled={loading}
                                        className="flex-grow-1"
                                    >
                                        {loading ? 'Calcul…' : 'Appliquer'}
                                    </Button>
                                    <Button
                                        color="light"
                                        onClick={resetFilters}
                                        disabled={loading}
                                    >
                                        Réinitialiser
                                    </Button>
                                </Col>
                            </Row>
                            <div className="d-flex mt-3 flex-wrap gap-2">
                                {sourceVariants.map((variant) => (
                                    <span
                                        key={variant.slug}
                                        className="badge bg-light text-body border"
                                    >
                                        {variant.slug} →{' '}
                                        {variant.code || 'global'}
                                    </span>
                                ))}
                            </div>
                        </CardBody>
                    </Card>

                    <Nav
                        tabs
                        className="nav-tabs-custom nav-success mb-3 flex-nowrap overflow-auto"
                        role="tablist"
                    >
                        {tabs.map(([key, label, icon]) => (
                            <NavItem key={key}>
                                <BootstrapNavLink
                                    href="#"
                                    active={activeTab === key}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        setActiveTab(key);
                                    }}
                                >
                                    <i className={`${icon} me-1`} /> {label}
                                </BootstrapNavLink>
                            </NavItem>
                        ))}
                    </Nav>

                    <TabContent activeTab={activeTab}>
                        <TabPane tabId="operationnel">
                            <Row className="g-3">
                                {operationalCards.map((card) => (
                                    <Col xxl={3} lg={4} md={6} key={card.key}>
                                        <Card className="card-animate h-100 border-0 shadow-sm">
                                            <CardBody>
                                                <div className="d-flex align-items-start">
                                                    <div
                                                        className={`avatar-sm me-3 flex-shrink-0 bg-${card.color}-subtle rounded`}
                                                    >
                                                        <span
                                                            className={`avatar-title text-${card.color} fs-18 rounded`}
                                                        >
                                                            <i
                                                                className={
                                                                    card.icon
                                                                }
                                                            />
                                                        </span>
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="text-truncate mb-1 text-muted">
                                                            {card.label}
                                                        </p>
                                                        <h4 className="mb-0">
                                                            {formatValue(
                                                                statistiques[
                                                                    card.key
                                                                ],
                                                                card.money,
                                                            )}
                                                        </h4>
                                                    </div>
                                                </div>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>

                            <Row className="g-3 mt-1">
                                <Col xl={7}>
                                    <Card className="h-100">
                                        <CardHeader>
                                            <h4 className="card-title mb-0">
                                                Répartition des droits par
                                                financement
                                            </h4>
                                        </CardHeader>
                                        <CardBody className="p-0">
                                            <div className="table-responsive">
                                                <Table className="mb-0 align-middle">
                                                    <thead className="table-light">
                                                        <tr>
                                                            <th>Source</th>
                                                            <th className="text-end">
                                                                Droits
                                                            </th>
                                                            <th className="text-end">
                                                                Montant
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {repartitionSourcesFinancement.length ? (
                                                            repartitionSourcesFinancement.map(
                                                                (item) => (
                                                                    <tr
                                                                        key={
                                                                            item
                                                                                .source
                                                                                .id
                                                                        }
                                                                    >
                                                                        <td>
                                                                            <div className="fw-medium">
                                                                                {
                                                                                    item
                                                                                        .source
                                                                                        .nom
                                                                                }
                                                                            </div>
                                                                            <small className="text-muted">
                                                                                {
                                                                                    item
                                                                                        .source
                                                                                        .code
                                                                                }
                                                                            </small>
                                                                        </td>
                                                                        <td className="text-end">
                                                                            {numberFormatter.format(
                                                                                item.total_droits,
                                                                            )}
                                                                        </td>
                                                                        <td className="text-end">
                                                                            {formatMoney(
                                                                                item.montant_total,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )
                                                        ) : (
                                                            <tr>
                                                                <td
                                                                    colSpan={3}
                                                                    className="py-4 text-center text-muted"
                                                                >
                                                                    Aucun droit
                                                                    pour les
                                                                    filtres
                                                                    courants.
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col xl={5}>
                                    <Card className="h-100">
                                        <CardHeader>
                                            <h4 className="card-title mb-0">
                                                Alertes à résoudre
                                            </h4>
                                        </CardHeader>
                                        <CardBody>
                                            {alertes.length ? (
                                                <div className="d-flex flex-column gap-3">
                                                    {alertes.map((alert) => (
                                                        <div
                                                            key={alert.titre}
                                                            className={`border border-${alert.niveau} rounded p-3`}
                                                        >
                                                            <div className="d-flex justify-content-between align-items-start gap-2">
                                                                <div>
                                                                    <h6 className="mb-1">
                                                                        {
                                                                            alert.titre
                                                                        }
                                                                    </h6>
                                                                    <p className="mb-2 text-muted">
                                                                        {
                                                                            alert.message
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <Badge
                                                                    color={
                                                                        alert.niveau
                                                                    }
                                                                    pill
                                                                >
                                                                    {numberFormatter.format(
                                                                        alert.compteur,
                                                                    )}
                                                                </Badge>
                                                            </div>
                                                            <Link
                                                                href={
                                                                    alert.lien
                                                                }
                                                                className="small fw-medium"
                                                            >
                                                                Ouvrir l’écran
                                                                de résolution{' '}
                                                                <i className="ri-arrow-right-line" />
                                                            </Link>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="py-5 text-center text-muted">
                                                    Aucune alerte pour les
                                                    filtres courants.
                                                </div>
                                            )}
                                        </CardBody>
                                    </Card>
                                </Col>
                            </Row>
                        </TabPane>

                        <TabPane tabId="bi">
                            <Row className="g-3 mb-3">
                                {indicateursBi.map((indicator) => (
                                    <Col
                                        xxl={3}
                                        lg={4}
                                        md={6}
                                        key={indicator.key}
                                    >
                                        <Card className="h-100 border-0 shadow-sm">
                                            <CardBody>
                                                <div className="d-flex justify-content-between gap-2">
                                                    <div>
                                                        <p className="mb-1 text-muted">
                                                            {indicator.label}
                                                        </p>
                                                        <h4 className="mb-2">
                                                            {numberFormatter.format(
                                                                indicator.valeur,
                                                            )}
                                                        </h4>
                                                    </div>
                                                    <Badge
                                                        color={
                                                            indicator.disponible
                                                                ? 'success'
                                                                : 'secondary'
                                                        }
                                                        className="align-self-start"
                                                    >
                                                        {indicator.disponible
                                                            ? 'Vérifié'
                                                            : 'Indisponible'}
                                                    </Badge>
                                                </div>
                                                <p className="small mb-2 text-muted">
                                                    {indicator.definition}
                                                </p>
                                                <Link
                                                    href={indicator.lien}
                                                    className="small fw-medium"
                                                >
                                                    Voir le détail{' '}
                                                    <i className="ri-external-link-line" />
                                                </Link>
                                            </CardBody>
                                        </Card>
                                    </Col>
                                ))}
                            </Row>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">
                                        Référentiel vérifiable des indicateurs
                                    </h4>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <div className="table-responsive">
                                        <Table className="mb-0 align-middle">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>
                                                        Indicateur / définition
                                                    </th>
                                                    <th>Source Next</th>
                                                    <th>Statut</th>
                                                    <th>
                                                        Période / financement
                                                    </th>
                                                    <th>Rôles</th>
                                                    <th>Export</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {indicateursBi.map(
                                                    (indicator) => (
                                                        <tr
                                                            key={`definition-${indicator.key}`}
                                                        >
                                                            <td
                                                                style={{
                                                                    minWidth: 280,
                                                                }}
                                                            >
                                                                <div className="fw-semibold">
                                                                    {
                                                                        indicator.label
                                                                    }
                                                                </div>
                                                                <small className="text-muted">
                                                                    {
                                                                        indicator.definition
                                                                    }
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <code>
                                                                    {
                                                                        indicator.modele
                                                                    }
                                                                </code>
                                                                <div className="small text-muted">
                                                                    {
                                                                        indicator.table
                                                                    }
                                                                </div>
                                                            </td>
                                                            <td
                                                                style={{
                                                                    minWidth: 220,
                                                                }}
                                                            >
                                                                <small>
                                                                    {
                                                                        indicator.statut
                                                                    }
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <div>
                                                                    {
                                                                        indicator.periode
                                                                    }
                                                                </div>
                                                                <small className="text-muted">
                                                                    {
                                                                        indicator.source_financement
                                                                    }
                                                                </small>
                                                            </td>
                                                            <td
                                                                style={{
                                                                    minWidth: 230,
                                                                }}
                                                            >
                                                                {indicator.roles.map(
                                                                    (role) => (
                                                                        <Badge
                                                                            key={
                                                                                role
                                                                            }
                                                                            color="light"
                                                                            className="text-body me-1 mb-1 border"
                                                                        >
                                                                            {
                                                                                role
                                                                            }
                                                                        </Badge>
                                                                    ),
                                                                )}
                                                            </td>
                                                            <td>
                                                                <Badge
                                                                    color={
                                                                        indicator.export
                                                                            ? 'success'
                                                                            : 'secondary'
                                                                    }
                                                                >
                                                                    {indicator.export
                                                                        ? 'Oui'
                                                                        : 'Non'}
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </TabPane>

                        <TabPane tabId="paiements">
                            <Card className="bg-primary-subtle mb-3 border-0">
                                <CardBody className="d-flex justify-content-between flex-wrap gap-4">
                                    <div>
                                        <small className="d-block text-muted">
                                            Année / mois
                                        </small>
                                        <strong>
                                            {recapPaiements.metadata.annee} ·{' '}
                                            {recapPaiements.metadata.mois}
                                        </strong>
                                    </div>
                                    <div>
                                        <small className="d-block text-muted">
                                            Jour de référence
                                        </small>
                                        <strong>
                                            {
                                                recapPaiements.metadata
                                                    .jour_reference
                                            }
                                        </strong>
                                    </div>
                                    <div>
                                        <small className="d-block text-muted">
                                            Qualification / validation
                                        </small>
                                        <strong>
                                            {
                                                recapPaiements.metadata
                                                    .qualification_validation
                                            }
                                        </strong>
                                    </div>
                                    <div>
                                        <small className="d-block text-muted">
                                            Financement
                                        </small>
                                        <strong>
                                            {sourceFinancement?.nom ||
                                                'Toutes sources'}
                                        </strong>
                                    </div>
                                </CardBody>
                            </Card>
                            <Row className="g-3 mb-3">
                                {Object.entries(recapPaiements.resume).map(
                                    ([key, item]) => (
                                        <Col xl={4} md={6} key={key}>
                                            <Card className="h-100">
                                                <CardBody>
                                                    <p className="mb-1 text-muted">
                                                        {item.label}
                                                    </p>
                                                    <h4 className="mb-1">
                                                        {formatValue(
                                                            item.valeur,
                                                            key.startsWith(
                                                                'montant_',
                                                            ),
                                                        )}
                                                    </h4>
                                                    <small className="text-muted">
                                                        {item.statuts.join(
                                                            ', ',
                                                        )}
                                                    </small>
                                                </CardBody>
                                            </Card>
                                        </Col>
                                    ),
                                )}
                            </Row>
                            <Row className="g-3 mb-3">
                                <Col xl={7}>
                                    <Card className="h-100">
                                        <CardHeader>
                                            <h4 className="card-title mb-0">
                                                Paiements par statut
                                            </h4>
                                        </CardHeader>
                                        <CardBody className="p-0">
                                            <div className="table-responsive">
                                                <Table className="mb-0 align-middle">
                                                    <thead className="table-light">
                                                        <tr>
                                                            <th>Statut</th>
                                                            <th className="text-end">
                                                                Paiements
                                                            </th>
                                                            <th className="text-end">
                                                                Montant
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {recapPaiements.statuts
                                                            .length ? (
                                                            recapPaiements.statuts.map(
                                                                (row) => (
                                                                    <tr
                                                                        key={
                                                                            row.statut
                                                                        }
                                                                    >
                                                                        <td>
                                                                            <Badge
                                                                                color="light"
                                                                                className="text-body border"
                                                                            >
                                                                                {
                                                                                    row.statut
                                                                                }
                                                                            </Badge>
                                                                        </td>
                                                                        <td className="text-end">
                                                                            {numberFormatter.format(
                                                                                row.total,
                                                                            )}
                                                                        </td>
                                                                        <td className="text-end">
                                                                            {formatMoney(
                                                                                row.montant,
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                ),
                                                            )
                                                        ) : (
                                                            <tr>
                                                                <td
                                                                    colSpan={3}
                                                                    className="py-4 text-center text-muted"
                                                                >
                                                                    Aucun
                                                                    paiement sur
                                                                    cette
                                                                    période.
                                                                </td>
                                                            </tr>
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>
                                        </CardBody>
                                    </Card>
                                </Col>
                                <Col xl={5}>
                                    <Card className="h-100">
                                        <CardHeader>
                                            <h4 className="card-title mb-0">
                                                Principaux motifs de rejet /
                                                différé
                                            </h4>
                                        </CardHeader>
                                        <CardBody>
                                            {recapPaiements.motifs_rejet
                                                .length ? (
                                                recapPaiements.motifs_rejet.map(
                                                    (row) => (
                                                        <div
                                                            key={row.motif}
                                                            className="d-flex justify-content-between border-bottom gap-3 py-2"
                                                        >
                                                            <span>
                                                                {row.motif}
                                                            </span>
                                                            <Badge
                                                                color="danger"
                                                                pill
                                                            >
                                                                {row.total}
                                                            </Badge>
                                                        </div>
                                                    ),
                                                )
                                            ) : (
                                                <div className="py-4 text-center text-muted">
                                                    Aucun motif enregistré.
                                                </div>
                                            )}
                                        </CardBody>
                                    </Card>
                                </Col>
                            </Row>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">
                                        Détail DAICG par agence
                                    </h4>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <div className="table-responsive">
                                        <Table className="mb-0 align-middle">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Agence</th>
                                                    <th className="text-end">
                                                        Bénéficiaires
                                                    </th>
                                                    <th className="text-end">
                                                        Droits
                                                    </th>
                                                    <th className="text-end">
                                                        Montant dû
                                                    </th>
                                                    <th className="text-end">
                                                        Payé
                                                    </th>
                                                    <th className="text-end">
                                                        Rejeté
                                                    </th>
                                                    <th className="text-end">
                                                        En cours
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {recapPaiements.detail_daicg
                                                    .length ? (
                                                    recapPaiements.detail_daicg.map(
                                                        (row) => (
                                                            <tr
                                                                key={
                                                                    row.agence_id
                                                                }
                                                            >
                                                                <td className="fw-medium">
                                                                    {row.agence}
                                                                </td>
                                                                <td className="text-end">
                                                                    {numberFormatter.format(
                                                                        row.beneficiaires,
                                                                    )}
                                                                </td>
                                                                <td className="text-end">
                                                                    {numberFormatter.format(
                                                                        row.droits,
                                                                    )}
                                                                </td>
                                                                <td className="text-end">
                                                                    {formatMoney(
                                                                        row.montant_du,
                                                                    )}
                                                                </td>
                                                                <td className="text-success text-end">
                                                                    {formatMoney(
                                                                        row.montant_paye,
                                                                    )}
                                                                </td>
                                                                <td className="text-danger text-end">
                                                                    {formatMoney(
                                                                        row.montant_rejete,
                                                                    )}
                                                                </td>
                                                                <td className="text-warning text-end">
                                                                    {formatMoney(
                                                                        row.montant_en_cours,
                                                                    )}
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )
                                                ) : (
                                                    <tr>
                                                        <td
                                                            colSpan={7}
                                                            className="py-4 text-center text-muted"
                                                        >
                                                            Aucun détail DAICG
                                                            pour les filtres
                                                            courants.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </TabPane>

                        <TabPane tabId="graphiques">
                            <ReportingChartsGrid
                                charts={graphiques}
                                loading={loading}
                                error={chartsError}
                            />
                        </TabPane>

                        <TabPane tabId="journal">
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">
                                        Événements d’audit récents
                                    </h4>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <div className="table-responsive">
                                        <Table className="mb-0 align-middle">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Utilisateur</th>
                                                    <th>Action</th>
                                                    <th>Objet</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {journalActivite.length ? (
                                                    journalActivite.map(
                                                        (item) => (
                                                            <tr key={item.id}>
                                                                <td>
                                                                    {item.date ||
                                                                        '—'}
                                                                </td>
                                                                <td>
                                                                    <div className="fw-medium">
                                                                        {
                                                                            item.utilisateur
                                                                        }
                                                                    </div>
                                                                    {item.email ? (
                                                                        <small className="text-muted">
                                                                            {
                                                                                item.email
                                                                            }
                                                                        </small>
                                                                    ) : null}
                                                                </td>
                                                                <td>
                                                                    <Badge
                                                                        color="info-subtle"
                                                                        className="text-info"
                                                                    >
                                                                        {
                                                                            item.action
                                                                        }
                                                                    </Badge>
                                                                </td>
                                                                <td>
                                                                    {
                                                                        item.modele
                                                                    }{' '}
                                                                    <span className="text-muted">
                                                                        #
                                                                        {
                                                                            item.modele_id
                                                                        }
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )
                                                ) : (
                                                    <tr>
                                                        <td
                                                            colSpan={4}
                                                            className="py-5 text-center text-muted"
                                                        >
                                                            Aucun événement
                                                            d’audit pour la
                                                            période filtrée.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </TabPane>
                    </TabContent>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default ReportingIndex;
