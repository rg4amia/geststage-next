<?php

namespace App\Domain\Workflow\Services;

use App\Enums\CorbeilleEnum;
use App\Enums\VisaDesseEnum;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;

/**
 * Reconstitue, pour chaque pointage mensuel d'un stage, sa position exacte dans le circuit
 * métier : CIP → Chef d'Agence → visa DESSE/AR → DMG (attente paiement démarrage ou présence,
 * dossier, multi-dossier) → traitement CB → ordre de paiement → bordereau → Agent Comptable →
 * état de paiement.
 *
 * Aucune colonne ne porte cette position de bout en bout : chaque maillon a son propre `statut`
 * et la corbeille de workflow se lit sur l'instance de parcours du pointage, avec repli sur celle
 * du stage (cf. DmgService::attentePaiement()). Le visa DESSE, lui, est une supervision parallèle
 * portée par `stages.visa_desse` et non par une corbeille. Ce service est le seul endroit où ces
 * signaux hétérogènes sont recomposés en une lecture unique.
 */
class SuiviPointageService
{
    /** L'étape est franchie. */
    public const ETAT_TERMINEE = 'terminee';

    /** Le dossier est actuellement posé sur cette étape. */
    public const ETAT_EN_COURS = 'en_cours';

    /** L'étape a renvoyé le dossier en arrière (ajournement, rejet, différé). */
    public const ETAT_AJOURNEE = 'ajournee';

    /** L'étape n'est pas encore atteinte. */
    public const ETAT_A_VENIR = 'a_venir';

    /** L'étape ne s'applique pas à ce dossier (ex. visa DESSE non sollicité). */
    public const ETAT_SANS_OBJET = 'sans_objet';

    /**
     * Relations à précharger pour que `pourStage()` ne déclenche aucune requête supplémentaire.
     * Le préfixe par défaut cible une instance de parcours ; passer `''` pour charger
     * directement un Stage.
     *
     * @return array<int, string>
     */
    public static function relationsStage(string $prefixe = 'stage.'): array
    {
        return array_map(fn (string $relation): string => $prefixe.$relation, [
            'instanceParcours',
            'pointages.periode',
            'pointages.versionCourante',
            'pointages.situationStage',
            'pointages.instanceParcours',
            'pointages.decisions.auteur',
            'pointages.droitsPaiement.paiements.decisions.auteur',
            'pointages.droitsPaiement.paiements.dossiersPaiement.groupes',
            'pointages.droitsPaiement.paiements.dossiersPaiement.ordrePaiement.bordereau',
        ]);
    }

    /**
     * Une ligne de suivi par pointage, du mois le plus récent au plus ancien.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pourStage(?Stage $stage): array
    {
        if (! $stage) {
            return [];
        }

        return $stage->pointages
            ->sortByDesc(fn (Pointage $pointage): int => $pointage->periode_id)
            ->map(fn (Pointage $pointage): array => $this->pourPointage($pointage, $stage))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function pourPointage(Pointage $pointage, ?Stage $stage = null): array
    {
        $stage ??= $pointage->stage;
        $version = $pointage->versionCourante;

        // Un droit annulé ne finance plus rien : le pointage retombe en attente de régularisation.
        $paiement = $pointage->droitsPaiement
            ->filter(fn ($droit): bool => $droit->annule_le === null)
            ->flatMap->paiements
            ->sortByDesc('id')
            ->first();

        // Une ligne retirée (`retire_le` non nul) ne rattache plus le paiement à ce dossier.
        $dossier = $paiement?->dossiersPaiement
            ->first(fn (DossierPaiement $d): bool => $d->pivot->retire_le === null);
        $ordre = $dossier?->ordrePaiement;
        $bordereau = $ordre?->bordereau;
        $groupe = $dossier?->groupes->first();

        return [
            'pointage_id' => $pointage->id,
            'mois' => $pointage->periode?->code,
            'nature' => $pointage->nature,
            'statut_pointage' => $pointage->statut,
            'position' => $version?->jours_presents,
            'situation' => $version?->presence ?? $pointage->situationStage?->nom,
            'date_pointage' => $version?->saisi_le?->toIso8601String(),

            // Corbeille du mois : celle du pointage, à défaut celle du stage.
            'corbeille' => $this->corbeille(
                $pointage->instanceParcours?->corbeille_actuelle
                    ?? $stage?->instanceParcours?->corbeille_actuelle
            ),

            'visa_desse' => $this->visaDesse($stage),
            'validation_ar' => $stage?->date_validation_ar?->toIso8601String(),

            'paiement' => $paiement ? [
                'id' => $paiement->id,
                'montant' => $paiement->montant,
                'statut' => $paiement->statut,
                'statut_label' => $this->labelPaiement($paiement->statut),
                'statut_dossier_physique' => $paiement->statut_dossier_physique,
                'paye_le' => $paiement->paye_le?->toIso8601String(),
            ] : null,
            'dossier' => $dossier ? [
                'numero' => $dossier->numero,
                'statut' => $dossier->statut,
                'statut_label' => $this->labelDossier($dossier->statut),
            ] : null,
            // Multi-dossier : regroupement de dossiers transmis ensemble au Contrôleur Budgétaire.
            'groupe' => $groupe ? [
                'numero' => $groupe->numero,
                'statut' => $groupe->statut,
            ] : null,
            'ordre_paiement' => $ordre ? [
                'numero' => $ordre->numero,
                'statut' => $ordre->statut,
                'statut_label' => $this->labelOrdre($ordre->statut),
            ] : null,
            'bordereau' => $bordereau ? [
                'numero' => $bordereau->numero,
                'statut' => $bordereau->statut,
                'statut_label' => $this->labelOrdre($bordereau->statut),
            ] : null,

            'etat_paiement' => $this->etatPaiement($paiement),
            'dernier_retour' => $this->dernierRetour($pointage, $paiement),
            'etapes' => $this->etapes($pointage, $stage, $paiement, $dossier, $ordre, $bordereau),
        ];
    }

    /**
     * Le circuit complet, étape par étape, avec l'état de chacune pour ce mois.
     *
     * @return array<int, array<string, mixed>>
     */
    private function etapes(
        Pointage $pointage,
        ?Stage $stage,
        ?Paiement $paiement,
        ?DossierPaiement $dossier,
        ?OrdrePaiement $ordre,
        $bordereau,
    ): array {
        $statutPointage = $pointage->statut;
        $statutPaiement = $paiement?->statut;
        $statutDossier = $dossier?->statut;
        $statutOrdre = $ordre?->statut;

        // Un dossier ajourné par le CB voit sa ligne retirée : le paiement revient en A_TRAITER
        // côté DMG, donc l'aval (OP, bordereau, AC) redevient « à venir ».
        $etapes = [
            [
                'code' => 'pointage_cip',
                'label' => 'Saisie et soumission du pointage',
                'acteur' => 'CIP',
                'etat' => match ($statutPointage) {
                    'BROUILLON', 'CORRIGE_CIP' => self::ETAT_EN_COURS,
                    'AJOURNE_CA', 'AJOURNE_DMG' => self::ETAT_AJOURNEE,
                    'REJETE_DEFINITIF' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'validation_ca',
                'label' => 'Validation du pointage',
                'acteur' => "Chef d'Agence",
                'etat' => match ($statutPointage) {
                    'BROUILLON' => self::ETAT_A_VENIR,
                    'SOUMIS', 'CORRIGE_CIP' => self::ETAT_EN_COURS,
                    'AJOURNE_CA' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'visa_desse',
                'label' => 'Visa DESSE / Agence régionale',
                'acteur' => 'DESSE',
                'etat' => match ($stage?->visa_desse) {
                    VisaDesseEnum::VISE => self::ETAT_TERMINEE,
                    VisaDesseEnum::REJETE => self::ETAT_AJOURNEE,
                    VisaDesseEnum::EN_ATTENTE => self::ETAT_EN_COURS,
                    // Tant que le chef d'agence n'a pas validé le démarrage, le dossier
                    // n'est pas soumis à la DESSE (colonne nulle).
                    default => self::ETAT_SANS_OBJET,
                },
            ],
            [
                'code' => 'attente_dmg',
                'label' => $pointage->nature === 'DEMARRAGE'
                    ? 'Attente paiement démarrage'
                    : 'Attente paiement présence',
                'acteur' => 'DMG',
                'etat' => match (true) {
                    $paiement === null => self::ETAT_A_VENIR,
                    $statutPaiement === 'A_TRAITER' => self::ETAT_EN_COURS,
                    $statutPaiement === 'AJOURNE_DMG' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'dossier_dmg',
                'label' => 'Constitution du dossier de paiement',
                'acteur' => 'DMG',
                'etat' => match (true) {
                    $dossier === null => self::ETAT_A_VENIR,
                    $statutDossier === 'BROUILLON' => self::ETAT_EN_COURS,
                    $statutDossier === 'ANNULE' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'traitement_cb',
                'label' => 'Traitement du dossier',
                'acteur' => 'Contrôleur Budgétaire',
                'etat' => match ($statutDossier) {
                    null, 'BROUILLON' => self::ETAT_A_VENIR,
                    'TRANSMIS_CB' => self::ETAT_EN_COURS,
                    'AJOURNE_CB' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'ordre_paiement',
                'label' => "Édition de l'ordre de paiement",
                'acteur' => 'DMG',
                'etat' => match (true) {
                    $ordre === null => self::ETAT_A_VENIR,
                    $statutOrdre === 'BROUILLON' => self::ETAT_EN_COURS,
                    in_array($statutOrdre, ['REJETE_AC', 'REJETE_AC_DEFINITIF', 'AJOURNE_DMG'], true) => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'bordereau',
                'label' => 'Édition du bordereau',
                'acteur' => 'DMG',
                'etat' => match (true) {
                    $bordereau === null => self::ETAT_A_VENIR,
                    $bordereau->statut === 'BROUILLON' => self::ETAT_EN_COURS,
                    $bordereau->statut === 'ANNULE' => self::ETAT_AJOURNEE,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'visa_ac',
                'label' => 'Visa / situation Agent Comptable',
                'acteur' => 'Agent Comptable',
                'etat' => match (true) {
                    $bordereau === null || $bordereau->statut === 'BROUILLON' => self::ETAT_A_VENIR,
                    $statutPaiement === 'DIFFERE_AC' => self::ETAT_AJOURNEE,
                    in_array($statutOrdre, ['REJETE_AC', 'REJETE_AC_DEFINITIF'], true) => self::ETAT_AJOURNEE,
                    $bordereau->statut === 'TRANSMIS_AC' => self::ETAT_EN_COURS,
                    default => self::ETAT_TERMINEE,
                },
            ],
            [
                'code' => 'paiement',
                'label' => 'Mise en paiement',
                'acteur' => 'Agent Comptable',
                'etat' => match ($statutPaiement) {
                    'PAYE' => self::ETAT_TERMINEE,
                    'NON_PAYE' => self::ETAT_AJOURNEE,
                    'VALIDE_AC' => self::ETAT_EN_COURS,
                    default => self::ETAT_A_VENIR,
                },
            ],
        ];

        return $etapes;
    }

    /**
     * Réponse courte à « ce mois est-il payé ou non ? ».
     *
     * @return array{code: string, label: string, paye: bool}
     */
    private function etatPaiement(?Paiement $paiement): array
    {
        return match ($paiement?->statut) {
            null => ['code' => 'SANS_PAIEMENT', 'label' => 'Aucun paiement généré', 'paye' => false],
            'PAYE' => ['code' => 'PAYE', 'label' => 'Payé', 'paye' => true],
            'NON_PAYE' => ['code' => 'NON_PAYE', 'label' => 'Non payé (confirmé par l\'AC)', 'paye' => false],
            'AJOURNE_DMG' => ['code' => 'AJOURNE', 'label' => 'Ajourné par la DMG', 'paye' => false],
            'DIFFERE_AC' => ['code' => 'DIFFERE', 'label' => "Différé par l'Agent Comptable", 'paye' => false],
            'REJETE_AC', 'REJETE_DEFINITIF' => ['code' => 'REJETE', 'label' => "Rejeté par l'Agent Comptable", 'paye' => false],
            default => ['code' => 'EN_COURS', 'label' => 'En cours de traitement', 'paye' => false],
        };
    }

    /**
     * Dernier retour en arrière subi par le mois (ajournement CA/DMG/CB, rejet AC), pour
     * afficher le motif au lieu d'un simple statut.
     *
     * @return array{origine: string, decision: string, motif: string|null, auteur: string|null, date: string|null}|null
     */
    private function dernierRetour(Pointage $pointage, ?Paiement $paiement): ?array
    {
        $candidats = collect();

        $decisionPointage = $pointage->decisions
            ->filter(fn ($decision): bool => $decision->motif !== null)
            ->sortByDesc('decide_le')
            ->first();

        if ($decisionPointage) {
            $candidats->push([
                'origine' => 'POINTAGE',
                'decision' => $decisionPointage->decision,
                'motif' => $decisionPointage->motif,
                'auteur' => $decisionPointage->auteur?->name,
                'date' => $decisionPointage->decide_le?->toIso8601String(),
                'tri' => $decisionPointage->decide_le,
            ]);
        }

        $decisionPaiement = $paiement?->decisions
            ->filter(fn ($decision): bool => $decision->motif !== null)
            ->sortByDesc('decide_le')
            ->first();

        if ($decisionPaiement) {
            $candidats->push([
                'origine' => 'PAIEMENT',
                'decision' => $decisionPaiement->decision,
                'motif' => $decisionPaiement->motif,
                'auteur' => $decisionPaiement->auteur?->name,
                'date' => $decisionPaiement->decide_le?->toIso8601String(),
                'tri' => $decisionPaiement->decide_le,
            ]);
        }

        $dernier = $candidats->sortByDesc('tri')->first();

        if (! $dernier) {
            return null;
        }

        unset($dernier['tri']);

        return $dernier;
    }

    /**
     * Résout un code de corbeille en libellé lisible, pour éviter de dupliquer les 38 libellés
     * de `CorbeilleEnum` côté frontend.
     *
     * @return array{code: string|null, label: string|null}
     */
    public function corbeille(?string $code): array
    {
        return [
            'code' => $code,
            'label' => $code ? CorbeilleEnum::tryFrom($code)?->label() : null,
        ];
    }

    /**
     * @return array{code: string|null, label: string|null, motif: string|null, date: string|null}
     */
    private function visaDesse(?Stage $stage): array
    {
        return [
            'code' => $stage?->visa_desse?->value,
            'label' => $stage?->visa_desse?->label(),
            'motif' => $stage?->motif_visa_desse,
            'date' => $stage?->visa_desse_le?->toIso8601String(),
        ];
    }

    private function labelPaiement(?string $statut): ?string
    {
        return match ($statut) {
            'A_TRAITER' => 'En attente de traitement DMG',
            'EN_DOSSIER' => 'Dans un dossier de paiement',
            'EN_OP' => "Dans un ordre de paiement, en attente de l'AC",
            'VALIDE_AC' => "Validé par l'Agent Comptable",
            'PAYE' => 'Payé',
            'NON_PAYE' => 'Non payé',
            'AJOURNE_DMG' => 'Ajourné par la DMG',
            'DIFFERE_AC' => "Différé par l'Agent Comptable",
            'REJETE_AC' => "Rejeté par l'Agent Comptable",
            'REJETE_DEFINITIF' => 'Rejeté définitivement',
            default => $statut,
        };
    }

    private function labelDossier(?string $statut): ?string
    {
        return match ($statut) {
            'BROUILLON' => 'En préparation à la DMG',
            'TRANSMIS_CB' => 'Transmis au Contrôleur Budgétaire',
            'VALIDE_CB' => 'Validé par le Contrôleur Budgétaire',
            'AJOURNE_CB' => 'Ajourné par le Contrôleur Budgétaire',
            'EN_OP' => 'Rattaché à un ordre de paiement',
            'VISE_AC' => "Visé par l'Agent Comptable",
            'ANNULE' => 'Annulé',
            default => $statut,
        };
    }

    /**
     * Ordres de paiement et bordereaux partagent le même vocabulaire de statuts.
     */
    private function labelOrdre(?string $statut): ?string
    {
        return match ($statut) {
            'BROUILLON' => 'En préparation à la DMG',
            'EN_BORDEREAU' => 'Rattaché à un bordereau',
            'SANS_BORDEREAU' => 'Sans bordereau',
            'TRANSMIS_AC' => "Transmis à l'Agent Comptable",
            'VISE_AC' => "Visé par l'Agent Comptable",
            'REJETE_AC' => "Rejeté par l'Agent Comptable",
            'REJETE_AC_DEFINITIF' => 'Rejeté définitivement',
            'AJOURNE_DMG' => 'Retourné à la DMG',
            'ANNULE' => 'Annulé',
            default => $statut,
        };
    }
}
