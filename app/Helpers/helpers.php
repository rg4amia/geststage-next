<?php

declare(strict_types=1);
use Illuminate\Support\Collection;

if (! function_exists('getInitials')) {
    /**
     * Extrait les initiales d'un nom complet
     */
    function getInitials(?string $fullName): string
    {
        // Handle null or empty string
        if (empty($fullName)) {
            return '';
        }

        // Split the name into parts and filter out empty elements
        $names = array_filter(explode(' ', trim($fullName)));

        // If no valid names, return empty string
        if (empty($names)) {
            return '';
        }

        $initials = '';

        // Handle different cases based on number of names
        switch (count($names)) {
            case 1:
                // Single name: take first letter
                $initials = mb_strtoupper(mb_substr($names[0], 0, 1, 'UTF-8'));
                break;
            case 2:
                // Two names: first letter of each
                $initials = mb_strtoupper(mb_substr($names[0], 0, 1, 'UTF-8').
                    mb_substr($names[1], 0, 1, 'UTF-8'));
                break;
            default:
                // More than two names: first letter of first and last name
                $firstInitial = mb_strtoupper(mb_substr($names[0], 0, 1, 'UTF-8'));
                $lastInitial = mb_strtoupper(mb_substr(end($names), 0, 1, 'UTF-8'));
                $initials = $firstInitial.$lastInitial;
                break;
        }

        return $initials;
    }
}

if (! function_exists('getPrimeDisplayDataByFinancementType')) {
    /**
     * Retourne un montant de prime exploitable par les vues legacy de contrat.
     */
    function getPrimeDisplayDataByFinancementType(object $stagiaire): array
    {
        $amount = (int) round((float) (
            $stagiaire->montant_indemnite
            ?? $stagiaire->prime_mensuelle
            ?? $stagiaire->montant_du
            ?? 0
        ));

        return [
            'amount' => $amount,
            'formatted_amount' => number_format($amount, 0, ',', ' '),
            'amount_in_words' => numberToFrenchWords($amount),
        ];
    }
}

if (! function_exists('convertir_en_lettres')) {
    /**
     * Convertit un nombre en lettres françaises (alias de numberToFrenchWords).
     */
    function convertir_en_lettres(int $number): string
    {
        return numberToFrenchWords($number);
    }
}

if (! function_exists('preparePaginatedDataWithFooterSpace')) {
    /**
     * Prépare les données pour une pagination personnalisée avec espace pour le footer.
     * Première page : 11 éléments, pages suivantes : 18 éléments.
     *
     * @return array<int, Collection>
     */
    function preparePaginatedDataWithFooterSpace(Collection $items): array
    {
        $firstPageItems = 11;
        $subsequentPageItems = 18;

        $pages = [$items->take($firstPageItems)];

        $remaining = $items->slice($firstPageItems);

        if ($remaining->isNotEmpty()) {
            $pages = array_merge($pages, array_map(
                fn (array $chunk) => Collection::make($chunk),
                array_chunk($remaining->all(), $subsequentPageItems)
            ));
        }

        return $pages;
    }
}

if (! function_exists('numberToFrenchWords')) {
    function numberToFrenchWords(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $units = [
            0 => 'zero', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
            6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix', 11 => 'onze',
            12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze', 16 => 'seize',
        ];

        if ($number <= 16) {
            return $units[$number];
        }

        if ($number < 20) {
            return 'dix-'.$units[$number - 10];
        }

        if ($number < 100) {
            $tensMap = [
                20 => 'vingt', 30 => 'trente', 40 => 'quarante',
                50 => 'cinquante', 60 => 'soixante',
            ];

            if ($number < 70) {
                $tens = intdiv($number, 10) * 10;
                $unit = $number % 10;
                $label = $tensMap[$tens];

                if ($unit === 0) {
                    return $label;
                }

                return $unit === 1 ? $label.' et un' : $label.'-'.numberToFrenchWords($unit);
            }

            if ($number < 80) {
                return 'soixante-'.numberToFrenchWords($number - 60);
            }

            if ($number === 80) {
                return 'quatre-vingts';
            }

            return 'quatre-vingt-'.numberToFrenchWords($number - 80);
        }

        if ($number < 1000) {
            $hundreds = intdiv($number, 100);
            $remainder = $number % 100;

            if ($hundreds === 1) {
                return $remainder === 0 ? 'cent' : 'cent '.numberToFrenchWords($remainder);
            }

            $prefix = $units[$hundreds].' cent';

            if ($remainder === 0) {
                return $prefix.'s';
            }

            return $prefix.' '.numberToFrenchWords($remainder);
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;

            $prefix = $thousands === 1 ? 'mille' : numberToFrenchWords($thousands).' mille';

            if ($remainder === 0) {
                return $prefix;
            }

            return $prefix.' '.numberToFrenchWords($remainder);
        }

        return (string) $number;
    }
}
