<?php

namespace Modules\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\InteractsWithTime;
use Modules\ModuleRateLimit;
use Symfony\Component\HttpFoundation\Response;

/**
 * Up to N requests per 60s (N from config). (N+1)th in the same window → 429.
 * Strike: 1–3 only rolling-window wait; from strike 4 extra lockout: 2, 4, 8, … (max 24h)
 * using 2 ** (strikes - 3) * 60 seconds.
 * While still inside an escalation block, every extra request increases strikes and extends the block.
 */
final class EscalatingThrottle
{
    use InteractsWithTime;

    private const STRIKE_TTL_SECONDS = 7 * 24 * 3600;

    private const MAX_BLOCK_SECONDS = 24 * 3600;

    public function handle(Request $request, Closure $next, string $name): mixed
    {
        $config = ModuleRateLimit::definitionByName($name);
        if ($config === null) {
            abort(500, "Unknown rate limiter [{$name}] for escalating-throttle.");
        }

        /** @var \Closure $keyFn */
        $keyFn = $config['key'];
        $fingerprint = $keyFn($request);
        $hash = hash('xxh128', $name . '|' . $fingerprint);
        $perMinute = max(1, (int) $config['perMinute']);
        $message = (string) $config['message'];

        $untilKey = "mle:escalation_until:{$name}:{$hash}";
        $rpmKey = "mle:rpm:{$name}:{$hash}";
        $strikesKey = "mle:strikes:{$name}:{$hash}";

        $blockUntil = (int) (Cache::get($untilKey) ?? 0);
        if ($blockUntil > time()) {
            $strikes = $this->incrementStrikes($strikesKey);
            $lockSeconds = $this->escalationBlockSeconds($strikes);
            if ($lockSeconds > 0) {
                $endsAt = time() + $lockSeconds;
                Cache::put($untilKey, $endsAt, $lockSeconds);
                $retry = $lockSeconds;
            } else {
                $retry = $blockUntil - time();
            }

            ModuleRateLimit::logToFile($name, $request, $fingerprint, $retry);

            return $this->json429($message, $retry, $perMinute, $request->ip());
        }

        if (RateLimiter::tooManyAttempts($rpmKey, $perMinute)) {
            $strikes = $this->incrementStrikes($strikesKey);

            $extraBlock = $this->escalationBlockSeconds($strikes);
            $availableInRpm = RateLimiter::availableIn($rpmKey);
            if ($extraBlock > 0) {
                Cache::put($untilKey, time() + $extraBlock, $extraBlock);
            }
            $retry = $extraBlock > 0 ? $extraBlock : $availableInRpm;

            ModuleRateLimit::logToFile($name, $request, $fingerprint, $retry);

            return $this->json429($message, $retry, $perMinute, $request->ip());
        }

        RateLimiter::hit($rpmKey, 60);

        $response = $next($request);
        if (!$response instanceof Response) {
            $response = response($response);
        }

        $remaining = RateLimiter::retriesLeft($rpmKey, $perMinute);
        $retryAfter = null;
        $h = $this->headerChunk($perMinute, $remaining, $retryAfter);
        foreach ($h as $k => $v) {
            $response->headers->set($k, (string) $v);
        }

        return $response;
    }

    private function incrementStrikes(string $strikesKey): int
    {
        $strikes = (int) Cache::get($strikesKey, 0);
        $strikes++;
        Cache::put($strikesKey, $strikes, self::STRIKE_TTL_SECONDS);

        return $strikes;
    }

    private function escalationBlockSeconds(int $strikes): int
    {
        if ($strikes < 4) {
            return 0;
        }

        $sec = (int) (2 ** ($strikes - 3)) * 60;

        return min(self::MAX_BLOCK_SECONDS, $sec);
    }

    private function json429(string $message, int $retry, int $perMinute, ?string $clientIp = null): Response
    {
        $h = $this->headerChunk($perMinute, 0, $retry);

        return response()->json([
            'status' => false,
            'message' => $message,
            'retry_in' => $retry,
            'client_ip' => $clientIp,
        ], 429, $h);
    }

    private function headerChunk(int $maxAttempts, int $remainingAttempts, ?int $retryAfter): array
    {
        $headers = [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];
        if (!is_null($retryAfter) && $retryAfter > 0) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }

        return $headers;
    }
}
