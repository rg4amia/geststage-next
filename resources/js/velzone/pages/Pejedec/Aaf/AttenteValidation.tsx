import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button } from 'reactstrap';
import AafSectionPage from './AafSectionPage';

const AttenteValidation = ({
    attenteValidation = [],
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
        router.get('/pejedec/af/attente-validation', selectedFilters, { preserveScroll: true, preserveState: true });
    };

    const validerPointage = (id: number) => {
        if (confirm('Valider ce pointage PEJEDEC ?')) {
            router.post(`/pejedec/af/pointages/${id}/valider`, {}, {
                preserveScroll: true,
            });
        }
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
        { header: 'Entreprise', accessorKey: 'stage.entreprise.raison_sociale', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Agence', accessorKey: 'stage.agence.nom', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Période', accessorKey: 'periode.code', cell: (cell: any) => cell.row.original.periode?.code || cell.row.original.periode?.nom || '-' },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: () => <Badge color="info">Soumis</Badge>,
        },
        {
            header: 'Actions',
            cell: (cell: any) => (
                <div className="d-flex gap-2">
                    <Button color="success" size="sm" outline onClick={() => validerPointage(cell.row.original.id)}>
                        Valider
                    </Button>
                    <Button color="primary" size="sm" outline onClick={() => router.visit('/cip/pointages/pejedec')}>
                        Ouvrir le pointage
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AafSectionPage
            title="PEJEDEC - Attente validation"
            pageTitle="Attente validation PEJEDEC"
            badge="PEJEDEC"
            heroTitle="Dossiers PEJEDEC en attente"
            heroText="Validation et contrôle des dossiers."
            summaryCards={[{ label: 'Dossiers à valider', value: attenteValidation.length, color: 'info' }]}
            filters={selectedFilters}
            setFilters={setSelectedFilters}
            onSearch={search}
            data={attenteValidation}
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

export default AttenteValidation;
