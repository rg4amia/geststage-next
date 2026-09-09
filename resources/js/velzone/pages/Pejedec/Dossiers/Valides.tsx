import React from 'react';
import DossierListPage from './DossierListPage';

const Valides = (props: any) => (
    <DossierListPage
        {...props}
        title="PEJEDEC - Dossiers validés"
        pageTitle="Dossiers PEJEDEC validés"
        mode="valides"
    />
);

export default Valides;
