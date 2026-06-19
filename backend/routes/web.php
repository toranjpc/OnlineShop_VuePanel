<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use Illuminate\Support\Facades\Redis;

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])->middleware('web');

Route::get('/', function () {
    return 'down';
});


Route::get('/routes', function () {

    $routes = collect(Route::getRoutes())->map(function ($route) {
        return [
            'method' => implode('|', $route->methods()),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            // 'data' => $route,
        ];
    });

    return response()->json($routes->values());
});
