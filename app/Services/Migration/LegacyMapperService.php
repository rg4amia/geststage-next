<?php

namespace App\Services\Migration;

use App\Enums\CorbeilleEnum;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LegacyMapperService
{
    public function normalizeLegacyDate(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || str_starts_with($trimmed, '-') || str_starts_with($trimmed, '0000')) {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normalize a legacy date range while preserving the ordering required by PostgreSQL checks.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function normalizeLegacyDateRange(?string $startValue, ?string $endValue, int $fallbackMonths = 6): array
    {
        $start = $this->normalizeLegacyDate($startValue) ?? now();
        $end = $this->normalizeLegacyDate($endValue);

        if ($end === null) {
            $end = $start->copy()->addMonths($fallbackMonths);
        }

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start, $end];
    }

    public function mapStatutStageToCorbeille(int $legacyStatutId): CorbeilleEnum
    {
        return match ($legacyStatutId) {
            1 => CorbeilleEnum::CIP_MES_STAGIAIRES,
            2 => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            3 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            4 => CorbeilleEnum::DESSE_DOUBLONS_A_TRAITER,
            5 => CorbeilleEnum::DESSE_DOUBLONS_TRAITES,
            6 => CorbeilleEnum::DESSE_DOUBLONS_TRAITES,
            7 => CorbeilleEnum::CIP_AJOURNE_DESSE,
            8 => CorbeilleEnum::DESSE_SUIVI_PROCESSUS,
            9 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            10 => CorbeilleEnum::CIP_AJOURNE_DMG,
            11 => CorbeilleEnum::CA_VALIDATION_POINTAGES,
            12 => CorbeilleEnum::CIP_AJOURNE_CA,
            13 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            14 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            15 => CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG,
            16 => CorbeilleEnum::CA_VALIDATION_POINTAGE_AJOURNE_ADP,
            17 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            18 => CorbeilleEnum::CIP_AJOURNE_CA,
            19 => CorbeilleEnum::CB_DOSSIER_MULTIPLE,
            20 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,
            21 => CorbeilleEnum::CB_ETAT_PAIEMENT_AJOURNE,
            22 => CorbeilleEnum::DMG_ELABORATION_OP,
            23 => CorbeilleEnum::DMG_OP_ATTENTE_BORDEREAU,
            24 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
            25 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
            26 => CorbeilleEnum::DMG_OP_REJETE_AC,
            27 => CorbeilleEnum::CIP_DIFFERE_AC,
            28 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
            29 => CorbeilleEnum::DMG_OP_REJETE_AC,
            30 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
            31 => CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE,
            default => CorbeilleEnum::CIP_MES_STAGIAIRES,
        };
    }

    public function mapChefAgenceCorbeille(object $legacyContrat): CorbeilleEnum
    {
        $etatChefAgence = (int) ($legacyContrat->etat_chef_agence ?? 0);
        $dateChefAgence = $this->normalizeLegacyDate($legacyContrat->date_chef_agence ?? null);

        if ($etatChefAgence === 0 && $dateChefAgence !== null) {
            return CorbeilleEnum::CA_RETOUR_AJOURNEMENT;
        }

        if ($etatChefAgence !== 0) {
            return $this->mapStatutStageToCorbeille((int) ($legacyContrat->etapetraitement_id ?? $legacyContrat->id_statut_stage ?? 1));
        }

        $dateDebut = $this->normalizeLegacyDate($legacyContrat->date_debut ?? null);

        if (!$dateDebut) {
            return CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE;
        }

        $moisDebut = Carbon::parse($dateDebut)->format('Y-m');

        return $moisDebut < now()->format('Y-m')
            ? CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS
            : CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE;
    }

    /**
     * Map les anciens type_user_id vers les rôles Spatie de la nouvelle application.
     */
    public function mapTypeUserToRole(int $typeUserId): string
    {
        return match ($typeUserId) {
            1 => 'Admin',
            2 => 'Agent Comptable',
            3 => 'Chef Agence',
            4 => 'CIP',
            5 => 'DMG',
            6 => 'DESSE',
            7 => 'DAICG',
            8 => 'Chef de Bureau',
            default => 'Visiteur',
        };
    }

    /**
     * Génère un nom d'utilisateur ou email propre si l'ancien est invalide.
     */
    public function sanitizeEmail(?string $email, string $nom, string $prenom, int $legacyId): string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $base = Str::slug($prenom . '.' . $nom);
            return $base . '.' . $legacyId . '@migration.local';
        }
        return $email;
    }
}
