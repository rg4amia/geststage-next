<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SourceFinancement extends Model
{
    use HasFactory;
    protected $table = 'sources_financement';
    protected $guarded = [];
}
