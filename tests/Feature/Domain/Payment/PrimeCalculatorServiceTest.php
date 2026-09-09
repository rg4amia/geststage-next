<?php

namespace Tests\Feature\Domain\Payment;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use App\Domain\Payment\Services\Prime\PrimeCalculationException;
use App\Domain\Payment\Services\Prime\PrimeCalculatorService;
use App\Domain\Payment\Services\Prime\PrimeConfigurationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Barème des primes : le montant attendu est la prime **du mois**, jamais le dû
 * de tout le contrat. Les cas ci-dessous transcrivent les règles du legacy
 * (`PrimeCalculatorService` et ses stratégies) sur les valeurs livrées dans
 * `config/primes.php`.
 */
class PrimeCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PrimeCalculatorService $calculateur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateur = app(PrimeCalculatorService::class);
    }

    private function contexte(
        int $typeStage,
        int $financement,
        string $debut,
        string $fin,
        ?int $structure = null,
        ?string $entreprise = null,
    ): ContexteCalculPrime {
        $dateDebut = Carbon::parse($debut);
        $dateFin = Carbon::parse($fin);

        return new ContexteCalculPrime(
            stageId: null,
            typeStageLegacyId: $typeStage,
            sourceFinancementLegacyId: $financement,
            typeStructureLegacyId: $structure,
            dateDebut: $dateDebut,
            dateFin: $dateFin,
            dureeMois: ContexteCalculPrime::dureeEnMois($dateDebut, $dateFin),
            nomEntreprise: $entreprise,
        );
    }

    // -----------------------------------------------------------------
    // Stage de qualification
    // -----------------------------------------------------------------

    public function test_qualification_mois_intermediaire_donne_le_montant_plein(): void
    {
        $contexte = $this->contexte(1, 1, '2024-03-10', '2024-05-09');

        $this->assertSame(45000.0, $this->calculateur->calculer($contexte, '2024-04'));
    }

    public function test_qualification_prorata_du_mois_de_demarrage_et_du_mois_de_fin(): void
    {
        // Démarrage le 10 → plage `10-19` : 31 500 au démarrage, 13 500 à la fin.
        $contexte = $this->contexte(1, 1, '2024-03-10', '2024-05-09');

        $this->assertSame(31500.0, $this->calculateur->calculer($contexte, '2024-03'));
        $this->assertSame(13500.0, $this->calculateur->calculer($contexte, '2024-05'));
    }

    public function test_qualification_jour_de_demarrage_hors_plage_ne_donne_aucune_prime(): void
    {
        // Aucune plage ne couvre le 7 : le legacy renvoie 0, on ne l'invente pas.
        $contexte = $this->contexte(1, 1, '2024-03-07', '2024-05-06');

        $this->assertSame(0.0, $this->calculateur->calculer($contexte, '2024-03'));
    }

    public function test_qualification_budget_etat_public_bascule_sur_la_grille_75000(): void
    {
        // Démarrage postérieur à `qualification.budget_aej_effective_date`.
        $contexte = $this->contexte(1, 3, '2026-03-01', '2026-06-30', structure: 1);

        $this->assertSame(75000.0, $this->calculateur->calculer($contexte, '2026-04'));
    }

    public function test_qualification_budget_etat_anterieur_a_la_date_d_effet_reste_sur_45000(): void
    {
        $contexte = $this->contexte(1, 3, '2024-03-01', '2024-06-30', structure: 1);

        $this->assertSame(45000.0, $this->calculateur->calculer($contexte, '2024-04'));
    }

    // -----------------------------------------------------------------
    // MIRAH
    // -----------------------------------------------------------------

    public function test_mirah_l_emporte_sur_la_grille_de_qualification(): void
    {
        $contexte = $this->contexte(1, 1, '2024-03-10', '2024-05-09', entreprise: 'DD MIRAH DE BEOUMI');

        $this->assertSame(150000.0, $this->calculateur->calculer($contexte, '2024-04'));
        $this->assertSame('Prime MIRAH forfaitaire', $this->calculateur->libelleStrategie($contexte));
    }

    public function test_mirah_ne_s_applique_pas_hors_paps_gouv(): void
    {
        $contexte = $this->contexte(1, 3, '2024-03-10', '2024-05-09', entreprise: 'DD MIRAH DE BEOUMI');

        $this->assertSame(45000.0, $this->calculateur->calculer($contexte, '2024-04'));
    }

    // -----------------------------------------------------------------
    // Stage école
    // -----------------------------------------------------------------

    public function test_stage_ecole_mois_intermediaire_puis_mois_de_fin(): void
    {
        // Contrat de 3 mois démarré le 10 : 15 000 au milieu, 4 500 le dernier mois.
        $contexte = $this->contexte(2, 1, '2024-03-10', '2024-06-09');

        $this->assertSame(10500.0, $this->calculateur->calculer($contexte, '2024-03'));
        $this->assertSame(15000.0, $this->calculateur->calculer($contexte, '2024-04'));
        $this->assertSame(4500.0, $this->calculateur->calculer($contexte, '2024-06'));
    }

    public function test_stage_ecole_contrat_d_un_mois_et_demi_a_sa_propre_grille(): void
    {
        $contexte = $this->contexte(2, 1, '2024-03-10', '2024-04-24');

        $this->assertSame(1.5, $contexte->dureeMois);
        $this->assertSame(12000.0, $this->calculateur->calculer($contexte, '2024-04'));
    }

    // -----------------------------------------------------------------
    // Repli et erreurs
    // -----------------------------------------------------------------

    public function test_type_de_stage_inconnu_retombe_sur_le_repli_smig(): void
    {
        $contexte = new ContexteCalculPrime(
            stageId: null,
            typeStageLegacyId: null,
            sourceFinancementLegacyId: 1,
            typeStructureLegacyId: null,
            dateDebut: Carbon::parse('2024-03-10'),
            dateFin: Carbon::parse('2024-05-09'),
            dureeMois: 2.0,
            nomEntreprise: null,
        );

        $this->assertSame(0.0, $this->calculateur->calculer($contexte, '2024-04'));
        $this->assertSame('Repli SMIG', $this->calculateur->libelleStrategie($contexte));
    }

    public function test_dates_manquantes_font_echouer_le_calcul(): void
    {
        $contexte = new ContexteCalculPrime(
            stageId: 42,
            typeStageLegacyId: 1,
            sourceFinancementLegacyId: 1,
            typeStructureLegacyId: null,
            dateDebut: null,
            dateFin: null,
            dureeMois: 0.0,
            nomEntreprise: null,
        );

        $this->expectException(PrimeCalculationException::class);

        $this->calculateur->calculer($contexte, '2024-04');
    }

    // -----------------------------------------------------------------
    // Paramétrage
    // -----------------------------------------------------------------

    public function test_le_bareme_enregistre_par_l_administrateur_est_pris_en_compte(): void
    {
        $configuration = app(PrimeConfigurationService::class);
        $configuration->save(['pae' => ['45000' => ['full' => 50000]]], null);

        $contexte = $this->contexte(1, 1, '2024-03-10', '2024-05-09');

        $this->assertSame(50000.0, app(PrimeCalculatorService::class)->calculer($contexte, '2024-04'));

        // La surcharge est fusionnée, pas substituée : le prorata reste celui du défaut.
        $this->assertSame(31500.0, app(PrimeCalculatorService::class)->calculer($contexte, '2024-03'));
    }
}
