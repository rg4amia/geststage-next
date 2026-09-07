import { Head, Link } from '@inertiajs/react';
import React from 'react';
import { Container, Row, Col, Card, CardBody, CardHeader, Table, Badge, Button, Alert } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';

interface CorbeilleInfo {
    code: string | null;
    label: string | null;
}

interface SuiviPaiement {
    montant: number | string;
    statut: string | null;
    statut_label: string | null;
    statut_dossier_physique: string | null;
}

interface MaillonPaiement {
    numero: string;
    statut: string;
    statut_label: string | null;
}

// Une étape du circuit, telle que renvoyée par SuiviPointageService::etapes().
interface EtapeCircuit {
    code: string;
    label: string;
    acteur: string;
    etat: 'terminee' | 'en_cours' | 'ajournee' | 'a_venir' | 'sans_objet';
}

interface SuiviPointage {
    pointage_id: number;
    mois: string | null;
    nature: string | null;
    statut_pointage: string | null;
    corbeille: CorbeilleInfo;
    visa_desse: { code: string | null; label: string | null; motif: string | null; date: string | null };
    paiement: SuiviPaiement | null;
    dossier: MaillonPaiement | null;
    groupe: { numero: string; statut: string } | null;
    ordre_paiement: MaillonPaiement | null;
    bordereau: MaillonPaiement | null;
    etat_paiement: { code: string; label: string; paye: boolean };
    dernier_retour: { origine: string; decision: string; motif: string | null; auteur: string | null; date: string | null } | null;
    etapes: EtapeCircuit[];
}

interface DoublonMatch {
    type: string;
    label: string;
    cle: string;
}

interface ShowProps {
    instance: any;
    corbeilleActuelle?: CorbeilleInfo;
    suiviPointages?: SuiviPointage[];
    doublons?: DoublonMatch[];
}

// Couleur du badge de corbeille selon le rôle propriétaire (préfixe du code).
const corbeilleBadgeColor = (code: string | null): string => {
    if (!code) {
return 'light';
}
    if (code.startsWith('cip_')) {
return 'info';
}
    if (code.startsWith('ca_') || code === 'en_stage') {
return 'primary';
}
    if (code.startsWith('dmg_')) {
return 'warning';
}
    if (code.startsWith('cb_')) {
return 'dark';
}
    if (code.startsWith('ac_')) {
return 'success';
}
    if (code.startsWith('desse_')) {
return 'danger';
}
    if (code.startsWith('daicg_')) {
return 'secondary';
}

    return 'light';
};

// Couleur du badge de statut de paiement (Paiement.statut / DossierPaiement.statut / etc.).
const statutPaiementBadgeColor = (statut: string | null): string => {
    switch (statut) {
        case 'A_TRAITER':
        case 'BROUILLON':
            return 'warning';
        case 'AJOURNE_DMG':
        case 'AJOURNE_CB':
        case 'AJOURNE_AC':
            return 'danger';
        case 'EN_DOSSIER':
        case 'TRANSMIS_CB':
        case 'TRANSMIS_AC':
        case 'EN_OP':
        case 'EN_BORDEREAU':
            return 'info';
        case 'VALIDE_CB':
        case 'VISE_AC':
        case 'PAYE':
            return 'success';
        default:
            return 'secondary';
    }
};

// Rendu d'une étape du circuit (cf. constantes ETAT_* de SuiviPointageService).
const ETAT_ETAPE: Record<string, { color: string; icone: string; label: string }> = {
    terminee: { color: 'success', icone: 'ri-check-line', label: 'Franchie' },
    en_cours: { color: 'primary', icone: 'ri-time-line', label: 'En cours' },
    ajournee: { color: 'danger', icone: 'ri-arrow-go-back-line', label: 'Retour / ajournement' },
    a_venir: { color: 'light', icone: 'ri-more-line', label: 'À venir' },
    sans_objet: { color: 'light', icone: 'ri-subtract-line', label: 'Sans objet' },
};

const Show = ({ instance, corbeilleActuelle, suiviPointages = [], doublons = [] }: ShowProps) => {
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
                                                {corbeilleActuelle?.label && (
                                                    <Badge color={corbeilleBadgeColor(corbeilleActuelle.code)} className="fs-14 px-3 py-2">
                                                        <i className="ri-inbox-line align-bottom me-1"></i> {corbeilleActuelle.label}
                                                    </Badge>
                                                )}
                                                <Link href={`/inscriptions/${instance.id}/edit`} className="btn btn-sm btn-soft-primary">
                                                    <i className="ri-edit-2-line align-bottom me-1"></i> Modifier
                                                </Link>
                                            </div>
                                        </div>
                                    </Row>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    {doublons.length > 0 && (
                        <Row>
                            <Col lg={12}>
                                <Alert color="danger" className="d-flex align-items-start gap-2">
                                    <i className="ri-error-warning-line fs-18 align-middle"></i>
                                    <div>
                                        <h6 className="alert-heading mb-1">Pare-feu doublons DESSE — dossier concerné</h6>
                                        <div className="mb-1">Ce stagiaire partage un ou plusieurs critères avec un autre bénéficiaire, encore non tranché par la DESSE :</div>
                                        <div className="hstack gap-2 flex-wrap">
                                            {doublons.map((d) => (
                                                <Badge key={d.type} color="danger" className="fs-12">{d.label}</Badge>
                                            ))}
                                        </div>
                                    </div>
                                </Alert>
                            </Col>
                        </Row>
                    )}

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

                            {/* SUIVI CORBEILLES & PAIEMENT CARD */}
                            {suiviPointages.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0"><i className="ri-inbox-line align-middle me-1 text-muted"></i> Positionnement dans le Workflow (Corbeilles &amp; Paiement)</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <div className="table-responsive">
                                            <Table className="table-nowrap align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Mois</th>
                                                        <th>Nature</th>
                                                        <th>Statut Pointage</th>
                                                        <th>Corbeille</th>
                                                        <th>Visa DESSE</th>
                                                        <th>Paiement</th>
                                                        <th>Dossier / OP / Bordereau</th>
                                                        <th>État</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {suiviPointages.map((p) => (
                                                        <React.Fragment key={p.pointage_id}>
                                                        <tr>
                                                            <td className="fw-medium">{p.mois || 'N/A'}</td>
                                                            <td>{p.nature || 'N/A'}</td>
                                                            <td><Badge color="light" className="text-body">{p.statut_pointage || 'N/A'}</Badge></td>
                                                            <td>
                                                                {p.corbeille?.label ? (
                                                                    <Badge color={corbeilleBadgeColor(p.corbeille.code)}>{p.corbeille.label}</Badge>
                                                                ) : (
                                                                    <span className="text-muted">—</span>
                                                                )}
                                                            </td>
                                                            <td>
                                                                {p.visa_desse?.code ? (
                                                                    <Badge color={p.visa_desse.code === 'VISE' ? 'success' : p.visa_desse.code === 'REJETE' ? 'danger' : 'warning'}>
                                                                        {p.visa_desse.label}
                                                                    </Badge>
                                                                ) : (
                                                                    <span className="text-muted">Non soumis</span>
                                                                )}
                                                            </td>
                                                            <td>
                                                                {p.paiement ? (
                                                                    <div className="hstack gap-1 flex-wrap">
                                                                        <Badge color={statutPaiementBadgeColor(p.paiement.statut)}>{p.paiement.statut_label || p.paiement.statut}</Badge>
                                                                        {p.paiement.statut_dossier_physique && (
                                                                            <Badge color="light" className="text-body">Physique : {p.paiement.statut_dossier_physique}</Badge>
                                                                        )}
                                                                        <span className="text-muted fs-12">{Number(p.paiement.montant).toLocaleString('fr-FR')} FCFA</span>
                                                                    </div>
                                                                ) : (
                                                                    <span className="text-muted">Aucun paiement généré</span>
                                                                )}
                                                            </td>
                                                            <td>
                                                                <div className="hstack gap-1 flex-wrap">
                                                                    {p.dossier && (
                                                                        <Badge color={statutPaiementBadgeColor(p.dossier.statut)} className="fs-11">
                                                                            Dossier {p.dossier.numero} ({p.dossier.statut})
                                                                        </Badge>
                                                                    )}
                                                                    {p.groupe && (
                                                                        <Badge color="secondary" className="fs-11">Multi-dossier {p.groupe.numero}</Badge>
                                                                    )}
                                                                    {p.ordre_paiement && (
                                                                        <Badge color={statutPaiementBadgeColor(p.ordre_paiement.statut)} className="fs-11">
                                                                            OP {p.ordre_paiement.numero} ({p.ordre_paiement.statut})
                                                                        </Badge>
                                                                    )}
                                                                    {p.bordereau && (
                                                                        <Badge color={statutPaiementBadgeColor(p.bordereau.statut)} className="fs-11">
                                                                            Bordereau {p.bordereau.numero} ({p.bordereau.statut})
                                                                        </Badge>
                                                                    )}
                                                                    {!p.dossier && !p.ordre_paiement && !p.bordereau && (
                                                                        <span className="text-muted">—</span>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <Badge color={p.etat_paiement?.paye ? 'success' : p.etat_paiement?.code === 'EN_COURS' ? 'info' : p.etat_paiement?.code === 'SANS_PAIEMENT' ? 'light' : 'danger'}>
                                                                    {p.etat_paiement?.label}
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                        <tr className="bg-light">
                                                            <td colSpan={8} className="text-wrap">
                                                                {p.dernier_retour && (
                                                                    <Alert color="warning" className="py-2 mb-2 fs-13">
                                                                        <strong>Dernier retour — {p.dernier_retour.decision}</strong> : {p.dernier_retour.motif}
                                                                        <span className="text-muted"> ({p.dernier_retour.auteur || 'auteur inconnu'})</span>
                                                                    </Alert>
                                                                )}
                                                                <div className="hstack gap-1 flex-wrap">
                                                                    {p.etapes?.map((etape) => {
                                                                        const etat = ETAT_ETAPE[etape.etat] ?? ETAT_ETAPE.a_venir;

                                                                        return (
                                                                            <Badge
                                                                                key={etape.code}
                                                                                color={etat.color}
                                                                                className={`fs-11 ${etat.color === 'light' ? 'text-body' : ''}`}
                                                                                title={`${etat.label} — ${etape.acteur}`}
                                                                            >
                                                                                <i className={`${etat.icone} me-1`}></i>{etape.label}
                                                                            </Badge>
                                                                        );
                                                                    })}
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        </React.Fragment>
                                                    ))}
                                                </tbody>
                                            </Table>
                                        </div>
                                    </CardBody>
                                </Card>
                            )}

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
                                            {evenements && evenements.length > 0 ? evenements.map((evt: any, idx: number) => {
                                                const sourceName = evt.etape_source?.nom || evt.etapeSource?.nom;
                                                const cibleName = evt.etape_cible?.nom || evt.etapeCible?.nom;
                                                const message = evt.donnees?.message || evt.donnees?.commentaire;
                                                
                                                return (
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
                                                                    <h6 className="fs-14 mb-1 fw-semibold">
                                                                        {cibleName ? `Passage à : ${cibleName}` : (evt.action || 'Mise à jour du dossier')}
                                                                    </h6>
                                                                    <small className="text-muted">{formatDateTime(evt.survenu_le)}</small>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <div id={`collapse${idx}`} className="accordion-collapse collapse show" aria-labelledby={`heading${idx}`} data-bs-parent="#accordionExample">
                                                        <div className="accordion-body ms-2 ps-5 pt-0">
                                                            <div className="mb-2">
                                                                <span className="fw-medium">Par : </span> {evt.acteur?.nom} {evt.acteur?.prenoms || evt.acteur?.prenom}
                                                            </div>
                                                            {sourceName && (
                                                                <div className="mb-2">
                                                                    <span className="fw-medium">Étape précédente : </span> <span className="badge bg-light text-body">{sourceName}</span>
                                                                </div>
                                                            )}
                                                            {message && (
                                                                <div className="mt-2 p-2 bg-light rounded">
                                                                    <i className="ri-message-2-line text-muted me-2"></i>
                                                                    <span className="text-muted fst-italic">{message}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            )
}) : (
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
