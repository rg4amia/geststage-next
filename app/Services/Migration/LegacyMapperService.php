<?php

namespace App\Services\Migration;

use App\Enums\CorbeilleEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LegacyMapperService
{
    // ────────────────────────────────────────────────────────────────────
    //  Normalisation de dates legacy
    // ────────────────────────────────────────────────────────────────────

    public function normalizeLegacyDate(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '' || $trimmed === '0000-00-00 00:00:00' || str_starts_with($trimmed, '0000')) {
            return null;
        }

        // Humain-friendly dates: "14 août 2026" → "14 aug 2026"
        $normalized = strtr($trimmed, [
            'janvier' => 'jan', 'février' => 'feb', 'mars' => 'mar', 'avril' => 'apr',
            'mai' => 'may', 'juin' => 'jun', 'juillet' => 'jul', 'août' => 'aug',
            'septembre' => 'sep', 'octobre' => 'oct', 'novembre' => 'nov', 'décembre' => 'dec',
        ]);

        try {
            $carbon = Carbon::parse($normalized);

            return $carbon->year < 1970 ? null : $carbon;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function normalizeLegacyDateRange(?string $startValue, ?string $endValue, int $fallbackMonths = 6): array
    {
        $start = $this->normalizeLegacyDate($startValue) ?? Carbon::now();
        $end = $this->normalizeLegacyDate($endValue);

        if ($end === null) {
            $end = $start->copy()->addMonths($fallbackMonths);
        }

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start, $end];
    }

    // ────────────────────────────────────────────────────────────────────
    //  Mapping etapetraitement_id → corbeille (source unique de vérité)
    //
    //  Table legacy `etapes` :
    //    1  CIP: Ajout des informations stagiaire
    //    2  Chef Agence: Validation des informations
    //    3  DMG: Vérification (CNI, Fiche Tresor, Contrat)
    //    4  DESSE: Doublon détecté (N° AEJ / N° Tresor)
    //    5  DESSE: Stagiaire ajourné (Doublon avéré)
    //    6  DESSE: Validation (Doublon non avéré)
    //    7  CA: Doublon avéré traité → retour CIP
    //    8  DESSE: Validé après retour CA
    //    9  DMG: Validé après vérification
    //   10  DMG: Rejet après vérification → retour CIP
    //   11  CIP: Pointage saisi → en attente validation CA
    //   12  CA: Ajournement pointage → retour CIP
    //   13  CA: Validation pointage → DMG attente (ADD / Démarrage)
    //   14  CA: Validation pointage → DMG attente (ADP / Présence)
    //   15  DMG: Ajournement du pointage → retour CIP
    //   16  CIP: Traitement ajournement DMG (correction pointage)
    //   17  CA: Validation après ajournement DMG (ADP)
    //   18  CA: Ajournement après DMG → retour CIP
    //   19  DMG: Pointage validé → CB (dossier groupé)
    //   20  DMG: Ajournement sur dossier → CB état ajourné
    //   21  CB: Stagiaire ajourné sur dossier → retour DMG
    //   22  CB: Dossier validé → DMG élaboration OP
    //   23  DMG: OP créées → en attente bordereau
    //   24  AC: OP en attente traitement
    //   25  AC: OP validée
    //   26  AC: OP rejetée → retour DMG
    //   27  AC: OP différée → retour CIP
    //   28  CIP: Paiement traité après différé AC
    //   29  DMG: Ajourné après rejet AC
    //   30  AC: Stagiaire PAYÉ (terminal)
    //   31  AC: Stagiaire NON-PAYÉ (terminal)
    // ────────────────────────────────────────────────────────────────────

    private const ETAPES_TERMINALES = [30, 31];

    public function estStatutStageTermine(int $legacyStatutId): bool
    {
        return in_array($legacyStatutId, self::ETAPES_TERMINALES, true);
    }

    public function mapStatutStageToCorbeille(int $legacyStatutId): CorbeilleEnum
    {
        return match ($legacyStatutId) {
            // ── CIP ──
            1 => CorbeilleEnum::CIP_MES_STAGIAIRES,
            7 => CorbeilleEnum::CIP_MES_STAGIAIRES,    // retour après doublon avéré traité par CA
            16 => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG, // traitement de l'ajournement DMG
            28 => CorbeilleEnum::CIP_DIFFERE_AC,         // paiement après différé AC

            // ── Chef d'Agence ──
            2 => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE, // validation des infos
            11 => CorbeilleEnum::CA_VALIDATION_POINTAGES,         // pointage saisi par CIP
            12 => CorbeilleEnum::CIP_AJOURNE_CA,                  // CA ajourne le pointage → retour CIP
            17 => CorbeilleEnum::CA_VALIDATION_POINTAGE_AJOURNE_ADP, // validation après ajournement DMG
            18 => CorbeilleEnum::CIP_AJOURNE_CA,                  // CA ajourne après DMG → retour CIP

            // ── DMG vérification / validation ──
            3 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,  // DMG vérifie le dossier
            9 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,  // DMG valide après vérification
            10 => CorbeilleEnum::CIP_AJOURNE_DMG,                 // DMG rejette → retour CIP

            // ── DESSE ──
            4 => CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER,
            5 => CorbeilleEnum::DESSE_DOUBLONS_TRAITES,  // doublon avéré
            6 => CorbeilleEnum::DESSE_DOUBLONS_TRAITES,  // doublon non avéré validé
            8 => CorbeilleEnum::DESSE_SUIVI_PROCESSUS,   // validé après retour CA

            // ── DMG → Pointage validé → Attente paiement ──
            13 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,  // ADD démarrage
            14 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,   // ADP présence
            15 => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG,        // DMG ajourne le pointage

            // ── DMG → CB (dossier groupé) ──
            19 => CorbeilleEnum::CB_DOSSIER_MULTIPLE,
            20 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,
            21 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,

            // ── CB validé → DMG élaboration OP ──
            22 => CorbeilleEnum::DMG_ELABORATION_OP,
            23 => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU,

            // ── AC ──
            24 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,  // OP en attente
            25 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,  // OP validée
            26 => CorbeilleEnum::DMG_OP_REJETE_AC,         // OP rejetée
            27 => CorbeilleEnum::CIP_DIFFERE_AC,           // OP différée
            29 => CorbeilleEnum::DMG_OP_REJETE_AC,         // ajourné après rejet AC

            // ── Terminaux ──
            30 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,  // payé — workflow clos
            31 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,  // non-payé — workflow clos

            default => CorbeilleEnum::CIP_MES_STAGIAIRES,
        };
    }

    // ────────────────────────────────────────────────────────────────────
    //  Mapping du contexte Chef d'Agence (etat_chef_agence, date_chef_agence…)
    //
    //  Retourne la VRAIE corbeille du dossier en se basant sur les colonnes
    //  d'état du Chef d'Agence, qui SURAVENT l'etapetraitement_id quand le
    //  dossier est en cours de traitement CA.
    // ────────────────────────────────────────────────────────────────────

    public function mapChefAgenceCorbeille(object $legacyContrat): CorbeilleEnum
    {
        $etatChefAgence = (int) ($legacyContrat->etat_chef_agence ?? 0);
        $dateChefAgence = $this->normalizeLegacyDate($legacyContrat->date_chef_agence ?? null);

        // Le Chef d'Agence a traité le dossier mais l'a ajourné / rejeté :
        // retour au CIP pour correction
        if ($etatChefAgence === 0 && $dateChefAgence !== null) {
            return CorbeilleEnum::CA_RETOUR_AJOURNEMENT;
        }

        // Le Chef d'Agence a validé : la corbeille dépend de l'étape suivante
        if ($etatChefAgence !== 0) {
            // Reprend la mapping standard en se basant sur l'etapetraitement_id
            return $this->mapStatutStageToCorbeille(
                (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1)
            );
        }

        // etat_chef_agence=0 et pas de date_chef_agence : le dossier n'est PAS
        // encore en cours de traitement Chef d'Agence.  On ne le classe en
        // CA_ATTENTE_VALIDATION_* que si le dossier remplit les conditions
        // d'éligibilité EXACTES du legacy (cf. WaitCheckedChefAgenceService).
        $estEligibleCA = (int) ($legacyContrat->agent_id ?? 0) === 3
            && (int) ($legacyContrat->avis_contrat ?? 0) === 1
            && ! empty($legacyContrat->file_contrat)
            && in_array((int) ($legacyContrat->etapetraitement_id ?? 0), [1, 4], true);

        if (! $estEligibleCA) {
            return $this->mapStatutStageToCorbeille(
                (int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1)
            );
        }

        // Le dossier est éligible CA : démarrage ou démarrage omis ?
        $dateDebut = $this->normalizeLegacyDate($legacyContrat->date_debut ?? null);

        if (! $dateDebut) {
            return CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE;
        }

        $moisDebut = Carbon::parse($dateDebut)->format('Y-m');

        return $moisDebut < now()->format('Y-m')
            ? CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS
            : CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Période et nature ADD/ADP
    //
    //  La colonne `mois` est la période métier legacy. `created_at` ne sert
    //  que de repli : il peut correspondre à une saisie tardive.
    // ────────────────────────────────────────────────────────────────────

    public function resolveLegacyPeriodDate(object $legacyRow): ?Carbon
    {
        $mois = trim((string) ($legacyRow->mois ?? ''));

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois) === 1) {
            return Carbon::createFromFormat('!Y-m', $mois)->startOfMonth();
        }

        foreach ([
            'date_pointage',
            'date_confirm_pay',
            'date_paiement_op',
            'date_bordereau',
            'date_borderau',
            'created_at',
        ] as $field) {
            $date = $this->normalizeLegacyDate($legacyRow->{$field} ?? null);

            if ($date !== null) {
                return $date->startOfMonth();
            }
        }

        return null;
    }

    public function naturePaiementPourPeriode(?string $dateDebutStage, string $codePeriode): string
    {
        $dateDebut = $this->normalizeLegacyDate($dateDebutStage);

        if ($dateDebut === null) {
            return 'PRESENCE';
        }

        return $dateDebut->format('Y-m') === $codePeriode ? 'DEMARRAGE' : 'PRESENCE';
    }

    public function mapPointageToCorbeille(?int $legacyEtapeId, string $statut, string $nature): CorbeilleEnum
    {
        return match ($legacyEtapeId) {
            2, 7 => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            11 => CorbeilleEnum::CA_VALIDATION_POINTAGES,
            12, 18 => CorbeilleEnum::CIP_AJOURNE_CA,
            13 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            14 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            15, 16 => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG,
            17 => CorbeilleEnum::CA_VALIDATION_POINTAGE_AJOURNE_ADP,
            19 => CorbeilleEnum::CB_DOSSIER_MULTIPLE,
            20, 21 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,
            default => match ($statut) {
                'AJOURNE_DMG' => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG,
                'AJOURNE_CA' => CorbeilleEnum::CIP_AJOURNE_CA,
                'VALIDE' => $nature === 'DEMARRAGE'
                    ? CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE
                    : CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
                default => CorbeilleEnum::CA_VALIDATION_POINTAGES,
            },
        };
    }

    /**
     * Mapping des types d'utilisateurs legacy vers les rôles Spatie.
     */
    public function mapTypeUserToRole(int $typeUserId): ?string
    {
        return match ($typeUserId) {
            1 => 'administrateur',
            2 => 'agent_comptable',
            3 => 'chef_agence',
            4 => 'cip',
            5 => 'dmg',
            6 => 'desse',
            7 => 'daicg',
            8 => 'cb',
            default => null,
        };
    }

    /**
     * Génère un email propre si l'ancien est invalide.
     */
    public function sanitizeEmail(?string $email, string $nom, string $prenom, int $legacyId): string
    {
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $base = Str::slug($prenom.'.'.$nom);

            return $base.'.'.$legacyId.'@migration.local';
        }

        return $email;
    }
}
