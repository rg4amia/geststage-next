<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paramètre système clé/valeur (équivalent cible de `SystemSetting`).
 *
 * La valeur est stockée en texte et interprétée selon `type` afin qu'un même
 * écran puisse exposer des bascules (booléen), des seuils (entier) ou des
 * libellés (chaîne) sans multiplier les colonnes.
 */
class ParametreSysteme extends Model
{
    use Auditable;

    public const TYPE_BOOLEEN = 'booleen';

    public const TYPE_ENTIER = 'entier';

    public const TYPE_CHAINE = 'chaine';

    /**
     * Paramètres attendus par l'application, recréés à la volée s'ils manquent
     * en base : l'écran reste utilisable avant le passage du seeder.
     *
     * @var array<string, array{type: string, libelle: string, description: string, defaut: string}>
     */
    public const CATALOGUE = [
        'cmu_obligatoire' => [
            'type' => self::TYPE_BOOLEEN,
            'libelle' => 'Prélèvement CMU obligatoire',
            'description' => 'Applique systématiquement la règle de prélèvement CMU active au paiement de démarrage.',
            'defaut' => '0',
        ],
        'sms_actif' => [
            'type' => self::TYPE_BOOLEEN,
            'libelle' => 'Notifications SMS',
            'description' => 'Autorise l’envoi de SMS aux stagiaires et aux conseillers.',
            'defaut' => '0',
        ],
        'email_actif' => [
            'type' => self::TYPE_BOOLEEN,
            'libelle' => 'Notifications e-mail',
            'description' => 'Autorise l’envoi des courriels transactionnels.',
            'defaut' => '1',
        ],
        'duree_stage_mois' => [
            'type' => self::TYPE_ENTIER,
            'libelle' => 'Durée de stage par défaut (mois)',
            'description' => 'Durée proposée à la création d’un contrat, avant ajustement par le CIP.',
            'defaut' => '12',
        ],
    ];

    protected $table = 'parametres_systeme';

    protected $guarded = [];

    /**
     * Le dernier utilisateur ayant modifié le paramètre.
     */
    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    /**
     * Retourne le catalogue complet, en créant les paramètres manquants.
     *
     * @return Collection<int, self>
     */
    public static function catalogue()
    {
        foreach (self::CATALOGUE as $cle => $definition) {
            static::firstOrCreate(
                ['cle' => $cle],
                [
                    'valeur' => $definition['defaut'],
                    'type' => $definition['type'],
                    'libelle' => $definition['libelle'],
                    'description' => $definition['description'],
                ]
            );
        }

        return static::query()
            ->whereIn('cle', array_keys(self::CATALOGUE))
            ->orderBy('cle')
            ->get();
    }

    /**
     * Valeur convertie dans le type déclaré.
     */
    public function getValeurTypeeAttribute(): bool|int|string|null
    {
        return match ($this->type) {
            self::TYPE_BOOLEEN => (bool) $this->valeur,
            self::TYPE_ENTIER => (int) $this->valeur,
            default => $this->valeur,
        };
    }

    /**
     * Normalise une saisie utilisateur avant stockage.
     */
    public function normaliser(mixed $valeur): string
    {
        return match ($this->type) {
            self::TYPE_BOOLEEN => filter_var($valeur, FILTER_VALIDATE_BOOL) ? '1' : '0',
            self::TYPE_ENTIER => (string) (int) $valeur,
            default => (string) $valeur,
        };
    }
}
