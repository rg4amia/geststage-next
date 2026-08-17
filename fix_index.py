import re

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'r') as f:
    content = f.read()

action_column = """            {
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
            },"""

content = re.sub(r'\{\s*header:\s*\'Action\',\s*cell:\s*\(cell:\s*any\)\s*=>\s*\{.*?\},\s*\},', action_column, content, flags=re.DOTALL)

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'w') as f:
    f.write(content)
