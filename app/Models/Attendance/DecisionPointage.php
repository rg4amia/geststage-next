<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionPointage extends Model
{
    use HasFactory;

    protected $table = 'decisions_pointages';

    protected $guarded = [];

    protected $casts = [
        'decide_le' => 'datetime',
    ];

    /**
     * Le pointage concerné par la décision.
     */
    public function pointage(): BelongsTo
    {
        return $this->belongsTo(Pointage::class);
    }

    /**
     * La version du pointage sur laquelle porte la décision.
     */
    public function versionPointage(): BelongsTo
    {
        return $this->belongsTo(VersionPointage::class);
    }

    /**
     * L'utilisateur (Chef d'Agence) qui a pris la décision.
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
