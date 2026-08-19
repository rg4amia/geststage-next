import React, { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { format } from 'date-fns';
import { fr } from 'date-fns/locale';
import BreadCrumb from '@/velzone/components/common/BreadCrumb';
import AuthenticatedLayout from '@/velzone/layouts/AuthenticatedLayout';
import { Card, CardBody, CardHeader, Col, Container, Row, Table, Badge, Spinner } from 'reactstrap';

interface HistoriqueItem {
    id: number;
    uuid_public: string;
    type_document: 'CONTRAT' | 'TRESOR_MONEY' | 'ADD';
    nom_fichier: string;
    parametres: Record<string, any>;
    source_financement: string | null;
    type_stage: string | null;
    nombre_stagiaires: number;
    created_at: string;
    user: {
        id: number;
        name: string;
    } | null;
    stage: {
        id: number;
        beneficiaire: {
            nom: string;
            prenoms: string;
            numero_aej: string;
        };
    } | null;
}

interface Statistiques {
    total_generations: number;
    par_type: Array<{
        type_document: string;
        total: number;
        total_stagiaires: number;
    }>;
    par_utilisateur: Array<{
        user_id: number;
        total: number;
        user: {
            id: number;
            name: string;
        };
    }>;
    dernieres_24h: number;
    derniere_semaine: number;
}

const HistoriqueGenerationIndex: React.FC = () => {
    const [historique, setHistorique] = useState<HistoriqueItem[]>([]);
    const [stats, setStats] = useState<Statistiques | null>(null);
    const [loading, setLoading] = useState(true);
    const [filtreType, setFiltreType] = useState<string>('');
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    useEffect(() => {
        chargerHistorique();
        chargerStatistiques();
    }, [filtreType, page]);

    const chargerHistorique = async () => {
        setLoading(true);
        try {
            const params: any = { page };
            if (filtreType) {
                params.type_document = filtreType;
            }

            const response = await axios.get('/chefagence/historique-generations', { params });
            setHistorique(response.data.data);
            setTotalPages(response.data.last_page);
        } catch (error) {
            console.error('Erreur chargement historique:', error);
        } finally {
            setLoading(false);
        }
    };

    const chargerStatistiques = async () => {
        try {
            const response = await axios.get('/chefagence/historique-generations/statistiques/global');
            setStats(response.data);
        } catch (error) {
            console.error('Erreur chargement statistiques:', error);
        }
    };

    const getTypeBadge = (type: string) => {
        const badges: Record<string, { color: string; label: string }> = {
            CONTRAT: { color: 'primary', label: 'Contrat' },
            TRESOR_MONEY: { color: 'success', label: 'Trésor Money' },
            ADD: { color: 'info', label: 'ADD' },
        };
        return badges[type] || { color: 'secondary', label: type };
    };

    const formatDate = (dateString: string) => {
        return format(new Date(dateString), 'dd/MM/yyyy HH:mm', { locale: fr });
    };

    return (
        <AuthenticatedLayout>
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Historique des Générations" pageTitle="Chef d'Agence" />

                    {/* Statistiques */}
                    {stats && (
                        <Row className="mb-4">
                            <Col lg={3} md={6}>
                                <Card className="card-animate">
                                    <CardBody>
                                        <div className="d-flex align-items-center">
                                            <div className="flex-grow-1">
                                                <p className="text-uppercase fw-medium text-muted mb-0">Total Générations</p>
                                            </div>
                                        </div>
                                        <div className="d-flex align-items-end justify-content-between mt-3">
                                            <div>
                                                <h4 className="fs-22 fw-semibold ff-secondary mb-0">
                                                    {stats.total_generations}
                                                </h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>

                            <Col lg={3} md={6}>
                                <Card className="card-animate">
                                    <CardBody>
                                        <div className="d-flex align-items-center">
                                            <div className="flex-grow-1">
                                                <p className="text-uppercase fw-medium text-muted mb-0">Dernières 24h</p>
                                            </div>
                                        </div>
                                        <div className="d-flex align-items-end justify-content-between mt-3">
                                            <div>
                                                <h4 className="fs-22 fw-semibold ff-secondary mb-0">
                                                    {stats.dernieres_24h}
                                                </h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>

                            <Col lg={3} md={6}>
                                <Card className="card-animate">
                                    <CardBody>
                                        <div className="d-flex align-items-center">
                                            <div className="flex-grow-1">
                                                <p className="text-uppercase fw-medium text-muted mb-0">Dernière Semaine</p>
                                            </div>
                                        </div>
                                        <div className="d-flex align-items-end justify-content-between mt-3">
                                            <div>
                                                <h4 className="fs-22 fw-semibold ff-secondary mb-0">
                                                    {stats.derniere_semaine}
                                                </h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>

                            <Col lg={3} md={6}>
                                <Card className="card-animate">
                                    <CardBody>
                                        <div className="d-flex align-items-center">
                                            <div className="flex-grow-1">
                                                <p className="text-uppercase fw-medium text-muted mb-0">Par Type</p>
                                            </div>
                                        </div>
                                        <div className="mt-3">
                                            {stats.par_type.map((type) => (
                                                <div key={type.type_document} className="d-flex justify-content-between mb-1">
                                                    <Badge color={getTypeBadge(type.type_document).color} className="me-2">
                                                        {getTypeBadge(type.type_document).label}
                                                    </Badge>
                                                    <span className="text-muted">{type.total}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        </Row>
                    )}

                    {/* Filtres et Tableau */}
                    <Row>
                        <Col lg={12}>
                            <Card>
                                <CardHeader>
                                    <div className="d-flex justify-content-between align-items-center">
                                        <h5 className="card-title mb-0">Historique des Documents</h5>
                                        <div>
                                            <select
                                                className="form-select"
                                                value={filtreType}
                                                onChange={(e) => {
                                                    setFiltreType(e.target.value);
                                                    setPage(1);
                                                }}
                                            >
                                                <option value="">Tous les types</option>
                                                <option value="CONTRAT">Contrats</option>
                                                <option value="TRESOR_MONEY">Trésor Money</option>
                                                <option value="ADD">ADD</option>
                                            </select>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardBody>
                                    {loading ? (
                                        <div className="text-center py-5">
                                            <Spinner color="primary" />
                                        </div>
                                    ) : (
                                        <>
                                            <div className="table-responsive">
                                                <Table className="table-nowrap align-middle mb-0">
                                                    <thead className="table-light">
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Type</th>
                                                            <th>Fichier</th>
                                                            <th>Stagiaire(s)</th>
                                                            <th>Utilisateur</th>
                                                            <th>Financement</th>
                                                            <th>Paramètres</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {historique.length === 0 ? (
                                                            <tr>
                                                                <td colSpan={7} className="text-center text-muted py-4">
                                                                    Aucune génération trouvée
                                                                </td>
                                                            </tr>
                                                        ) : (
                                                            historique.map((item) => (
                                                                <tr key={item.id}>
                                                                    <td>
                                                                        <small>{formatDate(item.created_at)}</small>
                                                                    </td>
                                                                    <td>
                                                                        <Badge color={getTypeBadge(item.type_document).color}>
                                                                            {getTypeBadge(item.type_document).label}
                                                                        </Badge>
                                                                    </td>
                                                                    <td>
                                                                        <span className="text-muted" style={{ fontSize: '0.85rem' }}>
                                                                            {item.nom_fichier}
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        {item.stage ? (
                                                                            <div>
                                                                                <div className="fw-medium">
                                                                                    {item.stage.beneficiaire.nom}{' '}
                                                                                    {item.stage.beneficiaire.prenoms}
                                                                                </div>
                                                                                <small className="text-muted">
                                                                                    {item.stage.beneficiaire.numero_aej}
                                                                                </small>
                                                                            </div>
                                                                        ) : (
                                                                            <Badge color="info">
                                                                                {item.nombre_stagiaires} stagiaire(s)
                                                                            </Badge>
                                                                        )}
                                                                    </td>
                                                                    <td>
                                                                        <small>{item.user?.name || 'N/A'}</small>
                                                                    </td>
                                                                    <td>
                                                                        <small>{item.source_financement || '-'}</small>
                                                                    </td>
                                                                    <td>
                                                                        {item.parametres && Object.keys(item.parametres).length > 0 ? (
                                                                            <small className="text-muted">
                                                                                {item.parametres.fonction && (
                                                                                    <div>Fonction: {item.parametres.fonction}</div>
                                                                                )}
                                                                                {item.parametres.montant && (
                                                                                    <div>
                                                                                        Montant: {item.parametres.montant.toLocaleString()} FCFA
                                                                                    </div>
                                                                                )}
                                                                                {item.parametres.stage_ids && (
                                                                                    <div>
                                                                                        {item.parametres.stage_ids.length} stage(s)
                                                                                    </div>
                                                                                )}
                                                                            </small>
                                                                        ) : (
                                                                            <small className="text-muted">-</small>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            ))
                                                        )}
                                                    </tbody>
                                                </Table>
                                            </div>

                                            {/* Pagination */}
                                            {totalPages > 1 && (
                                                <div className="d-flex justify-content-center mt-4">
                                                    <nav>
                                                        <ul className="pagination">
                                                            <li className={`page-item ${page === 1 ? 'disabled' : ''}`}>
                                                                <button
                                                                    className="page-link"
                                                                    onClick={() => setPage(page - 1)}
                                                                    disabled={page === 1}
                                                                >
                                                                    Précédent
                                                                </button>
                                                            </li>
                                                            {[...Array(totalPages)].map((_, i) => (
                                                                <li
                                                                    key={i + 1}
                                                                    className={`page-item ${page === i + 1 ? 'active' : ''}`}
                                                                >
                                                                    <button
                                                                        className="page-link"
                                                                        onClick={() => setPage(i + 1)}
                                                                    >
                                                                        {i + 1}
                                                                    </button>
                                                                </li>
                                                            ))}
                                                            <li className={`page-item ${page === totalPages ? 'disabled' : ''}`}>
                                                                <button
                                                                    className="page-link"
                                                                    onClick={() => setPage(page + 1)}
                                                                    disabled={page === totalPages}
                                                                >
                                                                    Suivant
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </nav>
                                                </div>
                                            )}
                                        </>
                                    )}
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>
        </AuthenticatedLayout>
    );
};

export default HistoriqueGenerationIndex;
