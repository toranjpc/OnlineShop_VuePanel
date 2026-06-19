<?php

return [
    'custom_tariff' => (float) env('TRAFFIC_CUSTOM_TARIFF', 2745500),
    'balk_tariff' => (float) env('TRAFFIC_BALK_TARIFF', 2745500),
    'aftab' => [
        'user' => env('TRAFFIC_USER_AFTAB'),
        'password' => env('TRAFFIC_PASSWORD_AFTAB'),
    ],
    'add' => [
        'user' => env('TRAFFIC_USER_ADD'),
        'password' => env('TRAFFIC_PASSWORD_ADD'),
    ],
    'ccs' => [
        'debug_save_raw_response' => (bool) env('CCS_DEBUG_SAVE_RAW', true),
    ],
    'gcoms' => [
        'debug_save_raw_response' => (bool) env('GCOMS_DEBUG_SAVE_RAW', true),
    ],
    'strip' => [
        'debug_save_raw_response' => (bool) env('STRIP_DEBUG_SAVE_RAW', true),
    ],
    'invoice' => [
        'debug_save_raw_response' => (bool) env('INVOICE_DEBUG_SAVE_RAW', true),
    ],
    'spad' => [
        'user' => env('TRAFFIC_USER_SPAD'),
        'password' => env('TRAFFIC_PASSWORD_SPAD'),
    ],
];
