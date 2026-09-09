import { router, usePage } from '@inertiajs/react';
import React from 'react';
import { Alert, Button, Col, Row } from 'reactstrap';

/**
 * Rappel permanent qu'une session est ouverte sous une autre identité, avec le
 * retour vers le compte réel. Sans lui, l'usurpateur n'aurait aucun moyen de
 * revenir depuis un compte dépourvu de droits d'administration.
 *
 * Rendu par BreadCrumb, juste sous le titre de page : rendu au niveau du
 * Layout, avant le contenu, il se retrouvait masqué derrière le topbar fixe.
 */
const UsurpationBanner = () => {
    const { usurpation } = usePage().props as {
        usurpation?: { utilisateur: string | null } | null;
    };

    if (!usurpation) {
        return null;
    }

    return (
        <Row>
            <Col xs={12}>
                <Alert color="warning" className="d-flex align-items-center">
                    <i className="ri-spy-line align-bottom me-2 fs-18" />
                    <span className="flex-grow-1">
                        Vous naviguez en tant que <strong>{usurpation.utilisateur}</strong>.
                        Les actions réalisées le sont sous cette identité.
                    </span>
                    <Button
                        color="warning"
                        size="sm"
                        onClick={() => router.post('/parametre-aides/cesser-usurpation')}
                    >
                        Revenir à mon compte
                    </Button>
                </Alert>
            </Col>
        </Row>
    );
};

export default UsurpationBanner;
