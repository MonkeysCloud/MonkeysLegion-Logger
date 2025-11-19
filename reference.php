<?php

return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'console'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'format' => '[{timestamp}] [{env}] {level}: {message} {context}',
        ],

        'daily' => [
            'driver' => 'file',
            'path' => 'logs/app-{date}.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'format' => '[{timestamp}] [{env}] {level}: {message} {context}',
            'date_format' => 'Y-m-d',
        ],

        'console' => [
            'driver' => 'console',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'colorize' => true,
            'format' => '[{env}] {level}: {message} {context}',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'ident' => $_ENV['APP_NAME'] ?? 'php',
            'facility' => LOG_USER,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'message_type' => 0,
            'destination' => null,
        ],

        'null' => [
            'driver' => 'null',
            'level' => 'debug',
        ],

        'emergency' => [
            'driver' => 'file',
            'path' => 'logs/emergency.log',
            'level' => 'emergency',
            'format' => '[{timestamp}] [{env}] EMERGENCY: {message} {context}',
        ],
    ],
];
