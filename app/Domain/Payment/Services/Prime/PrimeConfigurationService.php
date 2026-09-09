<?php

namespace App\Domain\Payment\Services\Prime;

use App\Models\Payment\PrimeConfigurationOverride;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Accès au barème des primes : valeurs livrées dans `config/primes.php`,
 * fusionnées avec la surcharge saisie par l'administrateur.
 *
 * Portage de `App\Services\PrimeConfigurationService` (legacy). Une panne de
 * base ne doit jamais bloquer la chaîne de paiement : on retombe alors sur les
 * valeurs par défaut du code.
 */
class PrimeConfigurationService
{
    private ?array $resolved = null;

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $defaults = config('primes', []);

        try {
            if (Schema::hasTable('prime_configuration_overrides')) {
                $override = PrimeConfigurationOverride::query()->find(1);
                if ($override?->configuration) {
                    $defaults = array_replace_recursive($defaults, $override->configuration);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Impossible de charger le paramétrage des primes.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->resolved = $defaults;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function defaults(): array
    {
        return config('primes', []);
    }

    /**
     * Vide le cache mémoire : nécessaire après une écriture faite hors de
     * `save()` (tests, seeders) pour que la lecture suivante reparte de la base.
     */
    public function forget(): void
    {
        $this->resolved = null;
    }

    /**
     * @throws ValidationException
     */
    public function validate(array $configuration): void
    {
        foreach (['pae', 'stage_ecole', 'qualification'] as $section) {
            if (! isset($configuration[$section]) || ! is_array($configuration[$section])) {
                throw ValidationException::withMessages([
                    'configuration' => "La section « {$section} » est obligatoire et doit être un objet JSON.",
                ]);
            }
        }

        if (isset($configuration['smig']) && ! is_array($configuration['smig'])) {
            throw ValidationException::withMessages([
                'configuration' => 'La section « smig » doit être un objet JSON.',
            ]);
        }

        foreach ([
            'qualification.budget_aej_effective_date',
            'stage_ecole.budget_aej_effective_date',
        ] as $datePath) {
            $date = data_get($configuration, $datePath);
            if ($date !== null && ! $this->isValidDate($date)) {
                throw ValidationException::withMessages([
                    'configuration' => "La date « {$datePath} » doit respecter le format AAAA-MM-JJ.",
                ]);
            }
        }

        $this->validateDateValues($configuration);
        $this->validateNumericValues($configuration);
    }

    private function validateDateValues(array $values, string $path = 'configuration'): void
    {
        foreach ($values as $key => $value) {
            $currentPath = "{$path}.{$key}";
            if (is_array($value)) {
                $this->validateDateValues($value, $currentPath);

                continue;
            }

            $keyName = strtolower((string) $key);
            $looksLikeDate = str_contains($keyName, 'date')
                || (str_contains($path, 'budget_aej_periods') && in_array($keyName, ['start', 'end'], true));

            if ($looksLikeDate && $value !== null && ! $this->isValidDate($value)) {
                throw ValidationException::withMessages([
                    'configuration' => "La date « {$currentPath} » doit respecter le format AAAA-MM-JJ.",
                ]);
            }
        }
    }

    private function validateNumericValues(array $values, string $path = 'configuration'): void
    {
        foreach ($values as $key => $value) {
            $currentPath = "{$path}.{$key}";
            if (is_array($value)) {
                $this->validateNumericValues($value, $currentPath);

                continue;
            }

            if (is_numeric($value) && (float) $value < 0) {
                throw ValidationException::withMessages([
                    'configuration' => "La valeur « {$currentPath} » ne peut pas être négative.",
                ]);
            }
        }
    }

    private function isValidDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    public function save(array $configuration, ?int $userId = null): PrimeConfigurationOverride
    {
        $mergedConfiguration = array_replace_recursive($this->defaults(), $configuration);
        $this->validate($mergedConfiguration);

        $record = PrimeConfigurationOverride::query()->findOrNew(1);
        $record->id = 1;
        $record->configuration = $mergedConfiguration;
        $record->modifie_par = $userId;
        $record->save();
        $this->resolved = $record->configuration;

        return $record;
    }

    public function reset(?int $userId = null): PrimeConfigurationOverride
    {
        return $this->save($this->defaults(), $userId);
    }
}
