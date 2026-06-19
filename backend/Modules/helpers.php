<?php
if (!function_exists('N_encode')) {
    function N_encode($plain_text, $key = "N@", $iv_len = 1)
    {
        if (is_array($plain_text)) {
            return N_crypto_transform_batch($plain_text, $key, 'encode', $iv_len);
        }

        // Keep signature for backwards compatibility.
        unset($iv_len);
        $options = null;

        try {
            if (!is_string($plain_text)) {
                N_crypto_fail('N_encode expects plain_text as string.');
            }

            $options = N_crypto_resolve_options($key);
            $primaryKid = $options['primary_kid'];
            $keyRing = $options['keys'];

            if (!isset($keyRing[$primaryKid])) {
                N_crypto_fail('Primary key id is missing from key ring.');
            }

            $keyMaterial = N_crypto_resolve_key_material($keyRing[$primaryKid]);
            $saltLen = 16;
            $ivLen = 12;
            $tagLen = 16;
            $salt = random_bytes($saltLen);
            $iv = random_bytes($ivLen);

            $aadData = [
                'v' => 3,
                'kid' => $primaryKid,
                'ctx' => $options['context'],
                'alg' => 'aes-256-gcm',
                'kdf' => 'hkdf-sha512',
            ];
            $aad = N_crypto_build_aad($aadData);

            $encKey = hash_hkdf(
                'sha512',
                $keyMaterial,
                32,
                'n_crypto|v3|' . $options['context'] . '|' . $primaryKid,
                $salt
            );

            $tag = '';
            $cipherText = openssl_encrypt(
                $plain_text,
                'aes-256-gcm',
                $encKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad,
                $tagLen
            );

            if (!is_string($cipherText) || strlen($tag) !== $tagLen) {
                N_crypto_fail('Encryption failed.');
            }

            $kidLen = strlen($primaryKid);
            $aadLen = strlen($aad);
            $cipherLen = strlen($cipherText);

            if ($kidLen < 1 || $kidLen > 255) {
                N_crypto_fail('Key id length is invalid.');
            }
            if ($aadLen > 65535) {
                N_crypto_fail('AAD length is too large.');
            }
            if ($cipherLen > 2147483647) {
                N_crypto_fail('Cipher text length is too large.');
            }

            // Payload v3:
            // [ver:1][kidLen:1][saltLen:1][ivLen:1][tagLen:1][aadLen:2][cipherLen:4]
            // [kid][salt][iv][tag][aad][cipherText]
            $header = pack('CCCCCnN', 3, $kidLen, $saltLen, $ivLen, $tagLen, $aadLen, $cipherLen);
            $payload = $header . $primaryKid . $salt . $iv . $tag . $aad . $cipherText;

            $encoded = base64_encode($payload);

            return $encoded;
        } catch (\Throwable $e) {
            if (class_exists(\Illuminate\Support\Facades\Log::class) && N_crypto_allow_error_log($e->getMessage())) {
                \Illuminate\Support\Facades\Log::warning('N_encode failed', ['error' => $e->getMessage()]);
            }
            return 'err';
        }
    }
}

if (!function_exists('N_decode')) {
    function N_decode($enc_text, $key = "N@", $iv_len = 1)
    {
        if (is_array($enc_text)) {
            return N_crypto_transform_batch($enc_text, $key, 'decode', $iv_len);
        }

        // Keep signature for backwards compatibility.
        unset($iv_len);
        $options = null;

        try {
            if (!is_string($enc_text) || $enc_text === '') {
                N_crypto_fail('N_decode expects enc_text as non-empty string.');
            }

            $options = N_crypto_resolve_options($key);
            $rawPayload = N_crypto_decode_base64($enc_text, $options['max_payload_bytes']);

            $version = ord($rawPayload[0]);
            $startedAt = microtime(true);
            if ($version === 3) {
                $decoded = N_decode_v3_payload($rawPayload, $options);
            } elseif ($version === 2) {
                $decoded = N_decode_v2_payload($rawPayload, $options);
            } else {
                $decoded = N_decode_v1_payload($rawPayload, $options);
            }
            N_crypto_assert_time_budget($options, $startedAt);

            return $decoded;
        } catch (\Throwable $e) {
            if (class_exists(\Illuminate\Support\Facades\Log::class) && N_crypto_allow_error_log($e->getMessage())) {
                \Illuminate\Support\Facades\Log::warning('N_decode failed', ['error' => $e->getMessage()]);
            }
            return 'err';
        }
    }
}

if (!function_exists('N_crypto_transform_batch')) {
    function N_crypto_transform_batch(array $items, $keyInput, $operation, $ivLen = 1)
    {
        $results = [];
        $usePerItemKeys = is_array($keyInput);
        $useSingleKey = is_string($keyInput) || $keyInput === null;

        if (!$usePerItemKeys && !$useSingleKey) {
            foreach ($items as $itemKey => $itemValue) {
                unset($itemValue);
                $results[$itemKey] = 'err';
            }
            return $results;
        }

        foreach ($items as $itemKey => $itemValue) {
            $resolvedKey = $keyInput;
            if ($usePerItemKeys) {
                if (!array_key_exists($itemKey, $keyInput)) {
                    $results[$itemKey] = 'err';
                    continue;
                }
                $resolvedKey = $keyInput[$itemKey];
            }

            if ($operation === 'encode') {
                $results[$itemKey] = N_encode($itemValue, $resolvedKey, $ivLen);
                continue;
            }

            $results[$itemKey] = N_decode($itemValue, $resolvedKey, $ivLen);
        }

        return $results;
    }
}

if (!function_exists('N_normalize_key_material')) {
    function N_normalize_key_material($keyMaterial)
    {
        if (!is_string($keyMaterial) || trim($keyMaterial) === '') {
            N_crypto_fail('Key material must be a non-empty string.');
        }

        // Laravel APP_KEY may be prefixed with "base64:".
        if (str_starts_with($keyMaterial, 'base64:')) {
            $decoded = base64_decode(substr($keyMaterial, 7), true);
            if ($decoded !== false && strlen($decoded) >= 16) {
                return $decoded;
            }
            N_crypto_fail('base64 key material is invalid.');
        }

        return $keyMaterial;
    }
}

if (!function_exists('N_crypto_decode_base64')) {
    function N_crypto_decode_base64($encoded, $maxPayloadBytes)
    {
        if (!is_string($encoded)) {
            N_crypto_fail('Encoded payload must be a string.');
        }

        // Guard memory before decode: decoded_size ~= encoded_size * 3/4.
        $encodedLen = strlen($encoded);
        if ($encodedLen < 4) {
            N_crypto_fail('Encoded payload is too short.');
        }
        $estimatedDecodedLen = (int) floor(($encodedLen * 3) / 4);
        if ($estimatedDecodedLen > $maxPayloadBytes) {
            N_crypto_fail('Encoded payload exceeds configured maximum size.');
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            N_crypto_fail('Payload is not valid base64.');
        }

        $rawLen = strlen($raw);
        if ($rawLen < 1) {
            N_crypto_fail('Payload is empty.');
        }
        if ($rawLen > $maxPayloadBytes) {
            N_crypto_fail('Payload exceeds configured maximum size.');
        }

        return $raw;
    }
}

if (!function_exists('N_decode_v3_payload')) {
    function N_decode_v3_payload($rawPayload, array $options)
    {
        if (strlen($rawPayload) < 11) {
            N_crypto_fail('v3 payload header is incomplete.');
        }

        $header = unpack('Cversion/CkidLen/CsaltLen/CivLen/CtagLen/naadLen/NcipherLen', substr($rawPayload, 0, 11));
        if (!is_array($header)) {
            N_crypto_fail('v3 payload header is invalid.');
        }

        $kidLen = (int) $header['kidLen'];
        $saltLen = (int) $header['saltLen'];
        $ivLen = (int) $header['ivLen'];
        $tagLen = (int) $header['tagLen'];
        $aadLen = (int) $header['aadLen'];
        $cipherLen = (int) $header['cipherLen'];

        if ($kidLen < 1 || $saltLen < 16 || $ivLen !== 12 || $tagLen !== 16 || $aadLen < 10 || $cipherLen < 0) {
            N_crypto_fail('v3 payload length fields are invalid.');
        }
        if ($cipherLen > ($options['max_cipher_bytes'] ?? 1048576)) {
            N_crypto_fail('v3 cipher length exceeds configured limit.');
        }

        $expectedLen = 11 + $kidLen + $saltLen + $ivLen + $tagLen + $aadLen + $cipherLen;
        if (strlen($rawPayload) !== $expectedLen) {
            N_crypto_fail('v3 payload length does not match header.');
        }

        $offset = 11;
        $kid = substr($rawPayload, $offset, $kidLen);
        $offset += $kidLen;
        $salt = substr($rawPayload, $offset, $saltLen);
        $offset += $saltLen;
        $iv = substr($rawPayload, $offset, $ivLen);
        $offset += $ivLen;
        $tag = substr($rawPayload, $offset, $tagLen);
        $offset += $tagLen;
        $aad = substr($rawPayload, $offset, $aadLen);
        $offset += $aadLen;
        $cipherText = substr($rawPayload, $offset, $cipherLen);

        if (!isset($options['keys'][$kid])) {
            N_crypto_fail('Key id in payload not found in key ring.');
        }

        $aadData = json_decode($aad, true);
        if (!is_array($aadData)) {
            N_crypto_fail('AAD metadata is invalid JSON: ' . json_last_error_msg());
        }
        if (
            ($aadData['v'] ?? null) !== 3 ||
            ($aadData['kid'] ?? null) !== $kid ||
            ($aadData['ctx'] ?? null) !== $options['context'] ||
            ($aadData['alg'] ?? null) !== 'aes-256-gcm' ||
            ($aadData['kdf'] ?? null) !== 'hkdf-sha512'
        ) {
            N_crypto_fail('AAD context binding check failed.');
        }

        $keyMaterial = N_crypto_resolve_key_material($options['keys'][$kid]);
        $encKey = hash_hkdf(
            'sha512',
            $keyMaterial,
            32,
            'n_crypto|v3|' . $options['context'] . '|' . $kid,
            $salt
        );

        $plainText = openssl_decrypt(
            $cipherText,
            'aes-256-gcm',
            $encKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if (!is_string($plainText)) {
            N_crypto_fail('Failed to decrypt v3 payload.');
        }

        return $plainText;
    }
}

if (!function_exists('N_decode_v2_payload')) {
    function N_decode_v2_payload($rawPayload, array $options)
    {
        if (strlen($rawPayload) < 1 + 12 + 16 + 1) {
            N_crypto_fail('v2 payload is too short.');
        }

        if (($options['allow_legacy_v2'] ?? false) !== true) {
            N_crypto_fail('v2 payload support is disabled.');
        }

        $iv = substr($rawPayload, 1, 12);
        $tag = substr($rawPayload, 13, 16);
        $cipherText = substr($rawPayload, 29);

        $candidateKeys = [];
        $primaryKid = $options['primary_kid'];
        if (isset($options['keys'][$primaryKid])) {
            $candidateKeys[] = N_crypto_resolve_key_material($options['keys'][$primaryKid]);
        }
        if (($options['legacy_try_all_keys'] ?? false) === true) {
            foreach ($options['keys'] as $kid => $value) {
                if ($kid === $primaryKid) {
                    continue;
                }
                $candidateKeys[] = N_crypto_resolve_key_material($value);
            }
        }

        N_crypto_throttle_attempts($options, count($candidateKeys));
        foreach ($candidateKeys as $candidateKey) {
            $encKey = hash_hkdf('sha512', $candidateKey, 32, 'n_encode:v2:aes-256-gcm');
            $plainText = openssl_decrypt(
                $cipherText,
                'aes-256-gcm',
                $encKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if (is_string($plainText)) {
                return $plainText;
            }
        }

        N_crypto_fail('Unable to decrypt v2 payload with available keys.');
    }
}

if (!function_exists('N_decode_v1_payload')) {
    function N_decode_v1_payload($rawPayload, array $options)
    {
        if (($options['allow_legacy_v1'] ?? false) !== true) {
            N_crypto_fail('v1 payload support is disabled.');
        }

        // Legacy v1 format had no version byte and often used iv_len=1.
        // Keep for short-lived migration only.
        $candidateIvLens = [1, 16];
        foreach ($options['keys'] as $value) {
            $keyMaterial = N_crypto_resolve_key_material($value);
            $encKey = substr(hash('sha256', $keyMaterial, true), 0, 32);

            foreach ($candidateIvLens as $legacyIvLen) {
                if (strlen($rawPayload) <= $legacyIvLen) {
                    continue;
                }

                $iv = substr($rawPayload, 0, $legacyIvLen);
                $cipherText = substr($rawPayload, $legacyIvLen);

                $plainText = openssl_decrypt(
                    $cipherText,
                    'AES-256-CBC',
                    $encKey,
                    OPENSSL_RAW_DATA,
                    $iv
                );

                if (!is_string($plainText) || $plainText === '') {
                    continue;
                }

                $pad = ord($plainText[strlen($plainText) - 1]);
                if ($pad < 1 || $pad > 16) {
                    continue;
                }
                $candidate = substr($plainText, 0, -$pad);
                if (is_string($candidate)) {
                    return $candidate;
                }
            }
        }

        N_crypto_fail('Unable to decrypt legacy payload with available keys.');
    }
}

if (!function_exists('N_crypto_resolve_options')) {
    function N_crypto_resolve_options($keyInput)
    {
        $defaults = [
            'context' => 'modules.helpers.default',
            'primary_kid' => 'k1',
            'keys' => [],
            'max_payload_bytes' => 1048576,
            'max_cipher_bytes' => 1048576,
            'allow_legacy_v1' => false,
            'allow_legacy_v2' => false,
            'legacy_try_all_keys' => false,
            'max_decrypt_attempts' => 4,
            'max_decrypt_ms' => 50,
        ];

        // Preferred usage:
        // N_encode($text, ['primary_kid' => 'k2', 'keys' => ['k2' => 'base64:...'], 'context' => 'traffic.plate']);
        if (is_array($keyInput)) {
            $options = array_merge($defaults, $keyInput);
        } elseif (is_string($keyInput) && $keyInput !== '') {
            $options = $defaults;
            $options['keys'] = ['legacy' => $keyInput];
            $options['primary_kid'] = 'legacy';
        } elseif ($keyInput === null) {
            // Explicit keys are required for sensitive data operations.
            N_crypto_fail('Key/options must be provided explicitly.');
        } else {
            N_crypto_fail('Invalid key/options input type.');
        }

        if (!is_string($options['context']) || trim($options['context']) === '') {
            N_crypto_fail('Crypto context must be a non-empty string.');
        }
        $options['context'] = N_crypto_sanitize_context($options['context']);

        if (!is_string($options['primary_kid']) || trim($options['primary_kid']) === '') {
            N_crypto_fail('primary_kid must be a non-empty string.');
        }
        if (strlen($options['primary_kid']) > 64) {
            N_crypto_fail('primary_kid is too long.');
        }

        if (!is_array($options['keys']) || $options['keys'] === []) {
            N_crypto_fail('Key ring is empty.');
        }

        foreach ($options['keys'] as $kid => $value) {
            if (!is_string($kid) || trim($kid) === '') {
                N_crypto_fail('Every key id must be a non-empty string.');
            }
            if (strlen($kid) > 64 || !preg_match('/^[a-zA-Z0-9._:-]+$/', $kid)) {
                N_crypto_fail('Every key id must match allowed format and length.');
            }
            N_crypto_resolve_key_material($value);
        }

        $maxBytes = (int) ($options['max_payload_bytes'] ?? 1048576);
        if ($maxBytes < 128) {
            N_crypto_fail('max_payload_bytes is too small.');
        }
        $options['max_payload_bytes'] = $maxBytes;
        $options['max_cipher_bytes'] = (int) max(128, (int) ($options['max_cipher_bytes'] ?? $maxBytes));

        return $options;
    }
}

if (!function_exists('N_crypto_fail')) {
    function N_crypto_fail($message)
    {
        if (class_exists(\Illuminate\Support\Facades\Log::class) && N_crypto_allow_error_log($message)) {
            \Illuminate\Support\Facades\Log::error('N_crypto error', ['message' => $message]);
        }

        throw new \RuntimeException($message);
    }
}

if (!function_exists('N_crypto_allow_error_log')) {
    function N_crypto_allow_error_log($message)
    {
        static $logCounter = [];

        $bucket = (int) floor(microtime(true) / 60);
        $key = $bucket . ':' . substr(hash('sha256', (string) $message), 0, 16);
        $count = $logCounter[$key] ?? 0;
        $logCounter[$key] = $count + 1;

        return $count < 10;
    }
}

if (!function_exists('N_crypto_sanitize_context')) {
    function N_crypto_sanitize_context($context)
    {
        $ctx = trim((string) $context);
        if (strlen($ctx) < 3 || strlen($ctx) > 128) {
            N_crypto_fail('Crypto context length is invalid.');
        }
        if (!preg_match('/^[a-z0-9]+([._:-][a-z0-9]+)*$/', $ctx)) {
            N_crypto_fail('Crypto context format is invalid.');
        }
        if (substr_count($ctx, '.') < 1) {
            N_crypto_fail('Crypto context must be namespaced (example: module.feature).');
        }

        return $ctx;
    }
}

if (!function_exists('N_crypto_resolve_key_material')) {
    function N_crypto_resolve_key_material($value)
    {
        if (is_string($value)) {
            return N_normalize_key_material($value);
        }
        if (!is_array($value)) {
            N_crypto_fail('Key definition must be string or structured array.');
        }

        $material = $value['material'] ?? null;
        $status = $value['status'] ?? 'active';
        $notBefore = $value['not_before'] ?? null;
        $notAfter = $value['not_after'] ?? null;

        if ($status !== 'active') {
            N_crypto_fail('Key is not active.');
        }
        $now = time();
        if (is_int($notBefore) && $now < $notBefore) {
            N_crypto_fail('Key is not valid yet.');
        }
        if (is_int($notAfter) && $now > $notAfter) {
            N_crypto_fail('Key is expired.');
        }

        return N_normalize_key_material($material);
    }
}

if (!function_exists('N_crypto_build_aad')) {
    function N_crypto_build_aad(array $aadData)
    {
        $canonical = [
            'alg' => (string) ($aadData['alg'] ?? ''),
            'ctx' => (string) ($aadData['ctx'] ?? ''),
            'kdf' => (string) ($aadData['kdf'] ?? ''),
            'kid' => (string) ($aadData['kid'] ?? ''),
            'v' => (int) ($aadData['v'] ?? 0),
        ];

        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            N_crypto_fail('Failed to encode canonical AAD.');
        }

        return $json;
    }
}

if (!function_exists('N_crypto_throttle_attempts')) {
    function N_crypto_throttle_attempts(array $options, $plannedAttempts)
    {
        $maxAttempts = (int) ($options['max_decrypt_attempts'] ?? 4);
        if ($plannedAttempts > $maxAttempts) {
            N_crypto_fail('Decrypt attempt limit exceeded.');
        }

        static $requestAttempts = 0;
        $requestAttempts += $plannedAttempts;
        if ($requestAttempts > $maxAttempts * 4) {
            N_crypto_fail('Too many decrypt attempts in current process.');
        }
    }
}

if (!function_exists('N_crypto_assert_time_budget')) {
    function N_crypto_assert_time_budget(array $options, $startedAt)
    {
        $maxDecryptMs = (int) ($options['max_decrypt_ms'] ?? 50);
        if ($maxDecryptMs < 1) {
            return;
        }

        $elapsedMs = (int) round((microtime(true) - (float) $startedAt) * 1000);
        if ($elapsedMs > $maxDecryptMs) {
            N_crypto_fail('Decrypt time budget exceeded.');
        }
    }
}


// if (!function_exists('log_action')) {
//     /**
//      * لاگ کردن یک عمل
//      * 
//      * @param string $action نوع عمل (login, logout, create, update, delete, etc.)
//      * @param string|null $model نام مدل (اختیاری)
//      * @param int|null $modelId آی‌دی رکورد (اختیاری)
//      * @param array|null $data اطلاعات اضافی
//      * @return \Modules\User\Models\Log
//      */
//     function log_action(string $action, ?string $model = null, ?int $modelId = null, ?array $data = null)
//     {
//         $request = request();
//         $authenticatedUser = \Illuminate\Support\Facades\Auth::user();

//         return \Modules\User\Models\Log::create([
//             'user_id' => $authenticatedUser?->getAuthIdentifier() ?? 0,
//             'action' => $action,
//             'model' => $model,
//             'model_id' => $modelId,
//             'data' => $data,
//             'ip' => $request->ip(),
//             'user_agent' => $request->userAgent(),
//             'method' => $request->method(),
//             'url' => $request?->fullUrl(),
//         ]);
//     }
// }

/*
    \composer.json
    ->
    "autoload": {
        "psr-4": {
            "Modules\\": "Modules/",


    \bootstrap\providers.php
    ->
    Modules\ModuleServiceProvider::class,

*/