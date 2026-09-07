import { Link } from '@inertiajs/react';
import React from 'react';
import Select from 'react-select';
import { Button, Col, Form, Input, Label, Row } from 'reactstrap';

export interface DonneesAgence {
    code: string;
    nom: string;
    contact_agence: string;
    chef_agence_nom: string;
    longitude: string | number;
    latitude: string | number;
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
                        Nom de l'agence <span className="text-danger">*</span>
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
                    <Label htmlFor="chef_agence_nom" className="form-label">Nom du chef d'agence</Label>
                    <Input
                        type="text"
                        id="chef_agence_nom"
                        placeholder="Nom complet du chef d'agence"
                        value={data.chef_agence_nom}
                        onChange={(e) => setData('chef_agence_nom', e.target.value)}
                        invalid={!!errors.chef_agence_nom}
                    />
                    {errors.chef_agence_nom && <div className="invalid-feedback">{errors.chef_agence_nom}</div>}
                </Col>
                <Col md={6}>
                    <Label htmlFor="contact_agence" className="form-label">Contact de l'agence</Label>
                    <Input
                        type="text"
                        id="contact_agence"
                        placeholder="Téléphone, email ou autre contact"
                        value={data.contact_agence}
                        onChange={(e) => setData('contact_agence', e.target.value)}
                        invalid={!!errors.contact_agence}
                    />
                    {errors.contact_agence && <div className="invalid-feedback">{errors.contact_agence}</div>}
                </Col>
                <Col md={6}>
                    <Label htmlFor="longitude" className="form-label">Longitude</Label>
                    <Input
                        type="number"
                        step="0.0000001"
                        id="longitude"
                        placeholder="-5.6781234"
                        value={data.longitude}
                        onChange={(e) => setData('longitude', e.target.value)}
                        invalid={!!errors.longitude}
                    />
                    {errors.longitude && <div className="invalid-feedback">{errors.longitude}</div>}
                </Col>
                <Col md={6}>
                    <Label htmlFor="latitude" className="form-label">Latitude</Label>
                    <Input
                        type="number"
                        step="0.0000001"
                        id="latitude"
                        placeholder="6.3674215"
                        value={data.latitude}
                        onChange={(e) => setData('latitude', e.target.value)}
                        invalid={!!errors.latitude}
                    />
                    {errors.latitude && <div className="invalid-feedback">{errors.latitude}</div>}
                </Col>
                <Col md={6}>
                    <Label className="form-label">Région</Label>
                    <Select
                        isSearchable
                        placeholder="Sélectionner une région"
                        noOptionsMessage={() => 'Aucune région'}
                        options={regions.map((r) => ({ value: String(r.id), label: r.nom }))}
                        value={data.region_id ? { value: String(data.region_id), label: regions.find((r) => String(r.id) === String(data.region_id))?.nom || '' } : null}
                        onChange={(selected) => {
                            setData('region_id', selected?.value || '');
                            setData('commune_id', '');
                        }}
                        classNamePrefix="react-select"
                        className={errors.region_id ? 'is-invalid' : ''}
                    />
                    {errors.region_id && <div className="text-danger small mt-1">{errors.region_id}</div>}
                </Col>
                <Col md={6}>
                    <Label className="form-label">Commune</Label>
                    <Select
                        isSearchable
                        placeholder="Sélectionner une commune"
                        noOptionsMessage={() => 'Aucune commune'}
                        options={communesFiltrees.map((c) => ({ value: String(c.id), label: c.nom }))}
                        value={data.commune_id ? { value: String(data.commune_id), label: communes.find((c) => String(c.id) === String(data.commune_id))?.nom || '' } : null}
                        onChange={(selected) => setData('commune_id', selected?.value || '')}
                        classNamePrefix="react-select"
                        className={errors.commune_id ? 'is-invalid' : ''}
                    />
                    {errors.commune_id && <div className="text-danger small mt-1">{errors.commune_id}</div>}
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
