import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useEffect, useMemo, useState } from 'react';
import {
    Alert,
    Badge,
    Button,
    Card,
    CardBody,
    CardHeader,
    Col,
    Container,
    FormFeedback,
    FormGroup,
    Input,
    Label,
    Modal,
    ModalBody,
    ModalFooter,
    ModalHeader,
    Nav,
    NavItem,
    NavLink,
    Progress,
    Row,
    Spinner,
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

/**
 * Écran unique de supervision régionale.
 *
 * Chaque onglet correspond à un écran legacy distinct ; ils partagent les mêmes filtres et
 * la même pagination serveur, car ils lisent tous le portefeuille de stages de l'agence.
 */
type Onglet =
    | 'attente_visa_desse'
    | 'rejetes_desse'
    | 'vises_desse'
    | 'valides_ar'
    | 'differes_ac'
    | 'suivi_enregistres'
    | 'suivi_valides_ar'
    | 'statistiques'
    | 'pieces';

interface Ligne {
    id: number;
    stage_id?: number | null;
    beneficiaire: { nom: string; prenoms: string; matricule: string };
    numero_aej: string;
    entreprise: string;
    agence: string;
    source_financement: string;
    type_stage: string;
    date_debut?: string;
    date_fin_prevue?: string;
    visa_desse?: string | null;
    visa_desse_label?: string | null;
    motif_visa_desse?: string | null;
    visa_desse_le?: string | null;
    decideur?: string | null;
    date_validation_ar?: string | null;
    statut_ca?: string;
    statut_parcours?: string;
    periode?: string;
    montant?: number;
    motif?: string | null;
    date_differe?: string | null;
}

interface LigneStatistique {
    agence_id: number;
    agence: string;
    inseres_pae: number;
    inseres_ecole: number;
    inseres_total: number;
    desse_pae: number;
    desse_ecole: number;
    desse_total: number;
    dmg_pae: number;
    dmg_ecole: number;
    dmg_total: number;
}

interface Piece {
    cle: string;
    libelle: string;
    disponible: boolean;
    nom_fichier: string | null;
    url: string | null;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: any[];
}

interface Props {
    onglet: Onglet;
    stages?: Pagination<Ligne>;
    statistiques?: {
        lignes: LigneStatistique[];
        totaux: Record<string, number>;
        periode: { date_debut: string; date_fin: string };
    };
    compteurs: Record<string, number>;
    filters: Record<string, string>;
    peutViser: boolean;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    typesstructures: Record<string, string>;
    situations: Record<string, string>;
}

const ONGLETS: { id: Onglet; label: string; compteur?: string }[] = [
    { id: 'attente_visa_desse', label: 'En attente de visa', compteur: 'attente_visa_desse' },
    { id: 'rejetes_desse', label: 'Rejetés DESSE', compteur: 'rejetes_desse' },
    { id: 'vises_desse', label: 'Visés DESSE', compteur: 'vises_desse' },
    { id: 'valides_ar', label: 'Validés agence régionale', compteur: 'valides_ar' },
    { id: 'differes_ac', label: 'Différés agent comptable', compteur: 'differes_ac' },
    { id: 'suivi_enregistres', label: 'Suivi : enregistrés', compteur: 'suivi_enregistres' },
    { id: 'suivi_valides_ar', label: 'Suivi : validés AR', compteur: 'suivi_valides_ar' },
    { id: 'pieces', label: 'Pièces justificatives' },
    { id: 'statistiques', label: 'Tableau statistique' },
];

const COULEUR_VISA: Record<string, string> = {
    EN_ATTENTE: 'warning',
    VISE: 'success',
    REJETE: 'danger',
};

const Index = () => {
    const { props } = usePage<any>();
    const {
        onglet,
        stages,
        statistiques,
        compteurs,
        filters,
        peutViser,
        agences,
        entreprises,
        typesfinancements,
        typestages,
        typesstructures,
        situations,
    } = props as Props;

    const flash = (props as any).flash ?? {};

    const [filtres, setFiltres] = useState<Record<string, string>>({
        agence_id: filters.agence_id ?? '',
        entreprise_id: filters.entreprise_id ?? '',
        source_financement_id: filters.source_financement_id ?? '',
        type_stage_id: filters.type_stage_id ?? '',
        type_structure_id: filters.type_structure_id ?? '',
        situation_stage: filters.situation_stage ?? '',
        date_debut: filters.date_debut ?? '',
        date_fin: filters.date_fin ?? '',
        date_valid_ar_debut: filters.date_valid_ar_debut ?? '',
        date_valid_ar_fin: filters.date_valid_ar_fin ?? '',
        date_valid_desse_debut: filters.date_valid_desse_debut ?? '',
        date_valid_desse_fin: filters.date_valid_desse_fin ?? '',
        annee_saisie: filters.annee_saisie ?? '',
        recherche: filters.recherche ?? '',
    });

    const [ligneActive, setLigneActive] = useState<Ligne | null>(null);
    const [modalRejet, setModalRejet] = useState(false);
    const [modalDetail, setModalDetail] = useState(false);
    const [modalPieces, setModalPieces] = useState(false);
    const [motif, setMotif] = useState('');
    const [erreurMotif, setErreurMotif] = useState<string | null>(null);
    const [enCours, setEnCours] = useState(false);
    const [pieces, setPieces] = useState<Piece[]>([]);
    const [urlArchive, setUrlArchive] = useState<string | null>(null);
    const [chargementPieces, setChargementPieces] = useState(false);
    const [batchExport, setBatchExport] = useState<{ id: string; progress: number; disponible: boolean } | null>(null);

    const parametres = useMemo(
        () => Object.fromEntries(Object.entries(filtres).filter(([, valeur]) => valeur !== '')),
        [filtres],
    );

    const naviguer = (versOnglet: Onglet, filtresUtilises: Record<string, string> = parametres) => {
        router.get('/agence-regionale/visas', { onglet: versOnglet, ...filtresUtilises }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const majFiltre = (cle: string, valeur: string) => setFiltres((etat) => ({ ...etat, [cle]: valeur }));

    const reinitialiser = () => {
        const vides = Object.fromEntries(Object.keys(filtres).map((cle) => [cle, '']));
        setFiltres(vides as Record<string, string>);
        router.get('/agence-regionale/visas', { onglet }, { preserveState: true, preserveScroll: true });
    };

    const decider = (ligne: Ligne, action: 'viser' | 'remettre-en-attente') => {
        setEnCours(true);
        router.post(`/agence-regionale/visas/${ligne.id}/${action}`, {}, {
            preserveScroll: true,
            onFinish: () => setEnCours(false),
        });
    };

    const rejeter = () => {
        if (motif.trim().length < 5) {
            setErreurMotif('Le motif doit contenir au moins 5 caractères.');
            return;
        }

        setEnCours(true);
        router.post(`/agence-regionale/visas/${ligneActive?.id}/rejeter`, { motif }, {
            preserveScroll: true,
            onSuccess: () => {
                setModalRejet(false);
                setMotif('');
                setErreurMotif(null);
            },
            onFinish: () => setEnCours(false),
        });
    };

    const ouvrirPieces = async (ligne: Ligne) => {
        const stageId = ligne.stage_id ?? ligne.id;
        setLigneActive(ligne);
        setModalPieces(true);
        setChargementPieces(true);

        try {
            const reponse = await fetch(`/agence-regionale/visas/${stageId}/pieces`, {
                headers: { Accept: 'application/json' },
            });
            const donnees = await reponse.json();
            setPieces(donnees.pieces ?? []);
            setUrlArchive(donnees.url_archive ?? null);
        } finally {
            setChargementPieces(false);
        }
    };

    const exporter = () => {
        const query = new URLSearchParams({ onglet, ...parametres }).toString();
        window.location.href = `/agence-regionale/visas/export?${query}`;
    };

    const exporterEnArrierePlan = async () => {
        const reponse = await fetch('/agence-regionale/visas/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ onglet, ...parametres }),
        });

        const donnees = await reponse.json();

        if (donnees.batch_id) {
            setBatchExport({ id: donnees.batch_id, progress: 0, disponible: false });
        }
    };

    // Suit l'avancement de l'export lancé en arrière-plan jusqu'à ce que le fichier soit
    // téléchargeable ; le sondage s'arrête de lui-même une fois le batch terminé.
    useEffect(() => {
        if (!batchExport || batchExport.disponible) {
            return;
        }

        const minuteur = window.setInterval(async () => {
            const reponse = await fetch(`/agence-regionale/visas/export/${batchExport.id}/progress`, {
                headers: { Accept: 'application/json' },
            });

            if (!reponse.ok) {
                window.clearInterval(minuteur);
                return;
            }

            const donnees = await reponse.json();
            setBatchExport((etat) =>
                etat ? { ...etat, progress: donnees.progress ?? 0, disponible: Boolean(donnees.disponible) } : etat,
            );
        }, 2000);

        return () => window.clearInterval(minuteur);
    }, [batchExport?.id, batchExport?.disponible]);

    const estOngletPaiement = onglet === 'differes_ac';
    const lignes = stages?.data ?? [];

    return (
        <React.Fragment>
            <Head title="Supervision régionale" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Supervision régionale" pageTitle="Agence régionale" />

                    {flash.success && <Alert color="success">{flash.success}</Alert>}
                    {flash.error && <Alert color="danger">{flash.error}</Alert>}

                    <Card>
                        <CardHeader className="border-0 pb-0">
                            <Nav tabs className="nav-tabs-custom nav-success flex-wrap">
                                {ONGLETS.map((item) => (
                                    <NavItem key={item.id}>
                                        <NavLink
                                            href="#"
                                            className={classnames({ active: onglet === item.id }, 'cursor-pointer')}
                                            onClick={(evenement) => {
                                                evenement.preventDefault();
                                                naviguer(item.id);
                                            }}
                                        >
                                            {item.label}
                                            {item.compteur !== undefined && (
                                                <Badge color="light" className="text-body ms-1">
                                                    {compteurs[item.compteur] ?? 0}
                                                </Badge>
                                            )}
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>
                        </CardHeader>

                        <CardBody className="border-bottom">
                            <Row className="g-2">
                                <Col md={3}>
                                    <Label className="form-label">Agence</Label>
                                    <Input
                                        type="select"
                                        value={filtres.agence_id}
                                        onChange={(e) => majFiltre('agence_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {Object.entries(agences).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Entreprise</Label>
                                    <Input
                                        type="select"
                                        value={filtres.entreprise_id}
                                        onChange={(e) => majFiltre('entreprise_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {Object.entries(entreprises).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Financement</Label>
                                    <Input
                                        type="select"
                                        value={filtres.source_financement_id}
                                        onChange={(e) => majFiltre('source_financement_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {Object.entries(typesfinancements).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Type de stage</Label>
                                    <Input
                                        type="select"
                                        value={filtres.type_stage_id}
                                        onChange={(e) => majFiltre('type_stage_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {Object.entries(typestages).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Type de structure</Label>
                                    <Input
                                        type="select"
                                        value={filtres.type_structure_id}
                                        onChange={(e) => majFiltre('type_structure_id', e.target.value)}
                                    >
                                        <option value="">Tous</option>
                                        {Object.entries(typesstructures).map(([id, nom]) => (
                                            <option key={id} value={id}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Situation de stage</Label>
                                    <Input
                                        type="select"
                                        value={filtres.situation_stage}
                                        onChange={(e) => majFiltre('situation_stage', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {Object.entries(situations).map(([code, nom]) => (
                                            <option key={code} value={code}>{nom}</option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Année de saisie</Label>
                                    <Input
                                        type="number"
                                        value={filtres.annee_saisie}
                                        onChange={(e) => majFiltre('annee_saisie', e.target.value)}
                                        placeholder="2024"
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Recherche</Label>
                                    <Input
                                        type="text"
                                        value={filtres.recherche}
                                        onChange={(e) => majFiltre('recherche', e.target.value)}
                                        placeholder="Nom, prénoms, n° AEJ, n° pièce, entreprise"
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Début de stage (à partir du)</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_debut}
                                        onChange={(e) => majFiltre('date_debut', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Fin de stage (jusqu'au)</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_fin}
                                        onChange={(e) => majFiltre('date_fin', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Validé AR du</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_valid_ar_debut}
                                        onChange={(e) => majFiltre('date_valid_ar_debut', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Validé AR au</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_valid_ar_fin}
                                        onChange={(e) => majFiltre('date_valid_ar_fin', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Visé DESSE du</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_valid_desse_debut}
                                        onChange={(e) => majFiltre('date_valid_desse_debut', e.target.value)}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Label className="form-label">Visé DESSE au</Label>
                                    <Input
                                        type="date"
                                        value={filtres.date_valid_desse_fin}
                                        onChange={(e) => majFiltre('date_valid_desse_fin', e.target.value)}
                                    />
                                </Col>
                                <Col md={6} className="d-flex align-items-end gap-2">
                                    <Button color="primary" onClick={() => naviguer(onglet)}>
                                        <i className="ri-search-line align-bottom me-1" /> Filtrer
                                    </Button>
                                    <Button color="light" onClick={reinitialiser}>Réinitialiser</Button>
                                    {onglet !== 'statistiques' && (
                                        <>
                                            <Button color="success" outline onClick={exporter}>
                                                <i className="ri-download-2-line align-bottom me-1" /> Export CSV
                                            </Button>
                                            <Button color="success" outline onClick={exporterEnArrierePlan}>
                                                Export volumineux
                                            </Button>
                                        </>
                                    )}
                                </Col>
                            </Row>

                            {batchExport && (
                                <div className="mt-3">
                                    <Progress value={batchExport.progress} className="mb-2">
                                        {batchExport.progress}%
                                    </Progress>
                                    {batchExport.disponible ? (
                                        <a
                                            className="btn btn-sm btn-success"
                                            href={`/agence-regionale/visas/export/${batchExport.id}/download`}
                                        >
                                            Télécharger l'export
                                        </a>
                                    ) : (
                                        <span className="text-muted">Export en cours de génération…</span>
                                    )}
                                </div>
                            )}
                        </CardBody>

                        <CardBody>
                            {onglet === 'statistiques' ? (
                                <div className="table-responsive">
                                    <Table className="table-sm align-middle table-bordered mb-0">
                                        <thead className="table-light">
                                            <tr>
                                                <th rowSpan={2}>Agence</th>
                                                <th colSpan={3} className="text-center">Mis en stage</th>
                                                <th colSpan={3} className="text-center">Validés DESSE</th>
                                                <th colSpan={3} className="text-center">Validés DMG</th>
                                            </tr>
                                            <tr>
                                                <th className="text-center">PAE</th>
                                                <th className="text-center">École</th>
                                                <th className="text-center">Total</th>
                                                <th className="text-center">PAE</th>
                                                <th className="text-center">École</th>
                                                <th className="text-center">Total</th>
                                                <th className="text-center">PAE</th>
                                                <th className="text-center">École</th>
                                                <th className="text-center">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(statistiques?.lignes ?? []).map((ligne) => (
                                                <tr key={ligne.agence_id}>
                                                    <td>{ligne.agence}</td>
                                                    <td className="text-center">{ligne.inseres_pae}</td>
                                                    <td className="text-center">{ligne.inseres_ecole}</td>
                                                    <td className="text-center fw-semibold">{ligne.inseres_total}</td>
                                                    <td className="text-center">{ligne.desse_pae}</td>
                                                    <td className="text-center">{ligne.desse_ecole}</td>
                                                    <td className="text-center fw-semibold">{ligne.desse_total}</td>
                                                    <td className="text-center">{ligne.dmg_pae}</td>
                                                    <td className="text-center">{ligne.dmg_ecole}</td>
                                                    <td className="text-center fw-semibold">{ligne.dmg_total}</td>
                                                </tr>
                                            ))}
                                            {(statistiques?.lignes ?? []).length === 0 && (
                                                <tr>
                                                    <td colSpan={10} className="text-center">
                                                        Aucune donnée sur la période sélectionnée.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                        {statistiques && statistiques.lignes.length > 0 && (
                                            <tfoot className="table-light fw-semibold">
                                                <tr>
                                                    <td>Total</td>
                                                    <td className="text-center">{statistiques.totaux.inseres_pae}</td>
                                                    <td className="text-center">{statistiques.totaux.inseres_ecole}</td>
                                                    <td className="text-center">{statistiques.totaux.inseres_total}</td>
                                                    <td className="text-center">{statistiques.totaux.desse_pae}</td>
                                                    <td className="text-center">{statistiques.totaux.desse_ecole}</td>
                                                    <td className="text-center">{statistiques.totaux.desse_total}</td>
                                                    <td className="text-center">{statistiques.totaux.dmg_pae}</td>
                                                    <td className="text-center">{statistiques.totaux.dmg_ecole}</td>
                                                    <td className="text-center">{statistiques.totaux.dmg_total}</td>
                                                </tr>
                                            </tfoot>
                                        )}
                                    </Table>
                                </div>
                            ) : (
                                <>
                                    <div className="table-responsive">
                                        <Table className="table-sm align-middle table-nowrap mb-0">
                                            <thead className="table-light">
                                                <tr>
                                                    <th>N° AEJ</th>
                                                    <th>Bénéficiaire</th>
                                                    <th>Entreprise</th>
                                                    <th>Agence</th>
                                                    <th>Financement</th>
                                                    <th>Type de stage</th>
                                                    {estOngletPaiement ? (
                                                        <>
                                                            <th>Période</th>
                                                            <th>Montant</th>
                                                            <th>Motif du différé</th>
                                                            <th>Différé le</th>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <th>Début</th>
                                                            <th>Fin prévue</th>
                                                            <th>Validé AR</th>
                                                            <th>Visa DESSE</th>
                                                        </>
                                                    )}
                                                    <th className="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {lignes.map((ligne) => (
                                                    <tr key={ligne.id}>
                                                        <td>{ligne.numero_aej}</td>
                                                        <td>
                                                            {ligne.beneficiaire.nom} {ligne.beneficiaire.prenoms}
                                                        </td>
                                                        <td>{ligne.entreprise}</td>
                                                        <td>{ligne.agence}</td>
                                                        <td>{ligne.source_financement}</td>
                                                        <td>{ligne.type_stage}</td>
                                                        {estOngletPaiement ? (
                                                            <>
                                                                <td>{ligne.periode}</td>
                                                                <td>{ligne.montant}</td>
                                                                <td>{ligne.motif ?? '-'}</td>
                                                                <td>{ligne.date_differe ?? '-'}</td>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <td>{ligne.date_debut}</td>
                                                                <td>{ligne.date_fin_prevue}</td>
                                                                <td>{ligne.date_validation_ar ?? '-'}</td>
                                                                <td>
                                                                    {ligne.visa_desse ? (
                                                                        <Badge color={COULEUR_VISA[ligne.visa_desse] ?? 'secondary'}>
                                                                            {ligne.visa_desse_label}
                                                                        </Badge>
                                                                    ) : (
                                                                        <span className="text-muted">-</span>
                                                                    )}
                                                                </td>
                                                            </>
                                                        )}
                                                        <td className="text-end">
                                                            <div className="d-flex gap-1 justify-content-end">
                                                                <Button
                                                                    size="sm"
                                                                    color="light"
                                                                    onClick={() => {
                                                                        setLigneActive(ligne);
                                                                        setModalDetail(true);
                                                                    }}
                                                                >
                                                                    Détail
                                                                </Button>
                                                                <Button size="sm" color="light" onClick={() => ouvrirPieces(ligne)}>
                                                                    Pièces
                                                                </Button>
                                                                {peutViser && !estOngletPaiement && onglet === 'attente_visa_desse' && (
                                                                    <>
                                                                        <Button
                                                                            size="sm"
                                                                            color="success"
                                                                            disabled={enCours}
                                                                            onClick={() => decider(ligne, 'viser')}
                                                                        >
                                                                            Viser
                                                                        </Button>
                                                                        <Button
                                                                            size="sm"
                                                                            color="danger"
                                                                            onClick={() => {
                                                                                setLigneActive(ligne);
                                                                                setMotif('');
                                                                                setErreurMotif(null);
                                                                                setModalRejet(true);
                                                                            }}
                                                                        >
                                                                            Rejeter
                                                                        </Button>
                                                                    </>
                                                                )}
                                                                {peutViser && onglet === 'rejetes_desse' && (
                                                                    <Button
                                                                        size="sm"
                                                                        color="warning"
                                                                        disabled={enCours}
                                                                        onClick={() => decider(ligne, 'remettre-en-attente')}
                                                                    >
                                                                        Remettre en attente
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                                {lignes.length === 0 && (
                                                    <tr>
                                                        <td colSpan={11} className="text-center">Aucun dossier dans cette corbeille.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>

                                    {stages && (
                                        <ServerPagination pagination={normalizePagination(stages)} itemLabel="dossiers" />
                                    )}
                                </>
                            )}
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={modalRejet} toggle={() => setModalRejet(false)} centered>
                <ModalHeader toggle={() => setModalRejet(false)}>Rejeter le dossier</ModalHeader>
                <ModalBody>
                    <p className="text-muted">
                        {ligneActive?.beneficiaire.nom} {ligneActive?.beneficiaire.prenoms} — {ligneActive?.numero_aej}
                    </p>
                    <FormGroup>
                        <Label className="form-label">Motif du rejet</Label>
                        <Input
                            type="textarea"
                            rows={4}
                            value={motif}
                            invalid={Boolean(erreurMotif)}
                            onChange={(e) => {
                                setMotif(e.target.value);
                                setErreurMotif(null);
                            }}
                        />
                        {erreurMotif && <FormFeedback>{erreurMotif}</FormFeedback>}
                    </FormGroup>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalRejet(false)}>Annuler</Button>
                    <Button color="danger" onClick={rejeter} disabled={enCours}>
                        {enCours && <Spinner size="sm" className="me-1" />} Confirmer le rejet
                    </Button>
                </ModalFooter>
            </Modal>

            <Modal isOpen={modalDetail} toggle={() => setModalDetail(false)} centered size="lg">
                <ModalHeader toggle={() => setModalDetail(false)}>Détail du dossier</ModalHeader>
                <ModalBody>
                    {ligneActive && (
                        <Row className="g-3">
                            <Col md={6}><strong>Bénéficiaire :</strong> {ligneActive.beneficiaire.nom} {ligneActive.beneficiaire.prenoms}</Col>
                            <Col md={6}><strong>N° AEJ :</strong> {ligneActive.numero_aej}</Col>
                            <Col md={6}><strong>Entreprise :</strong> {ligneActive.entreprise}</Col>
                            <Col md={6}><strong>Agence :</strong> {ligneActive.agence}</Col>
                            <Col md={6}><strong>Financement :</strong> {ligneActive.source_financement}</Col>
                            <Col md={6}><strong>Type de stage :</strong> {ligneActive.type_stage}</Col>
                            <Col md={6}><strong>Début :</strong> {ligneActive.date_debut ?? '-'}</Col>
                            <Col md={6}><strong>Fin prévue :</strong> {ligneActive.date_fin_prevue ?? '-'}</Col>
                            <Col md={6}><strong>Validé AR le :</strong> {ligneActive.date_validation_ar ?? '-'}</Col>
                            <Col md={6}><strong>Statut parcours :</strong> {ligneActive.statut_parcours ?? '-'}</Col>
                            <Col md={6}><strong>Visa DESSE :</strong> {ligneActive.visa_desse_label ?? '-'}</Col>
                            <Col md={6}><strong>Visé le :</strong> {ligneActive.visa_desse_le ?? '-'}</Col>
                            <Col md={6}><strong>Décideur :</strong> {ligneActive.decideur ?? '-'}</Col>
                            <Col md={12}><strong>Motif :</strong> {ligneActive.motif_visa_desse ?? ligneActive.motif ?? '-'}</Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalDetail(false)}>Fermer</Button>
                </ModalFooter>
            </Modal>

            <Modal isOpen={modalPieces} toggle={() => setModalPieces(false)} centered>
                <ModalHeader toggle={() => setModalPieces(false)}>Pièces justificatives</ModalHeader>
                <ModalBody>
                    {chargementPieces ? (
                        <div className="text-center py-3"><Spinner /></div>
                    ) : (
                        <Table className="table-sm align-middle mb-0">
                            <tbody>
                                {pieces.map((piece) => (
                                    <tr key={piece.cle}>
                                        <td>{piece.libelle}</td>
                                        <td className="text-end">
                                            {piece.disponible && piece.url ? (
                                                <a className="btn btn-sm btn-light" href={piece.url} target="_blank" rel="noreferrer">
                                                    Télécharger
                                                </a>
                                            ) : (
                                                <span className="text-muted">Non fournie</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </ModalBody>
                <ModalFooter>
                    {urlArchive && (
                        <a className="btn btn-success" href={urlArchive}>
                            Télécharger toutes les pièces (ZIP)
                        </a>
                    )}
                    <Button color="light" onClick={() => setModalPieces(false)}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default Index;
