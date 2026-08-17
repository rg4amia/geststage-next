import re

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'r') as f:
    content = f.read()

# 1. Update component signature to include auth
content = content.replace(
"""const MesStagiaires = ({ 
    instances, 
    agences, 
    entreprises, 
    typesfinancements, 
    typestages, 
    typestructures, 
    etapes, 
    situationstages, 
    filters 
}: any) => {""", 
"""const MesStagiaires = ({ 
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
}: any) => {""")

# 2. Add columnVisibility state
state_injection = """    const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>(() => {
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
"""
content = content.replace("const [dataList, setDataList] = useState<any[]>(instances || []);", state_injection + "\n    const [dataList, setDataList] = useState<any[]>(instances || []);")

# 3. Add Dropdown imports
content = content.replace("import { Card, CardBody, CardHeader, Col, Container, Row, Button, Form, Input, Modal, ModalHeader, ModalBody, ModalFooter, Badge, Spinner } from 'reactstrap';", "import { Card, CardBody, CardHeader, Col, Container, Row, Button, Form, Input, Modal, ModalHeader, ModalBody, ModalFooter, Badge, Spinner, Dropdown, DropdownToggle, DropdownMenu, DropdownItem } from 'reactstrap';")

# 4. Add dropDown state
content = content.replace("const [isLoading, setIsLoading] = useState(false);", "const [isLoading, setIsLoading] = useState(false);\n    const [columnsDropdownOpen, setColumnsDropdownOpen] = useState(false);")

# 5. Modify Action column
action_column = """            {
                header: 'Action',
                cell: (cell: any) => {
                    const row = cell.row.original;
                    const stage = row.stage || {};
                    const beneficiare = stage.beneficiaire || {};
                    
                    // Logic from legacy blade
                    // const hasAgentConsultation = auth?.user?.can?.('agent-consultation'); // pseudo
                    const isOwner = row.id_user === auth?.user?.id;
                    const type_user_id = auth?.user?.type_user_id || (auth?.user?.id === 1 ? 1 : null); // Fallback to 1 if admin
                    const isPrivileged = [1, 2, 17, 82, 76, 77, 78, 79, 80].includes(type_user_id);
                    
                    const pointages = stage.pointages || [];
                    const active_chef_agence = stage.active_chef_agence || 0;
                    const type_paiement_id = beneficiare.type_paiement?.id;
                    
                    // If not authorized, return null or limited buttons
                    // if (!isPrivileged && isOwner) return null; // Simplified
                    
                    return (
                        <div className="d-flex gap-1">
                            <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Détails" href={`/cip/mes-stagiaires/${row.id}`}>
                                <i className="fas fa-eye"></i>
                            </Button>
                            
                            {pointages.length > 0 && (
                                <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Pointage" onClick={() => toggleAnalyse(row)}>
                                    <i className="fas fa-folder-plus"></i>
                                    <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-light text-dark border">
                                        {pointages.length}
                                    </span>
                                </Button>
                            )}

                            {(type_user_id === 1 || type_user_id === 17) && active_chef_agence === 0 && (
                                <>
                                    <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Generer Contrat">
                                        <i className="fas fa-file-contract"></i>
                                    </Button>
                                    <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Transferer Contrat">
                                        <i className="fas fa-share"></i>
                                    </Button>
                                </>
                            )}
                            
                            {(type_user_id === 1 || type_user_id === 17) && type_paiement_id === 1 && (
                                <Button color="success" size="sm" className="btn-icon rounded-circle" title="Générer Trésor Money">
                                    <i className="fas fa-file-invoice-dollar"></i>
                                </Button>
                            )}
                            
                            {(type_user_id === 1 || type_user_id === 17) && active_chef_agence === 0 && type_paiement_id === 1 && (
                                <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Joindre Trèsor Money">
                                    <i className="fas fa-money-bill-wave"></i>
                                </Button>
                            )}
                            
                            {(type_user_id === 1 || type_user_id === 17) && active_chef_agence === 0 && (
                                <>
                                    <Button color="danger" size="sm" className="btn-icon rounded-circle" title="Supprimer">
                                        <i className="fas fa-trash-alt"></i>
                                    </Button>
                                    <Button color="light" size="sm" className="btn-icon rounded-circle text-info" title="Modifier" href={`/cip/mes-stagiaires/${row.id}/edit`}>
                                        <i className="fas fa-edit"></i>
                                    </Button>
                                </>
                            )}
                        </div>
                    );
                },
            },"""
content = re.sub(r'\{\s*header:\s*\'Action\',\s*cell:\s*\(cell:\s*any\)\s*=>\s*\{.*?\},\s*\},', action_column, content, flags=re.DOTALL)


# 6. Apply visibleColumns filter
visible_columns = """    const visibleColumns = useMemo(() => {
        return columns.filter(col => {
            if (col.header === 'Action') return true;
            const header = typeof col.header === 'string' ? col.header : col.accessorKey;
            return columnVisibility[header as string] !== false;
        });
    }, [columns, columnVisibility]);"""
content = content.replace("const activeFiltersCount = [", visible_columns + "\n\n    const activeFiltersCount = [")

content = content.replace("columns={columns}", "columns={visibleColumns}")

# 7. Add Column Selector Dropdown
dropdown_ui = """                                    <div className="d-flex justify-content-between align-items-center">
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
                                    </div>"""

content = re.sub(r'<div className="d-flex justify-content-between align-items-center">.*?</h5>\s*</div>', dropdown_ui, content, flags=re.DOTALL)

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'w') as f:
    f.write(content)
