import { router, usePage } from '@inertiajs/react';
import React from 'react';
import { Alert, Button, Container } from 'reactstrap';

/**
 * Rappel permanent qu'une session est ouverte sous une autre identité, avec le
 * retour vers le compte réel. Sans lui, l'usurpateur n'aurait aucun moyen de
 * revenir depuis un compte dépourvu de droits d'administration.
 */
const UsurpationBanner = () => {
    const { usurpation } = usePage().props as {
        usurpation?: { utilisateur: string | null } | null;
    };

    if (!usurpation) {
        return null;
    }

    return (
        <Container fluid className="usurpation-banner pt-3">
            <Alert color="warning" className="d-flex align-items-center mb-0">
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
        </Container>
    );
};

export default UsurpationBanner;
