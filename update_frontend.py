import re

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'r') as f:
    content = f.read()

# Add states and forms
states_to_add = """    const [modalAnalyse, setModalAnalyse] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);
    
    // Nouveaux états pour les modales d'action
    const [selectedActionStagiaire, setSelectedActionStagiaire] = useState<any>(null);
    const [modalGenererContrat, setModalGenererContrat] = useState(false);
    const [modalTransferer, setModalTransferer] = useState(false);
    const [modalTresorMoney, setModalTresorMoney] = useState(false);
    const [modalDelete, setModalDelete] = useState(false);

    const genererContratForm = useForm({
        fonction_dg: '',
        montant: '',
    });

    const transfererForm = useForm({
        contrat_stage: null as File | null,
    });

    const tresorMoneyForm = useForm({
        tresor_money_file: null as File | null,
    });

    const deleteForm = useForm({});

    const openActionModal = (stagiaire: any, modalSetter: any) => {
        setSelectedActionStagiaire(stagiaire);
        modalSetter(true);
    };"""

content = content.replace("""    const [modalAnalyse, setModalAnalyse] = useState(false);
    const [selectedStagiaire, setSelectedStagiaire] = useState<any>(null);""", states_to_add)

# Update action column buttons
old_buttons = """                                    <Button color="warning" size="sm" className="btn-icon rounded-circle text-white" title="Générer Contrat">
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
                                    </Button>"""

new_buttons = """                                    <Button color="warning" size="sm" className="btn-icon rounded-circle text-white" title="Générer Contrat" onClick={() => openActionModal(row, setModalGenererContrat)}>
                                        <i className="ri-file-text-line"></i>
                                    </Button>
                                    <Button color="secondary" size="sm" className="btn-icon rounded-circle" title="Transférer Contrat" onClick={() => openActionModal(row, setModalTransferer)}>
                                        <i className="ri-share-forward-line"></i>
                                    </Button>
                                </>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && type_paiement_id === 1 && (
                                <Button tag="a" href={`/cip/mes-stagiaires/${row.id}/generer-tresor-money`} target="_blank" color="success" size="sm" className="btn-icon rounded-circle" title="Générer Trésor Money">
                                    <i className="ri-money-dollar-circle-line"></i>
                                </Button>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && type_paiement_id === 1 && (
                                <Button color="success" outline size="sm" className="btn-icon rounded-circle" title="Joindre Trésor Money" onClick={() => openActionModal(row, setModalTresorMoney)}>
                                    <i className="ri-wallet-3-line"></i>
                                </Button>
                            )}
                            
                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && (
                                <>
                                    <Button color="dark" size="sm" className="btn-icon rounded-circle" title="Modifier" href={`/cip/mes-stagiaires/${row.id}/edit`}>
                                        <i className="ri-edit-line"></i>
                                    </Button>
                                    <Button color="danger" size="sm" className="btn-icon rounded-circle" title="Supprimer" onClick={() => openActionModal(row, setModalDelete)}>
                                        <i className="ri-delete-bin-line"></i>
                                    </Button>"""

content = content.replace(old_buttons, new_buttons)


# Add Modals at the bottom of the component
modals_injection = """
            {/* Modal Générer Contrat */}
            <Modal isOpen={modalGenererContrat} toggle={() => setModalGenererContrat(!modalGenererContrat)} centered>
                <ModalHeader toggle={() => setModalGenererContrat(!modalGenererContrat)} className="bg-warning text-white">Générer Contrat</ModalHeader>
                <Form onSubmit={(e) => {
                    e.preventDefault();
                    window.open(`/cip/mes-stagiaires/${selectedActionStagiaire?.id}/generer-contrat?fonction=${genererContratForm.data.fonction_dg}&montant=${genererContratForm.data.montant}`, '_blank');
                    setModalGenererContrat(false);
                }}>
                    <ModalBody>
                        <div className="mb-3">
                            <label htmlFor="fonction_dg" className="form-label">Fonction du représentant légal de l'entreprise</label>
                            <Input type="text" id="fonction_dg" value={genererContratForm.data.fonction_dg} onChange={e => genererContratForm.setData('fonction_dg', e.target.value)} required />
                        </div>
                        <div className="mb-3">
                            <label htmlFor="montant" className="form-label">Montant prime de stage</label>
                            <Input type="number" id="montant" value={genererContratForm.data.montant} onChange={e => genererContratForm.setData('montant', e.target.value)} required />
                        </div>
                    </ModalBody>
                    <ModalFooter>
                        <Button color="light" onClick={() => setModalGenererContrat(false)}>Fermer</Button>
                        <Button color="warning" type="submit" disabled={genererContratForm.processing}>Générer</Button>
                    </ModalFooter>
                </Form>
            </Modal>

            {/* Modal Transférer Contrat */}
            <Modal isOpen={modalTransferer} toggle={() => setModalTransferer(!modalTransferer)} centered>
                <ModalHeader toggle={() => setModalTransferer(!modalTransferer)} className="bg-info text-white">Transférer Contrat</ModalHeader>
                <Form onSubmit={(e) => {
                    e.preventDefault();
                    transfererForm.post(`/cip/mes-stagiaires/${selectedActionStagiaire?.id}/transferer-contrat`, {
                        onSuccess: () => setModalTransferer(false)
                    });
                }}>
                    <ModalBody>
                        <div className="mb-3">
                            <label htmlFor="contrat_stage" className="form-label">Contrat Signé (PDF)</label>
                            <Input type="file" id="contrat_stage" onChange={e => transfererForm.setData('contrat_stage', e.target.files ? e.target.files[0] : null)} required />
                            {transfererForm.errors.contrat_stage && <div className="text-danger mt-1">{transfererForm.errors.contrat_stage}</div>}
                        </div>
                    </ModalBody>
                    <ModalFooter>
                        <Button color="light" onClick={() => setModalTransferer(false)}>Fermer</Button>
                        <Button color="info" type="submit" disabled={transfererForm.processing}>Transférer</Button>
                    </ModalFooter>
                </Form>
            </Modal>

            {/* Modal Joindre Trésor Money */}
            <Modal isOpen={modalTresorMoney} toggle={() => setModalTresorMoney(!modalTresorMoney)} centered>
                <ModalHeader toggle={() => setModalTresorMoney(!modalTresorMoney)} className="bg-success text-white">Joindre Fichier Trésor Money</ModalHeader>
                <Form onSubmit={(e) => {
                    e.preventDefault();
                    tresorMoneyForm.post(`/cip/mes-stagiaires/${selectedActionStagiaire?.id}/upload-tresor-money`, {
                        onSuccess: () => setModalTresorMoney(false)
                    });
                }}>
                    <ModalBody>
                        <div className="mb-3">
                            <label htmlFor="tresor_money_file" className="form-label">Fichier Trésor Money</label>
                            <Input type="file" id="tresor_money_file" onChange={e => tresorMoneyForm.setData('tresor_money_file', e.target.files ? e.target.files[0] : null)} required />
                            {tresorMoneyForm.errors.tresor_money_file && <div className="text-danger mt-1">{tresorMoneyForm.errors.tresor_money_file}</div>}
                        </div>
                    </ModalBody>
                    <ModalFooter>
                        <Button color="light" onClick={() => setModalTresorMoney(false)}>Fermer</Button>
                        <Button color="success" type="submit" disabled={tresorMoneyForm.processing}>Enregistrer</Button>
                    </ModalFooter>
                </Form>
            </Modal>

            {/* Modal Confirmer Suppression */}
            <Modal isOpen={modalDelete} toggle={() => setModalDelete(!modalDelete)} centered>
                <ModalHeader toggle={() => setModalDelete(!modalDelete)} className="bg-danger text-white">Confirmer Suppression</ModalHeader>
                <Form onSubmit={(e) => {
                    e.preventDefault();
                    deleteForm.delete(`/cip/mes-stagiaires/${selectedActionStagiaire?.id}`, {
                        onSuccess: () => setModalDelete(false)
                    });
                }}>
                    <ModalBody>
                        <h5 className="text-center mt-3 mb-4">Êtes-vous sûr de vouloir supprimer cette donnée ?</h5>
                    </ModalBody>
                    <ModalFooter className="justify-content-center">
                        <Button color="danger" type="submit" disabled={deleteForm.processing} className="px-4">Oui</Button>
                        <Button color="light" onClick={() => setModalDelete(false)} className="px-4">Non</Button>
                    </ModalFooter>
                </Form>
            </Modal>

        </React.Fragment>"""

content = content.replace("        </React.Fragment>", modals_injection)

with open('resources/js/velzone/pages/Cip/MesStagiaires/Index.tsx', 'w') as f:
    f.write(content)
