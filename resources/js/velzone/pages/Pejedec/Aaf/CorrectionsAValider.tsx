import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button } from 'reactstrap';
import AafSectionPage from './AafSectionPage';

const CorrectionsAValider = ({
    correctionsAValider = [],
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
        router.get('/pejedec/af/corrections-a-valider', selectedFilters, { preserveScroll: true, preserveState: true });
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
        { header: 'Jours présents', accessorKey: 'jours_presents', cell: (cell: any) => cell.getValue() ?? '-' },
        { header: 'Jours absents', accessorKey: 'jours_absents', cell: (cell: any) => cell.getValue() ?? '-' },
        { header: 'Observation', accessorKey: 'observation', cell: (cell: any) => cell.getValue() || '-' },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: () => <Badge color="primary">Corrigé CIP</Badge>,
        },
        {
            header: 'Actions',
            cell: () => (
                <Button color="info" size="sm" outline onClick={() => router.visit('/cip/pointages/pejedec')}>
                    Vérifier la correction
                </Button>
            ),
        },
    ];

    return (
        <AafSectionPage
            title="PEJEDEC - Corrections à valider"
            pageTitle="Corrections à valider PEJEDEC"
            badge="PEJEDEC"
            heroTitle="Corrections PEJEDEC à valider"
            heroText="Cas corrigés côté CIP en attente de revue."
            summaryCards={[{ label: 'Corrections à valider', value: correctionsAValider.length, color: 'primary' }]}
            filters={selectedFilters}
            setFilters={setSelectedFilters}
            onSearch={search}
            data={correctionsAValider}
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

export default CorrectionsAValider;
