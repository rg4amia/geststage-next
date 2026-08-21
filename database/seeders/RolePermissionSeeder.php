<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Définition des permissions par domaine
        $permissionsByDomain = [
            'identite' => [
                'voir_utilisateurs',
                'gerer_utilisateurs',
            ],
            'referentiels' => [
                'voir_referentiels',
                'gerer_referentiels',
            ],
            'entreprises_offres' => [
                'voir_entreprises',
                'gerer_entreprises',
                'voir_offres',
                'gerer_offres',
            ],
            'beneficiaires_stages' => [
                'voir_beneficiaires',
                'gerer_beneficiaires',
                'voir_contrats',
                'gerer_contrats',
            ],
            'pointages' => [
                'voir_pointages',
                'gerer_pointages',
            ],
            'reporting' => [
                'voir_reporting',
            ],
            'workflow' => [
                'valider_chef_agence',
                'valider_desse',
                'valider_dmg',
                'valider_cb',
                'valider_ac',
                'valider_pejedec',
                'valider_aaf',
            ],
        'dmg_paiements' => [
            'voir_paiements_dmg',
            'voir_attente_paiement_demarrage',
                'voir_attente_paiement_presence',
                'valider_paiement_dmg',
                'generer_dossier_paiement',
                'generer_etat_financier',
                'generer_attestation',
                'fusionner_tresor_pay',
                'marquer_dossier_physique',
                'ajourner_paiement_dmg',
                'elaborer_op',
            'creer_bordereau',
            'transmettre_cb',
            'transmettre_bordereau_ac',
            'retirer_paiement_dossier',
            ],
            'cb_paiements' => [
                'voir_dossier_cb',
                'valider_dossier_cb',
                'ajourner_dossier_cb',
            ],
            'ac_paiements' => [
                'voir_bordereau_ac',
                'viser_bordereau_ac',
                'ajourner_bordereau_ac',
                'rejeter_bordereau_ac',
            ],
        ];

        // Création des permissions
        foreach ($permissionsByDomain as $domaine => $permissions) {
            foreach ($permissions as $permission) {
                Permission::updateOrCreate(
                    ['name' => $permission, 'guard_name' => 'web'],
                    ['domaine' => $domaine]
                );
            }
        }

        // Création des rôles
        $roles = [
            'administrateur',
            'cip',
            'chef_agence',
            'desse',
            'daicg',
            'dmg',
            'cb',
            'agent_comptable',
            'pejedec',
            'aaf',
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Assignation des permissions aux rôles
        // Note: L'administrateur bypass toutes les permissions via la Gate::before dans AppServiceProvider

        $cip = Role::findByName('cip');
        $cip->givePermissionTo([
            'voir_entreprises',
            'gerer_entreprises',
            'voir_offres',
            'gerer_offres',
            'voir_beneficiaires',
            'gerer_beneficiaires',
            'voir_contrats',
            'gerer_contrats',
            'voir_pointages',
            'gerer_pointages',
        ]);

        $chefAgence = Role::findByName('chef_agence');
        $chefAgence->givePermissionTo([
            'voir_entreprises',
            'voir_offres',
            'voir_beneficiaires',
            'voir_contrats',
            'voir_pointages',
            'valider_chef_agence',
        ]);

        $desse = Role::findByName('desse');
        $desse->givePermissionTo([
            'voir_beneficiaires',
            'valider_desse',
        ]);

        $dmg = Role::findByName('dmg');
        $dmg->givePermissionTo([
            'voir_beneficiaires',
            'voir_contrats',
            'voir_pointages',
            'valider_dmg',
            'voir_paiements_dmg',
            'voir_attente_paiement_demarrage',
            'voir_attente_paiement_presence',
            'valider_paiement_dmg',
            'generer_dossier_paiement',
            'generer_etat_financier',
            'generer_attestation',
            'fusionner_tresor_pay',
            'marquer_dossier_physique',
            'ajourner_paiement_dmg',
            'elaborer_op',
            'creer_bordereau',
            'transmettre_cb',
            'transmettre_bordereau_ac',
            'retirer_paiement_dossier',
        ]);

        $cb = Role::findByName('cb');
        $cb->givePermissionTo([
            'voir_beneficiaires',
            'voir_contrats',
            'voir_pointages',
            'valider_cb',
            'voir_dossier_cb',
            'valider_dossier_cb',
            'ajourner_dossier_cb',
        ]);

        $agentComptable = Role::findByName('agent_comptable');
        $agentComptable->givePermissionTo([
            'voir_beneficiaires',
            'valider_ac',
            'voir_bordereau_ac',
            'viser_bordereau_ac',
            'ajourner_bordereau_ac',
            'rejeter_bordereau_ac',
        ]);

        $pejedec = Role::findByName('pejedec');
        $pejedec->givePermissionTo([
            'voir_beneficiaires',
            'voir_contrats',
            'valider_pejedec',
        ]);

        $aaf = Role::findByName('aaf');
        $aaf->givePermissionTo([
            'voir_beneficiaires',
            'voir_contrats',
            'valider_aaf',
        ]);

        $daicg = Role::findByName('daicg');
        $daicg->givePermissionTo([
            'voir_beneficiaires',
            'voir_contrats',
        ]);

        foreach (['cip', 'chef_agence', 'desse', 'daicg', 'dmg', 'cb', 'agent_comptable', 'pejedec', 'aaf'] as $roleName) {
            Role::findByName($roleName)->givePermissionTo(['voir_reporting']);
        }
    }
}
