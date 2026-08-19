<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueGeneration extends Model
{
    use HasFactory;
    use HasPublicUuid;

    protected $table = 'historique_generations';

    protected $fillable = [
        'uuid_public',
        'type_document',
        'stage_id',
        'instance_parcours_id',
        'user_id',
        'nom_fichier',
        'chemin_fichier',
        'parametres',
        'source_financement',
        'type_stage',
        'nombre_stagiaires',
        'note',
    ];

    protected $casts = [
        'parametres' => 'array',
        'nombre_stagiaires' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function instanceParcours(): BelongsTo
    {
        return $this->belongsTo(InstanceParcours::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
