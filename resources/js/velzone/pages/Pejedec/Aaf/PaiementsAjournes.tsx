import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Badge, Button } from 'reactstrap';
import AafSectionPage from './AafSectionPage';

const PaiementsAjournes = ({
    paiementsAjournes = [],
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
        router.get('/pejedec/af/paiements-ajournes', selectedFilters, { preserveScroll: true, preserveState: true });
    };

    const columns = [
        {
            header: 'Bénéficiaire',
            accessorKey: 'stage.beneficiaire.nom',
            cell: (cell: any) => (
                <div>
                    <h5 className="fs-14 mb-1 text-danger">
                        {cell.row.original.stage?.beneficiaire?.nom} {cell.row.original.stage?.beneficiaire?.prenoms}
                    </h5>
                    <p className="text-muted mb-0">{cell.row.original.stage?.beneficiaire?.matricule}</p>
                </div>
            ),
        },
        { header: 'Motif / Observation', accessorKey: 'observation', cell: (cell: any) => cell.getValue() || '-' },
        { header: 'Jours', cell: (cell: any) => `${cell.row.original.jours_presents ?? 0} P / ${cell.row.original.jours_absents ?? 0} A` },
        {
            header: 'Statut',
            accessorKey: 'statut',
            cell: (cell: any) => {
                const statut = cell.getValue();
                if (statut === 'AJOURNE_DMG') return <Badge color="danger">Ajourné DMG</Badge>;
                if (statut === 'AJOURNE_CA') return <Badge color="warning">Ajourné CA</Badge>;
                return <Badge color="secondary">{statut || 'Inconnu'}</Badge>;
            },
        },
        { header: 'Date', accessorKey: 'date_soumission', cell: (cell: any) => cell.getValue() || cell.row.original.date_creation || '-' },
        {
            header: 'Actions',
            cell: () => (
                <Button color="warning" size="sm" outline onClick={() => router.visit('/cip/pointages/pejedec')}>
                    Revoir le pointage
                </Button>
            ),
        },
    ];

    return (
        <AafSectionPage
            title="PEJEDEC - Paiements ajournés"
            pageTitle="Paiements ajournés PEJEDEC"
            badge="PEJEDEC"
            heroTitle="Paiements PEJEDEC ajournés"
            heroText="Cas retournés pour correction ou reprise."
            summaryCards={[{ label: 'Paiements ajournés', value: paiementsAjournes.length, color: 'danger' }]}
            filters={selectedFilters}
            setFilters={setSelectedFilters}
            onSearch={search}
            data={paiementsAjournes}
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

export default PaiementsAjournes;
