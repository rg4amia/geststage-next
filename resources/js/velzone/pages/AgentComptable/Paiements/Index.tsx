import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import classnames from 'classnames';
import React, { useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    Col,
    Container,
    FormFeedback,
    Input,
    Label,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Nav,
    NavItem,
    NavLink,
    Row,
    Spinner,
    TabContent,
    TabPane,
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import './ac-paiements.scss';

type Ordre = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    nombre_dossiers: number;
    nombre_paiements: number;
    agences: string;
};

type Bordereau = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    date_transmission?: string;
    date_traitement?: string;
    motif?: string;
    agences?: string;
    nombre_ordres: number;
    nombre_dossiers: number;
    nombre_paiements: number;
    source_financement?: { code?: string; libelle?: string };
    ordres: Ordre[];
};

type Props = {
    bordereauxAttente?: Bordereau[];
    bordereauxRejetes?: Bordereau[];
    bordereauxVises?: Bordereau[];
    moisActuel: string;
    periodesDisponibles?: Array<{ code: string; count: number }>;
};

type Stagiaire = {
    paiement_id: number;
    statut_paiement: string;
    montant: string | number;
    beneficiaire_id?: number;
    numero_aej?: string;
    nom?: string;
    prenoms?: string;
    date_naissance?: string;
    numero_tresor_money?: string;
    entreprise?: string;
    type_stage?: string;
    date_debut?: string;
    date_fin?: string;
};

type DossierDetail = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    agence?: string;
    source_financement?: string;
    stagiaires: Stagiaire[];
};

type OrdreDetail = {
    id: number;
    numero: string;
    statut: string;
    montant_total: string | number;
    dossiers: DossierDetail[];
};

type ActionOp = 'retirer' | 'differer' | 'rejeter';

type FlashProps = {
    flash?: { success?: string; error?: string };
    errors?: { motif?: string; bordereau?: string; ordre?: string };
};

const formatMontant = (valeur: string | number) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(valeur || 0));

const libelleMois = (code: string) => {
    const [annee, mois] = code.split('-').map(Number);
    if (!annee || !mois) return code;

    return new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' })
        .format(new Date(annee, mois - 1, 1));
};

const actionsOp: Record<ActionOp, { titre: string; description: string; bouton: string; color: 'warning' | 'danger' }> = {
    retirer: { titre: 'Retirer cette OP du bordereau ?', description: 'L’OP restera disponible pour être intégrée à un nouveau bordereau par la DMG.', bouton: 'Retirer l’OP', color: 'warning' },
    differer: { titre: 'Différer toute cette OP ?', description: 'Tous ses dossiers et paiements seront retournés à la DMG pour correction.', bouton: 'Différer l’OP', color: 'warning' },
    rejeter: { titre: 'Rejeter toute cette OP ?', description: 'Tous les paiements de l’OP seront marqués comme rejetés par l’Agent Comptable.', bouton: 'Rejeter l’OP', color: 'danger' },
};

function StatCard({ icon, label, value, hint, tone }: { icon: string; label: string; value: string | number; hint: string; tone: 'warning' | 'success' | 'danger' }) {
    return (
        <Card className={`ac-stat-card ac-stat-card--${tone} h-100 mb-0`}>
            <CardBody className="d-flex align-items-center gap-3">
                <span className="ac-stat-card__icon" aria-hidden="true"><i className={icon} /></span>
                <div className="min-w-0">
                    <div className="text-uppercase text-muted fw-semibold fs-12">{label}</div>
                    <div className="fs-4 fw-bold text-dark lh-sm mt-1">{value}</div>
                    <div className="text-muted fs-12 mt-1">{hint}</div>
                </div>
            </CardBody>
        </Card>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className="ac-empty-state">
            <span className="ac-empty-state__icon"><i className="ri-inbox-archive-line" /></span>
            <h5 className="mb-1">Aucun résultat</h5>
            <p className="text-muted mb-0">{text}</p>
        </div>
    );
}

export default function AcPaiementsIndex({
    bordereauxAttente = [],
    bordereauxRejetes = [],
    bordereauxVises = [],
    moisActuel,
    periodesDisponibles = [],
}: Props) {
    const { flash, errors } = usePage<FlashProps>().props;
    const [activeTab, setActiveTab] = useState<'attente' | 'rejetes' | 'vises'>('attente');
    const [recherche, setRecherche] = useState('');
    const [details, setDetails] = useState<Bordereau | null>(null);
    const [ordreDetail, setOrdreDetail] = useState<OrdreDetail | null>(null);
    const [loadingOrdre, setLoadingOrdre] = useState(false);
    const [detailError, setDetailError] = useState('');
    const [ordreAValider, setOrdreAValider] = useState<Ordre | null>(null);
    const [actionOp, setActionOp] = useState<{ ordre: Ordre; type: ActionOp } | null>(null);
    const [motifOp, setMotifOp] = useState('');
    const [processing, setProcessing] = useState(false);

    const montantAttente = useMemo(
        () => bordereauxAttente.reduce((total, bordereau) => total + Number(bordereau.montant_total || 0), 0),
        [bordereauxAttente],
    );
    const nombreOrdres = useMemo(
        () => bordereauxAttente.reduce((total, bordereau) => total + bordereau.nombre_ordres, 0),
        [bordereauxAttente],
    );

    const filtrer = (liste: Bordereau[]) => {
        const terme = recherche.trim().toLocaleLowerCase('fr');
        if (!terme) return liste;

        return liste.filter((bordereau) => [
            bordereau.numero,
            bordereau.agences,
            bordereau.source_financement?.libelle,
            ...bordereau.ordres.map((ordre) => ordre.numero),
        ].filter(Boolean).some((valeur) => String(valeur).toLocaleLowerCase('fr').includes(terme)));
    };

    const attenteFiltree = useMemo(() => filtrer(bordereauxAttente), [bordereauxAttente, recherche]);
    const rejetesFiltres = useMemo(() => filtrer(bordereauxRejetes), [bordereauxRejetes, recherche]);
    const visesFiltres = useMemo(() => filtrer(bordereauxVises), [bordereauxVises, recherche]);

    const periodes = useMemo(() => {
        const options = [...periodesDisponibles];
        if (!options.some((periode) => periode.code === moisActuel)) options.unshift({ code: moisActuel, count: bordereauxAttente.length });
        return options;
    }, [periodesDisponibles, moisActuel, bordereauxAttente.length]);

    const changerMois = (mois: string) => {
        router.get('/agent-comptable/paiements', { mois }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const chargerOrdre = async (ordreId: number) => {
        setLoadingOrdre(true);
        setDetailError('');
        try {
            const response = await axios.get<OrdreDetail>(`/agent-comptable/paiements/ordres/${ordreId}/details`);
            setOrdreDetail(response.data);
        } catch {
            setOrdreDetail(null);
            setDetailError('Impossible de charger les dossiers et les stagiaires de cette OP.');
        } finally {
            setLoadingOrdre(false);
        }
    };

    const ouvrirDetails = (bordereau: Bordereau) => {
        setDetails(bordereau);
        const ordreInitial = bordereau.ordres.find((ordre) => ordre.statut === 'EN_BORDEREAU') || bordereau.ordres[0];
        if (ordreInitial) chargerOrdre(ordreInitial.id);
        else setOrdreDetail(null);
    };

    const fermerDetails = () => {
        setDetails(null);
        setOrdreDetail(null);
        setDetailError('');
    };

    const confirmerValidationOp = () => {
        if (!ordreAValider) return;
        setProcessing(true);
        router.post(`/agent-comptable/paiements/ordres/${ordreAValider.id}/valider`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setOrdreAValider(null);
                fermerDetails();
            },
            onFinish: () => setProcessing(false),
        });
    };

    const confirmerActionOp = () => {
        if (!actionOp || motifOp.trim().length < 5) return;
        setProcessing(true);
        router.post(`/agent-comptable/paiements/ordres/${actionOp.ordre.id}/${actionOp.type}`, { motif: motifOp }, {
            preserveScroll: true,
            onSuccess: () => {
                setActionOp(null);
                setMotifOp('');
                fermerDetails();
            },
            onFinish: () => setProcessing(false),
        });
    };

    const ouvrirActionOp = (ordre: Ordre, type: ActionOp) => {
        setActionOp({ ordre, type });
        setMotifOp('');
    };

    const ordreSelectionne = details?.ordres.find((ordre) => ordre.id === ordreDetail?.id);

    return (
        <>
            <Head title="Traitement des bordereaux - Agent Comptable" />
            <div className="page-content ac-payments-page">
                <Container fluid>
                    <BreadCrumb title="Traitement des bordereaux" pageTitle="Agent comptable" />

                    <Card className="ac-hero-card border-0">
                        <CardBody className="position-relative p-4">
                            <Row className="align-items-center g-3">
                                <Col lg={8}>
                                    <div className="d-flex align-items-center gap-3">
                                        <span className="ac-hero-card__icon"><i className="ri-file-shield-2-line" /></span>
                                        <div>
                                            <Badge color="light" className="text-success mb-2">Circuit Agent Comptable</Badge>
                                            <h3 className="text-white mb-1">Bordereaux à viser</h3>
                                            <p className="text-white-50 mb-0">Contrôlez les ordres de paiement, puis validez ou retournez le bordereau à la DMG.</p>
                                        </div>
                                    </div>
                                </Col>
                                <Col lg={4} className="text-lg-end">
                                    <div className="ac-hero-card__period">
                                        <span className="text-white-50 fs-12 text-uppercase">Période consultée</span>
                                        <strong className="d-block text-white text-capitalize">{libelleMois(moisActuel)}</strong>
                                    </div>
                                </Col>
                            </Row>
                        </CardBody>
                    </Card>

                    {(flash?.success || flash?.error || errors?.bordereau || errors?.ordre) && (
                        <Alert color={flash?.success ? 'success' : 'danger'} className="d-flex align-items-center gap-2">
                            <i className={flash?.success ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'} />
                            {flash?.success || flash?.error || errors?.bordereau || errors?.ordre}
                        </Alert>
                    )}

                    <Row className="g-3 mb-4">
                        <Col xl={4} md={6}><StatCard icon="ri-time-line" label="À traiter" value={bordereauxAttente.length} hint={`${nombreOrdres} ordre${nombreOrdres > 1 ? 's' : ''} de paiement`} tone="warning" /></Col>
                        <Col xl={4} md={6}><StatCard icon="ri-money-dollar-circle-line" label="Montant en attente" value={`${formatMontant(montantAttente)} FCFA`} hint="Montant cumulé à contrôler" tone="success" /></Col>
                        <Col xl={4} md={6}><StatCard icon="ri-arrow-go-back-line" label="Retours AC" value={bordereauxRejetes.length} hint="Différés et rejets définitifs" tone="danger" /></Col>
                    </Row>

                    <Card className="ac-workspace-card border-0">
                        <CardBody className="p-0">
                            <div className="ac-toolbar p-3 p-lg-4">
                                <Row className="align-items-end g-3">
                                    <Col lg={4} md={6}>
                                        <Label className="form-label text-uppercase fs-12 fw-semibold">Période</Label>
                                        <div className="position-relative">
                                            <i className="ri-calendar-event-line ac-input-icon" />
                                            <Input className="ac-control ps-5" type="select" value={moisActuel} onChange={(event) => changerMois(event.target.value)}>
                                                {periodes.map((periode) => <option key={periode.code} value={periode.code}>{libelleMois(periode.code)} — {periode.count} en attente</option>)}
                                            </Input>
                                        </div>
                                    </Col>
                                    <Col lg={5} md={6}>
                                        <Label className="form-label text-uppercase fs-12 fw-semibold">Recherche rapide</Label>
                                        <div className="position-relative">
                                            <i className="ri-search-line ac-input-icon" />
                                            <Input className="ac-control ps-5" value={recherche} onChange={(event) => setRecherche(event.target.value)} placeholder="Bordereau, OP, agence ou financement…" />
                                            {recherche && <Button color="link" className="ac-clear-search" onClick={() => setRecherche('')} aria-label="Effacer la recherche"><i className="ri-close-line" /></Button>}
                                        </div>
                                    </Col>
                                    <Col lg={3} className="text-lg-end">
                                        <div className="text-muted fs-13 pb-2"><i className="ri-information-line me-1" />{recherche ? `${attenteFiltree.length} résultat(s) en attente` : 'Cliquez sur une ligne pour voir ses OP'}</div>
                                    </Col>
                                </Row>
                            </div>

                            <Nav tabs className="ac-tabs px-3 px-lg-4" role="tablist">
                                <NavItem>
                                    <NavLink role="tab" aria-selected={activeTab === 'attente'} className={classnames({ active: activeTab === 'attente' })} onClick={() => setActiveTab('attente')}>
                                        <i className="ri-time-line me-2" />En attente <Badge pill color="warning" className="ms-2">{bordereauxAttente.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink role="tab" aria-selected={activeTab === 'vises'} className={classnames({ active: activeTab === 'vises' })} onClick={() => setActiveTab('vises')}>
                                        <i className="ri-checkbox-circle-line me-2" />Visés AC <Badge pill color="success" className="ms-2">{bordereauxVises.length}</Badge>
                                    </NavLink>
                                </NavItem>
                                <NavItem>
                                    <NavLink role="tab" aria-selected={activeTab === 'rejetes'} className={classnames({ active: activeTab === 'rejetes' })} onClick={() => setActiveTab('rejetes')}>
                                        <i className="ri-arrow-go-back-line me-2" />Retours DMG <Badge pill color="danger" className="ms-2">{bordereauxRejetes.length}</Badge>
                                    </NavLink>
                                </NavItem>
                            </Nav>

                            <TabContent activeTab={activeTab} className="p-3 p-lg-4 pt-lg-3">
                                <TabPane tabId="attente">
                                    {attenteFiltree.length ? (
                                        <div className="table-responsive ac-table-wrap">
                                            <Table hover className="align-middle mb-0 ac-table">
                                                <thead><tr><th>Bordereau</th><th>Financement / agences</th><th>Composition</th><th className="text-end">Montant</th><th>Transmission</th><th className="text-end">Actions</th></tr></thead>
                                                <tbody>{attenteFiltree.map((bordereau) => (
                                                    <tr key={bordereau.id} onDoubleClick={() => ouvrirDetails(bordereau)}>
                                                        <td><button type="button" className="ac-number-link" onClick={() => ouvrirDetails(bordereau)}>{bordereau.numero}</button><div className="text-muted fs-12 mt-1">{bordereau.nombre_paiements} paiement(s)</div></td>
                                                        <td><div className="fw-medium">{bordereau.source_financement?.libelle || 'Non renseigné'}</div><div className="text-muted fs-12 text-truncate ac-agencies" title={bordereau.agences}>{bordereau.agences || 'Aucune agence'}</div></td>
                                                        <td><Badge color="info" className="bg-info-subtle text-info me-1">{bordereau.nombre_ordres} OP</Badge><Badge color="secondary" className="bg-secondary-subtle text-secondary">{bordereau.nombre_dossiers} dossiers</Badge></td>
                                                        <td className="text-end fw-semibold text-dark">{formatMontant(bordereau.montant_total)} <span className="text-muted fs-11">FCFA</span></td>
                                                        <td><i className="ri-calendar-line text-muted me-1" />{bordereau.date_transmission || '—'}</td>
                                                        <td className="text-end text-nowrap"><Button size="sm" color="primary" onClick={() => ouvrirDetails(bordereau)}><i className="ri-list-check-2 me-1" />Traiter les OP</Button></td>
                                                    </tr>
                                                ))}</tbody>
                                            </Table>
                                        </div>
                                    ) : <EmptyState text={recherche ? 'Aucun bordereau ne correspond à la recherche.' : 'Aucun bordereau éligible pour cette période.'} />}
                                </TabPane>

                                <TabPane tabId="vises">
                                    {visesFiltres.length ? <div className="table-responsive ac-table-wrap"><Table hover className="align-middle mb-0 ac-table"><thead><tr><th>Bordereau</th><th>Financement</th><th>Paiements</th><th className="text-end">Montant</th><th>Date du visa</th><th>Statut</th></tr></thead><tbody>{visesFiltres.map((bordereau) => <tr key={bordereau.id}><td><button type="button" className="ac-number-link" onClick={() => ouvrirDetails(bordereau)}>{bordereau.numero}</button></td><td>{bordereau.source_financement?.libelle || '—'}</td><td>{bordereau.nombre_paiements}</td><td className="text-end fw-semibold">{formatMontant(bordereau.montant_total)} FCFA</td><td>{bordereau.date_traitement || '—'}</td><td><Badge color="success" className="bg-success-subtle text-success"><i className="ri-checkbox-circle-line me-1" />Visé AC</Badge></td></tr>)}</tbody></Table></div> : <EmptyState text="Aucun bordereau visé pour cette période." />}
                                </TabPane>

                                <TabPane tabId="rejetes">
                                    {rejetesFiltres.length ? <div className="table-responsive ac-table-wrap"><Table hover className="align-middle mb-0 ac-table"><thead><tr><th>Bordereau</th><th>Décision</th><th>Motif</th><th className="text-end">Montant</th><th>Date</th><th /></tr></thead><tbody>{rejetesFiltres.map((bordereau) => <tr key={bordereau.id}><td className="fw-semibold">{bordereau.numero}</td><td><Badge color={bordereau.statut === 'REJETE_AC_DEFINITIF' ? 'danger' : 'warning'} className={bordereau.statut === 'REJETE_AC_DEFINITIF' ? '' : 'bg-warning-subtle text-warning'}>{bordereau.statut === 'REJETE_AC_DEFINITIF' ? 'Rejet définitif' : 'Traité avec retour'}</Badge></td><td className="ac-motif-cell" title={bordereau.motif}>{bordereau.motif || 'Voir les décisions des OP'}</td><td className="text-end fw-semibold">{formatMontant(bordereau.montant_total)} FCFA</td><td>{bordereau.date_traitement || '—'}</td><td className="text-end"><Button size="sm" color="light" onClick={() => ouvrirDetails(bordereau)}><i className="ri-eye-line me-1" />Consulter</Button></td></tr>)}</tbody></Table></div> : <EmptyState text="Aucun retour ou rejet pour cette période." />}
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={Boolean(ordreAValider)} toggle={() => !processing && setOrdreAValider(null)} centered>
                <ModalBody className="p-4 text-center">
                    <span className="ac-confirm-icon ac-confirm-icon--success"><i className="ri-check-double-line" /></span>
                    <h4 className="mt-3">Valider cette OP ?</h4>
                    <p className="text-muted mb-1">L’OP <strong>{ordreAValider?.numero}</strong> sera validée avec ses {ordreAValider?.nombre_paiements} paiement(s).</p>
                    <p className="fw-semibold mb-0">{formatMontant(ordreAValider?.montant_total || 0)} FCFA</p>
                    <Alert color="info" className="text-start fs-13 mt-3 mb-0"><i className="ri-information-line me-2" />Le bordereau sera automatiquement clôturé après la validation de sa dernière OP.</Alert>
                </ModalBody>
                <ModalFooter className="justify-content-center border-0 pt-0 pb-4">
                    <Button color="light" disabled={processing} onClick={() => setOrdreAValider(null)}>Annuler</Button>
                    <Button color="primary" disabled={processing} onClick={confirmerValidationOp}>{processing && <Spinner size="sm" className="me-2" />}Valider l’OP</Button>
                </ModalFooter>
            </Modal>

            <Modal isOpen={Boolean(actionOp)} toggle={() => !processing && setActionOp(null)} centered>
                <ModalHeader toggle={() => !processing && setActionOp(null)}>{actionOp ? actionsOp[actionOp.type].titre : ''}</ModalHeader>
                <ModalBody>
                    {actionOp && <Alert color={actionsOp[actionOp.type].color} className="fs-13"><i className="ri-information-line me-2" />{actionsOp[actionOp.type].description}</Alert>}
                    <div className="rounded bg-light p-3 mb-3"><span className="text-muted fs-12 d-block">OP concernée</span><strong>{actionOp?.ordre.numero}</strong><span className="float-end fw-semibold">{formatMontant(actionOp?.ordre.montant_total || 0)} FCFA</span></div>
                    <Label className="fw-semibold">Motif obligatoire</Label>
                    <Input invalid={Boolean(errors?.motif)} type="textarea" rows={4} maxLength={2000} value={motifOp} onChange={(event) => setMotifOp(event.target.value)} placeholder="Précisez clairement le motif de la décision…" />
                    <FormFeedback>{errors?.motif}</FormFeedback>
                    <div className="d-flex justify-content-between mt-1 fs-11 text-muted"><span>5 caractères minimum</span><span>{motifOp.length}/2000</span></div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" disabled={processing} onClick={() => setActionOp(null)}>Annuler</Button>
                    <Button color={actionOp ? actionsOp[actionOp.type].color : 'secondary'} disabled={processing || motifOp.trim().length < 5} onClick={confirmerActionOp}>{processing && <Spinner size="sm" className="me-2" />}{actionOp ? actionsOp[actionOp.type].bouton : 'Confirmer'}</Button>
                </ModalFooter>
            </Modal>

            <Modal isOpen={Boolean(details)} toggle={fermerDetails} size="xl" centered scrollable className="ac-detail-modal">
                <ModalHeader toggle={fermerDetails}><div><span className="text-muted fs-12 d-block">Traitement des OP du bordereau</span>{details?.numero}</div></ModalHeader>
                <ModalBody className="p-0">
                    <div className="ac-detail-summary p-3"><Row className="g-3"><Col sm={4}><span>Montant total</span><strong>{formatMontant(details?.montant_total || 0)} FCFA</strong></Col><Col sm={4}><span>Progression</span><strong>{details?.ordres.filter((ordre) => ordre.statut !== 'EN_BORDEREAU').length || 0} / {details?.nombre_ordres || 0} OP traitées</strong></Col><Col sm={4}><span>Financement</span><strong>{details?.source_financement?.libelle || 'Non renseigné'}</strong></Col></Row></div>
                    <Row className="g-0 ac-op-workspace">
                        <Col lg={3} className="ac-op-sidebar">
                            <div className="p-3 border-bottom"><span className="text-uppercase text-muted fs-11 fw-semibold">Ordres de paiement</span></div>
                            <div className="ac-op-list">
                                {details?.ordres.map((ordre, index) => (
                                    <button key={ordre.id} type="button" className={classnames('ac-op-list__item', { active: ordreDetail?.id === ordre.id })} onClick={() => chargerOrdre(ordre.id)}>
                                        <span className="ac-op-list__index">{index + 1}</span>
                                        <span className="flex-grow-1 min-w-0"><strong className="d-block text-truncate">{ordre.numero}</strong><small>{ordre.nombre_dossiers} dossiers · {ordre.nombre_paiements} stagiaires</small></span>
                                        <i className={ordre.statut === 'VISE_AC' ? 'ri-checkbox-circle-fill text-success' : ordre.statut === 'EN_BORDEREAU' ? 'ri-time-fill text-warning' : 'ri-arrow-go-back-fill text-danger'} />
                                    </button>
                                ))}
                                {details && !details.ordres.length && <div className="p-3 text-muted text-center">Aucune OP rattachée</div>}
                            </div>
                        </Col>
                        <Col lg={9} className="ac-op-content">
                            {loadingOrdre && <div className="ac-detail-loading"><Spinner color="primary" /><span>Chargement des dossiers et stagiaires…</span></div>}
                            {!loadingOrdre && detailError && <Alert color="danger" className="m-3">{detailError}</Alert>}
                            {!loadingOrdre && ordreDetail && (
                                <>
                                    <div className="ac-op-heading p-3">
                                        <div><span className="text-muted fs-12">OP sélectionnée</span><h5 className="mb-0">{ordreDetail.numero} <Badge color={ordreDetail.statut === 'EN_BORDEREAU' ? 'warning' : 'secondary'} className="ms-2">{ordreDetail.statut.replaceAll('_', ' ')}</Badge></h5></div>
                                        {ordreSelectionne && ordreDetail.statut === 'EN_BORDEREAU' && (
                                            <div className="ac-op-actions">
                                                <Button size="sm" color="warning" outline onClick={() => ouvrirActionOp(ordreSelectionne, 'retirer')}><i className="ri-subtract-line me-1" />Retirer OP</Button>
                                                <Button size="sm" color="warning" onClick={() => ouvrirActionOp(ordreSelectionne, 'differer')}><i className="ri-pause-circle-line me-1" />Différer OP</Button>
                                                <Button size="sm" color="danger" onClick={() => ouvrirActionOp(ordreSelectionne, 'rejeter')}><i className="ri-close-circle-line me-1" />Rejeter</Button>
                                                <Button size="sm" color="primary" onClick={() => setOrdreAValider(ordreSelectionne)}><i className="ri-check-double-line me-1" />Valider</Button>
                                            </div>
                                        )}
                                    </div>
                                    <div className="p-3 ac-dossiers-list">
                                        {ordreDetail.dossiers.map((dossier) => (
                                            <section key={dossier.id} className="ac-dossier-block mb-3">
                                                <header className="ac-dossier-block__header"><div><span className="text-muted fs-11 text-uppercase">Dossier</span><strong className="d-block">{dossier.numero}</strong></div><div className="text-end"><span className="d-block fw-semibold">{formatMontant(dossier.montant_total)} FCFA</span><small className="text-muted">{dossier.agence || 'Agence non renseignée'} · {dossier.stagiaires.length} stagiaire(s)</small></div></header>
                                                {dossier.stagiaires.length ? <div className="table-responsive"><Table hover className="align-middle mb-0 ac-table ac-stagiaires-table"><thead><tr><th>N° AEJ</th><th>Nom et prénoms</th><th>Entreprise / stage</th><th>Période</th><th>N° Trésor Money</th><th className="text-end">Montant</th></tr></thead><tbody>{dossier.stagiaires.map((stagiaire) => <tr key={stagiaire.paiement_id}><td className="fw-semibold text-primary">{stagiaire.numero_aej || '—'}</td><td><strong>{stagiaire.nom || '—'} {stagiaire.prenoms || ''}</strong><small className="d-block text-muted">Né(e) le {stagiaire.date_naissance || '—'}</small></td><td><span>{stagiaire.entreprise || '—'}</span><small className="d-block text-muted">{stagiaire.type_stage || 'Type non renseigné'}</small></td><td>{stagiaire.date_debut || '—'}<small className="d-block text-muted">au {stagiaire.date_fin || '—'}</small></td><td>{stagiaire.numero_tresor_money || '—'}</td><td className="text-end fw-semibold">{formatMontant(stagiaire.montant)} FCFA</td></tr>)}</tbody></Table></div> : <div className="p-4 text-center text-muted">Aucun stagiaire actif dans ce dossier.</div>}
                                            </section>
                                        ))}
                                        {!ordreDetail.dossiers.length && <EmptyState text="Cette OP ne contient aucun dossier actif." />}
                                    </div>
                                </>
                            )}
                        </Col>
                    </Row>
                </ModalBody>
                <ModalFooter><Button color="light" onClick={fermerDetails}>Fermer</Button></ModalFooter>
            </Modal>
        </>
    );
}
