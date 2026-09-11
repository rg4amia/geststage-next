<?php

namespace Tests\Feature\Domain\Payment;

use App\Domain\Payment\Services\ApplicationPrelevementsService;
use App\Domain\Payment\Services\MultiDossierPdfService;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Les PDF de la page DMG (état de paiement, attestation de présence, attestation de
 * démarrage) doivent reprendre l'habillage institutionnel legacy (en-tête + signataire) qui
 * diffère par source de financement — voir la mémoire `pdf-dmg-paiements-portage-legacy`.
 *
 * DomPDF ne permet pas de relire le HTML rendu après conversion (`outputHtml()` ne
 * renvoie que le `<head>`), donc ces tests exercent directement les vues Blade avec les
 * mêmes variables que celles construites par `MultiDossierPdfService` /
 * `ExportPaiementDmgService`.
 */
class ExportPaiementDmgPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_etat_financier_renders_paps_gouv_header_and_signature(): void
    {
        $paiement = $this->paiementPourSource($this->source('PA_PS_GOUV'));

        $html = View::make('pdf.dmg-etat-paiement', [
            'pages' => [collect([$paiement])],
            'solde' => 45000,
            'total_brut' => 45000,
            'total_prelevement' => 0,
            'total' => 1,
            'mois' => 'septembre 2026',
            'moisCode' => '2026-09',
            'financement' => 'PA_PS_GOUV',
        ])->render();

        $this->assertStringContainsString('CABINET DU PREMIER MINISTRE', $html);
        $this->assertStringContainsString('CHEF UNITÉ COMPTABLE', $html);
    }

    public function test_etat_financier_renders_budget_aej_header_and_signature(): void
    {
        $paiement = $this->paiementPourSource($this->source('BUDGET_AEJ'));

        $html = View::make('pdf.dmg-etat-paiement', [
            'pages' => [collect([$paiement])],
            'solde' => 45000,
            'total_brut' => 45000,
            'total_prelevement' => 0,
            'total' => 1,
            'mois' => 'septembre 2026',
            'moisCode' => '2026-09',
            'financement' => 'BUDGET_AEJ',
        ])->render();

        $this->assertStringContainsString('MINISTERE DE LA PROMOTION DE LA JEUNESSE', $html);
        $this->assertStringContainsString("L'ORDONNATEUR", $html);
        $this->assertStringNotContainsString('Prélèvement CMU', $html);
    }

    public function test_etat_financier_shows_cmu_columns_only_when_a_deduction_applies(): void
    {
        $source = $this->source('BUDGET_AEJ');
        $paiement = $this->paiementPourSource($source, 'DEMARRAGE');

        ReglePrelevement::query()->create([
            'nom' => 'CMU',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $source->id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);
        app(ApplicationPrelevementsService::class)->appliquerAuxPaiementsEnAttente('2026-09', 'DEMARRAGE');
        $paiement->refresh();

        $html = View::make('pdf.dmg-etat-paiement', [
            'pages' => [collect([$paiement])],
            'solde' => (float) $paiement->montant,
            'total_brut' => (float) $paiement->montant_brut_calcule,
            'total_prelevement' => (float) $paiement->prelevement_calcule,
            'total' => 1,
            'mois' => 'septembre 2026',
            'moisCode' => '2026-09',
            'financement' => 'BUDGET_AEJ',
        ])->render();

        $this->assertStringContainsString('Prélèvement CMU', $html);
    }

    public function test_attestation_presence_renders_c2d_header_and_single_signature(): void
    {
        $paiement = $this->paiementPourSource($this->source('C2D'));

        $html = View::make('pdf.dmg-attestation-presence', [
            'paiements' => collect([$paiement]),
            'financement' => 'C2D',
            'mois' => 'septembre 2026',
            'moisCode' => '2026-09',
        ])->render();

        $this->assertStringContainsString('Contrat de Désendettement et de Développement (C2D)', $html);
        $this->assertStringContainsString('L&#039;ORDONNATEUR', $html);
    }

    public function test_attestation_presence_renders_paps_gouv_signataire(): void
    {
        $paiement = $this->paiementPourSource($this->source('PA_PS_GOUV'));

        $html = View::make('pdf.dmg-attestation-presence', [
            'paiements' => collect([$paiement]),
            'financement' => 'PA_PS_GOUV',
            'mois' => 'septembre 2026',
            'moisCode' => '2026-09',
        ])->render();

        $this->assertStringContainsString('CABINET DU PREMIER MINISTRE', $html);
        $this->assertStringContainsString('LE CHEF D&#039;UNITÉ', $html);
    }

    public function test_etat_financier_pdf_embeds_fonts_sans_double_serialisation(): void
    {
        $source = $this->source('BUDGET_AEJ');
        $paiements = collect([$this->paiementPourSource($source)]);

        $pdf = app(MultiDossierPdfService::class)->construireEtatFinancier($paiements, '2026-09', $source->id);
        $output = $pdf->output();

        // Les polices doivent être embarquées une seule fois (DejaVuSans + DejaVuSans-Bold).
        // Une double sérialisation du canvas (output() puis save()) duplique les polices et
        // corrompt les flux CIDToGIDMap (/FlateDecode sur des données non compressées), ce qui
        // rend le PDF illisible dans les visionneuses.
        $this->assertSame(2, substr_count($output, '/FontFile2'));
        $this->assertSame(2, substr_count($output, '/CIDToGIDMap'));
        $this->assertTrue($this->tousLesFlateStreamsSontValides($output));
    }

    /**
     * Vérifie que chaque flux /FlateDecode du PDF se décompresse (zlib valide) : les flux
     * CIDToGIDMap corrompus par la double sérialisation échouent à gzuncompress().
     */
    private function tousLesFlateStreamsSontValides(string $pdf): bool
    {
        preg_match_all('/(\d+) 0 obj\s*<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches, PREG_SET_ORDER);
        $flateStreams = 0;

        foreach ($matches as $match) {
            if (strpos($match[2], '/FlateDecode') === false) {
                continue;
            }
            $flateStreams++;
            if (gzuncompress($match[3]) === false) {
                return false;
            }
        }

        return $flateStreams > 0;
    }

    public function test_attestation_demarrage_renders_tabular_legacy_layout(): void
    {
        $paiement = $this->paiementPourSource($this->source('BUDGET_AEJ'), 'DEMARRAGE');

        $html = View::make('pdf.dmg-paiements', [
            'paiements' => collect([$paiement]),
            'titre' => 'Attestations de demarrage',
            'type' => 'attestation_demarrage',
            'mois' => 'septembre 2026',
        ])->render();

        $this->assertStringContainsString('ATTESTATION DE DEMARRAGE DU STAGE DE QUALIFICATION', $html);
        $this->assertStringContainsString('Nom et prénom(s) du bénéficiaire', $html);
    }

    private function source(string $code): SourceFinancement
    {
        return SourceFinancement::factory()->create(['code' => $code]);
    }

    private function paiementPourSource(SourceFinancement $source, string $nature = 'PRESENCE'): Paiement
    {
        $periode = Periode::query()->firstOrCreate(
            ['code' => '2026-09'],
            ['date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']
        );
        $stage = Stage::factory()->create(['source_financement_id' => $source->id]);
        $droit = DroitPaiement::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $source->id,
            'nature' => $nature,
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        return Paiement::create([
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => 'A_TRAITER',
        ]);
    }
}
