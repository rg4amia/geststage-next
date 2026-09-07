<?php

namespace App\Http\Requests\ParametreAides;

class UpdateReglePrelevementRequest extends StoreReglePrelevementRequest
{
    /**
     * La règle éditée ne doit pas se déclarer en chevauchement avec elle-même.
     */
    protected function regleIgnoree(): ?int
    {
        return $this->route('regle')->id;
    }
}
