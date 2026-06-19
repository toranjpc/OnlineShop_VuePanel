<?php

namespace Modules;

use Closure;
use Illuminate\Cache\RateLimiter as IlluminateRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Central config for named throttles + a RateLimiter proxy that applies
 * {@see ModuleRateLimit::defaultDefinition()} when `throttle:{name}` is used
 * but $name is not a key in {@see ModuleRateLimit::definitions()}.
 */
final class ModuleRateLimit
{
    public static function definitions(): array
    {
        $byIpAndMobile = static fn(Request $request): string => $request->ip() . '|' . $request->input('mobile', '');

        return [
            'auth-login' => [
                'perMinute' => 4,
                'key' => $byIpAndMobile,
                'message' => 'تعداد تلاش‌های ورود زیاد است.',
                'use_escalating_middleware' => true,
            ],
            'auth-reset-password' => [
                'perMinute' => 4,
                'key' => $byIpAndMobile,
                'message' => 'تعداد تلاش‌های بیش‌ازحد است. کمی بعد دوباره تلاش کنید.',
                'use_escalating_middleware' => true,
            ],
        ];
    }

    public static function definitionByName(string $name): ?array
    {
        $def = self::definitions()[$name] ?? null;
        if ($def === null) {
            return null;
        }
        if (!array_key_exists('perMinute', $def) || !isset($def['key'], $def['message'])) {
            return null;
        }

        return $def;
    }

    /**
     * Same fingerprint as {@see \Modules\Middleware\EscalatingThrottle} (ip|mobile).
     */
    public static function buildAuthFingerprint(string $ip, string $mobile): string
    {
        $m = preg_replace('/\D+/', '', $mobile) ?? '';

        return trim($ip) . '|' . $m;
    }

    /**
     * Remove escalating throttle cache + rpm counter for this client (admin unlock).
     */
    public static function clearEscalatingByIpAndMobile(string $ip, string $mobile): void
    {
        $fingerprint = self::buildAuthFingerprint($ip, $mobile);
        foreach (self::definitions() as $name => $config) {
            if (empty($config['use_escalating_middleware'])) {
                continue;
            }
            $hash = hash('xxh128', $name . '|' . $fingerprint);
            $p = 'mle';
            Cache::forget("{$p}:escalation_until:{$name}:{$hash}");
            Cache::forget("{$p}:strikes:{$name}:{$hash}");
            RateLimiter::clear("{$p}:rpm:{$name}:{$hash}");
        }
    }

    /**
     * Used for any `throttle:{name}` where `name` is not in {@see definitions()}.
     */
    public static function defaultDefinition(): array
    {
        return [
            'perMinute' => 4,
            'key' => static fn(Request $request): string => $request->ip(),
            'message' => 'تعداد درخواست بیش‌ازحد است. کمی بعد دوباره تلاش کنید.',
        ];
    }

    public static function logToFile(string $context, Request $request, string $fingerprint, int $retryIn): void
    {
        $directory = storage_path('logs_monitoring/rate-limit');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $logLine = sprintf(
            "[%s] rate_limit_exceeded context=%s ip=%s mobile=%s key=%s retry_in=%d%s",
            now()->toDateTimeString(),
            $context,
            $request->ip(),
            (string) $request->input('mobile', ''),
            $fingerprint,
            $retryIn,
            PHP_EOL
        );
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . now()->format('Y-m-d') . '.log',
            $logLine,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function buildLimitFromConfig(string $name, array $config, Request $request): Limit
    {
        $perMinute = (int) $config['perMinute'];
        /** @var Closure $keyFn */
        $keyFn = $config['key'];
        $message = (string) $config['message'];
        $key = $keyFn($request);

        return Limit::perMinute($perMinute)
            ->by($key)
            ->response(function (Request $request, array $headers) use ($name, $key, $message) {
                $retryIn = (int) ($headers['Retry-After'] ?? 0);
                self::logToFile($name, $request, $key, $retryIn);

                return response()->json([
                    'status' => false,
                    'message' => $message,
                    'retry_in' => $retryIn,
                ], 429, $headers);
            });
    }
}

/**
 * @mixin \Illuminate\Cache\RateLimiter
 */
final class ModuleRateLimiterProxy
{
    public function __construct(
        private readonly IlluminateRateLimiter $inner
    ) {}

    public function __call(string $name, array $arguments): mixed
    {
        return $this->inner->{$name}(...$arguments);
    }

    public function limiter(mixed $name): ?Closure
    {
        $found = $this->inner->limiter($name);
        if ($found !== null) {
            return $found;
        }
        if (!is_string($name) || $name === '') {
            return null;
        }
        if (array_key_exists($name, ModuleRateLimit::definitions())) {
            return null;
        }

        $default = ModuleRateLimit::defaultDefinition();

        return function (Request $request) use ($name, $default) {
            $perMinute = (int) $default['perMinute'];
            /** @var Closure $keyFn */
            $keyFn = $default['key'];
            $message = (string) $default['message'];
            $baseKey = $keyFn($request);
            $fingerprint = $name . '|' . $baseKey;

            return Limit::perMinute($perMinute)
                ->by($fingerprint)
                ->response(function (Request $request, array $headers) use ($name, $fingerprint, $message) {
                    $retryIn = (int) ($headers['Retry-After'] ?? 0);
                    ModuleRateLimit::logToFile($name, $request, $fingerprint, $retryIn);

                    return response()->json([
                        'status' => false,
                        'message' => $message,
                        'retry_in' => $retryIn,
                    ], 429, $headers);
                });
        };
    }
}
