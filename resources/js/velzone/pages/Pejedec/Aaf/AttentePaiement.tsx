import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button } from 'reactstrap';
import AafSectionPage from './AafSectionPage';

const AttentePaiement = ({
    attentePaiement = [],
    moisActuel,
    periode,
    sourceFinancement,
    agences = [],
    entreprises = [],
    sourcesFinancement = [],
    filters = {},
}: any) => {
    const [selectedFilters, setSelectedFilters] = useState({
        mois: filters?.mois || moisActuel || '',
        agence_id: filters?.agence_id || '',
        entreprise_id: filters?.entreprise_id || '',
        source_financement_id: filters?.source_financement_id || sourceFinancement?.id?.toString() || '3',
    });

    const search = () => {
        router.get('/pejedec/af/attente-paiement', selectedFilters, { preserveScroll: true, preserveState: true });
    };

    const columns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => (
                <div>
                    <h5 className="fs-14 mb-1">
                        {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                    </h5>
                    <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                </div>
            ),
        },
        { header: 'Agence', accessorKey: 'stage.agence.nom', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Montant', accessorKey: 'montant', cell: (cell: any) => `${cell.getValue() || 0} FCFA` },
        { header: 'Paiements', accessorKey: 'paiements_count', cell: (cell: any) => `${cell.getValue() || 0} associé(s)` },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: () => <Badge color="warning">Ouvert</Badge>,
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="success" size="sm" outline onClick={() => router.visit('/dmg/paiements')}>
                    Aller à la DMG
                </Button>
            ),
        },
    ];

    return (
        <AafSectionPage
            title="PEJEDEC - Attente paiement"
            pageTitle="Attente paiement PEJEDEC"
            badge="PEJEDEC"
            heroTitle="Dossiers PEJEDEC en attente de paiement"
            heroText="Préparation des droits et passage DMG."
            summaryCards={[{ label: 'Dossiers à payer', value: attentePaiement.length, color: 'warning' }]}
            filters={selectedFilters}
            setFilters={setSelectedFilters}
            onSearch={search}
            data={attentePaiement}
            columns={columns}
            moisActuel={moisActuel}
            periode={periode}
            sourceFinancement={sourceFinancement}
            agences={agences}
            entreprises={entreprises}
            sourcesFinancement={sourcesFinancement}
            backLink="/pejedec/af"
            backLabel="Retour tableau"
        />
    );
};

export default AttentePaiement;
