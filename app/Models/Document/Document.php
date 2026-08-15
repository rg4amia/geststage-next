<?php

namespace App\Models\Document;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory, HasPublicUuid, Auditable;

    protected $table = 'documents';
    protected $guarded = [];

    /**
     * Les versions de ce document.
     */
    public function versions()
    {
        return $this->hasMany(VersionDocument::class);
    }

    /**
     * Le type de document.
     */
    public function typeDocument()
    {
        return $this->belongsTo(\App\Models\Reference\TypeDocument::class);
    }
}
