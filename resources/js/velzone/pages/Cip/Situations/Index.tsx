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
    observations: string | null;
}

interface Props {
    onglet: string;
    stages: { data: LigneStage[]; total: number };
    compteurs: { abandon: number; suspension: number };
    filters: Record<string, string>;
    agences: Record<string, string>;
    entreprises: Record<string, string>;
    typesfinancements: Record<string, string>;
    typestages: Record<string, string>;
}

const ONGLETS = [
    { cle: 'suspension', libelle: 'Suspensions', couleur: 'warning' },
    { cle: 'abandon', libelle: 'Abandons', couleur: 'danger' },
] as const;

const CipSituationsIndex = ({
    onglet = 'suspension',
    stages,
    compteurs,
    filters = {},
    agences = {},
    entreprises = {},
    typesfinancements = {},
    typestages = {},
}: Props) => {
    const [recherche, setRecherche] = useState(filters.recherche ?? '');
    const [aReactiver, setAReactiver] = useState<LigneStage | null>(null);
    const [enCours, setEnCours] = useState(false);

    const naviguer = (params: Record<string, string>) => {
        router.get('/cip/situation-stagiaire', { ...filters, onglet, ...params }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const confirmerReactivation = () => {
        if (!aReactiver) return;
        setEnCours(true);
        router.post(`/cip/situation-stagiaire/${aReactiver.id}/reactiver`, {}, {
            preserveScroll: true,
            onFinish: () => {
                setEnCours(false);
                setAReactiver(null);
            },
        });
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
        ...(onglet === 'suspension'
            ? [
                  {
                      header: 'Actions',
                      cell: (cell: any) => (
                          <Button
                              color="success"
                              size="sm"
                              onClick={() => setAReactiver(cell.row.original)}
                          >
                              Réactiver
                          </Button>
                      ),
                  },
              ]
            : []),
    ];

    return (
        <React.Fragment>
            <Head title="Espace CIP - Situation des stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb
                        title="Situation des stagiaires"
                        pageTitle="Conseiller d'Insertion (CIP)"
                    />

                    <Card>
                        <CardHeader>
                            <h4 className="card-title mb-0">
                                Stagiaires sortis du pointage courant
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
                                                {compteurs[o.cle]}
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

            <Modal isOpen={aReactiver !== null} toggle={() => setAReactiver(null)}>
                <ModalHeader toggle={() => setAReactiver(null)}>
                    Réactiver la suspension
                </ModalHeader>
                <ModalBody>
                    <p className="mb-0">
                        Réactiver le stage de{' '}
                        <strong>
                            {aReactiver?.beneficiaire?.nom} {aReactiver?.beneficiaire?.prenoms}
                        </strong>{' '}
                        ? La date de fin sera repoussée du nombre de mois suspendus.
                    </p>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={() => setAReactiver(null)}>
                        Annuler
                    </Button>
                    <Button color="success" onClick={confirmerReactivation} disabled={enCours}>
                        {enCours ? 'Réactivation...' : 'Confirmer'}
                    </Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default CipSituationsIndex;
