<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NiveauxEtudeSeeder extends Seeder
{
    public function run(): void
    {
        // Données depuis le legacy nivo_etude (65 niveaux)
        $niveaux = [
            ['id' => 1, 'nom' => 'AUCUN', 'code' => 'AUCUN', 'actif' => true],
            ['id' => 2, 'nom' => 'PETITE SESSION', 'code' => 'PETITE_SESSION', 'actif' => true],
            ['id' => 3, 'nom' => 'MOYENNE SESSION', 'code' => 'MOYENNE_SESSION', 'actif' => true],
            ['id' => 4, 'nom' => 'GRANDE SESSION', 'code' => 'GRANDE_SESSION', 'actif' => true],
            ['id' => 5, 'nom' => 'CP1', 'code' => 'CP1', 'actif' => true],
            ['id' => 6, 'nom' => 'CP2', 'code' => 'CP2', 'actif' => true],
            ['id' => 7, 'nom' => 'CE1', 'code' => 'CE1', 'actif' => true],
            ['id' => 8, 'nom' => 'CE2', 'code' => 'CE2', 'actif' => true],
            ['id' => 9, 'nom' => 'CM1', 'code' => 'CM1', 'actif' => true],
            ['id' => 10, 'nom' => 'CM2', 'code' => 'CM2', 'actif' => true],
            ['id' => 11, 'nom' => '6EME', 'code' => '6EME', 'actif' => true],
            ['id' => 12, 'nom' => '5EME', 'code' => '5EME', 'actif' => true],
            ['id' => 13, 'nom' => '4EME', 'code' => '4EME', 'actif' => true],
            ['id' => 14, 'nom' => '3EME', 'code' => '3EME', 'actif' => true],
            ['id' => 15, 'nom' => 'CAP 1', 'code' => 'CAP_1', 'actif' => true],
            ['id' => 16, 'nom' => 'CAP 2', 'code' => 'CAP_2', 'actif' => true],
            ['id' => 17, 'nom' => 'CAP 3', 'code' => 'CAP_3', 'actif' => true],
            ['id' => 18, 'nom' => 'CQP 1', 'code' => 'CQP_1', 'actif' => true],
            ['id' => 19, 'nom' => 'CQP 2', 'code' => 'CQP_2', 'actif' => true],
            ['id' => 20, 'nom' => 'BEP 1', 'code' => 'BEP_1', 'actif' => true],
            ['id' => 21, 'nom' => 'BEP 2', 'code' => 'BEP_2', 'actif' => true],
            ['id' => 22, 'nom' => 'BP 1', 'code' => 'BP_1', 'actif' => true],
            ['id' => 23, 'nom' => 'BP 2', 'code' => 'BP_2', 'actif' => true],
            ['id' => 24, 'nom' => 'BP 3', 'code' => 'BP_3', 'actif' => true],
            ['id' => 25, 'nom' => 'BT 1', 'code' => 'BT_1', 'actif' => true],
            ['id' => 26, 'nom' => 'BT 2', 'code' => 'BT_2', 'actif' => true],
            ['id' => 27, 'nom' => 'BT 3', 'code' => 'BT_3', 'actif' => true],
            ['id' => 28, 'nom' => '2NDE A, C, D', 'code' => '2NDE_ACD', 'actif' => true],
            ['id' => 29, 'nom' => '2NDE B', 'code' => '2NDE_B', 'actif' => true],
            ['id' => 30, 'nom' => '2NDE F1', 'code' => '2NDE_F1', 'actif' => true],
            ['id' => 31, 'nom' => '2NDE F2', 'code' => '2NDE_F2', 'actif' => true],
            ['id' => 32, 'nom' => '2NDE G1', 'code' => '2NDE_G1', 'actif' => true],
            ['id' => 33, 'nom' => '2NDE G2', 'code' => '2NDE_G2', 'actif' => true],
            ['id' => 34, 'nom' => '2NDE T1', 'code' => '2NDE_T1', 'actif' => true],
            ['id' => 35, 'nom' => '1ERE A1, A2, C, D', 'code' => '1ERE_ACD', 'actif' => true],
            ['id' => 36, 'nom' => '1ERE B', 'code' => '1ERE_B', 'actif' => true],
            ['id' => 37, 'nom' => '1ERE E', 'code' => '1ERE_E', 'actif' => true],
            ['id' => 38, 'nom' => '1ERE F1', 'code' => '1ERE_F1', 'actif' => true],
            ['id' => 39, 'nom' => '1ERE F2', 'code' => '1ERE_F2', 'actif' => true],
            ['id' => 40, 'nom' => '1ERE F3', 'code' => '1ERE_F3', 'actif' => true],
            ['id' => 41, 'nom' => '1ERE F4', 'code' => '1ERE_F4', 'actif' => true],
            ['id' => 42, 'nom' => '1ERE F7', 'code' => '1ERE_F7', 'actif' => true],
            ['id' => 43, 'nom' => '1ERE G1', 'code' => '1ERE_G1', 'actif' => true],
            ['id' => 44, 'nom' => '1ERE G2', 'code' => '1ERE_G2', 'actif' => true],
            ['id' => 45, 'nom' => 'TLE A1, A2, C, D', 'code' => 'TLE_ACD', 'actif' => true],
            ['id' => 46, 'nom' => 'TLE B', 'code' => 'TLE_B', 'actif' => true],
            ['id' => 47, 'nom' => 'TLE E', 'code' => 'TLE_E', 'actif' => true],
            ['id' => 48, 'nom' => 'TLE F1', 'code' => 'TLE_F1', 'actif' => true],
            ['id' => 49, 'nom' => 'TLE F2', 'code' => 'TLE_F2', 'actif' => true],
            ['id' => 50, 'nom' => 'TLE F3', 'code' => 'TLE_F3', 'actif' => true],
            ['id' => 51, 'nom' => 'TLE F4', 'code' => 'TLE_F4', 'actif' => true],
            ['id' => 52, 'nom' => 'TLE F7', 'code' => 'TLE_F7', 'actif' => true],
            ['id' => 53, 'nom' => 'TLE G1', 'code' => 'TLE_G1', 'actif' => true],
            ['id' => 54, 'nom' => 'TLE G2', 'code' => 'TLE_G2', 'actif' => true],
            ['id' => 55, 'nom' => 'BAC + 1', 'code' => 'BAC_P1', 'actif' => true],
            ['id' => 56, 'nom' => 'BAC + 2; DUT', 'code' => 'BAC_P2_DUT', 'actif' => true],
            ['id' => 57, 'nom' => 'BTS 1', 'code' => 'BTS_1', 'actif' => true],
            ['id' => 58, 'nom' => 'BTS 2', 'code' => 'BTS_2', 'actif' => true],
            ['id' => 59, 'nom' => 'BAC + 3', 'code' => 'BAC_P3', 'actif' => true],
            ['id' => 60, 'nom' => 'BAC + 4', 'code' => 'BAC_P4', 'actif' => true],
            ['id' => 61, 'nom' => 'BAC + 5', 'code' => 'BAC_P5', 'actif' => true],
            ['id' => 62, 'nom' => 'BAC + 6', 'code' => 'BAC_P6', 'actif' => true],
            ['id' => 63, 'nom' => 'BAC + 7', 'code' => 'BAC_P7', 'actif' => true],
            ['id' => 64, 'nom' => 'DOCTORAT OU PLUS', 'code' => 'DOCTORAT', 'actif' => true],
            ['id' => 65, 'nom' => 'FORMATION QUALIFIANTE', 'code' => 'FORMATION_QUALIFIANTE', 'actif' => true],
        ];

        foreach ($niveaux as $niveau) {
            DB::table('niveaux_etude')->updateOrInsert(
                ['id' => $niveau['id']],
                array_merge($niveau, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
