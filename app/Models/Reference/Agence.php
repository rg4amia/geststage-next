<?php

namespace App\Models\Reference;

use App\Domain\Audit\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agence extends Model
{
    use HasFactory, Auditable;
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
}
