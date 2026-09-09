<?php

namespace Tests\Unit\Enums;

use App\Enums\RoleEnum;
use PHPUnit\Framework\TestCase;

class RoleEnumTest extends TestCase
{
    public function test_chaque_type_user_legacy_connu_est_arbitre(): void
    {
        // Aucun type legacy ne doit rester « oublié » : soit il porte un rôle, soit il
        // figure explicitement parmi ceux qui n'en ont aucun.
        foreach (array_keys(RoleEnum::LIBELLES_TYPES_LEGACY) as $typeLegacy) {
            $roles = RoleEnum::pourTypeUserLegacy($typeLegacy);
            $sansRole = array_key_exists($typeLegacy, RoleEnum::typesUserLegacySansRole());

            $this->assertSame(
                $roles === [],
                $sansRole,
                "Le type legacy {$typeLegacy} est incohérent entre pourTypeUserLegacy() et typesUserLegacySansRole().",
            );
        }
    }

    public function test_les_types_legacy_sans_role_sont_ceux_attendus(): void
    {
        // Profils de pilotage/consultation du legacy sans équivalent dans le nouveau
        // workflow : leurs comptes doivent être arbitrés à la main.
        $this->assertSame(
            [6, 7, 9, 10, 12, 13, 16],
            array_keys(RoleEnum::typesUserLegacySansRole()),
        );
    }

    public function test_un_type_user_inconnu_ne_donne_aucun_role(): void
    {
        $this->assertSame([], RoleEnum::pourTypeUserLegacy(999));
        $this->assertSame([], RoleEnum::pourTypeUserLegacy(0));
    }

    public function test_le_type_agent_comptable_controleur_budgetaire_porte_ses_deux_roles(): void
    {
        $this->assertSame(['cb', 'agent_comptable'], RoleEnum::pourTypeUserLegacy(84));
    }

    public function test_le_catalogue_couvre_tous_les_roles_avec_libelle_et_correspondance(): void
    {
        $catalogue = collect(RoleEnum::catalogue())->keyBy('name');

        $this->assertCount(count(RoleEnum::cases()), $catalogue);

        foreach (RoleEnum::cases() as $role) {
            $entree = $catalogue->get($role->value);

            $this->assertNotEmpty($entree['label'], "Rôle {$role->value} sans libellé.");
            $this->assertNotEmpty($entree['description'], "Rôle {$role->value} sans description.");
            $this->assertCount(count($role->typesUserLegacy()), $entree['types_legacy']);
        }
    }
}
