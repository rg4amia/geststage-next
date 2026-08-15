<?php

namespace App\Models\Attendance;

use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Audit\Traits\Auditable;

class Pointage extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes, Auditable;

    protected $table = 'pointages';

    protected $guarded = [];

    /**
     * Le stage concerné par le pointage.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * La période (ex: Février 2024) du pointage.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    /**
     * L'historique des versions (soumissions) de ce pointage.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(VersionPointage::class);
    }

    /**
     * La version courante du pointage.
     */
    public function versionCourante(): HasOne
    {
        return $this->hasOne(VersionPointage::class)->latestOfMany('numero_version');
    }

    /**
     * Les décisions (validation/rejet) prises sur ce pointage.
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(DecisionPointage::class);
    }

    /**
     * Scopes pour filtrer par statut de workflow
     */
    public function scopeAttenteValidationCA($query)
    {
        return $query->where('statut', 'SOUMIS');
    }

    public function scopeAjourneParCA($query)
    {
        return $query->where('statut', 'AJOURNE_CA');
    }

    public function scopeAjourneParDMG($query)
    {
        return $query->where('statut', 'AJOURNE_DMG');
    }

    public function scopeValide($query)
    {
        return $query->where('statut', 'VALIDE');
    }
}
