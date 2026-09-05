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
    jours_restants: number | null;
    avenant_id: number | null;
    motif_ajournement: string | null;
}

interface Props {
    onglet: string;
    stages: { data: LigneStage[]; total: number };
    compteurs: { attente: number; anticipe: number; ajourne: number };
    filters: Record<string, string>;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
}

const ONGLETS = [
    { cle: 'attente', libelle: 'À renouveler', couleur: 'warning' },
    { cle: 'anticipe', libelle: 'Renouvellement anticipé', couleur: 'info' },
    { cle: 'ajourne', libelle: 'Ajournés par le CA', couleur: 'danger' },
] as const;

type CleOnglet = (typeof ONGLETS)[number]['cle'];

const CipRenouvellementsIndex = ({
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
    const [aRenouveler, setARenouveler] = useState<LigneStage | null>(null);
    const [dureeMois, setDureeMois] = useState('6');
    const [motif, setMotif] = useState('');
    const [enCours, setEnCours] = useState(false);

    const naviguer = (params: Record<string, string>) => {
        router.get('/cip/renouvellements', { ...filters, onglet, ...params }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const fermerModal = () => {
        setARenouveler(null);
        setMotif('');
        setDureeMois('6');
    };

    const confirmerRenouvellement = () => {
        if (!aRenouveler) {
return;
}

        setEnCours(true);
        router.post(
            `/cip/renouvellements/${aRenouveler.id}/renouveler`,
            { duree_mois: Number(dureeMois), motif },
            {
                preserveScroll: true,
                onFinish: () => {
                    setEnCours(false);
                    fermerModal();
                },
            },
        );
    };

    const renvoyerAuChefAgence = (ligne: LigneStage) => {
        if (!ligne.avenant_id) {
return;
}

        router.post(
            `/cip/renouvellements/avenant/${ligne.avenant_id}/renvoyer`,
            {},
            { preserveScroll: true },
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
        {
            header: 'Fin prévue',
            accessorKey: 'date_fin_prevue',
            cell: (cell: any) => {
                const { date_fin_prevue: fin, jours_restants: restants } = cell.row.original;

                return (
                    <div>
                        <span>{fin}</span>
                        {onglet === 'anticipe' && restants !== null ? (
                            <Badge color="info" className="ms-2">
                                J-{restants}
                            </Badge>
                        ) : null}
                    </div>
                );
            },
        },
        ...(onglet === 'ajourne'
            ? [
                  {
                      header: 'Motif',
                      accessorKey: 'motif_ajournement',
                      cell: (cell: any) => (
                          <span className="text-muted">
                              {cell.row.original.motif_ajournement || '-'}
                          </span>
                      ),
                  },
                  {
                      header: 'Actions',
                      cell: (cell: any) => (
                          <Button
                              color="primary"
                              size="sm"
                              onClick={() => renvoyerAuChefAgence(cell.row.original)}
                          >
                              Renvoyer au CA
                          </Button>
                      ),
                  },
              ]
            : [
                  {
                      header: 'Actions',
                      cell: (cell: any) => (
                          <Button
                              color="success"
                              size="sm"
                              onClick={() => setARenouveler(cell.row.original)}
                          >
                              Renouveler
                          </Button>
                      ),
                  },
              ]),
    ];

    return (
        <React.Fragment>
            <Head title="Espace CIP - Renouvellements" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb
                        title="Renouvellements de contrat"
                        pageTitle="Conseiller d'Insertion (CIP)"
                    />

                    <Card>
                        <CardHeader>
                            <h4 className="card-title mb-0">Avenants de renouvellement</h4>
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
                                        value={filters.entreprise_id ?? ''}
                                        onChange={(e) => naviguer({ entreprise_id: e.target.value })}
                                    >
                                        <option value="">Toutes les entreprises</option>
                                        {Object.entries(entreprises).map(([id, nom]) => (
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
                                        {stages?.total ?? 0} stagiaire(s)
                                    </p>
                                </TabPane>
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={aRenouveler !== null} toggle={fermerModal}>
                <ModalHeader toggle={fermerModal}>Renouveler le contrat</ModalHeader>
                <ModalBody>
                    <p>
                        Renouvellement du stage de{' '}
                        <strong>
                            {aRenouveler?.beneficiaire?.nom} {aRenouveler?.beneficiaire?.prenoms}
                        </strong>{' '}
                        (fin actuelle : {aRenouveler?.date_fin_prevue}).
                    </p>
                    <div className="mb-3">
                        <Label for="duree_mois">Durée du renouvellement</Label>
                        <Input
                            id="duree_mois"
                            type="select"
                            value={dureeMois}
                            onChange={(e) => setDureeMois(e.target.value)}
                        >
                            {[1, 2, 3, 6, 9, 12].map((m) => (
                                <option key={m} value={m}>
                                    {m} mois
                                </option>
                            ))}
                        </Input>
                    </div>
                    <div>
                        <Label for="motif">Motif (facultatif)</Label>
                        <Input
                            id="motif"
                            type="textarea"
                            rows={3}
                            value={motif}
                            onChange={(e) => setMotif(e.target.value)}
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={fermerModal}>
                        Annuler
                    </Button>
                    <Button color="success" onClick={confirmerRenouvellement} disabled={enCours}>
                        {enCours ? 'Envoi...' : 'Soumettre au chef d’agence'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CipRenouvellementsIndex;
