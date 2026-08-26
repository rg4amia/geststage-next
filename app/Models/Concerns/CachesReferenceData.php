<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @mixin Model
 */
trait CachesReferenceData
{
    protected static function bootCachesReferenceData(): void
    {
        static::saved(fn() => Cache::forget(static::referenceCacheKey()));
        static::deleted(fn() => Cache::forget(static::referenceCacheKey()));

        // `restored` n'existe que via le trait SoftDeletes (absent des autres modèles de référence) :
        // l'appeler sans garde tomberait dans Model::__callStatic() et re-instancierait le modèle
        // en pleine phase de boot (LogicException "may not be called ... while it is being booted").
        if (method_exists(static::class, 'restored')) {
            static::restored(fn() => Cache::forget(static::referenceCacheKey()));
        }
    }

    public static function cached(): Collection
    {
        $attributes = Cache::rememberForever(
            static::referenceCacheKey(),
            fn(): array => static::query()
                ->get()
                ->map(fn(Model $model): array => $model->getAttributes())
                ->all(),
        );

        if (! is_array($attributes)) {
            Cache::forget(static::referenceCacheKey());

            $attributes = static::query()
                ->get()
                ->map(fn(Model $model): array => $model->getAttributes())
                ->all();

            Cache::forever(static::referenceCacheKey(), $attributes);
        }

        return static::query()->hydrate($attributes);
    }

    public static function forgetCached(): void
    {
        Cache::forget(static::referenceCacheKey());
    }

    /**
     * Liste triée d'options {id, $labelColumn} construite depuis le cache,
     * sans nouvelle requête (remplace les `orderBy(...)->get(['id', ...])->toArray()` ad-hoc).
     */
    public static function cachedOptions(string $labelColumn, bool $descending = false): array
    {
        return static::cached()
            ->sortBy($labelColumn, SORT_REGULAR, $descending)
            ->values()
            ->map(fn(self $model) => [
                'id' => $model->getKey(),
                $labelColumn => $model->{$labelColumn},
            ])
            ->all();
    }

    /**
     * Map triée [$keyColumn => $valueColumn] construite depuis le cache
     * (remplace les `orderBy(...)->pluck($value, $key)->toArray()` ad-hoc).
     */
    public static function cachedPluck(string $valueColumn, string $keyColumn = 'id'): array
    {
        return static::cached()
            ->sortBy($valueColumn)
            ->pluck($valueColumn, $keyColumn)
            ->all();
    }

    protected static function referenceCacheKey(): string
    {
        return 'ref.' . (new static)->getTable();
    }
}
