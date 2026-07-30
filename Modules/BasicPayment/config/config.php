<?php

return [
    'name' => 'BasicPayment',
    'default_schema_string_length' => (int) env('DEFAULT_SCHEMA_STRING_LENGTH', 255),
    'default_status' => [
        'active_text' => 'active',
        'inactive_text' => 'inactive',
        'active_bool' => true,
        'inactive_bool' => false,
        'active_int' => 1,
        'inactive_int' => 0,
    ],
];
