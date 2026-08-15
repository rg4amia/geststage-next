<?php

namespace App\Services\Migration;

use App\Enums\CorbeilleEnum;
use Illuminate\Support\Str;

class LegacyMapperService
{
    /**
     * Map les anciens statuts de stage vers les nouvelles étapes du workflow.
     */
    public function mapStatutStageToCorbeille(int $legacyStatutId): CorbeilleEnum
    {
        return match ($legacyStatutId) {
            // Ces valeurs (1, 2, 3...) sont des exemples et doivent être ajustées selon l'ancienne base
            1 => CorbeilleEnum::CIP_MES_STAGIAIRES,
            2 => CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            3 => CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            4 => CorbeilleEnum::EN_STAGE,
            5 => CorbeilleEnum::DMG_ELABORATION_OP,
            default => CorbeilleEnum::CIP_MES_STAGIAIRES,
        };
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
