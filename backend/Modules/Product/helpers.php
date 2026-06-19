<?php

use Illuminate\Support\Facades\Cache;

if (!function_exists('uuid')) {
    /**
     * شمارنده یکتا برای هر گیت در هر روز (جلالی با ymd در کلید کش).
     * کلید شامل گیت است؛ هر گیت شمارنده جدا؛ با عوض شدن تاریخ روز کلید جدید و از ۱.
     */
    function getGateLetter(int $index = 0, string $type)
    {
        $letters = ['', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        if ($type == "NUMBER") {
            return $index;
        }
        return $letters[$index] ?? '';
    }
    function uuid(int $gate = 1, string $key = 'seq', $type = "CHARACTER")
    {
        $cacheKey = $key . ':' . $gate . ':' . date('ymd');

        $seq = Cache::increment($cacheKey);
        if ($seq === 1) {
            Cache::put($cacheKey, $seq, 86500); // حدود ۲۴ ساعت
        }

        return jdate()->format('ymd') . $gate . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        // return jdate()->format('ymd') . "_" . getGateLetter($gate, $type) . "_" . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
