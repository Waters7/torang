<?php

namespace App\Support;

/**
 * IP dan user agent permintaan saat ini untuk activity log (kosong saat berjalan di console/queue).
 */
final class RequestContext
{
    /**
     * @return array{ip: ?string, user_agent: ?string}
     */
    public static function forActivity(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return ['ip' => null, 'user_agent' => null];
        }

        return [
            'ip' => request()->ip(),
            'user_agent' => str(request()->userAgent() ?? '')->limit(255, '')->toString() ?: null,
        ];
    }
}
