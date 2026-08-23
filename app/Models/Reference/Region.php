<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use App\Models\Concerns\CachesReferenceData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use Auditable, CachesReferenceData;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'regions';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Les agences de cette région.
     */
    public function agences(): HasMany
    {
        return $this->hasMany(Agence::class);
    }
}
