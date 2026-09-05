import { router } from '@inertiajs/react';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import type {
    AjourneRow,
    OptionDossier,
    RefItem,
    ReponsePaginee} from '../shared';
import {
    formatDate,
    formatMontant,
    getJson,
    getStatutBadge,
} from '../shared';

interface AjournesTabProps {
    /** Sous-onglet visible : rien n'est chargé tant que l'utilisateur n'y est pas venu. */
    actif: boolean;
    mois: string;
    agences: RefItem[];
    entreprises: RefItem[];
    typesStage: RefItem[];
    sourcesFinancement: RefItem[];
    /** Ouvre la modale de prévisualisation des pièces jointes portée par la page. */
    onApercuDocuments?: (row: { stage_id: number }) => void;
}

interface FiltresAjournes {
    dossier: string;
    agence_id: string;
    entreprise_id: string;
    type_stage_id: string;
    source_financement_id: string;
}

const FILTRES_VIDES: FiltresAjournes = {
    dossier: '',
    agence_id: '',
    entreprise_id: '',
    type_stage_id: '',
    source_financement_id: '',
};

const PAR_PAGE = 10;

/**
 * Onglet « Ajournés » — reprise de l'écran legacy `ajournement-controller-budgetaire/ajournement-stagiaire`.
 *
 * Liste nominative des stagiaires bloqués (ajournés par la DMG ou par le CB via leur dossier),
 * avec le motif de la dernière décision et la remise en file d'attente après correction.
 * La pagination est serveur : un mois de production dépasse largement ce qu'on peut charger
 * dans une prop différée.
 */
const AjournesTab = ({
    actif,
    mois,
    agences,
    entreprises,
    typesStage,
    sourcesFinancement,
    onApercuDocuments,
}: AjournesTabProps) => {
    const [filtres, setFiltres] = useState<FiltresAjournes>(FILTRES_VIDES);
    const [recherche, setRecherche] = useState('');
    const [rechercheAppliquee, setRechercheAppliquee] = useState('');
    const [page, setPage] = useState(1);

    const [lignes, setLignes] = useState<AjourneRow[]>([]);
    const [total, setTotal] = useState(0);
    const [dernierePage, setDernierePage] = useState(1);
    const [chargement, setChargement] = useState(false);
    const [erreur, setErreur] = useState<string | null>(null);

    const [optionsDossier, setOptionsDossier] = useState<OptionDossier[]>([]);
    const [selection, setSelection] = useState<number[]>([]);

    const [modalOuverte, setModalOuverte] = useState(false);
    const [motif, setMotif] = useState('');
    const [envoiEnCours, setEnvoiEnCours] = useState(false);
    const [erreurMotif, setErreurMotif] = useState<string | null>(null);

    // La saisie libre ne doit pas déclencher une requête par frappe.
    useEffect(() => {
        const minuteur = window.setTimeout(() => {
            setRechercheAppliquee(recherche);
            setPage(1);
        }, 400);

        return () => window.clearTimeout(minuteur);
    }, [recherche]);

    const parametres = useMemo(
        () => ({ mois, ...filtres, search: rechercheAppliquee }),
        [mois, filtres, rechercheAppliquee],
    );

    // Une requête peut en doubler une autre (changement de filtre pendant un chargement) :
    // on abandonne la précédente pour ne pas afficher un résultat périmé.
    const requeteEnCours = useRef<AbortController | null>(null);

    const charger = useCallback(() => {
        requeteEnCours.current?.abort();
        const controleur = new AbortController();
        requeteEnCours.current = controleur;

        setChargement(true);
        setErreur(null);
        getJson<ReponsePaginee<AjourneRow>>(
            '/dmg/paiements/ajournes',
            { ...parametres, page, per_page: PAR_PAGE },
            controleur.signal,
        )
            .then((reponse) => {
                setLignes(reponse.data ?? []);
                setTotal(reponse.total ?? 0);
                setDernierePage(reponse.last_page ?? 1);
            })
            .catch((e: Error) => {
                if (e.name === 'AbortError') {
return;
}

                setLignes([]);
                setTotal(0);
                setErreur(e.message);
            })
            .finally(() => {
                if (!controleur.signal.aborted) {
setChargement(false);
}
            });
    }, [parametres, page]);

    useEffect(() => {
        if (!actif) {
return;
}

        charger();
    }, [actif, charger]);

    useEffect(() => {
        if (!actif) {
return;
}

        let annule = false;
        getJson<OptionDossier[]>('/dmg/paiements/ajournes/dossiers', { mois })
            .then((options) => {
                if (!annule) {
setOptionsDossier(options ?? []);
}
            })
            .catch(() => {
                if (!annule) {
setOptionsDossier([]);
}
            });

        return () => {
            annule = true;
        };
    }, [actif, mois]);

    // Le dossier sélectionné peut disparaître du mois suivant : on évite un filtre fantôme.
    useEffect(() => {
        if (filtres.dossier && !optionsDossier.some((o) => o.value === filtres.dossier)) {
            setFiltres((prev) => ({ ...prev, dossier: '' }));
        }
    }, [optionsDossier, filtres.dossier]);

    const changerFiltre = (champ: keyof FiltresAjournes, valeur: string) => {
        setFiltres((prev) => ({ ...prev, [champ]: valeur }));
        setPage(1);
        setSelection([]);
    };

    const reinitialiser = () => {
        setFiltres(FILTRES_VIDES);
        setRecherche('');
        setPage(1);
        setSelection([]);
    };

    const basculerLigne = (id: number) =>
        setSelection((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));

    const idsPage = lignes.map((l) => l.paiement_id);
    const toutSelectionne = idsPage.length > 0 && idsPage.every((id) => selection.includes(id));

    const basculerPage = () =>
        setSelection((prev) =>
            toutSelectionne ? prev.filter((id) => !idsPage.includes(id)) : [...new Set([...prev, ...idsPage])],
        );

    const reprendre = () => {
        if (motif.trim().length < 5) {
            setErreurMotif('Le motif doit contenir au moins 5 caractères.');

            return;
        }

        setEnvoiEnCours(true);
        setErreurMotif(null);
        router.post(
            '/dmg/paiements/ajournes/reprendre',
            { paiement_ids: selection, motif: motif.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModalOuverte(false);
                    setMotif('');
                    setSelection([]);
                    charger();
                },
                onError: (erreurs) => setErreurMotif(Object.values(erreurs)[0] ?? 'Reprise impossible.'),
                onFinish: () => setEnvoiEnCours(false),
            },
        );
    };

    const debut = total === 0 ? 0 : (page - 1) * PAR_PAGE + 1;
    const fin = Math.min(page * PAR_PAGE, total);

    return (
        <>
            <Card className="border shadow-none mb-3">
                <CardHeader className="bg-light py-2 d-flex align-items-center gap-2">
                    <i className="ri-filter-3-line text-danger"></i>
                    <h6 className="card-title mb-0 fs-13 fw-semibold">Filtres — stagiaires ajournés</h6>
                    {chargement && <Spinner size="sm" color="danger" />}
                    <Badge color="danger" pill className="ms-auto fs-11">{total} stagiaire(s)</Badge>
                </CardHeader>
                <CardBody className="py-3">
                    <Row className="g-3 align-items-end">
                        <Col md={3}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Dossier / Multi-dossier</Label>
                            <Input type="select" bsSize="sm" value={filtres.dossier}
                                onChange={(e) => changerFiltre('dossier', e.target.value)}>
                                <option value="">Tous</option>
                                {optionsDossier.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </Input>
                        </Col>
                        <Col md={3}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Agence</Label>
                            <Input type="select" bsSize="sm" value={filtres.agence_id}
                                onChange={(e) => changerFiltre('agence_id', e.target.value)}>
                                <option value="">Toutes</option>
                                {agences.map((a) => <option key={a.id} value={a.id}>{a.nom}</option>)}
                            </Input>
                        </Col>
                        <Col md={3}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Entreprise</Label>
                            <Input type="select" bsSize="sm" value={filtres.entreprise_id}
                                onChange={(e) => changerFiltre('entreprise_id', e.target.value)}>
                                <option value="">Toutes</option>
                                {entreprises.map((e2) => <option key={e2.id} value={e2.id}>{e2.raison_sociale ?? e2.nom}</option>)}
                            </Input>
                        </Col>
                        <Col md={3}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Type de stage</Label>
                            <Input type="select" bsSize="sm" value={filtres.type_stage_id}
                                onChange={(e) => changerFiltre('type_stage_id', e.target.value)}>
                                <option value="">Tous</option>
                                {typesStage.map((t) => <option key={t.id} value={t.id}>{t.nom}</option>)}
                            </Input>
                        </Col>
                        <Col md={3}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Source de financement</Label>
                            <Input type="select" bsSize="sm" value={filtres.source_financement_id}
                                onChange={(e) => changerFiltre('source_financement_id', e.target.value)}>
                                <option value="">Toutes</option>
                                {sourcesFinancement.map((s) => <option key={s.id} value={s.id}>{s.nom}</option>)}
                            </Input>
                        </Col>
                        <Col md={4}>
                            <Label className="form-label fs-12 text-muted fw-semibold">Recherche (nom, prénoms, n° AEJ)</Label>
                            <Input type="text" bsSize="sm" value={recherche} placeholder="Rechercher…"
                                onChange={(e) => setRecherche(e.target.value)} />
                        </Col>
                        <Col md={5} className="d-flex gap-2">
                            <Button color="light" size="sm" onClick={reinitialiser}>
                                <i className="ri-refresh-line me-1"></i>Réinitialiser
                            </Button>
                            <Button color="success" size="sm" className="ms-auto"
                                disabled={selection.length === 0}
                                onClick={() => {
 setErreurMotif(null); setModalOuverte(true); 
}}>
                                <i className="ri-arrow-go-back-line me-1"></i>
                                Remettre en file d'attente ({selection.length})
                            </Button>
                        </Col>
                    </Row>
                </CardBody>
            </Card>

            {erreur && (
                <Alert color="danger" className="d-flex align-items-center gap-2">
                    <i className="ri-error-warning-line"></i>
                    <span>{erreur}</span>
                    <Button color="danger" size="sm" outline className="ms-auto" onClick={charger}>Réessayer</Button>
                </Alert>
            )}

            <Card className="border shadow-none">
                <CardBody className="p-0">
                    <div className="table-responsive">
                        <table className="table table-striped table-hover align-middle table-nowrap mb-0">
                            <thead className="table-light text-uppercase fs-11 fw-semibold">
                                <tr>
                                    <th style={{ width: 40 }}>
                                        <Input type="checkbox" className="form-check-input" checked={toutSelectionne}
                                            disabled={idsPage.length === 0} onChange={basculerPage} />
                                    </th>
                                    <th>#</th>
                                    <th>N° AEJ</th>
                                    <th>Nom &amp; prénoms</th>
                                    <th>Né(e) le</th>
                                    <th>Trésor Pay</th>
                                    <th>Entreprise</th>
                                    <th>Agence</th>
                                    <th>Type de stage</th>
                                    <th>Financement</th>
                                    <th>Période</th>
                                    <th>Dossier</th>
                                    <th className="text-end">Montant</th>
                                    <th>Motif</th>
                                    <th>Ajourné le</th>
                                    <th>Statut</th>
                                    <th className="text-center">Pièces</th>
                                </tr>
                            </thead>
                            <tbody>
                                {chargement && lignes.length === 0 && (
                                    <tr><td colSpan={17} className="text-center py-4"><Spinner color="danger" size="sm" /></td></tr>
                                )}
                                {!chargement && lignes.length === 0 && (
                                    <tr><td colSpan={17} className="text-center py-4 text-muted">
                                        <i className="ri-inbox-line fs-24 d-block mb-2"></i>Aucun stagiaire ajourné.
                                    </td></tr>
                                )}
                                {lignes.map((ligne, index) => (
                                    <tr key={ligne.paiement_id}>
                                        <td>
                                            <Input type="checkbox" className="form-check-input"
                                                checked={selection.includes(ligne.paiement_id)}
                                                onChange={() => basculerLigne(ligne.paiement_id)} />
                                        </td>
                                        <td>{(page - 1) * PAR_PAGE + index + 1}</td>
                                        <td className="fw-medium text-primary">{ligne.numero_aej || '-'}</td>
                                        <td className="fw-medium">{ligne.nom} {ligne.prenoms}</td>
                                        <td>{formatDate(ligne.date_naissance)}</td>
                                        <td>{ligne.tresor_pay || '-'}</td>
                                        <td>{ligne.entreprise || '-'}</td>
                                        <td>{ligne.agence || '-'}</td>
                                        <td>{ligne.type_stage || '-'}</td>
                                        <td><Badge color="light" className="text-body">{ligne.source_financement || '-'}</Badge></td>
                                        <td>{ligne.periode || '-'}</td>
                                        <td>
                                            {ligne.multi_dossier
                                                ? <Badge color="warning-subtle" className="text-warning">{ligne.multi_dossier}</Badge>
                                                : (ligne.dossier || '-')}
                                        </td>
                                        <td className="text-end fw-bold">{formatMontant(ligne.montant)}</td>
                                        <td className="text-truncate" style={{ maxWidth: 240 }} title={ligne.motif || ''}>
                                            {ligne.motif || '-'}
                                        </td>
                                        <td>{formatDate(ligne.ajourne_le)}</td>
                                        <td><Badge color={getStatutBadge(ligne.statut)} className="fs-11">{ligne.statut}</Badge></td>
                                        <td className="text-center">
                                            <Button color="light" size="sm" title="Pièces jointes"
                                                onClick={() => onApercuDocuments?.(ligne)}>
                                                <i className="ri-attachment-2"></i>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {total > PAR_PAGE && (
                        <div className="d-flex justify-content-between align-items-center p-2 border-top">
                            <small className="text-muted">{debut}–{fin} sur {total}</small>
                            <div className="d-flex align-items-center gap-1">
                                <Button size="sm" color="light" disabled={page <= 1 || chargement}
                                    onClick={() => setPage((p) => p - 1)}><i className="ri-arrow-left-s-line"></i></Button>
                                <small className="text-muted px-2">Page {page} / {dernierePage}</small>
                                <Button size="sm" color="light" disabled={page >= dernierePage || chargement}
                                    onClick={() => setPage((p) => p + 1)}><i className="ri-arrow-right-s-line"></i></Button>
                            </div>
                        </div>
                    )}
                </CardBody>
            </Card>

            <Modal isOpen={modalOuverte} toggle={() => !envoiEnCours && setModalOuverte(false)} centered>
                <ModalHeader toggle={() => !envoiEnCours && setModalOuverte(false)} className="bg-success text-white">
                    <i className="ri-arrow-go-back-line me-2"></i>Remettre en file d'attente
                </ModalHeader>
                <ModalBody>
                    <p className="text-muted fs-13">
                        {selection.length} paiement(s) retrouveront leur corbeille d'origine (démarrage ou présence)
                        et seront retirés de leur dossier en cours.
                    </p>
                    <Label className="form-label fs-12 fw-semibold">Motif de la reprise <span className="text-danger">*</span></Label>
                    <Input type="textarea" rows={4} value={motif} invalid={!!erreurMotif}
                        placeholder="Pièces justificatives complétées…"
                        onChange={(e) => setMotif(e.target.value)} />
                    {erreurMotif && <div className="text-danger fs-12 mt-1">{erreurMotif}</div>}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" disabled={envoiEnCours} onClick={() => setModalOuverte(false)}>Annuler</Button>
                    <Button color="success" disabled={envoiEnCours || selection.length === 0} onClick={reprendre}>
                        {envoiEnCours ? <><Spinner size="sm" className="me-1" />Traitement…</> : 'Confirmer la reprise'}
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
};

export default AjournesTab;
