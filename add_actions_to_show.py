import re

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'r') as f:
    content = f.read()

# 1. Update imports
if "usePage" not in content:
    content = content.replace("import { Head, Link } from '@inertiajs/react';", "import { Head, Link, usePage, useForm } from '@inertiajs/react';")
if "useState" not in content:
    content = content.replace("import React from 'react';", "import React, { useState } from 'react';")
if "Modal" not in content:
    content = content.replace("import { Container, Row, Col, Card, CardBody, CardHeader, Table, Badge, Button } from 'reactstrap';", "import { Container, Row, Col, Card, CardBody, CardHeader, Table, Badge, Button, Modal, ModalHeader, ModalBody, ModalFooter, Input, Form } from 'reactstrap';")

# 2. Add state and forms
states = """    const { auth } = usePage<any>().props;
    const userRoles = auth?.user?.roles || [];
    const isAdministrateur = userRoles.includes('administrateur') || auth?.user?.id === 1;
    const isChefAgence = userRoles.includes('chef_agence');
    const active_chef_agence = stage?.active_chef_agence || 0;
    const type_paiement_id = beneficiaire?.type_paiement?.id;

    // Modals
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

    const deleteForm = useForm({});"""

content = content.replace("const documents = stage?.documents || [];", "const documents = stage?.documents || [];\n" + states)

# 3. Replace Actions Disponibles
old_actions = """                                            {etapeCourante?.nom === 'Validation CA' ? (
                                                <>
                                                    <Link method="post" href={`/validations/demarrage/${instance.id}`} as="button" className="btn btn-success">
                                                        <i className="ri-check-double-line align-middle me-1"></i> Valider le Démarrage
                                                    </Link>
                                                    <Button color="danger" outline onClick={() => {
                                                        const motif = prompt("Veuillez saisir le motif de l'ajournement :");
                                                        if(motif) {
                                                            import('@inertiajs/react').then(({ router }) => {
                                                                router.post(`/validations/ajourner/${instance.id}`, { motif });
                                                            });
                                                        }
                                                    }}>
                                                        <i className="ri-close-circle-line align-middle me-1"></i> Ajourner le Dossier
                                                    </Button>
                                                </>
                                            ) : taches_ouvertes && taches_ouvertes.length > 0 ? (
                                                <Button color="primary">Prendre en charge la tâche</Button>
                                            ) : (
                                                <div className="text-muted text-center">Aucune tâche ouverte pour votre profil.</div>
                                            )}"""

new_actions = """                                            {etapeCourante?.nom === 'Validation CA' ? (
                                                <>
                                                    <Link method="post" href={`/validations/demarrage/${instance.id}`} as="button" className="btn btn-success">
                                                        <i className="ri-check-double-line align-middle me-1"></i> Valider le Démarrage
                                                    </Link>
                                                    <Button color="danger" outline onClick={() => {
                                                        const motif = prompt("Veuillez saisir le motif de l'ajournement :");
                                                        if(motif) {
                                                            import('@inertiajs/react').then(({ router }) => {
                                                                router.post(`/validations/ajourner/${instance.id}`, { motif });
                                                            });
                                                        }
                                                    }}>
                                                        <i className="ri-close-circle-line align-middle me-1"></i> Ajourner le Dossier
                                                    </Button>
                                                </>
                                            ) : taches_ouvertes && taches_ouvertes.length > 0 ? (
                                                <Button color="primary">Prendre en charge la tâche</Button>
                                            ) : null}

                                            {/* Nouveaux boutons d'actions optimisés */}
                                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && (
                                                <>
                                                    <Button color="warning" className="text-white" onClick={() => setModalGenererContrat(true)}>
                                                        <i className="ri-file-text-line align-middle me-1"></i> Générer Contrat
                                                    </Button>
                                                    <Button color="secondary" onClick={() => setModalTransferer(true)}>
                                                        <i className="ri-share-forward-line align-middle me-1"></i> Transférer Contrat
                                                    </Button>
                                                </>
                                            )}
                                            
                                            {(isAdministrateur || isChefAgence) && type_paiement_id === 1 && (
                                                <Button tag="a" href={`/cip/mes-stagiaires/${instance.id}/generer-tresor-money`} target="_blank" color="success">
                                                    <i className="ri-money-dollar-circle-line align-middle me-1"></i> Générer Trésor Money
                                                </Button>
                                            )}
                                            
                                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && type_paiement_id === 1 && (
                                                <Button color="success" outline onClick={() => setModalTresorMoney(true)}>
                                                    <i className="ri-wallet-3-line align-middle me-1"></i> Joindre Trésor Money
                                                </Button>
                                            )}

                                            {(isAdministrateur || isChefAgence) && active_chef_agence === 0 && (
                                                <>
                                                    <Button tag="a" href={`/cip/mes-stagiaires/${instance.id}/edit`} color="dark">
                                                        <i className="ri-edit-line align-middle me-1"></i> Modifier
                                                    </Button>
                                                    <Button color="danger" onClick={() => setModalDelete(true)}>
                                                        <i className="ri-delete-bin-line align-middle me-1"></i> Supprimer
                                                    </Button>
                                                </>
                                            )}"""

content = content.replace(old_actions, new_actions)

# 4. Insert Modals right before the end of the file `    );`
modals = """            {/* Modal Générer Contrat */}
            <Modal isOpen={modalGenererContrat} toggle={() => setModalGenererContrat(!modalGenererContrat)} centered>
                <ModalHeader toggle={() => setModalGenererContrat(!modalGenererContrat)} className="bg-warning text-white">Générer Contrat</ModalHeader>
                <Form onSubmit={(e) => {
                    e.preventDefault();
                    window.open(`/cip/mes-stagiaires/${instance.id}/generer-contrat?fonction=${genererContratForm.data.fonction_dg}&montant=${genererContratForm.data.montant}`, '_blank');
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
                    transfererForm.post(`/cip/mes-stagiaires/${instance.id}/transferer-contrat`, {
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
                    tresorMoneyForm.post(`/cip/mes-stagiaires/${instance.id}/upload-tresor-money`, {
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
                    deleteForm.delete(`/cip/mes-stagiaires/${instance.id}`, {
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
"""

content = re.sub(r'(\s*</React\.Fragment>\s*\);\s*};\s*export default Show;)', r'\n' + modals + r'\1', content)

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'w') as f:
    f.write(content)
