<?php

namespace Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // اگر کاربر لاگین نکرده باشد
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'احراز هویت انجام نشده است.'
            ], 401);
        }

        // دریافت نام route فعلی
        $routeName = $request->route()?->getName();
        
        // اگر route name وجود نداشت، اجازه بده (برای route های بدون name)
        if (!$routeName) {
            return $next($request);
        }

        // دریافت دسترسی‌های کاربر از فیلد per
        $userPermissions = $user->per ?? [];

        // اگر per شامل "*" باشد، دسترسی کامل دارد
        if (is_array($userPermissions) && in_array('*', $userPermissions)) {
            return $next($request);
        }

        // مسیرهایی که همان سطح users.update کافی است (ویرایش کاربر)
        if (in_array($routeName, ['users.revoke-sessions', 'users.clear-auth-rate-limit'], true) && is_array($userPermissions) && in_array('users.update', $userPermissions, true)) {
            return $next($request);
        }

        // بررسی اینکه route name در دسترسی‌های کاربر باشد
        if (is_array($userPermissions) && in_array($routeName, $userPermissions)) {
            return $next($request);
        }

        // اگر دسترسی نداشت
        return response()->json([
            'status' => false,
            'message' => 'شما دسترسی لازم برای انجام این عملیات را ندارید.',
            'route' => $routeName
        ], 403);
    }
}
