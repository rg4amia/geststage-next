import { Head, router } from '@inertiajs/react';
import classnames from 'classnames';
import React, { useState } from 'react';
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
    TabContent,
    TabPane,
} from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

interface LigneStage {
    id: number;
    beneficiaire: { nom: string; prenoms: string; matricule: string };
    entreprise: string;
    agence: string;
    source_financement: string;
    type_stage: string;
    date_debut: string;
    date_fin_prevue: string;
    visa_desse: string | null;
    visa_desse_label: string | null;
    motif_visa_desse: string | null;
    visa_desse_le: string | null;
}

interface Props {
    onglet: string;
    stages: { data: LigneStage[]; total: number };
    compteurs: { attente: number; rejetes: number; vises: number };
    filters: Record<string, string>;
    visaLabels: Record<string, string>;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
}

const ONGLETS = [
    { cle: 'attente', libelle: 'En attente de visa', couleur: 'warning' },
    { cle: 'rejetes', libelle: 'Rejetés', couleur: 'danger' },
    { cle: 'vises', libelle: 'Visés', couleur: 'success' },
] as const;

type CleOnglet = (typeof ONGLETS)[number]['cle'];

const DesseVisasIndex = ({
    onglet = 'attente',
    stages,
    compteurs,
    filters = {},
    agences = {},
    entreprises = {},
    typesfinancements = {},
    typestages = {},
}: Props) => {
    const [recherche, setRecherche] = useState(filters.recherche ?? '');
    const [aRejeter, setARejeter] = useState<LigneStage | null>(null);
    const [motif, setMotif] = useState('');
    const [enCours, setEnCours] = useState(false);

    const naviguer = (params: Record<string, string>) => {
        router.get('/desse/visas', { ...filters, onglet, ...params }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const viser = (ligne: LigneStage) => {
        router.post(`/desse/visas/${ligne.id}/viser`, {}, { preserveScroll: true });
    };

    const remettreEnAttente = (ligne: LigneStage) => {
        router.post(
            `/desse/visas/${ligne.id}/remettre-en-attente`,
            {},
            { preserveScroll: true },
        );
    };

    const fermerModal = () => {
        setARejeter(null);
        setMotif('');
    };

    const confirmerRejet = () => {
        if (!aRejeter) {
return;
}

        setEnCours(true);
        router.post(
            `/desse/visas/${aRejeter.id}/rejeter`,
            { motif },
            {
                preserveScroll: true,
                onFinish: () => {
                    setEnCours(false);
                    fermerModal();
                },
            },
        );
    };

    const colonnes = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'beneficiaire.nom',
            cell: (cell: any) => {
                const b = cell.row.original.beneficiaire;

                return (
                    <div>
                        <h5 className="fs-14 mb-1">
                            {b?.nom} {b?.prenoms}
                        </h5>
                        <p className="text-muted mb-0">{b?.matricule || '-'}</p>
                    </div>
                );
            },
        },
        { header: 'Entreprise', accessorKey: 'entreprise' },
        { header: 'Agence', accessorKey: 'agence' },
        { header: 'Financement', accessorKey: 'source_financement' },
        { header: 'Début', accessorKey: 'date_debut' },
        { header: 'Fin prévue', accessorKey: 'date_fin_prevue' },
        ...(onglet === 'attente'
            ? [
                  {
                      header: 'Actions',
                      cell: (cell: any) => (
                          <div className="hstack gap-2">
                              <Button
                                  color="success"
                                  size="sm"
                                  onClick={() => viser(cell.row.original)}
                              >
                                  Viser
                              </Button>
                              <Button
                                  color="danger"
                                  size="sm"
                                  outline
                                  onClick={() => setARejeter(cell.row.original)}
                              >
                                  Rejeter
                              </Button>
                          </div>
                      ),
                  },
              ]
            : []),
        ...(onglet === 'rejetes'
            ? [
                  {
                      header: 'Motif du rejet',
                      accessorKey: 'motif_visa_desse',
                      cell: (cell: any) => (
                          <span className="text-muted">
                              {cell.row.original.motif_visa_desse || '-'}
                          </span>
                      ),
                  },
                  {
                      header: 'Actions',
                      cell: (cell: any) => (
                          <Button
                              color="primary"
                              size="sm"
                              onClick={() => remettreEnAttente(cell.row.original)}
                          >
                              Remettre en attente
                          </Button>
                      ),
                  },
              ]
            : []),
        ...(onglet === 'vises'
            ? [{ header: 'Visé le', accessorKey: 'visa_desse_le' }]
            : []),
    ];

    return (
        <React.Fragment>
            <Head title="Espace DESSE - Visa des dossiers" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Visa des dossiers" pageTitle="DESSE" />

                    <Card>
                        <CardHeader>
                            <h4 className="card-title mb-0">
                                Dossiers validés par le chef d’agence
                            </h4>
                        </CardHeader>
                        <CardBody>
                            <Row className="g-2 mb-3">
                                <Col md={3}>
                                    <Input
                                        placeholder="Nom, prénoms ou n° AEJ..."
                                        value={recherche}
                                        onChange={(e) => setRecherche(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                naviguer({ recherche });
                                            }
                                        }}
                                    />
                                </Col>
                                <Col md={3}>
                                    <Input
                                        type="select"
                                        value={filters.agence_id ?? ''}
                                        onChange={(e) => naviguer({ agence_id: e.target.value })}
                                    >
                                        <option value="">Toutes les agences</option>
                                        {Object.entries(agences).map(([id, nom]) => (
                                            <option key={id} value={id}>
                                                {nom}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Input
                                        type="select"
                                        value={filters.source_financement_id ?? ''}
                                        onChange={(e) =>
                                            naviguer({ source_financement_id: e.target.value })
                                        }
                                    >
                                        <option value="">Tous les financements</option>
                                        {Object.entries(typesfinancements).map(([id, nom]) => (
                                            <option key={id} value={id}>
                                                {nom}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                                <Col md={3}>
                                    <Input
                                        type="select"
                                        value={filters.type_stage_id ?? ''}
                                        onChange={(e) => naviguer({ type_stage_id: e.target.value })}
                                    >
                                        <option value="">Tous les types de stage</option>
                                        {Object.entries(typestages).map(([id, nom]) => (
                                            <option key={id} value={id}>
                                                {nom}
                                            </option>
                                        ))}
                                    </Input>
                                </Col>
                            </Row>

                            <Nav tabs className="nav-tabs-custom nav-success nav-justified mb-3">
                                {ONGLETS.map((o) => (
                                    <NavItem key={o.cle}>
                                        <NavLink
                                            style={{ cursor: 'pointer' }}
                                            className={classnames({ active: onglet === o.cle })}
                                            onClick={() => naviguer({ onglet: o.cle })}
                                        >
                                            {o.libelle}{' '}
                                            <Badge color={o.couleur} className="ms-1">
                                                {compteurs[o.cle as CleOnglet]}
                                            </Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={onglet}>
                                <TabPane tabId={onglet}>
                                    <TableContainerReactTable
                                        columns={colonnes}
                                        data={stages?.data ?? []}
                                        isGlobalFilter={false}
                                        customPageSize={25}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light"
                                    />
                                    <p className="text-muted mb-0">
                                        {stages?.total ?? 0} dossier(s)
                                    </p>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={aRejeter !== null} toggle={fermerModal}>
                <ModalHeader toggle={fermerModal}>Refuser le visa</ModalHeader>
                <ModalBody>
                    <p>
                        Dossier de{' '}
                        <strong>
                            {aRejeter?.beneficiaire?.nom} {aRejeter?.beneficiaire?.prenoms}
                        </strong>
                        .
                    </p>
                    <Label for="motif">Motif du rejet</Label>
                    <Input
                        id="motif"
                        type="textarea"
                        rows={4}
                        value={motif}
                        onChange={(e) => setMotif(e.target.value)}
                        placeholder="Ce motif indique au CIP ce qu’il doit corriger."
                    />
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={fermerModal}>
                        Annuler
                    </Button>
                    <Button
                        color="danger"
                        onClick={confirmerRejet}
                        disabled={enCours || motif.trim().length < 5}
                    >
                        {enCours ? 'Envoi...' : 'Refuser le visa'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default DesseVisasIndex;
