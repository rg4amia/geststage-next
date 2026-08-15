<?php

namespace App\Domain\Shared\Traits;

use Illuminate\Support\Str;

trait HasPublicUuid
{
    /**
     * Boot the trait.
     */
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid_public)) {
                $model->uuid_public = (string) Str::uuid();
            }
        });
    }
}
