<?php

namespace App\Models\Contract;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    use Auditable, HasFactory, HasPublicUuid;

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
