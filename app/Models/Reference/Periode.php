<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    use HasFactory;

    protected $table = 'periodes';

    protected $guarded = [];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'cloture_le' => 'datetime',
        'actif' => 'boolean',
    ];
}
