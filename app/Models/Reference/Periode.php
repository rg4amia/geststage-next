<?php

namespace App\Models\Reference;

use App\Domain\Shared\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Periode extends Model
{
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $table = 'periodes';

    protected $guarded = [];

    protected $casts = [
        'debut_le' => 'date',
        'fin_le' => 'date',
        'cloture_le' => 'datetime',
        'actif' => 'boolean',
    ];
}
