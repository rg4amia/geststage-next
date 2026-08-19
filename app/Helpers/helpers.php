<?php

declare(strict_types=1);

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
