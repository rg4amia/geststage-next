import { Link } from '@inertiajs/react';
import React from 'react';
import { Button, Col, Form, Input, Label, Row } from 'reactstrap';

export interface DonneesConseiller {
    agence_id: string | number;
    nom: string;
    prenoms: string;
    matricule: string;
    actif: boolean;
}

interface Props {
    data: DonneesConseiller;
    setData: (champ: keyof DonneesConseiller, valeur: any) => void;
    errors: Partial<Record<keyof DonneesConseiller, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    agences: { id: number; nom: string }[];
}

const FormulaireConseiller = ({ data, setData, errors, processing, onSubmit, agences }: Props) => (
    <Form onSubmit={onSubmit}>
        <Row className="g-3">
            <Col md={6}>
                <Label htmlFor="agence_id" className="form-label">
                    Agence <span className="text-danger">*</span>
                </Label>
                <select
                    className={`form-select ${errors.agence_id ? 'is-invalid' : ''}`}
                    id="agence_id"
                    value={data.agence_id}
                    onChange={(e) => setData('agence_id', e.target.value)}
                >
                    <option value="">Sélectionner une agence</option>
                    {agences.map((agence) => (
                        <option key={agence.id} value={agence.id}>{agence.nom}</option>
                    ))}
                </select>
                {errors.agence_id && <div className="invalid-feedback">{errors.agence_id}</div>}
            </Col>
            <Col md={6}>
                <Label htmlFor="matricule" className="form-label">Matricule</Label>
                <Input
                    type="text"
                    id="matricule"
                    value={data.matricule}
                    onChange={(e) => setData('matricule', e.target.value)}
                    invalid={!!errors.matricule}
                />
                {errors.matricule && <div className="invalid-feedback">{errors.matricule}</div>}
            </Col>
            <Col md={6}>
                <Label htmlFor="nom" className="form-label">
                    Nom <span className="text-danger">*</span>
                </Label>
                <Input
                    type="text"
                    id="nom"
                    value={data.nom}
                    onChange={(e) => setData('nom', e.target.value)}
                    invalid={!!errors.nom}
                />
                {errors.nom && <div className="invalid-feedback">{errors.nom}</div>}
            </Col>
            <Col md={6}>
                <Label htmlFor="prenoms" className="form-label">Prénoms</Label>
                <Input
                    type="text"
                    id="prenoms"
                    value={data.prenoms}
                    onChange={(e) => setData('prenoms', e.target.value)}
                    invalid={!!errors.prenoms}
                />
                {errors.prenoms && <div className="invalid-feedback">{errors.prenoms}</div>}
            </Col>
            <Col md={12}>
                <div className="form-check form-switch">
                    <Input
                        type="checkbox"
                        className="form-check-input"
                        id="actif"
                        checked={data.actif}
                        onChange={(e) => setData('actif', e.target.checked)}
                    />
                    <Label className="form-check-label" htmlFor="actif">Conseiller actif</Label>
                </div>
            </Col>
            <Col md={12}>
                <div className="text-end mt-4">
                    <Link href="/parametre-aides/conseillers" className="btn btn-light me-2">Annuler</Link>
                    <Button color="primary" type="submit" disabled={processing}>Enregistrer</Button>
                </div>
            </Col>
        </Row>
    </Form>
);

export default FormulaireConseiller;
