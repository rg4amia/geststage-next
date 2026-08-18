<?php

namespace App\Enums;

enum DoublonTypeEnum: string
{
    case AEJ = 'aej';
    case PIECE_IDENTITE = 'piece_identite';
    case CONTACT_TYPE_STAGE = 'contact_type_stage';
    case IDENTITE_TYPE_STAGE = 'identite_type_stage';
    case COMPTE_PAIEMENT = 'compte_paiement';
    case DIPLOME_TYPE_STAGE = 'diplome_type_stage';

    public function label(): string
    {
        return match ($this) {
            self::AEJ => 'Numéro AEJ',
            self::PIECE_IDENTITE => "N° de Pièce d'Identité",
            self::CONTACT_TYPE_STAGE => 'Type de Stage & Contact',
            self::IDENTITE_TYPE_STAGE => 'Nom, Prénoms, Date de Naissance et Type de Stage',
            self::COMPTE_PAIEMENT => 'Trésor Money ou Wave',
            self::DIPLOME_TYPE_STAGE => 'Stage Qualification / Diplôme',
        };
    }
}
