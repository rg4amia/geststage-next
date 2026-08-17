<?php

namespace App\Models\Document;

use App\Domain\Audit\Traits\Auditable;
use App\Domain\Shared\Traits\HasPublicUuid;
use App\Models\Reference\TypeDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use Auditable, HasFactory, HasPublicUuid;

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
        return $this->belongsTo(TypeDocument::class);
    }
}
