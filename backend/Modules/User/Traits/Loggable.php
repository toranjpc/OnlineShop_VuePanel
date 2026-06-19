<?php

namespace Modules\User\Traits;

use Modules\User\Models\Log;
use Illuminate\Support\Facades\Auth;

trait Loggable
{
    /**
     * لاگ کردن یک عمل
     * 
     * @param string $action نوع عمل (login, logout, create, update, delete, etc.)
     * @param string|null $model نام مدل (اختیاری - اگر null باشه از getTable استفاده می‌کنه)
     * @param int|null $modelId آی‌دی رکورد (اختیاری)
     * @param array|null $data اطلاعات اضافی
     * @return Log
     */
    public static function logAction(
        string $action,
        ?string $model = null,
        ?int $modelId = null,
        ?array $data = null
    ): Log {
        $request = request();
        
        return Log::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model' => $model ?? (new static)->getTable(),
            'model_id' => $modelId,
            'data' => $data,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
    }

    /**
     * لاگ کردن یک عمل برای مدل فعلی
     * 
     * @param string $action
     * @param array|null $data
     * @return Log
     */
    public function log(string $action, ?array $data = null): Log
    {
        return static::logAction($action, $this->getTable(), $this->id, $data);
    }
}
