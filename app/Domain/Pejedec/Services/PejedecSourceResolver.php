<?php

namespace App\Domain\Pejedec\Services;

use App\Models\Reference\SourceFinancement;

class PejedecSourceResolver
{
    private const PEJEDEC_CODE = 'PEJEDEC';

    private const PEJEDEC_LEGACY_ID = 5;

    public function source(): ?SourceFinancement
    {
        return SourceFinancement::query()
            ->where('code', self::PEJEDEC_CODE)
            ->orWhere('ancien_id', self::PEJEDEC_LEGACY_ID)
            ->first();
    }

    public function id(): ?int
    {
        return $this->source()?->id;
    }
}
