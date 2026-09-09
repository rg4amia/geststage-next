<?php

namespace App\Enums;

/**
 * Rôles applicatifs (rôles Spatie créés par RolePermissionSeeder) et leur correspondance
 * avec les types d'utilisateur du legacy (table `type_users`, cf. App\Models\TypeUser côté
 * Gestage historique).
 *
 * Source de vérité unique de cette correspondance : la migration
 * (LegacyMapperService::mapTypeUserToRoles) comme l'écran d'administration des comptes
 * (/parametre-aides/comptes) la lisent ici, pour qu'un compte repris du legacy et un compte
 * créé à la main reçoivent exactement les mêmes rôles.
 */
enum RoleEnum: string
{
    case ADMINISTRATEUR = 'administrateur';
    case CHEF_AGENCE = 'chef_agence';
    case CIP = 'cip';
    case DESSE = 'desse';
    case DAICG = 'daicg';
    case DMG = 'dmg';
    // CB avant AGENT_COMPTABLE : le type legacy 84 porte les deux rôles, dans cet ordre.
    case CB = 'cb';
    case AGENT_COMPTABLE = 'agent_comptable';
    case PEJEDEC = 'pejedec';
    case AAF = 'aaf';

    /**
     * Libellés des types d'utilisateur legacy (`type_users.libelle_type_user`), repris des
     * constantes de App\Models\TypeUser du projet historique.
     */
    public const LIBELLES_TYPES_LEGACY = [
        1 => 'Administrateur',
        2 => "Chef d'Agence",
        3 => 'Agent de consultation',
        4 => 'Direction des Opérations',
        5 => 'Direction des Moyens Généraux',
        6 => 'DIC',
        7 => 'DPF',
        8 => 'DAICG',
        9 => 'Comité PSGouv',
        10 => 'Cabinet',
        11 => 'DESSE',
        12 => 'Chef de projet',
        13 => 'DRHAJA',
        14 => 'Administrateur AEJ',
        15 => 'Administrateur adjoint',
        16 => 'Call Center',
        17 => 'Agent de saisie',
        74 => 'DESSE Validation',
        75 => 'DESSE Administration',
        76 => 'DMG Administration',
        77 => 'DMG Validation',
        78 => 'DMG Vérification & Validation',
        79 => 'DMG Dossier',
        80 => 'DMG Consultation',
        81 => 'SDSI paramètre',
        82 => 'DMG Vérifier, Valider et État de paiement',
        83 => 'Validation et vérification stagiaire PEJEDEC',
        84 => 'Agent Comptable - Contrôleur Budgétaire',
        85 => 'DAICG (type legacy 85)',
    ];

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATEUR => 'Administrateur',
            self::CHEF_AGENCE => "Chef d'Agence",
            self::CIP => 'CIP — Conseiller en Insertion Professionnelle',
            self::DESSE => 'DESSE',
            self::DAICG => 'DAICG',
            self::DMG => 'DMG — Direction des Moyens Généraux',
            self::CB => 'Contrôleur Budgétaire',
            self::AGENT_COMPTABLE => 'Agent Comptable',
            self::PEJEDEC => 'PEJEDEC',
            self::AAF => 'AAF',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ADMINISTRATEUR => 'Accès global : comptes, référentiels, paramètres et journaux.',
            self::CHEF_AGENCE => "Validation des dossiers et des pointages de son périmètre d'agence.",
            self::CIP => 'Inscription des stagiaires, pointages et suivi de son portefeuille.',
            self::DESSE => 'Vérification des dossiers, visa et traitement des doublons.',
            self::DAICG => 'Contrôle et supervision régionale, sans pouvoir de visa.',
            self::DMG => 'Paiements : dossiers, ordres de paiement, bordereaux et états financiers.',
            self::CB => 'Contrôle budgétaire des dossiers de paiement.',
            self::AGENT_COMPTABLE => 'Visa des bordereaux de paiement.',
            self::PEJEDEC => 'Validation et vérification des stagiaires du programme PEJEDEC.',
            self::AAF => 'Validation AAF.',
        };
    }

    /**
     * Types d'utilisateur legacy repris par ce rôle.
     *
     * @return list<int>
     */
    public function typesUserLegacy(): array
    {
        return match ($this) {
            self::ADMINISTRATEUR => [1, 14, 15, 81],
            self::CHEF_AGENCE => [2],
            self::CIP => [3, 17],
            self::DESSE => [11, 74, 75],
            self::DAICG => [4, 8, 85],
            self::DMG => [5, 76, 77, 78, 79, 80, 82],
            self::CB => [84],
            self::AGENT_COMPTABLE => [84],
            self::PEJEDEC => [83],
            // Rôle créé pour le nouveau workflow : aucun type d'utilisateur legacy.
            self::AAF => [],
        };
    }

    /**
     * Rôles à attribuer à un compte repris du legacy, dans l'ordre de déclaration de l'enum.
     * Renvoie un tableau vide pour un type legacy sans équivalent (DIC, DPF, Cabinet...) :
     * ces comptes doivent être arbitrés à la main dans /parametre-aides/comptes.
     *
     * @return list<string>
     */
    public static function pourTypeUserLegacy(int $typeUserId): array
    {
        return array_values(array_map(
            fn (self $role): string => $role->value,
            array_filter(
                self::cases(),
                fn (self $role): bool => in_array($typeUserId, $role->typesUserLegacy(), true),
            ),
        ));
    }

    /**
     * Types legacy sans rôle cible : leurs comptes arrivent sans aucun droit.
     *
     * @return array<int, string> id legacy => libellé
     */
    public static function typesUserLegacySansRole(): array
    {
        return array_filter(
            self::LIBELLES_TYPES_LEGACY,
            fn (string $libelle, int $id): bool => self::pourTypeUserLegacy($id) === [],
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Catalogue destiné à l'écran des comptes : pour chaque rôle, son libellé, ce qu'il
     * autorise et les profils legacy qu'il remplace.
     *
     * @return list<array{name: string, label: string, description: string, types_legacy: list<string>}>
     */
    public static function catalogue(): array
    {
        return array_map(fn (self $role): array => [
            'name' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'types_legacy' => array_values(array_map(
                fn (int $id): string => self::LIBELLES_TYPES_LEGACY[$id] ?? "Type legacy {$id}",
                $role->typesUserLegacy(),
            )),
        ], self::cases());
    }
}
