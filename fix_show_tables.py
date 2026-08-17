import re

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'r') as f:
    content = f.read()

old_tables_block = """                                    <Table className="table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td className="fw-medium">Nom complet</td>
                                                <td>{beneficiaire?.nom} {beneficiaire?.prenoms}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Date et lieu de naissance</td>
                                                <td>{formatDate(beneficiaire?.date_naissance)} à {beneficiaire?.lieu_naissance}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Sexe</td>
                                                <td>{beneficiaire?.sexe === 'M' ? 'Masculin' : 'Féminin'}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Niveau d'étude / Diplôme</td>
                                                <td>{beneficiaire?.diplome?.nom || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Commune de résidence</td>
                                                <td>{beneficiaire?.commune_residence?.nom || 'N/A'}</td>
                                            </tr>
                                        </tbody>
                                    </Table>
                                </CardBody>
                            </Card>

                            {/* STAGE & CONTRAT CARD */}
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-briefcase-line align-middle me-1 text-muted"></i> Informations sur le Stage</h5>
                                </CardHeader>
                                <CardBody>
                                    <Table className="table-borderless table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td className="fw-medium">Intitulé du poste</td>
                                                <td>{stage?.intitule_poste}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Dates prévues</td>
                                                <td>Du {formatDate(stage?.date_debut)} au {formatDate(stage?.date_fin_prevue)}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Encadreur</td>
                                                <td>{stage?.nom_encadreur} ({stage?.contact_encadreur})</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Prime mensuelle</td>
                                                <td>{contrats[0]?.prime_mensuelle ? `${contrats[0]?.prime_mensuelle} FCFA` : 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td className="fw-medium">Observations</td>
                                                <td>{stage?.observations || 'Aucune observation'}</td>
                                            </tr>
                                        </tbody>
                                    </Table>"""

new_tables_block = """                                    <Row>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Numéro AEJ</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_aej || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Nom complet</td>
                                                        <td>{beneficiaire?.nom} {beneficiaire?.prenoms}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Date et lieu de naissance</td>
                                                        <td>{formatDate(beneficiaire?.date_naissance)} à {beneficiaire?.lieu_naissance}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Sexe</td>
                                                        <td>{beneficiaire?.sexe === 'M' ? 'Masculin' : (beneficiaire?.sexe === 'F' ? 'Féminin' : 'N/A')}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Pièce d'identité</td>
                                                        <td>{beneficiaire?.nature_piece_identite || 'N/A'} N° {beneficiaire?.numero_piece_identite || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Commune de résidence</td>
                                                        <td>{beneficiaire?.commune_residence?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Contact Urgence</td>
                                                        <td>{beneficiaire?.personne_urgence || 'N/A'} ({beneficiaire?.contact_urgence_1 || 'N/A'})</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Téléphones</td>
                                                        <td>{beneficiaire?.telephone_principal || 'N/A'} / {beneficiaire?.telephone_secondaire || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Email</td>
                                                        <td>{beneficiaire?.email || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Niveau d'étude</td>
                                                        <td>{beneficiaire?.niveau_etude?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Diplôme</td>
                                                        <td>{beneficiaire?.diplome?.nom || beneficiaire?.autre_diplome || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Type Paiement</td>
                                                        <td><Badge color="info">{beneficiaire?.type_paiement?.nom || 'N/A'}</Badge></td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">N° Trésor Money</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_tresor_money || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">N° Wave</td>
                                                        <td className="fw-bold">{beneficiaire?.numero_wave || 'N/A'}</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                    </Row>
                                </CardBody>
                            </Card>

                            {/* STAGE & CONTRAT CARD */}
                            <Card>
                                <CardHeader>
                                    <h5 className="card-title mb-0"><i className="ri-briefcase-line align-middle me-1 text-muted"></i> Informations sur le Stage</h5>
                                </CardHeader>
                                <CardBody>
                                    <Row>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Intitulé du poste</td>
                                                        <td className="fw-bold">{stage?.intitule_poste}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Type de Stage</td>
                                                        <td>{stage?.type_stage?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Programme</td>
                                                        <td>{stage?.programme?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Source de financement</td>
                                                        <td>{stage?.source_financement?.nom || 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Situation du Stage</td>
                                                        <td><Badge color="secondary">{stage?.situation_stage || 'N/A'}</Badge></td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                        <Col md={6}>
                                            <Table className="table-borderless table-sm mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Dates prévues</td>
                                                        <td>Du {formatDate(stage?.date_debut)} au {formatDate(stage?.date_fin_prevue)}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Encadreur</td>
                                                        <td>{stage?.nom_encadreur} <br/><span className="text-muted fs-12">{stage?.contact_encadreur}</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Prime mensuelle</td>
                                                        <td className="fw-bold text-success">{contrats[0]?.prime_mensuelle ? `${Number(contrats[0]?.prime_mensuelle).toLocaleString('fr-FR')} FCFA` : 'N/A'}</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="fw-medium text-muted">Observations</td>
                                                        <td>{stage?.observations || 'Aucune observation'}</td>
                                                    </tr>
                                                </tbody>
                                            </Table>
                                        </Col>
                                    </Row>"""

content = content.replace(old_tables_block, new_tables_block)

with open('resources/js/velzone/pages/Inscriptions/Show.tsx', 'w') as f:
    f.write(content)
