import { router } from '@inertiajs/react';
import React, { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Input,
    Label,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Row,
    Spinner,
} from 'reactstrap';
import {
    DossierEligibleRow,
    DossierOpRow,
    OpRow,
    formatMontant,
    getJson,
    getStatutBadge,
} from '../shared';

interface OrdresPaiementTabProps {
    actif: boolean;
    mois: string;
    periodeId: number | null;
    /** Dossiers VALIDE_CB sans OP, servis en prop différée par la page. */
    dossiersEligibles: DossierEligibleRow[];
}

/**
 * Onglet « Ordres de paiement » — reprise de l'écran legacy `dmg/operation/wait-op-generer`.
 *
 * Deux temps, comme dans l'ancien Gestage : on choisit les dossiers validés par le CB puis on
 * élabore l'OP (titre libre et montant de l'état de financement), et on consulte ensuite les OP
 * de la période, dépliables pour vérifier — ou retirer — les dossiers qui les composent.
 */
const OrdresPaiementTab = ({ actif, mois, periodeId, dossiersEligibles }: OrdresPaiementTabProps) => {
    const [selection, setSelection] = useState<number[]>([]);
    const [modalOpOuverte, setModalOpOuverte] = useState(false);
    const [libelle, setLibelle] = useState('');
    const [montantEtat, setMontantEtat] = useState('');
    const [envoiEnCours, setEnvoiEnCours] = useState(false);
    const [erreurFormulaire, setErreurFormulaire] = useState<string | null>(null);

    const [ops, setOps] = useState<OpRow[]>([]);
    const [chargement, setChargement] = useState(false);
    const [erreur, setErreur] = useState<string | null>(null);

    const [opDeplie, setOpDeplie] = useState<number | null>(null);
    const [dossiersOp, setDossiersOp] = useState<DossierOpRow[]>([]);
    const [chargementDossiers, setChargementDossiers] = useState(false);

    const [retrait, setRetrait] = useState<{ op: OpRow; dossier: DossierOpRow } | null>(null);
    const [motifRetrait, setMotifRetrait] = useState('');

    const chargerOps = useCallback(() => {
        setChargement(true);
        setErreur(null);
        getJson<OpRow[]>('/dmg/paiements/ops', { mois })
            .then((data) => setOps(data ?? []))
            .catch((e: Error) => { setOps([]); setErreur(e.message); })
            .finally(() => setChargement(false));
    }, [mois]);

    useEffect(() => {
        if (!actif) return;
        chargerOps();
    }, [actif, chargerOps]);

    const deplier = (op: OpRow) => {
        if (opDeplie === op.id) {
            setOpDeplie(null);
            return;
        }
        setOpDeplie(op.id);
        setDossiersOp([]);
        setChargementDossiers(true);
        getJson<DossierOpRow[]>(`/dmg/paiements/ops/${op.id}/dossiers`)
            .then((data) => setDossiersOp(data ?? []))
            .catch((e: Error) => { setDossiersOp([]); setErreur(e.message); })
            .finally(() => setChargementDossiers(false));
    };

    const basculerDossier = (id: number) =>
        setSelection((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));

    const basculerTous = () =>
        setSelection((prev) => (prev.length === dossiersEligibles.length ? [] : dossiersEligibles.map((d) => d.id)));

    const montantSelection = dossiersEligibles
        .filter((d) => selection.includes(d.id))
        .reduce((somme, d) => somme + Number(d.montant_total || 0), 0);

    const ouvrirModalOp = () => {
        setErreurFormulaire(null);
        // Le montant de l'état de financement vaut par défaut le cumul des dossiers retenus :
        // dans l'immense majorité des cas, c'est ce que la DMG saisissait à la main.
        setMontantEtat(String(montantSelection));
        setLibelle('');
        setModalOpOuverte(true);
    };

    const elaborerOp = () => {
        if (!periodeId || selection.length === 0) return;
        setEnvoiEnCours(true);
        setErreurFormulaire(null);
        router.post(
            '/dmg/paiements/elaborer-op',
            {
                dossiers: selection,
                periode_id: periodeId,
                libelle: libelle.trim() || null,
                montant_etat_financement: montantEtat === '' ? null : Number(montantEtat),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOpOuverte(false);
                    setSelection([]);
                    setLibelle('');
                    setMontantEtat('');
                    chargerOps();
                },
                onError: (erreurs) => setErreurFormulaire(Object.values(erreurs)[0] ?? "Élaboration impossible."),
                onFinish: () => setEnvoiEnCours(false),
            },
        );
    };

    const confirmerRetrait = () => {
        if (!retrait) return;
        if (motifRetrait.trim().length < 5) {
            setErreurFormulaire('Le motif doit contenir au moins 5 caractères.');
            return;
        }
        setEnvoiEnCours(true);
        setErreurFormulaire(null);
        const op = retrait.op;
        router.post(
            `/dmg/paiements/ops/${op.id}/retirer-dossier`,
            { dossier_id: retrait.dossier.id, motif: motifRetrait.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRetrait(null);
                    setMotifRetrait('');
                    chargerOps();
                    setDossiersOp((prev) => prev.filter((d) => d.id !== retrait.dossier.id));
                },
                onError: (erreurs) => setErreurFormulaire(Object.values(erreurs)[0] ?? 'Retrait impossible.'),
                onFinish: () => setEnvoiEnCours(false),
            },
        );
    };

    return (
        <>
            {/* ═══ Section 1 : dossiers validés CB en attente d'OP ═══ */}
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 className="fs-14 mb-1 text-success fw-bold">
                        <i className="ri-check-double-line me-1"></i>Dossiers validés CB — en attente d'OP
                    </h5>
                    <p className="text-muted mb-0 fs-12">
                        Sélectionnez les dossiers à regrouper dans un même ordre de paiement.
                    </p>
                </div>
                <div className="d-flex align-items-center gap-2">
                    {selection.length > 0 && (
                        <span className="fs-12 text-muted">Total sélection : <strong>{formatMontant(montantSelection)}</strong></span>
                    )}
                    <Button color="success" size="sm" disabled={selection.length === 0 || !periodeId} onClick={ouvrirModalOp}>
                        <i className="ri-file-list-3-line me-1"></i>Élaborer l'OP ({selection.length})
                    </Button>
                </div>
            </div>

            {!periodeId && (
                <Alert color="warning" className="fs-12">
                    Sélectionnez une période pour élaborer un ordre de paiement.
                </Alert>
            )}

            <Card className="border shadow-none mb-4">
                <CardBody className="p-0">
                    <div className="table-responsive">
                        <table className="table table-striped table-hover align-middle table-nowrap mb-0">
                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <Input type="checkbox" className="form-check-input"
                                            checked={dossiersEligibles.length > 0 && selection.length === dossiersEligibles.length}
                                            disabled={dossiersEligibles.length === 0} onChange={basculerTous} />
                                    </th>
                                    <th>#</th>
                                    <th>Numéro</th>
                                    <th>Agence</th>
                                    <th>Financement</th>
                                    <th className="text-center">Stagiaires</th>
                                    <th className="text-end">Montant</th>
                                    <th>Créé le</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                {dossiersEligibles.length === 0 && (
                                    <tr><td colSpan={9} className="text-center py-4 text-muted">
                                        <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun dossier validé CB en attente d'OP.
                                    </td></tr>
                                )}
                                {dossiersEligibles.map((dossier, index) => (
                                    <tr key={dossier.id}>
                                        <td>
                                            <Input type="checkbox" className="form-check-input"
                                                checked={selection.includes(dossier.id)}
                                                onChange={() => basculerDossier(dossier.id)} />
                                        </td>
                                        <td>{index + 1}</td>
                                        <td className="fw-medium text-success">{dossier.numero}</td>
                                        <td>{dossier.agence?.nom || '-'}</td>
                                        <td><Badge color="success-subtle" className="text-success">{dossier.source_financement?.libelle || '-'}</Badge></td>
                                        <td className="text-center"><Badge color="success" pill>{dossier.nombre_stagiaires}</Badge></td>
                                        <td className="text-end fw-bold">{formatMontant(dossier.montant_total)}</td>
                                        <td>{dossier.date_creation}</td>
                                        <td><Badge color={getStatutBadge(dossier.statut)} className="fs-11">{dossier.statut}</Badge></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardBody>
            </Card>

            {/* ═══ Section 2 : ordres de paiement de la période ═══ */}
            <Card className="border shadow-none">
                <CardHeader className="bg-light py-2 d-flex align-items-center gap-2">
                    <i className="ri-file-list-3-line text-primary"></i>
                    <h6 className="card-title mb-0 fs-13 fw-semibold">Ordres de paiement de la période</h6>
                    {chargement && <Spinner size="sm" color="primary" />}
                    <Badge color="primary" pill className="ms-auto fs-11">{ops.length} OP</Badge>
                </CardHeader>
                <CardBody className="p-0">
                    {erreur && (
                        <Alert color="danger" className="m-3 d-flex align-items-center gap-2">
                            <i className="ri-error-warning-line"></i>
                            <span>{erreur}</span>
                            <Button color="danger" size="sm" outline className="ms-auto" onClick={chargerOps}>Réessayer</Button>
                        </Alert>
                    )}
                    <div className="table-responsive">
                        <table className="table table-hover align-middle table-nowrap mb-0">
                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                <tr>
                                    <th style={{ width: 40 }}></th>
                                    <th>N° OP</th>
                                    <th>Libellé</th>
                                    <th>Financement</th>
                                    <th>Agences</th>
                                    <th className="text-center">Dossiers</th>
                                    <th className="text-center">Stagiaires</th>
                                    <th className="text-end">Montant</th>
                                    <th className="text-end">État financement</th>
                                    <th>Bordereau</th>
                                    <th>Créé le</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                {!chargement && ops.length === 0 && (
                                    <tr><td colSpan={12} className="text-center py-4 text-muted">
                                        <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun ordre de paiement pour cette période.
                                    </td></tr>
                                )}
                                {ops.map((op) => (
                                    <React.Fragment key={op.id}>
                                        <tr className={opDeplie === op.id ? 'table-active' : undefined}>
                                            <td>
                                                <Button color="light" size="sm" onClick={() => deplier(op)}
                                                    title={opDeplie === op.id ? 'Replier' : 'Voir les dossiers'}>
                                                    <i className={opDeplie === op.id ? 'ri-subtract-line' : 'ri-add-line'}></i>
                                                </Button>
                                            </td>
                                            <td className="fw-medium text-primary">{op.numero}</td>
                                            <td className="text-truncate" style={{ maxWidth: 220 }} title={op.libelle || ''}>{op.libelle || '-'}</td>
                                            <td>{op.source_financement || '-'}</td>
                                            <td className="text-truncate" style={{ maxWidth: 200 }} title={op.agences || ''}>{op.agences || '-'}</td>
                                            <td className="text-center"><Badge color="primary" pill>{op.dossiers_count}</Badge></td>
                                            <td className="text-center"><Badge color="info" pill>{op.stagiaires_count}</Badge></td>
                                            <td className="text-end fw-bold">{formatMontant(op.montant_total)}</td>
                                            <td className="text-end">{op.montant_etat_financement === null ? '-' : formatMontant(op.montant_etat_financement)}</td>
                                            <td>{op.bordereau || <span className="text-muted">—</span>}</td>
                                            <td>{op.created_at || '-'}</td>
                                            <td><Badge color={getStatutBadge(op.statut)} className="fs-11">{op.statut}</Badge></td>
                                        </tr>
                                        {opDeplie === op.id && (
                                            <tr>
                                                <td colSpan={12} className="bg-light-subtle">
                                                    {chargementDossiers ? (
                                                        <div className="text-center py-3"><Spinner size="sm" color="primary" /></div>
                                                    ) : dossiersOp.length === 0 ? (
                                                        <p className="text-muted text-center fs-12 my-3">Aucun dossier rattaché.</p>
                                                    ) : (
                                                        <table className="table table-sm align-middle mb-0">
                                                            <thead className="fs-11 text-uppercase text-muted">
                                                                <tr>
                                                                    <th>Dossier</th>
                                                                    <th>Nature</th>
                                                                    <th>Agence</th>
                                                                    <th>Financement</th>
                                                                    <th className="text-center">Stagiaires</th>
                                                                    <th className="text-end">Montant</th>
                                                                    <th>Statut</th>
                                                                    <th className="text-end">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {dossiersOp.map((dossier) => (
                                                                    <tr key={dossier.id}>
                                                                        <td className="fw-medium">{dossier.numero}</td>
                                                                        <td>{dossier.nature || '-'}</td>
                                                                        <td>{dossier.agence || '-'}</td>
                                                                        <td>{dossier.source_financement || '-'}</td>
                                                                        <td className="text-center">{dossier.nombre_stagiaires ?? '-'}</td>
                                                                        <td className="text-end">{formatMontant(dossier.montant_total)}</td>
                                                                        <td><Badge color={getStatutBadge(dossier.statut)} className="fs-11">{dossier.statut}</Badge></td>
                                                                        <td className="text-end">
                                                                            {/* Un OP déjà porté par un bordereau n'est plus modifiable. */}
                                                                            <Button color="danger" size="sm" outline
                                                                                disabled={op.statut !== 'BROUILLON'}
                                                                                title={op.statut !== 'BROUILLON' ? 'OP déjà rattaché à un bordereau' : 'Retirer du OP'}
                                                                                onClick={() => { setErreurFormulaire(null); setMotifRetrait(''); setRetrait({ op, dossier }); }}>
                                                                                <i className="ri-delete-bin-line me-1"></i>Retirer
                                                                            </Button>
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    )}
                                                </td>
                                            </tr>
                                        )}
                                    </React.Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardBody>
            </Card>

            {/* ── Modale : élaboration de l'OP ── */}
            <Modal isOpen={modalOpOuverte} toggle={() => !envoiEnCours && setModalOpOuverte(false)} centered>
                <ModalHeader toggle={() => !envoiEnCours && setModalOpOuverte(false)} className="bg-success text-white">
                    <i className="ri-file-list-3-line me-2"></i>Élaborer un ordre de paiement
                </ModalHeader>
                <ModalBody>
                    <Row className="g-3">
                        <Col xs={12}>
                            <div className="bg-light rounded p-2 fs-12 d-flex justify-content-between">
                                <span>{selection.length} dossier(s) sélectionné(s)</span>
                                <strong>{formatMontant(montantSelection)}</strong>
                            </div>
                        </Col>
                        <Col xs={12}>
                            <Label className="form-label fs-12 fw-semibold">Titre de l'OP</Label>
                            <Input type="text" value={libelle} maxLength={255}
                                placeholder="Ex. : OP paiement stagiaires — août"
                                onChange={(e) => setLibelle(e.target.value)} />
                        </Col>
                        <Col xs={12}>
                            <Label className="form-label fs-12 fw-semibold">Montant état de financement</Label>
                            <Input type="number" min={0} step="0.01" value={montantEtat}
                                onChange={(e) => setMontantEtat(e.target.value)} />
                            <small className="text-muted fs-11">
                                Pré-rempli avec le cumul des dossiers ; corrigez-le si l'état de financement diffère.
                            </small>
                        </Col>
                    </Row>
                    {erreurFormulaire && <div className="text-danger fs-12 mt-2">{erreurFormulaire}</div>}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" disabled={envoiEnCours} onClick={() => setModalOpOuverte(false)}>Annuler</Button>
                    <Button color="success" disabled={envoiEnCours} onClick={elaborerOp}>
                        {envoiEnCours ? <><Spinner size="sm" className="me-1" />Élaboration…</> : "Créer l'OP"}
                    </Button>
                </ModalFooter>
            </Modal>

            {/* ── Modale : retrait d'un dossier de l'OP ── */}
            <Modal isOpen={retrait !== null} toggle={() => !envoiEnCours && setRetrait(null)} centered>
                <ModalHeader toggle={() => !envoiEnCours && setRetrait(null)} className="bg-danger text-white">
                    <i className="ri-delete-bin-line me-2"></i>Retirer un dossier de l'OP
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted fs-13">
                        Le dossier <strong>{retrait?.dossier.numero}</strong> sera détaché de l'OP{' '}
                        <strong>{retrait?.op.numero}</strong> et redeviendra disponible pour un nouvel ordre de paiement.
                    </p>
                    <Label className="form-label fs-12 fw-semibold">Motif du retrait <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={4} value={motifRetrait} invalid={!!erreurFormulaire}
                        placeholder="Erreur de rattachement…" onChange={(e) => setMotifRetrait(e.target.value)} />
                    {erreurFormulaire && <div className="text-danger fs-12 mt-1">{erreurFormulaire}</div>}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" disabled={envoiEnCours} onClick={() => setRetrait(null)}>Annuler</Button>
                    <Button color="danger" disabled={envoiEnCours} onClick={confirmerRetrait}>
                        {envoiEnCours ? <><Spinner size="sm" className="me-1" />Retrait…</> : 'Confirmer le retrait'}
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
};

export default OrdresPaiementTab;
