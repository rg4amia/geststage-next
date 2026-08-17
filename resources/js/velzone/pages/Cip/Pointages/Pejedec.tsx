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

const PointagesPejedec = ({
    attente = [],
    effectues = [],
    ajournesCA = [],
    ajournesDMG = [],
    moisActuel,
    periode,
    sourceFinancement,
}: any) => {
    const [activeTab, setActiveTab] = useState('1');
    const [modalOpen, setModalOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [selectedAction, setSelectedAction] = useState<any>(null);
    const [joursPresents, setJoursPresents] = useState(30);
    const [joursAbsents, setJoursAbsents] = useState(0);
    const [motif, setMotif] = useState('');

    const openSubmissionModal = (item: any, mode: 'submit' | 'resubmit' | 'correct') => {
        setSelectedAction({ item, mode });
        setJoursPresents(item?.versionCourante?.jours_presents ?? 30);
        setJoursAbsents(item?.versionCourante?.jours_absents ?? 0);
        setMotif(item?.versionCourante?.observation ?? '');
        setModalOpen(true);
    };

    const closeModal = () => {
        if (!submitting) {
            setModalOpen(false);
            setSelectedAction(null);
        }
    };

    const submitPointage = () => {
        if (!selectedAction?.item?.stage?.id || !periode?.id) {
            return;
        }

        setSubmitting(true);
        router.post(
            `/cip/pointages/soumettre/${selectedAction.item.stage.id}`,
            {
                periode_id: periode.id,
                jours_presents: joursPresents,
                jours_absents: joursAbsents,
                observation: motif || undefined,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setModalOpen(false);
                    setSelectedAction(null);
                },
            },
        );
    };

    const correctDmgPointage = () => {
        if (!selectedAction?.item?.id) {
            return;
        }

        setSubmitting(true);
        router.post(
            `/cip/pointages/corriger-ajournement-dmg/${selectedAction.item.id}`,
            { motif: motif || undefined },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    setModalOpen(false);
                    setSelectedAction(null);
                },
            },
        );
    };

    const statusBadge = (item: any, fallback = 'A Saisir') => {
        const statut = item?.statut || fallback;

        if (statut === 'SOUMIS') {
return <span className="badge bg-info-subtle text-info">Soumis</span>;
}

        if (statut === 'VALIDE') {
return <span className="badge bg-success-subtle text-success">Validé</span>;
}

        if (statut === 'AJOURNE_CA') {
return <span className="badge bg-warning-subtle text-warning">Ajourné CA</span>;
}

        if (statut === 'AJOURNE_DMG') {
return <span className="badge bg-danger-subtle text-danger">Ajourné DMG</span>;
}

        if (statut === 'CORRIGE_CIP') {
return <span className="badge bg-primary-subtle text-primary">Corrigé CIP</span>;
}

        return <span className="badge bg-secondary-subtle text-secondary">{fallback}</span>;
    };

    const buildColumns = (kind: 'waiting' | 'submitted' | 'ajourne-ca' | 'ajourne-dmg') => [
        {
            header: 'Bénéficiaire',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => (
                <div>
                    <h5 className="fs-14 mb-1">
                        {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                    </h5>
                    <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                </div>
            ),
        },
        {
            header: 'Entreprise',
            accessorKey: 'stage.entreprise.raison_sociale',
            cell: (cell: any) => cell.getValue() || '-',
        },
        {
            header: 'Financement',
            cell: (cell: any) => (
                <Badge color="primary" className="text-uppercase">
                    {cell.row.original.stage?.sourceFinancement?.nom || sourceFinancement?.nom || 'PEJEDEC'}
                </Badge>
            ),
        },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => statusBadge(cell.row.original, kind === 'waiting' ? 'A Saisir' : '-'),
        },
        {
            header: 'Jours',
            cell: (cell: any) => {
                if (kind === 'waiting') {
                    return '-';
                }

                return cell.row.original.versionCourante?.jours_presents ?? '-';
            },
        },
        {
            header: 'Action',
            cell: (cell: any) => {
                if (kind === 'waiting') {
                    return (
                        <Button color="success" size="sm" onClick={() => openSubmissionModal(cell.row.original, 'submit')} disabled={!periode?.id}>
                            Soumettre
                        </Button>
                    );
                }

                if (kind === 'ajourne-ca') {
                    return (
                        <Button color="warning" size="sm" onClick={() => openSubmissionModal(cell.row.original, 'resubmit')} disabled={!periode?.id}>
                            Resoumettre
                        </Button>
                    );
                }

                if (kind === 'ajourne-dmg') {
                    return (
                        <Button color="primary" size="sm" onClick={() => openSubmissionModal(cell.row.original, 'correct')}>
                            Corriger DMG
                        </Button>
                    );
                }

                return <span className="text-muted">Consultation</span>;
            },
        },
    ];

    const tabs = [
        { id: '1', label: 'A saisir', data: attente, kind: 'waiting' as const },
        { id: '2', label: 'Soumis', data: effectues, kind: 'submitted' as const },
        { id: '3', label: 'Ajournés CA', data: ajournesCA, kind: 'ajourne-ca' as const },
        { id: '4', label: 'Ajournés DMG', data: ajournesDMG, kind: 'ajourne-dmg' as const },
    ];

    const actionTitle = selectedAction?.mode === 'correct'
        ? 'Correction DMG'
        : selectedAction?.mode === 'resubmit'
            ? 'Resoumission du pointage'
            : 'Soumission du pointage';

    const actionDescription = selectedAction?.mode === 'correct'
        ? 'Le pointage retourne au flux CA avec le statut corrigé.'
        : 'La saisie relance le pointage dans le flux normal PEJEDEC.';

    return (
        <React.Fragment>
            <Head title="PEJEDEC - Pointages CIP" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Pointages PEJEDEC" pageTitle="Espace CIP" />

                    <Row className="mb-4">
                        <Col lg={8}>
                            <Card className="border-0 shadow-sm h-100">
                                <CardBody>
                                    <div className="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div>
                                            <div className="d-flex align-items-center gap-2 mb-2">
                                                <Badge color="primary">PEJEDEC</Badge>
                                                <span className="text-muted">Financement verrouillé</span>
                                            </div>
                                            <h4 className="mb-2">Suivi des pointages PEJEDEC</h4>
                                            <p className="text-muted mb-0">
                                                Le filtre serveur conserve uniquement les stagiaires PEJEDEC et réutilise
                                                le même moteur de pointage que le flux standard.
                                            </p>
                                        </div>
                                        <div className="text-md-end">
                                            <p className="mb-1 text-muted">Mois actif</p>
                                            <h5 className="mb-0">{moisActuel}</h5>
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        </Col>
                        <Col lg={4}>
                            <Card className="border-0 shadow-sm h-100 bg-light">
                                <CardBody>
                                    <p className="text-muted mb-2">Source de financement</p>
                                    <h4 className="mb-1">{sourceFinancement?.nom || 'PEJEDEC'}</h4>
                                    <p className="text-muted mb-0">{sourceFinancement?.code || 'PEJEDEC'}</p>
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>

                    <Row className="g-3 mb-3">
                        {[
                            { label: 'A saisir', value: attente.length, color: 'warning' },
                            { label: 'Soumis', value: effectues.length, color: 'info' },
                            { label: 'Ajournés CA', value: ajournesCA.length, color: 'danger' },
                            { label: 'Ajournés DMG', value: ajournesDMG.length, color: 'secondary' },
                        ].map((item) => (
                            <Col lg={3} md={6} key={item.label}>
                                <Card className="mb-0 shadow-sm">
                                    <CardBody>
                                        <p className="text-muted mb-1">{item.label}</p>
                                        <h3 className={`text-${item.color} mb-0`}>{item.value}</h3>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    <Card className="shadow-sm">
                        <CardHeader className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <div>
                                <h4 className="card-title mb-1">Files PEJEDEC</h4>
                                <p className="text-muted mb-0">Validation, soumission et correction des pointages du programme.</p>
                            </div>
                            <Input type="month" defaultValue={moisActuel} style={{ maxWidth: '180px' }} />
                        </CardHeader>
                        <CardBody>
                            <Nav tabs className="nav-tabs-custom nav-primary nav-justified mb-3">
                                {tabs.map((tab) => (
                                    <NavItem key={tab.id}>
                                        <NavLink
                                            style={{ cursor: 'pointer' }}
                                            className={classnames({ active: activeTab === tab.id })}
                                            onClick={() => setActiveTab(tab.id)}
                                        >
                                            {tab.label} <Badge color="light" className="ms-1 text-dark">{tab.data.length}</Badge>
                                        </NavLink>
                                    </NavItem>
                                ))}
                            </Nav>

                            <TabContent activeTab={activeTab}>
                                {tabs.map((tab) => (
                                    <TabPane key={tab.id} tabId={tab.id}>
                                        <TableContainerReactTable
                                            columns={buildColumns(tab.kind)}
                                            data={tab.data || []}
                                            isGlobalFilter={true}
                                            customPageSize={10}
                                            divClass="table-responsive table-card mb-3"
                                            tableClass="align-middle table-nowrap mb-0"
                                            theadClass="table-light"
                                            SearchPlaceholder="Rechercher..."
                                        />
                                    </TabPane>
                                ))}
                            </TabContent>
                        </CardBody>
                    </Card>
                </Container>
            </div>

            <Modal isOpen={modalOpen} toggle={closeModal} centered>
                <ModalHeader toggle={closeModal}>{actionTitle}</ModalHeader>
                <ModalBody>
                    <p className="text-muted">{actionDescription}</p>

                    {selectedAction?.mode !== 'correct' && (
                        <>
                            <div className="mb-3">
                                <label className="form-label">Jours de présence</label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={31}
                                    value={joursPresents}
                                    onChange={(event) => setJoursPresents(Number(event.target.value))}
                                />
                            </div>
                            <div className="mb-3">
                                <label className="form-label">Jours d'absence</label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={31}
                                    value={joursAbsents}
                                    onChange={(event) => setJoursAbsents(Number(event.target.value))}
                                />
                            </div>
                        </>
                    )}

                    <div className="mb-0">
                        <label className="form-label">Observation</label>
                        <Input
                            type="textarea"
                            rows={4}
                            value={motif}
                            onChange={(event) => setMotif(event.target.value)}
                            placeholder={selectedAction?.mode === 'correct' ? 'Motif de la correction DMG' : 'Observation du pointage'}
                        />
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="light" onClick={closeModal} disabled={submitting}>
                        Annuler
                    </Button>
                    {selectedAction?.mode === 'correct' ? (
                        <Button color="primary" onClick={correctDmgPointage} disabled={submitting}>
                            Corriger et renvoyer
                        </Button>
                    ) : (
                        <Button color="success" onClick={submitPointage} disabled={submitting || !periode?.id}>
                            Soumettre
                        </Button>
                    )}
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default PointagesPejedec;
