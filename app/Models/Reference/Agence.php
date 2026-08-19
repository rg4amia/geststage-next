<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agence extends Model
{
    use Auditable, HasFactory;

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
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['chef_agence'];

    /**
     * La région de l'agence.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Récupère le nom complet du chef d'agence.
     */
    public function getChefAgenceAttribute(): string
    {
        // Récupérer l'utilisateur avec le rôle chef_agence pour cette agence
        // via la table pivot perimetres_agences_utilisateurs
        $chefAgence = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('name', 'chef_agence');
        })
            ->whereHas('perimetresAgences', function ($query) {
                $query->where('agence_id', $this->id)
                    ->where(function ($q) {
                        $q->whereNull('valide_au')
                            ->orWhere('valide_au', '>=', now());
                    });
            })
            ->first();

        return $chefAgence ? $chefAgence->nom : 'N/A';
    }
}
