import React, { useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Form, FormGroup, Label, Input, Modal, ModalHeader, ModalBody, ModalFooter } from 'reactstrap';
import BreadCrumb from '../../../Components/Common/BreadCrumb';
import TableContainerReactTable from '../../../Components/Common/TableContainerReactTable';

const MesStagiaires = ({ 
    instances, 
    agences, 
    entreprises, 
    typesfinancements, 
    typestages, 
    typestructures, 
    etapes, 
    situationstages, 
    filters 
}: any) => {
    const data = instances || [];
    const [modalAnalyse, setModalAnalyse] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);

    const { data: formData, setData, get } = useForm({
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        typesfinancement_id: filters?.typesfinancement_id || '',
        typestage_id: filters?.typestage_id || '',
        type_structure_id: filters?.type_structure_id || '',
        etape_id: filters?.etape_id || '',
        situationstage_id: filters?.situationstage_id || '',
        date_debut: filters?.date_debut || '',
        date_fin: filters?.date_fin || '',
    });

    const handleSearch = (e: any) => {
        e.preventDefault();
        get(route('cip.mes-stagiaires')); // Assumes route is named this way
    };

    const toggleAnalyse = (stagiaire: any = null) => {
        setSelectedStagiaire(stagiaire);
        setModalAnalyse(!modalAnalyse);
    };

    const columns = useMemo(
        () => [
            {
                header: 'Situation Stage',
                accessorKey: 'stage.situation_stage',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Date',
                accessorKey: 'created_at',
                cell: (cell: any) => {
                    const val = cell.getValue();
                    return val ? new Date(val).toLocaleDateString() : '-';
                },
            },
            {
                header: 'Agence',
                accessorKey: 'stage.agence.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Entreprise',
                accessorKey: 'stage.entreprise.raison_sociale',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Source de financement',
                accessorKey: 'stage.source_financement.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type de stage',
                accessorKey: 'stage.type_stage.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type Structure',
                accessorKey: 'stage.entreprise.type_structure.nom',
                cell: (cell: any) => {
                    const type = cell.getValue();
                    if (!type) return '-';
                    if (type.toUpperCase().includes('NEANT')) {
                        return <span className="badge bg-warning">{type}</span>;
                    }
                    if (type.toUpperCase().includes('PRIVE')) {
                        return <span className="badge bg-primary">{type}</span>;
                    }
                    return <span className="badge bg-success">{type}</span>;
                },
            },
            {
                header: 'Date debut',
                accessorKey: 'stage.date_debut',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Date fin',
                accessorKey: 'stage.date_fin_prevue',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Numéro AEJ',
                accessorKey: 'stage.beneficiaire.numero_aej',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Type Paiement',
                accessorKey: 'stage.beneficiaire.type_paiement_id', // Needs resolution if type_paiement relation is available
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Numéro Trésor Pay',
                accessorKey: 'stage.beneficiaire.numero_tresor_money',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Numéro Wave',
                accessorKey: 'stage.beneficiaire.numero_wave',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Nom et prénoms',
                accessorKey: 'stage.beneficiaire.nom',
                cell: (cell: any) => {
                    const row = cell.row.original.stage?.beneficiaire;
                    return row ? `${row.nom} ${row.prenoms}` : '-';
                },
            },
            {
                header: 'Date de naissance',
                accessorKey: 'stage.beneficiaire.date_naissance',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Sexe',
                accessorKey: 'stage.beneficiaire.sexe',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Contrat',
                accessorKey: 'stage.contrats',
                cell: (cell: any) => {
                    const contrats = cell.getValue();
                    const hasContrat = contrats && contrats.length > 0;
                    return hasContrat ? (
                        <span className="badge bg-success">Avec Contrat</span>
                    ) : (
                        <span className="badge bg-warning">Sans Contrat</span>
                    );
                },
            },
            {
                header: 'Incidence financière',
                accessorKey: 'stage.prime_mensuelle', // Needs real check depending on logic
                cell: (cell: any) => {
                    return <span className="badge bg-primary">Oui</span>; // Example logic, adapt based on real data
                },
            },
            {
                header: 'Etape Traitement',
                accessorKey: 'etape_courante',
                cell: (cell: any) => {
                    return <span className="badge bg-info">{cell.getValue() || '-'}</span>;
                },
            },
            {
                header: 'Action',
                cell: (cell: any) => {
                    return (
                        <div className="d-flex gap-1">
                            <Button color="info" size="sm" className="btn-icon" title="Voir Stagiaire">
                                <i className="ri-eye-line"></i>
                            </Button>
                            <Button color="primary" size="sm" className="btn-icon" title="Pointage" onClick={() => toggleAnalyse(cell.row.original)}>
                                <i className="ri-folder-open-line"></i>
                            </Button>
                            <Button color="success" size="sm" className="btn-icon" title="PDF">
                                <i className="ri-file-pdf-line"></i>
                            </Button>
                            <Button color="warning" size="sm" className="btn-icon" title="Envoyer">
                                <i className="ri-arrow-right-line"></i>
                            </Button>
                        </div>
                    );
                },
            },
        ],
        []
    );

    return (
        <React.Fragment>
            <Head title="Mes Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Mes Stagiaires" pageTitle="Espace CIP" />
                    
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <h4 className="card-title mb-0">Ma Liste de Stagiaires</h4>
                                </CardHeader>
                                <CardBody>
                                    <Form onSubmit={handleSearch} className="mb-4">
                                        <Row className="g-3">
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">AGENCE</Label>
                                                    <Input type="select" value={formData.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                                        <option value="">Sélectionner une agence</option>
                                                        {Object.entries(agences || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">ENTREPRISE</Label>
                                                    <Input type="select" value={formData.entreprise_id} onChange={e => setData('entreprise_id', e.target.value)}>
                                                        <option value="">Sélectionner une entreprise</option>
                                                        {Object.entries(entreprises || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">TYPE DE FINANCEMENT</Label>
                                                    <Input type="select" value={formData.typesfinancement_id} onChange={e => setData('typesfinancement_id', e.target.value)}>
                                                        <option value="">Sélectionner un financement</option>
                                                        {Object.entries(typesfinancements || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">TYPE DE STAGE</Label>
                                                    <Input type="select" value={formData.typestage_id} onChange={e => setData('typestage_id', e.target.value)}>
                                                        <option value="">Sélectionner un type de stage</option>
                                                        {Object.entries(typestages || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">TYPE DE STRUCTURE</Label>
                                                    <Input type="select" value={formData.type_structure_id} onChange={e => setData('type_structure_id', e.target.value)}>
                                                        <option value="">Sélectionner un type de structure</option>
                                                        {Object.entries(typestructures || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">ETAPE TRAITEMENT</Label>
                                                    <Input type="select" value={formData.etape_id} onChange={e => setData('etape_id', e.target.value)}>
                                                        <option value="">Sélectionner une étape</option>
                                                        {Object.entries(etapes || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">SITUATION DE STAGE</Label>
                                                    <Input type="select" value={formData.situationstage_id} onChange={e => setData('situationstage_id', e.target.value)}>
                                                        <option value="">Sélectionner une situation</option>
                                                        {Object.entries(situationstages || {}).map(([id, label]) => (
                                                            <option key={id} value={id}>{String(label)}</option>
                                                        ))}
                                                    </Input>
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">DATE DEBUT</Label>
                                                    <Input type="date" value={formData.date_debut} onChange={e => setData('date_debut', e.target.value)} />
                                                </FormGroup>
                                            </Col>
                                            <Col md={4}>
                                                <FormGroup>
                                                    <Label className="fw-bold">DATE FIN</Label>
                                                    <Input type="date" value={formData.date_fin} onChange={e => setData('date_fin', e.target.value)} />
                                                </FormGroup>
                                            </Col>
                                            <Col md={12} className="text-end">
                                                <Button color="success" type="submit" className="w-100" style={{background: 'linear-gradient(135deg, #28a745, #20c997)', border: 'none'}}>
                                                    <i className="ri-search-line me-1"></i> RECHERCHER
                                                </Button>
                                            </Col>
                                        </Row>
                                    </Form>

                                    <TableContainerReactTable
                                        columns={columns}
                                        data={data}
                                        isGlobalFilter={true}
                                        customPageSize={10}
                                        divClass="table-responsive table-card mb-3"
                                        tableClass="align-middle table-nowrap mb-0"
                                        theadClass="table-light bg-success text-white"
                                        SearchPlaceholder="Rechercher..."
                                    />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            <Modal isOpen={modalAnalyse} toggle={() => toggleAnalyse()} size="xl">
                <ModalHeader toggle={() => toggleAnalyse()} className="bg-info text-white">
                    Analyse donnée stagiaire
                </ModalHeader>
                <ModalBody>
                    <div className="legend-container mb-4 p-3 bg-light rounded border">
                        <h6 className="mb-3 text-muted"><i className="ri-information-line me-1"></i> Légende - Comprendre le suivi du pointage</h6>
                        <Row>
                            <Col md={6}>
                                <h6 className="text-muted mb-2"><i className="ri-checkbox-blank-circle-fill fs-10 me-1"></i> Statuts des étapes</h6>
                                <div className="d-flex flex-wrap gap-2">
                                    <span className="badge bg-success rounded-pill px-3 py-2"><i className="ri-check-line me-1"></i> Validé/Complété</span>
                                    <span className="badge bg-primary rounded-pill px-3 py-2"><i className="ri-time-line me-1"></i> En cours/Attente</span>
                                    <span className="badge bg-danger rounded-pill px-3 py-2"><i className="ri-close-line me-1"></i> Rejeté/Ajourné</span>
                                    <span className="badge bg-warning rounded-pill px-3 py-2 text-dark"><i className="ri-pause-line me-1"></i> Différé</span>
                                    <span className="badge bg-secondary rounded-pill px-3 py-2"><i className="ri-more-line me-1"></i> En attente</span>
                                </div>
                            </Col>
                            <Col md={6}>
                                <h6 className="text-muted mb-2"><i className="ri-route-line me-1"></i> Flux de traitement</h6>
                                <div className="p-2 bg-white rounded shadow-sm" style={{fontSize: '0.85rem', lineHeight: '1.6'}}>
                                    <strong>1. Agence Rég.</strong> → Pointage par Agent de Saisie, validation Chef d'Agence<br/>
                                    <strong>2. DESSE</strong> → Vérification des doublons par la Direction des Études, de la Statistique et de l'Évaluation<br/>
                                    <strong>3. DMG</strong> → Validation du pointage par la Direction des Moyens Généraux<br/>
                                    <strong>4. CB</strong> → Validation du dossier par le Contrôleur Budgétaire<br/>
                                    <strong>5. DMG-OP</strong> → Élaboration des Ordres de Paiement et Bordereaux<br/>
                                    <strong>6. AC</strong> → Validation et paiement par l'Agent Comptable
                                </div>
                            </Col>
                        </Row>
                    </div>

                    <div className="table-responsive">
                        <table className="table align-middle table-nowrap">
                            <thead className="table-light">
                                <tr>
                                    <th>Mois</th>
                                    <th>Étape Traitement</th>
                                    <th>Étape DESSE</th>
                                    <th>N° OP</th>
                                    <th>N° Bordereau</th>
                                    <th>Dossier</th>
                                    <th>Position du Traitement</th>
                                    <th>Situation Stage</th>
                                    <th>Date Pointage</th>
                                </tr>
                            </thead>
                            <tbody>
                                {/* Example row, replace with real pointage data when available on stagiaire */}
                                <tr>
                                    <td colSpan={9} className="text-center text-muted p-4">
                                        Sélectionnez les pointages du stagiaire {selectedStagiaire?.stage?.beneficiaire?.nom} pour les afficher ici.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </ModalBody>
                <ModalFooter>
                    <Button color="secondary" onClick={() => toggleAnalyse()}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default MesStagiaires;
