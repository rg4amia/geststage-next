import { Head, router, usePage } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useMemo, useState } from 'react';
import {
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
    Row,
    Table,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import ServerPagination, { normalizePagination } from '../../../Components/Common/ServerPagination';

/**
 * Vue globale DAICG des stagiaires (portage de `daicg/stagiaire-valider-par-chef-agence` et
 * `daicg/stagiaire-valider-par-desse`).
 *
 * Écran de consultation uniquement : la DAICG ne tranche rien ici, elle vérifie qui a déjà
 * été validé par le chef d'agence ou par la DESSE. Aucune action de validation/rejet.
 */
type Onglet = 'valides_ca' | 'valides_desse';

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
    situation_stage?: string | null;
    date_debut?: string;
    date_fin_prevue?: string;
    type_paiement?: string | null;
    numero_tresor_money?: string | null;
    numero_wave?: string | null;
    visa_desse?: string | null;
    visa_desse_label?: string | null;
    visa_desse_le?: string | null;
    decideur?: string | null;
    date_validation_ar?: string | null;
    statut_ca?: string;
    statut_parcours?: string;
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
    stages: Pagination<Ligne>;
    filters: Record<string, string>;
    agences: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
    situations: Record<string, string>;
}

const ONGLETS: { id: Onglet; label: string; icon: string; message: string }[] = [
    {
        id: 'valides_ca',
        label: 'VALIDÉS CA',
        icon: 'ri-check-double-line',
        message: 'Dossiers déjà validés par le chef d’agence (visa DESSE non requis pour figurer ici).',
    },
    {
        id: 'valides_desse',
        label: 'VALIDÉS DESSE',
        icon: 'ri-stamp-line',
        message: 'Dossiers ayant reçu le visa DESSE.',
    },
];

const Index = () => {
    const { props } = usePage<any>();
    const { onglet, stages, filters, agences, typesfinancements, typestages, situations } = props as Props;

    const [filtres, setFiltres] = useState<Record<string, string>>({
        agence_id: filters.agence_id ?? '',
        source_financement_id: filters.source_financement_id ?? '',
        type_stage_id: filters.type_stage_id ?? '',
        situation_stage: filters.situation_stage ?? '',
        date_debut: filters.date_debut ?? '',
        date_fin: filters.date_fin ?? '',
        date_valid_ar_debut: filters.date_valid_ar_debut ?? '',
        date_valid_ar_fin: filters.date_valid_ar_fin ?? '',
        date_valid_desse_debut: filters.date_valid_desse_debut ?? '',
        date_valid_desse_fin: filters.date_valid_desse_fin ?? '',
        recherche: filters.recherche ?? '',
    });

    const [ligneActive, setLigneActive] = useState<Ligne | null>(null);
    const [modalDetail, setModalDetail] = useState(false);

    const parametres = useMemo(
        () => Object.fromEntries(Object.entries(filtres).filter(([, valeur]) => valeur !== '')),
        [filtres],
    );

    const naviguer = (versOnglet: Onglet, filtresUtilises: Record<string, string> = parametres) => {
        router.get('/daicg/stagiaires', { onglet: versOnglet, ...filtresUtilises }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const majFiltre = (cle: string, valeur: string) => setFiltres((etat) => ({ ...etat, [cle]: valeur }));

    const reinitialiser = () => {
        const vides = Object.fromEntries(Object.keys(filtres).map((cle) => [cle, '']));
        setFiltres(vides as Record<string, string>);
        router.get('/daicg/stagiaires', { onglet }, { preserveState: true, preserveScroll: true });
    };

    const ongletActif = ONGLETS.find((item) => item.id === onglet) ?? ONGLETS[0];
    const lignes = stages?.data ?? [];

    return (
        <React.Fragment>
            <Head title="Vue globale des stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Vue globale des stagiaires" pageTitle="DAICG" />

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
                                            <i className={`${item.icon} me-1`}></i>
                                            {item.label}
                                            {onglet === item.id && (
                                                <Badge color="light" className="text-body ms-1">
                                                    {stages?.total ?? 0}
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
                                {onglet === 'valides_ca' ? (
                                    <>
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
                                    </>
                                ) : (
                                    <>
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
                                    </>
                                )}
                                <Col md={6} className="d-flex align-items-end gap-2">
                                    <Button color="primary" onClick={() => naviguer(onglet)}>
                                        <i className="ri-search-line align-bottom me-1" /> Filtrer
                                    </Button>
                                    <Button color="light" onClick={reinitialiser}>Réinitialiser</Button>
                                </Col>
                            </Row>
                        </CardBody>

                        <CardBody>
                            <div className="alert alert-info border-0 mb-3">
                                <i className="ri-information-line align-middle me-2"></i>
                                {ongletActif.message} Écran de consultation : aucune validation n’est déclenchée ici.
                            </div>

                            <div className="table-responsive">
                                <Table className="table-sm align-middle table-nowrap mb-0">
                                    <thead className="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>N° AEJ</th>
                                            <th>Bénéficiaire</th>
                                            <th>Entreprise</th>
                                            <th>Agence</th>
                                            <th>Financement</th>
                                            <th>Type de stage</th>
                                            <th>Situation</th>
                                            <th>Début</th>
                                            <th>Fin prévue</th>
                                            {onglet === 'valides_ca' ? (
                                                <th>Validé AR le</th>
                                            ) : (
                                                <>
                                                    <th>Visa DESSE</th>
                                                    <th>Visé le</th>
                                                </>
                                            )}
                                            <th className="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {lignes.map((ligne, idx) => (
                                            <tr key={ligne.id}>
                                                <td>{(stages.from ?? 1) + idx}</td>
                                                <td>{ligne.numero_aej}</td>
                                                <td>{ligne.beneficiaire.nom} {ligne.beneficiaire.prenoms}</td>
                                                <td>{ligne.entreprise}</td>
                                                <td>{ligne.agence}</td>
                                                <td>{ligne.source_financement}</td>
                                                <td>{ligne.type_stage}</td>
                                                <td>{ligne.situation_stage ?? '-'}</td>
                                                <td>{ligne.date_debut}</td>
                                                <td>{ligne.date_fin_prevue}</td>
                                                {onglet === 'valides_ca' ? (
                                                    <td>{ligne.date_validation_ar ?? '-'}</td>
                                                ) : (
                                                    <>
                                                        <td>
                                                            <span className="badge bg-success-subtle text-success">
                                                                {ligne.visa_desse_label ?? '-'}
                                                            </span>
                                                        </td>
                                                        <td>{ligne.visa_desse_le ?? '-'}</td>
                                                    </>
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
                                                <td colSpan={11} className="text-center py-4">
                                                    <i className="ri-inbox-line fs-1 text-muted d-block mb-2"></i>
                                                    Aucun dossier dans cet onglet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </Table>
                            </div>

                            {stages && <ServerPagination pagination={normalizePagination(stages)} itemLabel="dossiers" />}
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
                            <Col md={6}><strong>Agence :</strong> {ligneActive.agence}</Col>
                            <Col md={6}><strong>Financement :</strong> {ligneActive.source_financement}</Col>
                            <Col md={6}><strong>Type de stage :</strong> {ligneActive.type_stage}</Col>
                            <Col md={6}><strong>Situation :</strong> {ligneActive.situation_stage ?? '-'}</Col>
                            <Col md={6}><strong>Début :</strong> {ligneActive.date_debut ?? '-'}</Col>
                            <Col md={6}><strong>Fin prévue :</strong> {ligneActive.date_fin_prevue ?? '-'}</Col>
                            <Col md={6}><strong>Validé AR le :</strong> {ligneActive.date_validation_ar ?? '-'}</Col>
                            <Col md={6}><strong>Statut parcours :</strong> {ligneActive.statut_parcours ?? '-'}</Col>
                            <Col md={6}><strong>Visa DESSE :</strong> {ligneActive.visa_desse_label ?? '-'}</Col>
                            <Col md={6}><strong>Visé le :</strong> {ligneActive.visa_desse_le ?? '-'}</Col>
                            <Col md={6}><strong>Type de paiement :</strong> {ligneActive.type_paiement ?? '-'}</Col>
                            <Col md={6}><strong>N° Trésor Money :</strong> {ligneActive.numero_tresor_money ?? '-'}</Col>
                            <Col md={6}><strong>N° Wave :</strong> {ligneActive.numero_wave ?? '-'}</Col>
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
