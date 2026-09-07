import { Link } from '@inertiajs/react';
import React from 'react';
import { Button, Col, Form, Input, Label, Row } from 'reactstrap';

export interface DonneesAgence {
    code: string;
    nom: string;
    region_id: string | number;
    commune_id: string | number;
    adresse: string;
    actif: boolean;
}

interface Props {
    data: DonneesAgence;
    setData: (champ: keyof DonneesAgence, valeur: any) => void;
    errors: Partial<Record<keyof DonneesAgence, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    regions: { id: number; nom: string }[];
    communes: { id: number; nom: string; region_id: number | null }[];
}

const FormulaireAgence = ({ data, setData, errors, processing, onSubmit, regions, communes }: Props) => {
    // Les communes proposées suivent la région sélectionnée, comme dans le legacy.
    const communesFiltrees = data.region_id
        ? communes.filter((commune) => String(commune.region_id) === String(data.region_id))
        : communes;

    return (
        <Form onSubmit={onSubmit}>
            <Row className="g-3">
                <Col md={4}>
                    <Label htmlFor="code" className="form-label">
                        Code <span className="text-danger">*</span>
                    </Label>
                    <Input
                        type="text"
                        id="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        invalid={!!errors.code}
                    />
                    {errors.code && <div className="invalid-feedback">{errors.code}</div>}
                </Col>
                <Col md={8}>
                    <Label htmlFor="nom" className="form-label">
                        Nom de l’agence <span className="text-danger">*</span>
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
                    <Label htmlFor="region_id" className="form-label">Région</Label>
                    <select
                        className={`form-select ${errors.region_id ? 'is-invalid' : ''}`}
                        id="region_id"
                        value={data.region_id}
                        onChange={(e) => {
                            setData('region_id', e.target.value);
                            setData('commune_id', '');
                        }}
                    >
                        <option value="">Sélectionner une région</option>
                        {regions.map((region) => (
                            <option key={region.id} value={region.id}>{region.nom}</option>
                        ))}
                    </select>
                    {errors.region_id && <div className="invalid-feedback">{errors.region_id}</div>}
                </Col>
                <Col md={6}>
                    <Label htmlFor="commune_id" className="form-label">Commune</Label>
                    <select
                        className={`form-select ${errors.commune_id ? 'is-invalid' : ''}`}
                        id="commune_id"
                        value={data.commune_id}
                        onChange={(e) => setData('commune_id', e.target.value)}
                    >
                        <option value="">Sélectionner une commune</option>
                        {communesFiltrees.map((commune) => (
                            <option key={commune.id} value={commune.id}>{commune.nom}</option>
                        ))}
                    </select>
                    {errors.commune_id && <div className="invalid-feedback">{errors.commune_id}</div>}
                </Col>
                <Col md={12}>
                    <Label htmlFor="adresse" className="form-label">Adresse / contact</Label>
                    <Input
                        type="text"
                        id="adresse"
                        value={data.adresse}
                        onChange={(e) => setData('adresse', e.target.value)}
                        invalid={!!errors.adresse}
                    />
                    {errors.adresse && <div className="invalid-feedback">{errors.adresse}</div>}
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
                        <Label className="form-check-label" htmlFor="actif">Agence active</Label>
                    </div>
                </Col>
                <Col md={12}>
                    <div className="text-end mt-4">
                        <Link href="/parametre-aides/agences" className="btn btn-light me-2">Annuler</Link>
                        <Button color="primary" type="submit" disabled={processing}>Enregistrer</Button>
                    </div>
                </Col>
            </Row>
        </Form>
    );
};

export default FormulaireAgence;
