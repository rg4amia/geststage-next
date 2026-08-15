<?php

namespace App\Models\Beneficiary;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiaire extends Model
{
    use HasPublicUuid, Auditable, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'beneficiaires';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Les stages du bénéficiaire.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class);
    }
}
