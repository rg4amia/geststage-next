import { Head, Link } from '@inertiajs/react';
import React from 'react';
import { Container, Row, Col, Card, CardBody, CardHeader, Table, Badge, Button } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface ShowProps {
    instance: any;
}

const Show = ({ instance }: ShowProps) => {
    const { stage, etapeCourante, evenements, taches_ouvertes } = instance;
    const beneficiaire = stage?.beneficiaire;
    const entreprise = stage?.entreprise;
    const contrats = stage?.contrats || [];
    const documents = stage?.documents || [];

    // Helper to format date
    const formatDate = (dateString: string) => {
        if (!dateString) {
return 'N/A';
}

        const date = new Date(dateString);

        return date.toLocaleDateString('fr-FR');
    };

    // Helper to format datetime
    const formatDateTime = (dateString: string) => {
        if (!dateString) {
return 'N/A';
}

        const date = new Date(dateString);

        return date.toLocaleString('fr-FR');
    };

    return (
        <React.Fragment>
            <Head title={`Dossier - ${beneficiaire?.nom} ${beneficiaire?.prenoms}`} />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Détails du Dossier" pageTitle="Inscriptions" />

                    {/* STATUS BANNER */}
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardBody className="pb-0 px-4">
                                    <Row className="mb-3">
                                        <div className="col-md">
                                            <div className="row align-items-center g-3">
                                                <div className="col-md-auto">
                                                    <div className="avatar-md">
                                                        <div className="avatar-title bg-light text-primary rounded-circle fs-24">
                                                            <i className="ri-user-2-fill"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="col-md">
                                                    <div>
                                                        <h4 className="fw-bold">{beneficiaire?.nom} {beneficiaire?.prenoms}</h4>
                                                        <div className="hstack gap-3 flex-wrap">
                                                            <div><i className="ri-hashtag text-primary me-1 align-bottom"></i> AEJ: {beneficiaire?.numero_aej}</div>
                                                            <div className="vr"></div>
                                                            <div><i className="ri-building-line text-primary me-1 align-bottom"></i> {entreprise?.raison_sociale || 'N/A'}</div>
                                                            <div className="vr"></div>
                                                            <div><i className="ri-briefcase-line text-primary me-1 align-bottom"></i> {stage?.intitule_poste}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="col-md-auto">
                                            <div className="hstack gap-1 flex-wrap">
                                                <Badge color="info" className="fs-14 px-3 py-2">
                                                    <i className="ri-loader-4-line align-bottom me-1"></i> {etapeCourante?.nom || 'Initialisation'}
                                                </Badge>
                                            </div>
                                        </div>
                                    </Row>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row>
                        {/* LEFT COLUMN: INFO & DOCS */}
                        <Col xl={8}>
                            {/* BENEFICIAIRE CARD */}
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-user-line align-middle me-1 text-muted"></i> Informations du Stagiaire</h5>
                                </CardHeader>
                                <CardBody>
                                    <Row>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Numéro AEJ</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_aej || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Nom complet</td>
                                                        <td>{beneficiaire?.nom} {beneficiaire?.prenoms}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Date et lieu de naissance</td>
                                                        <td>{formatDate(beneficiaire?.date_naissance)} à {beneficiaire?.lieu_naissance}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Sexe</td>
                                                        <td>{beneficiaire?.sexe === 'M' ? 'Masculin' : (beneficiaire?.sexe === 'F' ? 'Féminin' : 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Pièce d'identité</td>
                                                        <td>{beneficiaire?.nature_piece_identite || 'N/A'} N° {beneficiaire?.numero_piece_identite || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Commune de résidence</td>
                                                        <td>{beneficiaire?.commune_residence?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Contact Urgence</td>
                                                        <td>{beneficiaire?.personne_urgence || 'N/A'} ({beneficiaire?.contact_urgence_1 || 'N/A'})</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Téléphones</td>
                                                        <td>{beneficiaire?.telephone_principal || 'N/A'} / {beneficiaire?.telephone_secondaire || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Email</td>
                                                        <td>{beneficiaire?.email || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Niveau d'étude</td>
                                                        <td>{beneficiaire?.niveau_etude?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Diplôme</td>
                                                        <td>{beneficiaire?.diplome?.nom || beneficiaire?.autre_diplome || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Type Paiement</td>
                                                        <td><Badge color="info">{beneficiaire?.type_paiement?.nom || 'N/A'}</Badge></td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">N° Trésor Money</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_tresor_money || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">N° Wave</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_wave || 'N/A'}</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                    </Row>
                                </CardBody>
                            </Card>

                            {/* STAGE & CONTRAT CARD */}
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-briefcase-line align-middle me-1 text-muted"></i> Informations sur le Stage</h5>
                                </CardHeader>
                                <CardBody>
                                    <Row>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Intitulé du poste</td>
                                                        <td className="fw-bold">{stage?.intitule_poste}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Type de Stage</td>
                                                        <td>{stage?.type_stage?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Programme</td>
                                                        <td>{stage?.programme?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Source de financement</td>
                                                        <td>{stage?.source_financement?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Situation du Stage</td>
                                                        <td><Badge color="secondary">{stage?.situation_stage || 'N/A'}</Badge></td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Dates prévues</td>
                                                        <td>Du {formatDate(stage?.date_debut)} au {formatDate(stage?.date_fin_prevue)}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Encadreur</td>
                                                        <td>{stage?.nom_encadreur} <br/><span className="text-muted fs-12">{stage?.contact_encadreur}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Prime mensuelle</td>
                                                        <td className="fw-bold text-success">{contrats[0]?.prime_mensuelle ? `${Number(contrats[0]?.prime_mensuelle).toLocaleString('fr-FR')} FCFA` : 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Observations</td>
                                                        <td>{stage?.observations || 'Aucune observation'}</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                    </Row>
                                </CardBody>
                            </Card>

                            {/* DOCUMENTS CARD */}
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-folder-2-line align-middle me-1 text-muted"></i> Pièces Justificatives (GED)</h5>
                                </CardHeader>
                                <CardBody>
                                    <div className="table-responsive">
                                        <Table className="table-nowrap mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Type de Document</th>
                                                    <th>Nom du fichier</th>
                                                    <th>Taille</th>
                                                    <th>Statut</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {documents.length > 0 ? documents.map((doc: any, index: number) => {
                                                    const latestVersion = doc.versions?.[0];

                                                    return (
                                                        <tr key={index}>
                                                            <td className="fw-medium">{doc.type_document?.nom || 'Document'}</td>
                                                            <td>{latestVersion?.nom_original || doc.nom}</td>
                                                            <td>{latestVersion?.taille_octets ? (latestVersion.taille_octets / 1024).toFixed(2) + ' KB' : 'N/A'}</td>
                                                            <td>
                                                                <Badge color={doc.statut === 'VALIDE' ? 'success' : 'warning'}>{doc.statut}</Badge>
                                                            </td>
                                                            <td>
                                                                <div className="hstack gap-2">
                                                                    {/* Note: Download route should be implemented in controller */}
                                                                    <Button color="light" size="sm" className="btn-icon">
                                                                        <i className="ri-download-2-line"></i>
                                                                    </Button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )
                                                }) : (
                                                    <tr>
                                                        <td colSpan={5} className="text-center text-muted">Aucun document joint à ce dossier.</td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </Table>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>

                        {/* RIGHT COLUMN: WORKFLOW TIMELINE */}
                        <Col xl={4}>
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-git-merge-line align-middle me-1 text-muted"></i> Historique du Parcours</h5>
                                </CardHeader>
                                <CardBody>
                                    <div className="profile-timeline">
                                        <div className="accordion accordion-flush" id="accordionFlushExample">
                                            {evenements && evenements.length > 0 ? evenements.map((evt: any, idx: number) => (
                                                <div className="accordion-item border-0" key={idx}>
                                                    <div className="accordion-header" id={`heading${idx}`}>
                                                        <a className="accordion-button p-2 shadow-none" data-bs-toggle="collapse" href={`#collapse${idx}`} aria-expanded="true">
                                                            <div className="d-flex align-items-center">
                                                                <div className="flex-shrink-0 avatar-xs">
                                                                    <div className="avatar-title bg-success rounded-circle">
                                                                        <i className="ri-check-line"></i>
                                                                    </div>
                                                                </div>
                                                                <div className="flex-grow-1 ms-3">
                                                                    <h6 className="fs-14 mb-0 fw-semibold">{evt.action || 'Transition'}</h6>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <div id={`collapse${idx}`} className="accordion-collapse collapse show" aria-labelledby={`heading${idx}`} data-bs-parent="#accordionExample">
                                                        <div className="accordion-body ms-2 ps-5 pt-0">
                                                            <h6 className="mb-1">{evt.acteur?.nom} {evt.acteur?.prenom}</h6>
                                                            <p className="text-muted mb-0">{formatDateTime(evt.survenu_le)}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            )) : (
                                                <div className="text-center text-muted p-3">
                                                    Aucun événement enregistré.
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-4 pt-2 border-top">
                                        <h6 className="mb-3">Actions Disponibles</h6>
                                        <div className="d-grid gap-2">
                                            {/* Note: Roles are simulated here. Usually driven by Inertia shared props or etape_courante_id logic. */}
                                            {etapeCourante?.nom === 'Validation CA' ? (
                                                <>
                                                    <Link method="post" href={`/validations/demarrage/${instance.id}`} as="button" className="btn btn-success">
                                                        <i className="ri-check-double-line align-middle me-1"></i> Valider le Démarrage
                                                    </Link>
                                                    <Button color="danger" outline onClick={() => {
                                                        const motif = prompt("Veuillez saisir le motif de l'ajournement :");

                                                        if(motif) {
                                                            import('@inertiajs/react').then(({ router }) => {
                                                                router.post(`/validations/ajourner/${instance.id}`, { motif });
                                                            });
                                                        }
                                                    }}>
                                                        <i className="ri-close-circle-line align-middle me-1"></i> Ajourner le Dossier
                                                    </Button>
                                                </>
                                            ) : taches_ouvertes && taches_ouvertes.length > 0 ? (
                                                <Button color="primary">Prendre en charge la tâche</Button>
                                            ) : (
                                                <div className="text-muted text-center">Aucune tâche ouverte pour votre profil.</div>
                                            )}
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Show;
