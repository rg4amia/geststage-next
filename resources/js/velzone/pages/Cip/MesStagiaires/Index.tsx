import React, { useMemo, useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Form, Input, Modal, ModalHeader, ModalBody, ModalFooter, Badge, Spinner, Dropdown, DropdownToggle, DropdownMenu, DropdownItem } from 'reactstrap';
import axios from 'axios';
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
    filters,
    auth
}: any) => {
        const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>(() => {
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('mesStagiairesColumnVisibility');
            if (saved) {
                try {
                    return JSON.parse(saved);
                } catch (e) {}
            }
        }
        return {};
    });

    useEffect(() => {
        localStorage.setItem('mesStagiairesColumnVisibility', JSON.stringify(columnVisibility));
    }, [columnVisibility]);

    const toggleColumn = (header: string) => {
        setColumnVisibility(prev => ({
            ...prev,
            [header]: prev[header] === false ? true : false
        }));
    };

    const [dataList, setDataList] = useState<any[]>(instances || []);
    const [stats, setStats] = useState<any>({ total: 0, avecContrat: 0, sansContrat: 0, enAttente: 0 });
    const [paginationInfo, setPaginationInfo] = useState<any>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [columnsDropdownOpen, setColumnsDropdownOpen] = useState(false);
    const data = dataList;

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
        search: filters?.search || '',
    });

    const fetchData = async (filtersData: any) => {
        setIsLoading(true);
        try {
            const response: any = await axios.get('/cip/mes-stagiaires', {
                params: filtersData,
                headers: { Accept: 'application/json' }
            });
            const responseData = response.data !== undefined ? response.data : response;
            const fetchedInstances = responseData.instances;
            setDataList(fetchedInstances?.data || fetchedInstances || []);
            setPaginationInfo(fetchedInstances);
            if (responseData.stats) {
                setStats(responseData.stats);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        if (!instances || instances.length === 0) {
            fetchData(formData);
        }
    }, []);

    const handleSearch = (e?: any) => {
        if(e) e.preventDefault();
        fetchData(formData);
    };

    const handleReset = () => {
        const resetData = {
            agence_id: '',
            entreprise_id: '',
            typesfinancement_id: '',
            typestage_id: '',
            type_structure_id: '',
            etape_id: '',
            situationstage_id: '',
            date_debut: '',
            date_fin: '',
            search: '',
        };
        Object.keys(resetData).forEach(key => setData(key as any, (resetData as any)[key]));
        fetchData(resetData);
    };

    const toggleAnalyse = (stagiaire: any = null) => {
        setSelectedStagiaire(stagiaire);
        setModalAnalyse(!modalAnalyse);
    };

    // Étapes principales du circuit d'une instance de parcours (cf. WorkflowTransitionService)
    const WORKFLOW_STEPS = [
        { step: 1, label: 'Soumission CIP' },
        { step: 2, label: "Validation Démarrage" },
        { step: 3, label: 'Paiement Démarrage' },
        { step: 4, label: 'En stage / Pointage' },
    ];

    // Correspondance corbeille_actuelle -> étape du circuit + libellé lisible (cf. App\Enums\CorbeilleEnum)
    const CORBEILLE_INFO: Record<string, { label: string; acteur: string; step: number }> = {
        cip_mes_stagiaires: { label: 'Soumission du dossier', acteur: 'CIP', step: 1 },
        cip_ajourne_ca: { label: "Ajourné par le Chef d'Agence — à corriger", acteur: 'CIP', step: 1 },
        cip_ajourne_desse: { label: 'Ajourné par la DESSE — à corriger', acteur: 'CIP', step: 1 },
        cip_ajourne_dmg: { label: 'Ajourné par la DMG — à corriger', acteur: 'CIP', step: 1 },
        cip_ajourne_aaf: { label: 'Ajourné (AAF) — à corriger', acteur: 'CIP', step: 1 },

        ca_attente_validation_demarrage: { label: 'En attente de validation du démarrage', acteur: "Chef d'Agence", step: 2 },
        ca_attente_validation_omis: { label: 'En attente de validation du démarrage omis', acteur: "Chef d'Agence", step: 2 },

        dmg_attente_paiement_demarrage: { label: 'En attente du paiement de démarrage', acteur: 'DMG', step: 3 },

        en_stage: { label: 'En stage — cycle de pointage mensuel', acteur: 'Cycle mensuel', step: 4 },
        cip_pointage: { label: 'Pointage mensuel en cours', acteur: 'CIP', step: 4 },
        cip_pointage_ajourne_dmg: { label: 'Pointage ajourné par la DMG', acteur: 'CIP', step: 4 },
        cip_pointage_pejedec: { label: 'Pointage PEJEDEC en cours', acteur: 'CIP', step: 4 },
        cip_differe_ac: { label: "Différé par l'Agent Comptable", acteur: 'CIP', step: 4 },
        cip_fin_contrat: { label: 'Fin de contrat', acteur: 'CIP', step: 4 },
        ca_validation_pointages: { label: 'En attente de validation des pointages', acteur: "Chef d'Agence", step: 4 },
        ca_validation_pointage_ajourne_adp: { label: 'Validation de la correction du pointage', acteur: "Chef d'Agence", step: 4 },
        ca_stagiaire_differe_ac: { label: "Différé par l'Agent Comptable", acteur: "Chef d'Agence", step: 4 },
        desse_doublons_a_traiter: { label: 'Doublon à traiter', acteur: 'DESSE', step: 4 },
        desse_attente_verification_dmg: { label: 'En attente de vérification DMG', acteur: 'DESSE', step: 4 },
        desse_retour_agence: { label: "Retourné à l'agence", acteur: 'DESSE', step: 4 },
        desse_doublons_traites: { label: 'Doublon traité', acteur: 'DESSE', step: 4 },
        desse_suivi_processus: { label: 'Suivi du processus', acteur: 'DESSE', step: 4 },
        desse_beneficiaires_2023: { label: 'Bénéficiaire 2023', acteur: 'DESSE', step: 4 },
        desse_attente_ca: { label: "En attente du Chef d'Agence", acteur: 'DESSE', step: 4 },
        desse_suivi_enregistres: { label: 'Suivi des dossiers enregistrés', acteur: 'DESSE', step: 4 },
        desse_suivi_valides_ar: { label: 'Suivi des dossiers validés AR', acteur: 'DESSE', step: 4 },
        daicg_valides_ca: { label: "Validé par le Chef d'Agence", acteur: 'DAICG', step: 4 },
        daicg_valides_desse: { label: 'Validé par la DESSE', acteur: 'DAICG', step: 4 },
        daicg_sans_contrat: { label: 'Dossier sans contrat', acteur: 'DAICG', step: 4 },
        daicg_attente_dmg: { label: 'En attente de la DMG', acteur: 'DAICG', step: 4 },
    };

    const getWorkflowProgress = (corbeille?: string | null) => {
        const info = corbeille ? CORBEILLE_INFO[corbeille] : undefined;
        const currentStep = info?.step ?? 0;

        return (
            <div>
                <div className="d-flex align-items-center mb-2">
                    {WORKFLOW_STEPS.map((s, idx) => {
                        const isDone = currentStep > s.step;
                        const isCurrent = currentStep === s.step;
                        const color = isDone ? 'var(--vz-success)' : isCurrent ? 'var(--vz-primary)' : 'var(--vz-border-color)';
                        return (
                            <React.Fragment key={s.step}>
                                <div className="d-flex flex-column align-items-center" style={{ minWidth: 90 }}>
                                    <div
                                        className="d-flex align-items-center justify-content-center rounded-circle fw-semibold flex-shrink-0"
                                        style={{
                                            width: 28,
                                            height: 28,
                                            fontSize: 12,
                                            color: isDone || isCurrent ? '#fff' : 'var(--vz-secondary-color)',
                                            backgroundColor: color,
                                            border: `2px solid ${color}`,
                                        }}
                                    >
                                        {isDone ? <i className="ri-check-line"></i> : s.step}
                                    </div>
                                    <span className="fs-11 text-muted mt-1 text-center">{s.label}</span>
                                </div>
                                {idx < WORKFLOW_STEPS.length - 1 && (
                                    <div
                                        className="flex-grow-1"
                                        style={{
                                            height: 2,
                                            backgroundColor: currentStep > s.step ? 'var(--vz-success)' : 'var(--vz-border-color)',
                                            marginBottom: 18,
                                        }}
                                    ></div>
                                )}
                            </React.Fragment>
                        );
                    })}
                </div>
                <Badge color={currentStep === 4 ? 'success' : 'primary'} className="fs-12">
                    {info ? `${info.acteur} — ${info.label}` : 'Étape non renseignée'}
                </Badge>
            </div>
        );
    };

    // Couleur de badge pour l'étape/corbeille courante (cf. CORBEILLE_INFO)
    const getEtapeBadgeColor = (corbeille?: string | null, step?: number) => {
        if (!corbeille) {
            return 'secondary';
        }
        if (corbeille.includes('ajourne') || corbeille.includes('differe') || corbeille.includes('rejet')) {
            return 'danger';
        }
        if (step === 4) {
            return 'success';
        }
        return 'primary';
    };

    // Couleur de badge pour la situation de stage (cf. légende légacy : EN COURS / ABANDON / SUSPENSION / FIN DE STAGE / REACTIVATION / DESISTEMENT)
    const getSituationBadgeColor = (label: string) => {
        const upper = label.toUpperCase();
        if (upper.includes('EN COURS')) {
            return 'success';
        }
        if (upper.includes('ABANDON')) {
            return 'warning';
        }
        if (upper.includes('SUSPENSION')) {
            return 'info';
        }
        if (upper.includes('REACTIV')) {
            return 'warning';
        }
        if (upper.includes('DESIST')) {
            return 'secondary';
        }
        if (upper.includes('FIN')) {
            return 'danger';
        }
        return 'light';
    };

    // Use backend global stats instead of calculating on local paginated data
    const totalStagiaires = stats.total || 0;
    const avecContrat = stats.avecContrat || 0;
    const sansContrat = stats.sansContrat || 0;
    const enAttente = stats.enAttente || 0;

    const columns = useMemo(
        () => [
            {
                header: 'Situation Stage',
                accessorKey: 'stage.situation_stage',
                cell: (cell: any) => {
                    const code = cell.getValue();
                    if (!code) {
                        return <Badge color="light" className="text-dark">NEANT</Badge>;
                    }
                    const label = situationstages?.[code] || code;
                    return <Badge color={getSituationBadgeColor(String(label))}>{label}</Badge>;
                },
            },
            {
                header: 'Date',
                accessorKey: 'created_at',
                cell: (cell: any) => {
                    const val = cell.getValue();
                    return val ? new Date(val).toLocaleDateString('fr-FR') : '-';
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
                        return <Badge color="warning">{type}</Badge>;
                    }
                    if (type.toUpperCase().includes('PRIVE')) {
                        return <Badge color="primary">{type}</Badge>;
                    }
                    return <Badge color="success">{type}</Badge>;
                },
            },
            {
                header: 'Date debut',
                accessorKey: 'stage.date_debut',
                cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
            },
            {
                header: 'Date fin',
                accessorKey: 'stage.date_fin_prevue',
                cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
            },
            {
                header: 'Numéro AEJ',
                accessorKey: 'stage.beneficiaire.numero_aej',
                cell: (cell: any) => <span className="fw-medium">{cell.getValue() || '-'}</span>,
            },
            {
                header: 'Type Paiement',
                accessorKey: 'stage.beneficiaire.type_paiement.nom',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'N° Trésor Money',
                accessorKey: 'stage.beneficiaire.numero_tresor_money',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'N° Wave',
                accessorKey: 'stage.beneficiaire.numero_wave',
                cell: (cell: any) => cell.getValue() || '-',
            },
            {
                header: 'Nom et prénoms',
                accessorKey: 'stage.beneficiaire.nom',
                cell: (cell: any) => {
                    const row = cell.row.original.stage?.beneficiaire;
                    return row ? <span className="fw-semibold text-primary">{row.nom} {row.prenoms}</span> : '-';
                },
            },
            {
                header: 'Date naissance',
                accessorKey: 'stage.beneficiaire.date_naissance',
                cell: (cell: any) => cell.getValue() ? new Date(cell.getValue()).toLocaleDateString('fr-FR') : '-',
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
                        <Badge color="success"><i className="ri-check-line me-1"></i> Avec Contrat</Badge>
                    ) : (
                        <Badge color="warning">Sans Contrat</Badge>
                    );
                },
            },
            {
                header: 'Capitalisation financière',
                accessorKey: 'stage.nbr_mois_capitaliser',
                cell: (cell: any) => {
                    const mois = cell.getValue() || 0;
                    return mois > 0 ? <Badge color="success">Oui ({mois} mois)</Badge> : <Badge color="secondary">Non</Badge>;
                },
            },
            {
                header: 'Etape Traitement',
                accessorKey: 'corbeille_actuelle',
                cell: (cell: any) => {
                    const corbeille = cell.getValue();
                    const info = corbeille ? CORBEILLE_INFO[corbeille] : undefined;
                    return <Badge color={getEtapeBadgeColor(corbeille, info?.step)}>{info?.label || corbeille || 'N/A'}</Badge>;
                },
            },
                                    {
                header: 'Action',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const stage = row.stage || {};
                    const beneficiare = stage.beneficiaire || {};
                    
                    const userRoles = auth?.user?.roles || [];
                    const isAdministrateur = userRoles.includes('administrateur') || auth?.user?.id === 1;
                    const isChefAgence = userRoles.includes('chef_agence');
                    
                    const pointages = stage.pointages || [];
                    const active_chef_agence = stage.active_chef_agence || 0;
                    const type_paiement_id = beneficiare.type_paiement?.id;
                    
                    return (
                        <div className="d-flex gap-1">
                            <Button color="info" size="sm" className="btn-icon rounded-circle" title="Détails" href={`/cip/mes-stagiaires/${row.id}`}>
                                <i className="ri-eye-line"></i>
                            </Button>
                            
                            {pointages.length > 0 && (
                                <Button color="primary" size="sm" className="btn-icon rounded-circle" title="Pointage" onClick={() => toggleAnalyse(row)}>
                                    <i className="ri-folder-add-line"></i>
                                    <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                                        {pointages.length}
                                    </span>
                                </Button>
                            )}

                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && (
                                <>
                                    <Button color="warning" size="sm" className="btn-icon rounded-circle text-white" title="Générer Contrat">
                                        <i className="ri-file-text-line"></i>
                                    </Button>
                                    <Button color="secondary" size="sm" className="btn-icon rounded-circle" title="Transférer Contrat">
                                        <i className="ri-share-forward-line"></i>
                                    </Button>
                                </>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && type_paiement_id === 1 && (
                                <Button color="success" size="sm" className="btn-icon rounded-circle" title="Générer Trésor Money">
                                    <i className="ri-money-dollar-circle-line"></i>
                                </Button>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && type_paiement_id === 1 && (
                                <Button color="success" outline size="sm" className="btn-icon rounded-circle" title="Joindre Trésor Money">
                                    <i className="ri-wallet-3-line"></i>
                                </Button>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && (
                                <>
                                    <Button color="dark" size="sm" className="btn-icon rounded-circle" title="Modifier" href={`/cip/mes-stagiaires/${row.id}/edit`}>
                                        <i className="ri-edit-line"></i>
                                    </Button>
                                    <Button color="danger" size="sm" className="btn-icon rounded-circle" title="Supprimer">
                                        <i className="ri-delete-bin-line"></i>
                                    </Button>
                                </>
                            )}
                        </div>
                    );
                },
            },
        ].map(col => ({ ...col, enableColumnFilter: false })),
        []
    );

        const visibleColumns = useMemo(() => {
        return columns.filter(col => {
            if (col.header === 'Action') return true;
            const header = typeof col.header === 'string' ? col.header : col.accessorKey;
            return columnVisibility[header as string] !== false;
        });
    }, [columns, columnVisibility]);

    const activeFiltersCount = [
        formData.agence_id, formData.entreprise_id, formData.typesfinancement_id,
        formData.typestage_id, formData.type_structure_id, formData.etape_id,
        formData.situationstage_id, formData.date_debut, formData.date_fin, formData.search
    ].filter(Boolean).length;

    return (
        <React.Fragment>
            <Head title="Mes Stagiaires" />
            <div className="page-content">
                <Container fluid>
                    {/* Page Title */}
                    <div className="row">
                        <div className="col-12">
                            <div className="page-title-box d-sm-flex align-items-center justify-content-between">
                                <h4 className="mb-sm-0">Mes Stagiaires</h4>
                                <div className="page-title-right">
                                    <ol className="breadcrumb m-0">
                                        <li className="breadcrumb-item">
                                            <Link href="/dashboard">Accueil</Link>
                                        </li>
                                        <li className="breadcrumb-item active">
                                            Mes Stagiaires
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Statistics Cards */}
                    <Row className="g-3 mb-3">
                        {[
                            { label: 'Total Stagiaires', value: totalStagiaires, icon: 'ri-team-line', bg: '#e8f4fd', iconColor: '#0dcaf0', valColor: '#0dcaf0' },
                            { label: 'Avec Contrat', value: avecContrat, icon: 'ri-file-list-3-line', bg: '#e8f8f0', iconColor: '#198754', valColor: '#198754' },
                            { label: 'En Attente', value: enAttente, icon: 'ri-time-line', bg: '#fff8e6', iconColor: '#fd7e14', valColor: '#fd7e14' },
                            { label: 'Sans Contrat', value: sansContrat, icon: 'ri-close-circle-line', bg: '#fde8e8', iconColor: '#dc3545', valColor: '#dc3545' },
                        ].map((s, i) => (
                            <Col xl={3} md={6} key={i}>
                                <Card className="card-animate mb-0 h-100 border-0 shadow-sm">
                                    <CardBody className="p-3">
                                        <div className="d-flex align-items-center gap-3">
                                            <div className="avatar-sm flex-shrink-0">
                                                <span className="avatar-title rounded fs-3" style={{ background: s.bg, color: s.iconColor }}>
                                                    <i className={s.icon}></i>
                                                </span>
                                            </div>
                                            <div>
                                                <p className="text-uppercase fw-medium mb-0 fs-11" style={{ color: '#878a99' }}>{s.label}</p>
                                                <h4 className="fs-22 fw-bold mb-0" style={{ color: s.valColor }}>{s.value}</h4>
                                            </div>
                                        </div>
                                    </CardBody>
                                </Card>
                            </Col>
                        ))}
                    </Row>

                    {/* Filters Card */}
                    <Card className="mb-3 border-0 shadow-sm">
                        <CardBody className="pb-2">
                            <Form onSubmit={handleSearch}>
                                {/* Ligne 1 : recherche + filtres principaux */}
                                <Row className="g-2 align-items-end mb-2">
                                    <Col xs={12} sm={6} md={3}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-search-line me-1"></i>Recherche
                                        </label>
                                        <Input 
                                            type="text" 
                                            className="form-control-sm" 
                                            placeholder="Nom, Prénom, Numéro AEJ..."
                                            value={formData.search} 
                                            onChange={(e) => setData('search', e.target.value)} 
                                        />
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-building-4-line me-1"></i>Agence
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.agence_id} onChange={e => setData('agence_id', e.target.value)}>
                                            <option value="">Toutes les agences</option>
                                            {Object.entries(agences || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-building-line me-1"></i>Entreprise
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.entreprise_id} onChange={e => setData('entreprise_id', e.target.value)}>
                                            <option value="">Toutes les entreprises</option>
                                            {Object.entries(entreprises || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-bank-card-line me-1"></i>Financement
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.typesfinancement_id} onChange={e => setData('typesfinancement_id', e.target.value)}>
                                            <option value="">Tous</option>
                                            {Object.entries(typesfinancements || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={3}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-macbook-line me-1"></i>Type de Stage
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.typestage_id} onChange={e => setData('typestage_id', e.target.value)}>
                                            <option value="">Tous les types</option>
                                            {Object.entries(typestages || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                </Row>

                                {/* Ligne 2 : filtres secondaires + boutons */}
                                <Row className="g-2 align-items-end">
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-community-line me-1"></i>Structure
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.type_structure_id} onChange={e => setData('type_structure_id', e.target.value)}>
                                            <option value="">Toutes</option>
                                            {Object.entries(typestructures || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-git-merge-line me-1"></i>Étape
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.etape_id} onChange={e => setData('etape_id', e.target.value)}>
                                            <option value="">Toutes les étapes</option>
                                            {Object.entries(etapes || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-flag-line me-1"></i>Situation
                                        </label>
                                        <Input type="select" className="form-select-sm" value={formData.situationstage_id} onChange={e => setData('situationstage_id', e.target.value)}>
                                            <option value="">Toutes les situations</option>
                                            {Object.entries(situationstages || {}).map(([id, label]) => (
                                                <option key={id} value={id}>{String(label)}</option>
                                            ))}
                                        </Input>
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-calendar-line me-1"></i>Du
                                        </label>
                                        <Input type="date" className="form-control-sm" value={formData.date_debut} onChange={e => setData('date_debut', e.target.value)} />
                                    </Col>
                                    <Col xs={6} sm={4} md={2}>
                                        <label className="form-label fs-12 text-muted mb-1">
                                            <i className="ri-calendar-line me-1"></i>Au
                                        </label>
                                        <Input type="date" className="form-control-sm" value={formData.date_fin} onChange={e => setData('date_fin', e.target.value)} />
                                    </Col>

                                    {/* Boutons */}
                                    <Col xs={12} sm={4} md={2} className="d-flex gap-2 align-items-end">
                                        <button
                                            type="submit"
                                            className="btn btn-primary btn-sm flex-fill"
                                            title="Appliquer les filtres"
                                        >
                                            <i className="ri-search-line me-1"></i>Filtrer
                                            {activeFiltersCount > 0 && (
                                                <span className="badge ms-1 fs-10" style={{ background: 'var(--vz-primary)', color: '#fff' }}>{activeFiltersCount}</span>
                                            )}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-outline-secondary btn-sm"
                                            onClick={handleReset}
                                            title="Réinitialiser"
                                        >
                                            <i className="ri-refresh-line"></i>
                                        </button>
                                    </Col>
                                </Row>

                                {/* Barre d'actions optionnelle (si besoin d'actions globales) */}
                                <div className="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                                    <div className="d-flex gap-2">
                                        <button type="button" className="btn btn-outline-success btn-sm">
                                            <i className="ri-file-excel-line me-1"></i> Exporter Excel
                                        </button>
                                    </div>
                                    {activeFiltersCount > 0 && (
                                        <small className="text-muted">
                                            <i className="ri-filter-3-line me-1"></i>
                                            {activeFiltersCount} filtre{activeFiltersCount > 1 ? 's' : ''} actif{activeFiltersCount > 1 ? 's' : ''}
                                            {' — '}
                                            <button type="button" className="btn btn-link btn-sm p-0 text-danger" onClick={handleReset}>
                                                Tout effacer
                                            </button>
                                        </small>
                                    )}
                                </div>
                            </Form>
                        </CardBody>
                    </Card>

                    {/* Main Content: Table */}
                    <Row>
                        <Col lg={12}>
                            <Card className="border-0 shadow-sm">
                                <CardHeader>
                                                                        <div className="d-flex justify-content-between align-items-center">
                                        <h5 className="card-title mb-0" style={{ color: '#495057' }}>
                                            Liste des Stagiaires
                                            <span className="badge ms-2 fs-12" style={{ background: 'var(--vz-primary-bg-subtle)', color: 'var(--vz-primary)' }}>
                                                {totalStagiaires}
                                            </span>
                                        </h5>
                                        <Dropdown isOpen={columnsDropdownOpen} toggle={() => setColumnsDropdownOpen(!columnsDropdownOpen)}>
                                            <DropdownToggle color="light" size="sm" className="btn-icon">
                                                <i className="ri-layout-column-line"></i> Colonnes
                                            </DropdownToggle>
                                            <DropdownMenu end style={{ maxHeight: '300px', overflowY: 'auto' }}>
                                                <div className="px-3 py-2 text-muted fs-12 fw-semibold text-uppercase bg-light">Affichage des colonnes</div>
                                                {columns.filter(c => c.header !== 'Action').map((col, idx) => {
                                                    const header = col.header as string;
                                                    const isVisible = columnVisibility[header] !== false;
                                                    return (
                                                        <DropdownItem key={idx} toggle={false} onClick={() => toggleColumn(header)}>
                                                            <div className="form-check">
                                                                <input 
                                                                    className="form-check-input" 
                                                                    type="checkbox" 
                                                                    checked={isVisible} 
                                                                    readOnly 
                                                                />
                                                                <label className="form-check-label ms-2">{header}</label>
                                                            </div>
                                                        </DropdownItem>
                                                    );
                                                })}
                                            </DropdownMenu>
                                        </Dropdown>
                                    </div>
                                </CardHeader>
                                <CardBody className="p-0">
                                    <TableContainerReactTable
                                        columns={visibleColumns}
                                        data={data}
                                        isGlobalFilter={false}
                                        customPageSize={data.length}
                                        isServerPagination={true}
                                        serverPagination={paginationInfo}
                                        onPageChange={(page) => fetchData({ ...formData, page })}
                                        divClass="table-responsive"
                                        tableClass="table table-striped table-hover align-middle mb-0"
                                        theadClass="table-light"
                                        SearchPlaceholder="Rechercher dans le tableau..."
                                    />
                                </CardBody>
                            </Card>
                        </Col>
                    </Row>
                </Container>
            </div>

            <Modal isOpen={modalAnalyse} toggle={() => toggleAnalyse()} size="xl">
                <ModalHeader toggle={() => toggleAnalyse()} className="bg-light border-bottom">
                    <h5 className="modal-title mb-0" style={{ color: '#495057' }}>
                        <i className="ri-folder-open-line me-2 align-middle text-primary"></i>
                        Analyse du Pointage - {selectedStagiaire?.stage?.beneficiaire?.nom} {selectedStagiaire?.stage?.beneficiaire?.prenoms}
                    </h5>
                </ModalHeader>
                <ModalBody className="bg-white">
                    <div className="alert alert-info border-0 rounded-3 mb-4 d-flex">
                        <i className="ri-information-line fs-18 me-3 mt-1"></i>
                        <div className="w-100">
                            <h6 className="alert-heading fw-semibold mb-1">Flux de traitement</h6>
                            {getWorkflowProgress(selectedStagiaire?.corbeille_actuelle)}
                        </div>
                    </div>

                    <Card className="border shadow-none mb-0">
                        <CardBody className="p-0">
                            <div className="table-responsive">
                                <table className="table align-middle table-nowrap table-striped mb-0">
                                    <thead className="table-light text-muted fs-12">
                                        <tr>
                                            <th>Mois</th>
                                            <th>Étape Traitement</th>
                                            <th>Étape DESSE</th>
                                            <th>N° OP</th>
                                            <th>N° Bordereau</th>
                                            <th>Dossier</th>
                                            <th>Position</th>
                                            <th>Situation</th>
                                            <th>Date Pointage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {/* Example empty state row */}
                                        {selectedStagiaire?.stage?.pointages?.length > 0 ? (
                                            selectedStagiaire.stage.pointages.map((pointage: any, index: number) => (
                                                <tr key={index}>
                                                    <td>{pointage.periode?.code || '-'}</td>
                                                    <td><Badge color="info">{pointage.statut || '-'}</Badge></td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>-</td>
                                                    <td>
                                                        {pointage.version_courante?.jours_presents !== undefined 
                                                            ? `${pointage.version_courante.jours_presents} J` 
                                                            : '-'}
                                                    </td>
                                                    <td>{pointage.version_courante?.presence || '-'}</td>
                                                    <td>
                                                        {pointage.version_courante?.saisi_le 
                                                            ? new Date(pointage.version_courante.saisi_le).toLocaleDateString('fr-FR') 
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan={9} className="text-center text-muted py-5">
                                                    <div className="d-flex flex-column align-items-center justify-content-center">
                                                        <i className="ri-file-search-line display-5 text-muted opacity-50 mb-3"></i>
                                                        <h6>Aucun pointage disponible</h6>
                                                        <p className="mb-0 fs-13">Les données de pointage de ce stagiaire s'afficheront ici.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardBody>
                    </Card>
                </ModalBody>
                <ModalFooter className="border-top">
                    <Button color="light" onClick={() => toggleAnalyse()}>Fermer</Button>
                </ModalFooter>
            </Modal>
        </React.Fragment>
    );
};

export default MesStagiaires;
