<?php

namespace App\Models\Document;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionDocument extends Model
{
    use HasFactory;

    protected $table = 'versions_documents';

    protected $guarded = [];

    /**
     * Auteur du dépôt de cette version : la fiche détail affiche la traçabilité GED
     * (qui a déposé quoi, quand), que le legacy ne conservait pas.
     */
    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par_id');
    }
}
