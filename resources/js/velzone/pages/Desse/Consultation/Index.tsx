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
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

/**
 * Listes de consultation DESSE (portage de `desse/beneficiaire/index` et
 * `desse/stagiaire-sans-contrat`).
 *
 * Consultation seule : lecture, filtres et export (synchrone ou en arrière-plan avec
 * suivi d'avancement). Aucune décision de workflow n'est prise depuis ces écrans.
 */
type Liste = 'beneficiaires' | 'stagiaires-sans-contrat';

const LISTES: { id: Liste; label: string; icon: string; description: string }[] = [
    {
        id: 'beneficiaires',
        label: 'Bénéficiaires',
        icon: 'ri-group-line',
        description: 'Registre de consultation de tous les stagiaires enregistrés.',
    },
    {
        id: 'stagiaires-sans-contrat',
        label: 'Stagiaires sans contrat',
        icon: 'ri-file-forbid-line',
        description: 'Stagiaires enregistrés pour lesquels aucun contrat n’a encore été généré.',
    },
];

interface Ligne {
    id: number;
    beneficiaire: {
        nom: string;
        prenoms: string;
        matricule: string;
        sexe?: string | null;
        date_naissance?: string | null;
        telephone?: string | null;
    };
    numero_aej: string;
    entreprise: string;
    agence: string;
    source_financement: string;
    type_stage: string;
    type_structure?: string;
    situation_stage?: string | null;
    date_debut?: string;
    date_fin_prevue?: string;
    type_paiement?: string | null;
    numero_tresor_money?: string | null;
    numero_wave?: string | null;
    visa_desse_label?: string | null;
    date_validation_ar?: string | null;
    statut_parcours?: string;
    contrat?: string | null;
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
    liste: Liste;
    stages: Pagination<Ligne>;
    compteur: number;
    filters: Record<string, string>;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    typesstructures: Record<string, string>;
    situations: Record<string, string>;
    etapes: Record<string, string>;
}

const Index = () => {
    const { props } = usePage<any>();
    const {
        liste,
        stages,
        compteur,
        filters,
        agences,
        entreprises,
        typesfinancements,
        typestages,
        typesstructures,
        situations,
        etapes,
    } = props as Props;

    const [filtres, setFiltres] = useState<Record<string, string>>({
        agence_id: filters.agence_id ?? '',
        entreprise_id: filters.entreprise_id ?? '',
        source_financement_id: filters.source_financement_id ?? '',
        type_stage_id: filters.type_stage_id ?? '',
        type_structure_id: filters.type_structure_id ?? '',
        situation_stage: filters.situation_stage ?? '',
        etape_id: filters.etape_id ?? '',
        date_debut: filters.date_debut ?? '',
        date_fin: filters.date_fin ?? '',
        annee_saisie: filters.annee_saisie ?? '',
        recherche: filters.recherche ?? '',
    });

    const [ligneActive, setLigneActive] = useState<Ligne | null>(null);
    const [modalDetail, setModalDetail] = useState(false);
    const [batchExport, setBatchExport] = useState<{ id: string; progress: number; disponible: boolean } | null>(null);

    const definition = LISTES.find((item) => item.id === liste) ?? LISTES[0];
    const base = liste === 'stagiaires-sans-contrat' ? '/desse/stagiaires-sans-contrat' : '/desse/beneficiaires';

    const parametres = useMemo(
        () => Object.fromEntries(Object.entries(filtres).filter(([, valeur]) => valeur !== '')),
        [filtres],
    );

    const majFiltre = (cle: string, valeur: string) => setFiltres((etat) => ({ ...etat, [cle]: valeur }));

    const naviguer = (versListe: Liste, filtresUtilises: Record<string, string> = parametres) => {
        const cible = versListe === 'stagiaires-sans-contrat' ? '/desse/stagiaires-sans-contrat' : '/desse/beneficiaires';

        router.get(cible, filtresUtilises, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const reinitialiser = () => {
        const vides = Object.fromEntries(Object.keys(filtres).map((cle) => [cle, '']));
        setFiltres(vides as Record<string, string>);
        router.get(base, {}, { preserveState: true, preserveScroll: true });
    };

    const exporter = () => {
        window.location.href = `${base}/export?${new URLSearchParams(parametres).toString()}`;
    };

    const exporterEnArrierePlan = async () => {
        const reponse = await fetch(`${base}/export`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify(parametres),
        });

        const donnees = await reponse.json();

        if (donnees.batch_id) {
            setBatchExport({ id: donnees.batch_id, progress: 0, disponible: false });
        }
    };

    // Suit l'avancement de l'export en arrière-plan jusqu'au fichier téléchargeable.
    useEffect(() => {
        if (!batchExport || batchExport.disponible) {
            return;
        }

        const minuteur = window.setInterval(async () => {
            const reponse = await fetch(`${base}/export/${batchExport.id}/progress`, {
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
    }, [batchExport?.id, batchExport?.disponible, base]);

    const lignes = stages?.data ?? [];

    return (
        <React.Fragment>
            <Head title={definition.label} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title={definition.label} pageTitle="Espace DESSE" />

                    <Card>
                        <CardHeader className="border-0 pb-0">
                            <Nav tabs className="nav-tabs-custom nav-success flex-wrap">
                                {LISTES.map((item) => (
                                    <NavItem key={item.id}>
                                        <NavLink
                                            href="#"
                                            className={classnames({ active: liste === item.id }, 'cursor-pointer')}
                                            onClick={(evenement) => {
                                                evenement.preventDefault();
                                                naviguer(item.id);
                                            }}
                                        >
                                            <i className={`${item.icon} me-1`}></i>
                                            {item.label}
                                            {liste === item.id && (
                                                <Badge color="light" className="text-body ms-1">
                                                    {compteur ?? 0}
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
                                    <Label className="form-label">Étape workflow</Label>
                                    <Input
                                        type="select"
                                        value={filtres.etape_id}
                                        onChange={(e) => majFiltre('etape_id', e.target.value)}
                                    >
                                        <option value="">Toutes</option>
                                        {Object.entries(etapes).map(([code, nom]) => (
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
                                        placeholder="2026"
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
                                    <Label className="form-label">Recherche</Label>
                                    <Input
                                        type="text"
                                        value={filtres.recherche}
                                        onChange={(e) => majFiltre('recherche', e.target.value)}
                                        placeholder="Nom, prénoms, n° AEJ, n° pièce, entreprise"
                                    />
                                </Col>
                                <Col md={6} className="d-flex align-items-end gap-2">
                                    <Button color="primary" onClick={() => naviguer(liste)}>
                                        <i className="ri-search-line align-bottom me-1" /> Filtrer
                                    </Button>
                                    <Button color="light" onClick={reinitialiser}>Réinitialiser</Button>
                                    <Button color="success" outline onClick={exporter}>
                                        <i className="ri-download-2-line align-bottom me-1" /> Export CSV
                                    </Button>
                                    <Button color="success" outline onClick={exporterEnArrierePlan}>
                                        Export volumineux
                                    </Button>
                                </Col>
                            </Row>

                            {batchExport && (
                                <div className="mt-3">
                                    <Progress value={batchExport.progress} className="mb-2">
                                        {batchExport.progress}%
                                    </Progress>
                                    {batchExport.disponible ? (
                                        <a className="btn btn-sm btn-success" href={`${base}/export/${batchExport.id}/download`}>
                                            Télécharger l'export
                                        </a>
                                    ) : (
                                        <span className="text-muted">Export en cours de génération…</span>
                                    )}
                                </div>
                            )}
                        </CardBody>

                        <CardBody>
                            <Alert color="info" className="border-0 mb-3">
                                <i className={`${definition.icon} align-middle me-2`}></i>
                                {definition.description}
                            </Alert>

                            <div className="table-responsive">
                                <Table className="table-sm align-middle table-nowrap mb-0">
                                    <thead className="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>N° AEJ</th>
                                            <th>Bénéficiaire</th>
                                            <th>Téléphone</th>
                                            <th>Entreprise</th>
                                            <th>Agence</th>
                                            <th>Financement</th>
                                            <th>Type de stage</th>
                                            <th>Début</th>
                                            <th>Fin prévue</th>
                                            <th>Étape workflow</th>
                                            {liste === 'stagiaires-sans-contrat' && <th>Contrat</th>}
                                            <th className="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lignes.map((ligne, idx) => (
                                            <tr key={ligne.id}>
                                                <td>{stages?.from ? stages.from + idx : idx + 1}</td>
                                                <td>{ligne.numero_aej}</td>
                                                <td>
                                                    {ligne.beneficiaire.nom} {ligne.beneficiaire.prenoms}
                                                </td>
                                                <td>{ligne.beneficiaire.telephone ?? '-'}</td>
                                                <td>{ligne.entreprise}</td>
                                                <td>{ligne.agence}</td>
                                                <td>{ligne.source_financement}</td>
                                                <td>{ligne.type_stage}</td>
                                                <td>{ligne.date_debut ?? '-'}</td>
                                                <td>{ligne.date_fin_prevue ?? '-'}</td>
                                                <td>{ligne.statut_parcours ?? '-'}</td>
                                                {liste === 'stagiaires-sans-contrat' && (
                                                    <td>
                                                        {ligne.contrat ? (
                                                            <span className="badge bg-success-subtle text-success">{ligne.contrat}</span>
                                                        ) : (
                                                            <span className="badge bg-danger-subtle text-danger">Sans contrat</span>
                                                        )}
                                                    </td>
                                                )}
                                                <td className="text-end">
                                                    <Button
                                                        size="sm"
                                                        color="light"
                                                        onClick={() => {
                                                            setLigneActive(ligne);
                                                            setModalDetail(true);
                                                        }}
                                                    >
                                                        <i className="ri-eye-line me-1"></i>Détail
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                        {lignes.length === 0 && (
                                            <tr>
                                                <td colSpan={13} className="text-center py-4">
                                                    <i className="ri-inbox-line fs-1 text-muted d-block mb-2"></i>
                                                    Aucun dossier dans cette liste.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </Table>
                            </div>

                            {stages && (
                                <ServerPagination pagination={normalizePagination(stages)} itemLabel="dossiers" />
                            )}
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={modalDetail} toggle={() => setModalDetail(false)} centered size="lg">
                <ModalHeader toggle={() => setModalDetail(false)}>
                    <i className="ri-eye-line me-2 text-primary"></i>
                    Détail du dossier
                </ModalHeader>
                <ModalBody>
                    {ligneActive && (
                        <Row className="g-3">
                            <Col md={6}><strong>Bénéficiaire :</strong> {ligneActive.beneficiaire.nom} {ligneActive.beneficiaire.prenoms}</Col>
                            <Col md={6}><strong>N° AEJ :</strong> {ligneActive.numero_aej}</Col>
                            <Col md={6}><strong>Sexe :</strong> {ligneActive.beneficiaire.sexe ?? '-'}</Col>
                            <Col md={6}><strong>Date de naissance :</strong> {ligneActive.beneficiaire.date_naissance ?? '-'}</Col>
                            <Col md={6}><strong>Téléphone :</strong> {ligneActive.beneficiaire.telephone ?? '-'}</Col>
                            <Col md={6}><strong>Entreprise :</strong> {ligneActive.entreprise}</Col>
                            <Col md={6}><strong>Type de structure :</strong> {ligneActive.type_structure ?? '-'}</Col>
                            <Col md={6}><strong>Agence :</strong> {ligneActive.agence}</Col>
                            <Col md={6}><strong>Financement :</strong> {ligneActive.source_financement}</Col>
                            <Col md={6}><strong>Type de stage :</strong> {ligneActive.type_stage}</Col>
                            <Col md={6}><strong>Situation :</strong> {ligneActive.situation_stage ?? '-'}</Col>
                            <Col md={6}><strong>Type de paiement :</strong> {ligneActive.type_paiement ?? '-'}</Col>
                            <Col md={6}><strong>N° Trésor Money :</strong> {ligneActive.numero_tresor_money ?? '-'}</Col>
                            <Col md={6}><strong>N° Wave :</strong> {ligneActive.numero_wave ?? '-'}</Col>
                            <Col md={6}><strong>Début :</strong> {ligneActive.date_debut ?? '-'}</Col>
                            <Col md={6}><strong>Fin prévue :</strong> {ligneActive.date_fin_prevue ?? '-'}</Col>
                            <Col md={6}><strong>Étape workflow :</strong> {ligneActive.statut_parcours ?? '-'}</Col>
                            <Col md={6}><strong>Visa DESSE :</strong> {ligneActive.visa_desse_label ?? '-'}</Col>
                            <Col md={6}><strong>Validé AR le :</strong> {ligneActive.date_validation_ar ?? '-'}</Col>
                            <Col md={6}><strong>Contrat :</strong> {ligneActive.contrat ?? 'Sans contrat'}</Col>
                        </Row>
                    )}
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setModalDetail(false)}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default Index;
