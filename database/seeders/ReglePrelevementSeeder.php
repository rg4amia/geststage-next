<?php

namespace Database\Seeders;

use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\SourceFinancement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Prélèvements CMU datés (équivalent des deux lignes insérées par la migration
 * legacy 2026_08_31_000001_create_payment_deduction_rules_and_snapshot_payments).
 *
 * Le budget État porte la cotisation CMU depuis septembre 2026 :
 *  - prime de démarrage : 6 000 F quel que soit le type de stage ;
 *  - stage école : 3 000 F.
 *
 * La source « Budget État » correspond côté Next à `sources_financement`
 * `ancien_id = 3` (libellé BUDGET AEJ), renumérotée à la migration.
 */
class ReglePrelevementSeeder extends Seeder
{
    private const BUDGET_ETAT_ANCIEN_ID = 3;

    public function run(): void
    {
        $sourceBudgetEtat = SourceFinancement::query()
            ->where('ancien_id', self::BUDGET_ETAT_ANCIEN_ID)
            ->first();

        if (! $sourceBudgetEtat) {
            return;
        }

        $regles = [
            [
                'nom' => 'Cotisation CMU - Prime de démarrage Budget État',
                'type_prelevement' => ReglePrelevement::TYPE_CMU,
                'source_financement_id' => $sourceBudgetEtat->id,
                'type_stage_id' => null,
                'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
                'montant' => 6000,
            ],
            [
                'nom' => 'Cotisation CMU - Stage école Budget État',
                'type_prelevement' => ReglePrelevement::TYPE_CMU,
                'source_financement_id' => $sourceBudgetEtat->id,
                'type_stage_id' => 2,
                'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
                'montant' => 3000,
            ],
        ];

        foreach ($regles as $regle) {
            ReglePrelevement::query()->updateOrCreate([
                'nom' => $regle['nom'],
                'source_financement_id' => $regle['source_financement_id'],
                'type_paiement' => $regle['type_paiement'],
                'effet_du' => '2026-09-01',
            ], $regle + [
                'effet_du' => '2026-09-01',
                'effet_au' => null,
                'actif' => true,
            ]);
        }

        // Le legacy conservait le type de stage cible sous la forme d'un
        // `type_stage_id` legacy (2 = stage école) : le référentiel Next
        // conserve `ancien_id`, on résout donc l'identifiant courant.
        $stageEcoleId = DB::table('types_stage')->where('ancien_id', 2)->value('id');

        if ($stageEcoleId) {
            ReglePrelevement::query()
                ->where('nom', 'Cotisation CMU - Stage école Budget État')
                ->update(['type_stage_id' => $stageEcoleId]);
        }
    }
}
