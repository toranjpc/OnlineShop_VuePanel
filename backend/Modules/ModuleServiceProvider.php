<?php

namespace Modules;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Modules\Middleware\EscalatingThrottle;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modulesPath = ["User","Product"];

        foreach ($modulesPath as $moduleName) {
            $providerClass = "Modules\\{$moduleName}\\{$moduleName}ServiceProvider";
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }

        $helpers = __DIR__ . '/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $this->app->extend(RateLimiter::class, function (RateLimiter $inner) {
            return new ModuleRateLimiterProxy($inner);
        });
    }

    public function boot(): void
    {
        Carbon::serializeUsing(static fn (Carbon $date) => $date->format('Y-m-d H:i:s'));

        Schema::defaultStringLength(191);
        // Paginator::useBootstrap();

        $this->registerNamedRateLimiters();
        
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('escalating-throttle', EscalatingThrottle::class);
    }

    /**
     * @see ModuleRateLimit::definitions()  explicit named limiters
     * @see ModuleRateLimit::defaultDefinition()  any other `throttle:{name}` uses this
     */
    private function registerNamedRateLimiters(): void
    {
        foreach (ModuleRateLimit::definitions() as $name => $config) {
            if (!empty($config['use_escalating_middleware'])) {
                continue;
            }
            RateLimiterFacade::for($name, function (Request $request) use ($name, $config) {
                return ModuleRateLimit::buildLimitFromConfig($name, $config, $request);
            });
        }
    }
}
