<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conseiller extends Model
{
    use Auditable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conseillers';

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
    protected $appends = ['nom_complet'];

    /**
     * L'agence du conseiller.
     */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * L'utilisateur associé au conseiller.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Récupère le nom complet du conseiller.
     */
    public function getNomCompletAttribute(): string
    {
        $prenoms = $this->prenoms ? ' ' . $this->prenoms : '';
        return $this->nom . $prenoms;
    }
}
