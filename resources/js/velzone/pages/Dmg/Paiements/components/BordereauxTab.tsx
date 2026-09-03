import { router } from '@inertiajs/react';
import React, { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Input,
    Label,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Spinner,
} from 'reactstrap';
import {
    BordereauRow,
    DossierOpRow,
    OpRow,
    formatMontant,
    getJson,
    getStatutBadge,
} from '../shared';

interface BordereauxTabProps {
    actif: boolean;
    mois: string;
    periodeId: number | null;
    /** Bordereaux de la période, servis en prop différée par la page. */
    bordereaux: BordereauRow[];
}

/** Un bordereau ne peut porter plus de dix ordres de paiement (règle reprise du legacy). */
const MAX_OP_PAR_BORDEREAU = 10;

/**
 * Onglet « Bordereaux » — reprise de l'écran legacy
 * `dmg/bordereau/operations-wait-to-generate-bordereau`.
 *
 * On y assemble un bordereau à partir des OP encore en brouillon (dix au maximum, tous sur le
 * même financement), puis on suit les bordereaux de la période : dépliement des OP qui les
 * composent, retrait motivé d'un OP, et transmission à l'agent comptable.
 */
const BordereauxTab = ({ actif, mois, periodeId, bordereaux }: BordereauxTabProps) => {
    const [opsEligibles, setOpsEligibles] = useState<OpRow[]>([]);
    const [chargement, setChargement] = useState(false);
    const [erreur, setErreur] = useState<string | null>(null);
    const [selection, setSelection] = useState<number[]>([]);

    const [bordereauDeplie, setBordereauDeplie] = useState<number | null>(null);
    const [opsBordereau, setOpsBordereau] = useState<DossierOpRow[]>([]);
    const [chargementOps, setChargementOps] = useState(false);

    const [retrait, setRetrait] = useState<{ bordereau: BordereauRow; op: DossierOpRow } | null>(null);
    const [motifRetrait, setMotifRetrait] = useState('');
    const [envoiEnCours, setEnvoiEnCours] = useState(false);
    const [erreurAction, setErreurAction] = useState<string | null>(null);

    const chargerOpsEligibles = useCallback(() => {
        setChargement(true);
        setErreur(null);
        getJson<OpRow[]>('/dmg/paiements/ops', { mois, statut: 'BROUILLON' })
            .then((data) => { setOpsEligibles(data ?? []); setSelection([]); })
            .catch((e: Error) => { setOpsEligibles([]); setErreur(e.message); })
            .finally(() => setChargement(false));
    }, [mois]);

    useEffect(() => {
        if (!actif) return;
        chargerOpsEligibles();
    }, [actif, chargerOpsEligibles]);

    const opsSelectionnes = opsEligibles.filter((op) => selection.includes(op.id));
    const montantSelection = opsSelectionnes.reduce((somme, op) => somme + Number(op.montant_total || 0), 0);
    // Le service refuse un bordereau à cheval sur deux financements : autant le dire avant l'envoi.
    const financementsMelanges = new Set(opsSelectionnes.map((op) => op.source_financement ?? '')).size > 1;
    const selectionValide = selection.length > 0
        && selection.length <= MAX_OP_PAR_BORDEREAU
        && !financementsMelanges
        && !!periodeId;

    const basculerOp = (id: number) =>
        setSelection((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));

    const creerBordereau = () => {
        if (!selectionValide) return;
        setEnvoiEnCours(true);
        setErreurAction(null);
        router.post(
            '/dmg/paiements/creer-bordereau',
            { ops: selection, periode_id: periodeId },
            {
                preserveScroll: true,
                onSuccess: () => { setSelection([]); chargerOpsEligibles(); },
                onError: (erreurs) => setErreurAction(Object.values(erreurs)[0] ?? 'Création impossible.'),
                onFinish: () => setEnvoiEnCours(false),
            },
        );
    };

    const deplier = (bordereau: BordereauRow) => {
        if (bordereauDeplie === bordereau.id) {
            setBordereauDeplie(null);
            return;
        }
        setBordereauDeplie(bordereau.id);
        setOpsBordereau([]);
        setChargementOps(true);
        getJson<DossierOpRow[]>(`/dmg/paiements/bordereaux/${bordereau.id}/ops`)
            .then((data) => setOpsBordereau(data ?? []))
            .catch((e: Error) => { setOpsBordereau([]); setErreur(e.message); })
            .finally(() => setChargementOps(false));
    };

    const transmettre = (bordereau: BordereauRow) => {
        setEnvoiEnCours(true);
        setErreurAction(null);
        router.post(`/dmg/paiements/transmettre-bordereau/${bordereau.id}`, {}, {
            preserveScroll: true,
            onError: (erreurs) => setErreurAction(Object.values(erreurs)[0] ?? 'Transmission impossible.'),
            onFinish: () => setEnvoiEnCours(false),
        });
    };

    const confirmerRetrait = () => {
        if (!retrait) return;
        if (motifRetrait.trim().length < 5) {
            setErreurAction('Le motif doit contenir au moins 5 caractères.');
            return;
        }
        setEnvoiEnCours(true);
        setErreurAction(null);
        router.post(
            `/dmg/paiements/bordereaux/${retrait.bordereau.id}/retirer-op`,
            { op_id: retrait.op.id, motif: motifRetrait.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpsBordereau((prev) => prev.filter((op) => op.id !== retrait.op.id));
                    setRetrait(null);
                    setMotifRetrait('');
                    chargerOpsEligibles();
                },
                onError: (erreurs) => setErreurAction(Object.values(erreurs)[0] ?? 'Retrait impossible.'),
                onFinish: () => setEnvoiEnCours(false),
            },
        );
    };

    return (
        <>
            {/* ═══ Section 1 : OP en attente de bordereau ═══ */}
            <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 className="fs-14 mb-1">OP en attente de bordereau</h5>
                    <p className="text-muted mb-0 fs-12">
                        Dix ordres de paiement au maximum, tous sur le même financement.
                    </p>
                </div>
                <div className="d-flex align-items-center gap-2">
                    {selection.length > 0 && (
                        <span className="fs-12 text-muted">Total sélection : <strong>{formatMontant(montantSelection)}</strong></span>
                    )}
                    <Button color="success" size="sm" disabled={!selectionValide || envoiEnCours} onClick={creerBordereau}>
                        <i className="ri-file-shield-2-line me-1"></i>
                        Créer le bordereau ({selection.length}/{MAX_OP_PAR_BORDEREAU})
                    </Button>
                </div>
            </div>

            {financementsMelanges && (
                <Alert color="warning" className="fs-12 py-2">
                    <i className="ri-error-warning-line me-1"></i>
                    Les OP sélectionnés relèvent de financements différents : un bordereau ne peut en couvrir qu'un seul.
                </Alert>
            )}
            {selection.length > MAX_OP_PAR_BORDEREAU && (
                <Alert color="warning" className="fs-12 py-2">
                    <i className="ri-error-warning-line me-1"></i>
                    {selection.length} OP sélectionnés : retirez-en {selection.length - MAX_OP_PAR_BORDEREAU}.
                </Alert>
            )}
            {erreurAction && (
                <Alert color="danger" className="fs-12 py-2"><i className="ri-error-warning-line me-1"></i>{erreurAction}</Alert>
            )}
            {erreur && (
                <Alert color="danger" className="d-flex align-items-center gap-2">
                    <i className="ri-error-warning-line"></i>
                    <span>{erreur}</span>
                    <Button color="danger" size="sm" outline className="ms-auto" onClick={chargerOpsEligibles}>Réessayer</Button>
                </Alert>
            )}

            <Card className="border shadow-none mb-4">
                <CardBody className="p-0">
                    <div className="table-responsive">
                        <table className="table table-striped table-hover align-middle table-nowrap mb-0">
                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                <tr>
                                    <th style={{ width: 40 }}></th>
                                    <th>#</th>
                                    <th>N° OP</th>
                                    <th>Libellé</th>
                                    <th>Financement</th>
                                    <th>Agences</th>
                                    <th className="text-center">Dossiers</th>
                                    <th className="text-center">Stagiaires</th>
                                    <th className="text-end">Montant</th>
                                    <th>Créé le</th>
                                </tr>
                            </thead>
                            <tbody>
                                {chargement && opsEligibles.length === 0 && (
                                    <tr><td colSpan={10} className="text-center py-4"><Spinner size="sm" color="success" /></td></tr>
                                )}
                                {!chargement && opsEligibles.length === 0 && (
                                    <tr><td colSpan={10} className="text-center py-4 text-muted">
                                        <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun ordre de paiement en attente de bordereau.
                                    </td></tr>
                                )}
                                {opsEligibles.map((op, index) => (
                                    <tr key={op.id}>
                                        <td>
                                            <Input type="checkbox" className="form-check-input"
                                                checked={selection.includes(op.id)} onChange={() => basculerOp(op.id)} />
                                        </td>
                                        <td>{index + 1}</td>
                                        <td className="fw-medium text-primary">{op.numero}</td>
                                        <td className="text-truncate" style={{ maxWidth: 220 }} title={op.libelle || ''}>{op.libelle || '-'}</td>
                                        <td><Badge color="light" className="text-body">{op.source_financement || '-'}</Badge></td>
                                        <td className="text-truncate" style={{ maxWidth: 200 }} title={op.agences || ''}>{op.agences || '-'}</td>
                                        <td className="text-center"><Badge color="primary" pill>{op.dossiers_count}</Badge></td>
                                        <td className="text-center"><Badge color="info" pill>{op.stagiaires_count}</Badge></td>
                                        <td className="text-end fw-bold">{formatMontant(op.montant_total)}</td>
                                        <td>{op.created_at || '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </CardBody>
            </Card>

            {/* ═══ Section 2 : bordereaux de la période ═══ */}
            <Card className="border shadow-none">
                <CardHeader className="bg-light py-2 d-flex align-items-center gap-2">
                    <i className="ri-file-shield-2-line text-success"></i>
                    <h6 className="card-title mb-0 fs-13 fw-semibold">Bordereaux de la période</h6>
                    <Badge color="success" pill className="ms-auto fs-11">{bordereaux.length} bordereau(x)</Badge>
                </CardHeader>
                <CardBody className="p-0">
                    <div className="table-responsive">
                        <table className="table table-hover align-middle table-nowrap mb-0">
                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                <tr>
                                    <th style={{ width: 40 }}></th>
                                    <th>N° bordereau</th>
                                    <th className="text-center">OP</th>
                                    <th className="text-end">Montant</th>
                                    <th>Créé le</th>
                                    <th>Statut</th>
                                    <th className="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {bordereaux.length === 0 && (
                                    <tr><td colSpan={7} className="text-center py-4 text-muted">
                                        <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun bordereau pour cette période.
                                    </td></tr>
                                )}
                                {bordereaux.map((bordereau) => (
                                    <React.Fragment key={bordereau.id}>
                                        <tr className={bordereauDeplie === bordereau.id ? 'table-active' : undefined}>
                                            <td>
                                                <Button color="light" size="sm" onClick={() => deplier(bordereau)}
                                                    title={bordereauDeplie === bordereau.id ? 'Replier' : 'Voir les OP'}>
                                                    <i className={bordereauDeplie === bordereau.id ? 'ri-subtract-line' : 'ri-add-line'}></i>
                                                </Button>
                                            </td>
                                            <td className="fw-medium text-primary">{bordereau.numero}</td>
                                            <td className="text-center">
                                                {bordereau.ordres_paiement_count === undefined
                                                    ? '-'
                                                    : <Badge color="primary" pill>{bordereau.ordres_paiement_count}</Badge>}
                                            </td>
                                            <td className="text-end fw-bold">{formatMontant(bordereau.montant_total)}</td>
                                            <td>{bordereau.created_at ? new Date(bordereau.created_at).toLocaleDateString('fr-FR') : '-'}</td>
                                            <td><Badge color={getStatutBadge(bordereau.statut)} className="fs-11">{bordereau.statut}</Badge></td>
                                            <td className="text-end">
                                                <Button color="success" size="sm" outline
                                                    disabled={bordereau.statut !== 'BROUILLON' || envoiEnCours}
                                                    title={bordereau.statut !== 'BROUILLON' ? 'Déjà transmis' : "Transmettre à l'agent comptable"}
                                                    onClick={() => transmettre(bordereau)}>
                                                    <i className="ri-send-plane-line me-1"></i>Transmettre AC
                                                </Button>
                                            </td>
                                        </tr>
                                        {bordereauDeplie === bordereau.id && (
                                            <tr>
                                                <td colSpan={7} className="bg-light-subtle">
                                                    {chargementOps ? (
                                                        <div className="text-center py-3"><Spinner size="sm" color="success" /></div>
                                                    ) : opsBordereau.length === 0 ? (
                                                        <p className="text-muted text-center fs-12 my-3">Aucun ordre de paiement rattaché.</p>
                                                    ) : (
                                                        <table className="table table-sm align-middle mb-0">
                                                            <thead className="fs-11 text-uppercase text-muted">
                                                                <tr>
                                                                    <th>N° OP</th>
                                                                    <th>Libellé</th>
                                                                    <th className="text-center">Dossiers</th>
                                                                    <th className="text-end">Montant</th>
                                                                    <th>Statut</th>
                                                                    <th className="text-end">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                {opsBordereau.map((op) => (
                                                                    <tr key={op.id}>
                                                                        <td className="fw-medium">{op.numero}</td>
                                                                        <td>{op.libelle || '-'}</td>
                                                                        <td className="text-center">{op.dossiers_count ?? '-'}</td>
                                                                        <td className="text-end">{formatMontant(op.montant_total)}</td>
                                                                        <td><Badge color={getStatutBadge(op.statut)} className="fs-11">{op.statut}</Badge></td>
                                                                        <td className="text-end">
                                                                            {/* Un bordereau transmis est figé côté agent comptable. */}
                                                                            <Button color="danger" size="sm" outline
                                                                                disabled={bordereau.statut !== 'BROUILLON'}
                                                                                title={bordereau.statut !== 'BROUILLON' ? 'Bordereau déjà transmis' : 'Retirer du bordereau'}
                                                                                onClick={() => { setErreurAction(null); setMotifRetrait(''); setRetrait({ bordereau, op }); }}>
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

            {/* ── Modale : retrait d'un OP du bordereau ── */}
            <Modal isOpen={retrait !== null} toggle={() => !envoiEnCours && setRetrait(null)} centered>
                <ModalHeader toggle={() => !envoiEnCours && setRetrait(null)} className="bg-danger text-white">
                    <i className="ri-delete-bin-line me-2"></i>Retirer un OP du bordereau
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted fs-13">
                        L'ordre de paiement <strong>{retrait?.op.numero}</strong> sera détaché du bordereau{' '}
                        <strong>{retrait?.bordereau.numero}</strong> et redeviendra disponible pour un nouveau bordereau.
                    </p>
                    <Label className="form-label fs-12 fw-semibold">Motif du retrait <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={4} value={motifRetrait} invalid={!!erreurAction}
                        placeholder="Montant à corriger…" onChange={(e) => setMotifRetrait(e.target.value)} />
                    {erreurAction && <div className="text-danger fs-12 mt-1">{erreurAction}</div>}
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

export default BordereauxTab;
