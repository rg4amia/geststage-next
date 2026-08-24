<?php

namespace App\Domain\Audit\Support;

use Closure;

final class AuditContext
{
    private static int $suppressionDepth = 0;

    public static function isSuppressed(): bool
    {
        return self::$suppressionDepth > 0;
    }

    public static function withoutAuditing(Closure $callback): mixed
    {
        self::$suppressionDepth++;

        try {
            return $callback();
        } finally {
            self::$suppressionDepth--;
        }
    }
}
