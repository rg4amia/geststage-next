<?php

namespace App\Models\Contract;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contrat extends Model
{
    use HasPublicUuid, Auditable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contrats';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Le stage couvert par le contrat.
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
