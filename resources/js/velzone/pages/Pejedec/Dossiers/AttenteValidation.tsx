import React from 'react';
import DossierListPage from './DossierListPage';

const AttenteValidation = (props: any) => (
    <DossierListPage
        {...props}
        title="PEJEDEC - Dossiers à valider"
        pageTitle="Dossiers PEJEDEC à valider"
        mode="attente"
    />
);

export default AttenteValidation;
