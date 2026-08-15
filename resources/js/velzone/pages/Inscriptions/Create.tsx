import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardBody, CardHeader, Col, Container, Row, Button, Input, Label, Form } from 'reactstrap';
import BreadCrumb from '../../Components/Common/BreadCrumb';
import Select from 'react-select';

interface Props {
    offres: any[];
    agences: any[];
    communes: any[];
    typesStage: any[];
    originesStagiaire: any[];
    liensParente: any[];
    niveauxEtude: any[];
    diplomes: any[];
    typesEnseignement: any[];
    handicaps: any[];
    typesHandicap: any[];
    typesPaiement: any[];
}

const Create = ({ 
    offres, agences, communes, typesStage, originesStagiaire, 
    liensParente, niveauxEtude, diplomes, typesEnseignement, 
    handicaps, typesHandicap, typesPaiement 
}: Props) => {
    const { data, setData, post, processing, errors } = useForm({
        beneficiaire: {
            numero_aej: '',
            nom: '',
            prenoms: '',
            sexe: '',
            date_naissance: '',
            lieu_naissance: '',
            sous_prefecture_naissance: '',
            commune_residence_id: '',
            sous_prefecture_residence: '',
            nature_piece_identite: '',
            numero_piece_identite: '',
            numero_cmu: '',
            telephone_principal: '',
            telephone_secondaire: '',
            personne_urgence: '',
            lien_parente_id: '',
            contact_urgence_1: '',
            contact_urgence_2: '',
            niveau_etude_id: '',
            diplome_id: '',
            autre_diplome: '',
            specialite: '',
            annee_diplome: '',
            etablissement_frequente: '',
            type_enseignement_id: '',
            handicap_id: '',
            type_handicap_id: '',
            autre_handicap: '',
            type_paiement_id: '',
            numero_tresor_money: '',
            numero_wave: '',
        },
        stage: {
            agence_id: '',
            conseiller_id: '',
            origine_stagiaire_id: '',
            source_financement_id: '',
            type_stage_id: '',
            offre_emploi_id: '',
            entreprise_id: '',
            date_entree_portefeuille: '',
            service_affectation: '',
            intitule_poste: '',
            localite_stage: '',
            commune_stage: '',
            sous_prefecture_stage: '',
            nom_encadreur: '',
            fonction_encadreur: '',
            contact_encadreur: '',
            statut_stage: '',
            situation_stage: '',
            date_debut: '',
            date_fin_prevue: '',
            nbr_mois_capitaliser: 0,
            date_demarrage_capitalisation: '',
            date_demarrage_capitalisation_sans_financiere: '',
            observations: '',
        },
        contrat: {
            numero: '',
            date_debut: '',
            date_fin: '',
            prime_mensuelle: '',
        },
        documents: {
            cmu: null,
            piece_identite: null,
            diplome: null,
            rib: null,
            fiche_tresor_money: null,
            fiche_wave: null,
        }
    });

    const offresOptions = offres.map((offre) => ({
        value: offre.id,
        label: `${offre.numero} - ${offre.intitule} (${offre.entreprise?.raison_sociale})`,
        offre: offre
    }));

    const handleOffreSelect = (selectedOption: any) => {
        if (!selectedOption) return;
        const offre = selectedOption.offre;
        setData('stage', {
            ...data.stage,
            offre_emploi_id: offre.id,
            entreprise_id: offre.entreprise_id,
            agence_id: offre.agence_id,
            type_stage_id: offre.type_stage_id,
            source_financement_id: offre.source_financement_id,
            intitule_poste: offre.intitule,
        });
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/inscriptions');
    };

    return (
        <React.Fragment>
            <Head title="Création de Stagiaire" />
            <div className="page-content">
                <Container fluid>
                    <BreadCrumb title="Nouveau Stagiaire" pageTitle="Dossiers" />
                    
                    <Form onSubmit={submit}>
                        <Row>
                            {/* SECTION 1: AGENCE REGIONALE */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">AGENCE REGIONALE</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={4}>
                                                <Label>Offre d'emploi (Optionnel)</Label>
                                                <Select
                                                    options={offresOptions}
                                                    onChange={handleOffreSelect}
                                                    placeholder="Sélectionner une offre..."
                                                />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Agence <span className="text-danger">*</span></Label>
                                                <select className="form-select" value={data.stage.agence_id} onChange={e => setData('stage', {...data.stage, agence_id: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    {agences.map(a => <option key={a.id} value={a.id}>{a.nom}</option>)}
                                                </select>
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Origine du stagiaire</Label>
                                                <select className="form-select" value={data.stage.origine_stagiaire_id} onChange={e => setData('stage', {...data.stage, origine_stagiaire_id: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    {originesStagiaire.map(o => <option key={o.id} value={o.id}>{o.nom}</option>)}
                                                </select>
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Date d'entrée en portefeuille</Label>
                                                <Input type="date" value={data.stage.date_entree_portefeuille} onChange={e => setData('stage', {...data.stage, date_entree_portefeuille: e.target.value})} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* SECTION 2: IDENTIFICATION STAGIAIRE */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">IDENTIFICATION STAGIAIRE</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={3}>
                                                <Label>Numéro AEJ <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.numero_aej} onChange={e => setData('beneficiaire', {...data.beneficiaire, numero_aej: e.target.value})} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Nom <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.nom} onChange={e => setData('beneficiaire', {...data.beneficiaire, nom: e.target.value})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Prénoms <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.beneficiaire.prenoms} onChange={e => setData('beneficiaire', {...data.beneficiaire, prenoms: e.target.value})} />
                                            </Col>
                                            <Col lg={2}>
                                                <Label>Sexe <span className="text-danger">*</span></Label>
                                                <select className="form-select" value={data.beneficiaire.sexe} onChange={e => setData('beneficiaire', {...data.beneficiaire, sexe: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    <option value="M">Masculin</option>
                                                    <option value="F">Féminin</option>
                                                </select>
                                            </Col>

                                            <Col lg={4}>
                                                <Label>Date de naissance <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.beneficiaire.date_naissance} onChange={e => setData('beneficiaire', {...data.beneficiaire, date_naissance: e.target.value})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Lieu de naissance</Label>
                                                <Input type="text" value={data.beneficiaire.lieu_naissance} onChange={e => setData('beneficiaire', {...data.beneficiaire, lieu_naissance: e.target.value})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Sous-préfecture de naissance</Label>
                                                <Input type="text" value={data.beneficiaire.sous_prefecture_naissance} onChange={e => setData('beneficiaire', {...data.beneficiaire, sous_prefecture_naissance: e.target.value})} />
                                            </Col>

                                            <Col lg={6}>
                                                <Label>Commune de résidence</Label>
                                                <select className="form-select" value={data.beneficiaire.commune_residence_id} onChange={e => setData('beneficiaire', {...data.beneficiaire, commune_residence_id: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    {communes.map(c => <option key={c.id} value={c.id}>{c.nom}</option>)}
                                                </select>
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Sous-préfecture de résidence</Label>
                                                <Input type="text" value={data.beneficiaire.sous_prefecture_residence} onChange={e => setData('beneficiaire', {...data.beneficiaire, sous_prefecture_residence: e.target.value})} />
                                            </Col>

                                            {/* Académique */}
                                            <Col lg={4}>
                                                <Label>Niveau d'étude</Label>
                                                <select className="form-select" value={data.beneficiaire.niveau_etude_id} onChange={e => setData('beneficiaire', {...data.beneficiaire, niveau_etude_id: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    {niveauxEtude.map(n => <option key={n.id} value={n.id}>{n.nom}</option>)}
                                                </select>
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Diplôme</Label>
                                                <select className="form-select" value={data.beneficiaire.diplome_id} onChange={e => setData('beneficiaire', {...data.beneficiaire, diplome_id: e.target.value})}>
                                                    <option value="">Sélectionner</option>
                                                    {diplomes.map(d => <option key={d.id} value={d.id}>{d.nom}</option>)}
                                                </select>
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Établissement fréquenté</Label>
                                                <Input type="text" value={data.beneficiaire.etablissement_frequente} onChange={e => setData('beneficiaire', {...data.beneficiaire, etablissement_frequente: e.target.value})} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* SECTION 3: MISE EN STAGE */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">MISE EN STAGE</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={6}>
                                                <Label>Intitulé du poste <span className="text-danger">*</span></Label>
                                                <Input type="text" value={data.stage.intitule_poste} onChange={e => setData('stage', {...data.stage, intitule_poste: e.target.value})} />
                                            </Col>
                                            <Col lg={6}>
                                                <Label>Service d'affectation</Label>
                                                <Input type="text" value={data.stage.service_affectation} onChange={e => setData('stage', {...data.stage, service_affectation: e.target.value})} />
                                            </Col>
                                            
                                            <Col lg={4}>
                                                <Label>Nom Encadreur</Label>
                                                <Input type="text" value={data.stage.nom_encadreur} onChange={e => setData('stage', {...data.stage, nom_encadreur: e.target.value})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Fonction Encadreur</Label>
                                                <Input type="text" value={data.stage.fonction_encadreur} onChange={e => setData('stage', {...data.stage, fonction_encadreur: e.target.value})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Contact Encadreur</Label>
                                                <Input type="text" value={data.stage.contact_encadreur} onChange={e => setData('stage', {...data.stage, contact_encadreur: e.target.value})} />
                                            </Col>

                                            <Col lg={3}>
                                                <Label>Date de début <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.stage.date_debut} onChange={e => setData('stage', {...data.stage, date_debut: e.target.value})} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Date de fin prévue <span className="text-danger">*</span></Label>
                                                <Input type="date" value={data.stage.date_fin_prevue} onChange={e => setData('stage', {...data.stage, date_fin_prevue: e.target.value})} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Nbr mois à capitaliser</Label>
                                                <Input type="number" value={data.stage.nbr_mois_capitaliser} onChange={e => setData('stage', {...data.stage, nbr_mois_capitaliser: parseInt(e.target.value) || 0})} />
                                            </Col>
                                            <Col lg={3}>
                                                <Label>Prime mensuelle (Contrat) <span className="text-danger">*</span></Label>
                                                <Input type="number" value={data.contrat.prime_mensuelle} onChange={e => setData('contrat', {...data.contrat, prime_mensuelle: e.target.value, date_debut: data.stage.date_debut, date_fin: data.stage.date_fin_prevue})} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* SECTION 4: PIECES JUSTIFICATIVES */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">PIÈCES JUSTIFICATIVES</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={4}>
                                                <Label>Pièce d'identité</Label>
                                                <Input type="file" onChange={e => setData('documents', {...data.documents, piece_identite: e.target.files ? e.target.files[0] : null})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Fichier CMU</Label>
                                                <Input type="file" onChange={e => setData('documents', {...data.documents, cmu: e.target.files ? e.target.files[0] : null})} />
                                            </Col>
                                            <Col lg={4}>
                                                <Label>Diplôme</Label>
                                                <Input type="file" onChange={e => setData('documents', {...data.documents, diplome: e.target.files ? e.target.files[0] : null})} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            {/* SECTION 5: OBSERVATIONS */}
                            <Col lg={12}>
                                <Card>
                                    <CardHeader>
                                        <h5 className="card-title mb-0">OBSERVATIONS</h5>
                                    </CardHeader>
                                    <CardBody>
                                        <Row className="g-3">
                                            <Col lg={12}>
                                                <Label>Observations éventuelles</Label>
                                                <Input type="textarea" rows="4" value={data.stage.observations} onChange={e => setData('stage', {...data.stage, observations: e.target.value})} />
                                            </Col>
                                        </Row>
                                    </CardBody>
                                </Card>
                            </Col>

                            <Col lg={12}>
                                <div className="text-end mb-4">
                                    <Link href="/inscriptions" className="btn btn-light me-2">Annuler</Link>
                                    <Button color="primary" type="submit" disabled={processing}>
                                        <i className="ri-save-line align-bottom me-1"></i> Enregistrer le dossier
                                    </Button>
                                </div>
                            </Col>
                        </Row>
                    </Form>
                </Container>
            </div>
        </React.Fragment>
    );
};

export default Create;
