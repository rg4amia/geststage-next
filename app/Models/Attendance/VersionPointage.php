<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionPointage extends Model
{
    use HasFactory;

    protected $table = 'versions_pointages';

    protected $guarded = [];

    protected $casts = [
        'donnees_complementaires' => 'json',
        'saisi_le' => 'datetime',
    ];

    /**
     * Le pointage parent.
     */
    public function pointage(): BelongsTo
    {
        return $this->belongsTo(Pointage::class);
    }

    /**
     * L'utilisateur (souvent CIP) qui a saisi cette version.
     */
    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par_id');
    }
}
