<?php

namespace App\Models\Document;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VersionDocument extends Model
{
    use HasFactory;

    protected $table = 'versions_documents';
    protected $guarded = [];
}
