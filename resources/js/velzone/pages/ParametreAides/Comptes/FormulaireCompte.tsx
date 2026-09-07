import { Link } from '@inertiajs/react';
import React from 'react';
import { Button, Col, Form, Input, Label, Row } from 'reactstrap';

export interface DonneesCompte {
    nom: string;
    email: string;
    telephone: string;
    password: string;
    password_confirmation: string;
    actif: boolean;
    roles: string[];
    agences: number[];
}

interface Props {
    data: DonneesCompte;
    setData: (champ: keyof DonneesCompte, valeur: any) => void;
    errors: Partial<Record<keyof DonneesCompte, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    roles: string[];
    agences: { id: number; nom: string }[];
    /** En modification, laisser le mot de passe vide conserve celui en place. */
    motDePasseFacultatif?: boolean;
}

const basculerDansListe = <T,>(liste: T[], valeur: T): T[] =>
    liste.includes(valeur) ? liste.filter((element) => element !== valeur) : [...liste, valeur];

const FormulaireCompte = ({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    roles,
    agences,
    motDePasseFacultatif = false,
}: Props) => (
    <Form onSubmit={onSubmit}>
        <Row className="g-3">
            <Col md={6}>
                <Label htmlFor="nom" className="form-label">
                    Nom et prénoms <span className="text-danger">*</span>
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
                <Label htmlFor="email" className="form-label">
                    Adresse e-mail <span className="text-danger">*</span>
                </Label>
                <Input
                    type="email"
                    id="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    invalid={!!errors.email}
                />
                {errors.email && <div className="invalid-feedback">{errors.email}</div>}
            </Col>
            <Col md={6}>
                <Label htmlFor="telephone" className="form-label">Téléphone</Label>
                <Input
                    type="text"
                    id="telephone"
                    value={data.telephone}
                    onChange={(e) => setData('telephone', e.target.value)}
                    invalid={!!errors.telephone}
                />
                {errors.telephone && <div className="invalid-feedback">{errors.telephone}</div>}
            </Col>
            <Col md={6}>
                <Label className="form-label d-block">Statut</Label>
                <div className="form-check form-switch mt-2">
                    <Input
                        type="checkbox"
                        className="form-check-input"
                        id="actif"
                        checked={data.actif}
                        onChange={(e) => setData('actif', e.target.checked)}
                    />
                    <Label className="form-check-label" htmlFor="actif">
                        Compte actif
                    </Label>
                </div>
            </Col>
            <Col md={6}>
                <Label htmlFor="password" className="form-label">
                    Mot de passe {motDePasseFacultatif ? '' : <span className="text-danger">*</span>}
                </Label>
                <Input
                    type="password"
                    id="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    invalid={!!errors.password}
                />
                {motDePasseFacultatif && (
                    <small className="text-muted">Laisser vide pour conserver le mot de passe actuel.</small>
                )}
                {errors.password && <div className="invalid-feedback">{errors.password}</div>}
            </Col>
            <Col md={6}>
                <Label htmlFor="password_confirmation" className="form-label">Confirmation</Label>
                <Input
                    type="password"
                    id="password_confirmation"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />
            </Col>

            <Col md={6}>
                <Label className="form-label">Rôles</Label>
                <div className="border rounded p-3">
                    {roles.map((role) => (
                        <div className="form-check" key={role}>
                            <Input
                                type="checkbox"
                                className="form-check-input"
                                id={`role-${role}`}
                                checked={data.roles.includes(role)}
                                onChange={() => setData('roles', basculerDansListe(data.roles, role))}
                            />
                            <Label className="form-check-label" htmlFor={`role-${role}`}>
                                {role}
                            </Label>
                        </div>
                    ))}
                </div>
                {errors.roles && <div className="text-danger small mt-1">{errors.roles}</div>}
            </Col>
            <Col md={6}>
                <Label className="form-label">Périmètre d’agences</Label>
                <div className="border rounded p-3" style={{ maxHeight: '220px', overflowY: 'auto' }}>
                    {agences.map((agence) => (
                        <div className="form-check" key={agence.id}>
                            <Input
                                type="checkbox"
                                className="form-check-input"
                                id={`agence-${agence.id}`}
                                checked={data.agences.includes(agence.id)}
                                onChange={() => setData('agences', basculerDansListe(data.agences, agence.id))}
                            />
                            <Label className="form-check-label" htmlFor={`agence-${agence.id}`}>
                                {agence.nom}
                            </Label>
                        </div>
                    ))}
                </div>
                {errors.agences && <div className="text-danger small mt-1">{errors.agences}</div>}
            </Col>

            <Col md={12}>
                <div className="text-end mt-4">
                    <Link href="/parametre-aides/comptes" className="btn btn-light me-2">Annuler</Link>
                    <Button color="primary" type="submit" disabled={processing}>Enregistrer</Button>
                </div>
            </Col>
        </Row>
    </Form>
);

export default FormulaireCompte;
