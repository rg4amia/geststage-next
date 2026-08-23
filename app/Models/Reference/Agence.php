<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use App\Models\Concerns\CachesReferenceData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agence extends Model
{
    use Auditable, CachesReferenceData, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'agences';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * La région de l'agence.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Récupère le nom complet du chef d'agence via une jointure unique
     * (users + perimetres_agences_utilisateurs + model_has_roles + roles).
     */
    public function getChefAgenceAttribute(): string
    {
        $nom = User::query()
            ->join('perimetres_agences_utilisateurs', 'perimetres_agences_utilisateurs.user_id', '=', 'users.id')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('perimetres_agences_utilisateurs.agence_id', $this->id)
            ->where(function ($query): void {
                $query->whereNull('perimetres_agences_utilisateurs.valide_au')
                    ->orWhere('perimetres_agences_utilisateurs.valide_au', '>=', now());
            })
            ->where('roles.name', 'chef_agence')
            ->value('users.nom');

        return $nom ?? 'N/A';
    }
}
