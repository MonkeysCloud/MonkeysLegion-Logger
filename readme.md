# MonkeysLegion Log

A flexible, PSR-3 compliant PHP logging library with environment-aware logging, multiple drivers, and extensive configuration options.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Features

- 🎯 **PSR-3 Compliant** - Implements PSR-3 LoggerInterface
- 🌍 **Environment-Aware Logging** - Smart logging based on environment
- 🔌 **Multiple Drivers** - File, Console, Syslog, Error Log, Null, and Stack
- 🎨 **Colorized Console Output** - Beautiful colored terminal logs
- 📊 **Log Level Filtering** - Control what gets logged
- 🎭 **Custom Formatting** - Flexible message formatting
- 📚 **Stack Logger** - Combine multiple loggers
- 📅 **Daily Rotation** - Automatic date-based log file rotation
- 🔄 **Circular Dependency Detection** - Prevents infinite loops
- 🧪 **Fully Tested** - Comprehensive test coverage
- 💪 **Type-Safe** - Full PHPStan level max compliance

## Installation

```bash
composer require monkeyscloud/monkeyslegion-logger
```

## Quick Start

```php
use MonkeysLegion\Logger\Factory\LoggerFactory;

// Load configuration
$config = require 'config/logging.php';

// Create logger factory
$factory = new LoggerFactory($config, 'production');

// Get default logger
$logger = $factory->make();

// Start logging
$logger->info('Application started', ['user_id' => 123]);
$logger->error('Database connection failed', ['error' => $e->getMessage()]);
```

## Configuration

Create a configuration file (e.g., `config/logging.php`):

```php
<?php

return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'stack',

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'console'],
        ],

        'single' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'level' => 'debug',
        ],

        'daily' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'daily' => true,  // Enable daily rotation
            'date_format' => 'Y-m-d',  // Optional, defaults to Y-m-d
            'level' => 'debug',
            'format' => '[{timestamp}] [{env}] {level}: {message} {context}',
        ],

        'console' => [
            'driver' => 'console',
            'level' => 'debug',
            'colorize' => true,
            'format' => '[{env}] {level}: {message} {context}',
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'warning',
            'ident' => 'my-app',
            'facility' => LOG_USER,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'error',
            'message_type' => 0,
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
```

## Available Drivers

### File Logger

Logs to files with optional date-based rotation.

**Single File (No Rotation):**
```php
'single' => [
    'driver' => 'file',
    'path' => 'logs/app.log',
    'level' => 'debug',
    'format' => '[{timestamp}] {level}: {message} {context}',
]
```

**Daily Rotation:**
```php
'daily' => [
    'driver' => 'file',
    'path' => 'logs/app.log',
    'daily' => true,  // Enable daily rotation
    'date_format' => 'Y-m-d',  // Optional, defaults to Y-m-d
    'level' => 'debug',
    'format' => '[{timestamp}] [{env}] {level}: {message} {context}',
]
```

When daily rotation is enabled:
- `logs/app.log` → `logs/app-2024-01-15.log`
- `logs/errors.log` → `logs/errors-2024-01-15.log`
- `logs/debug.txt` → `logs/debug-2024-01-15.txt`

The date is automatically inserted before the file extension!

### Console Logger

Outputs to console with optional colorization.

```php
'console' => [
    'driver' => 'console',
    'level' => 'debug',
    'colorize' => true,
    'format' => '[{env}] {level}: {message}',
]
```

**Color Scheme:**
- 🔴 **Emergency/Alert/Critical** - Bold Red
- 🔴 **Error** - Red
- 🟡 **Warning** - Yellow
- 🔵 **Notice** - Cyan
- 🟢 **Info** - Green
- ⚪ **Debug** - White

### Syslog Logger

Sends logs to system logger.

```php
'syslog' => [
    'driver' => 'syslog',
    'level' => 'warning',
    'ident' => 'my-application',
    'facility' => LOG_USER,
]
```

### Error Log Logger

Uses PHP's native `error_log()` function.

```php
'errorlog' => [
    'driver' => 'errorlog',
    'level' => 'error',
    'message_type' => 0, // 0=system, 1=email, 3=file, 4=SAPI
    'destination' => null, // Required for types 1 and 3
]
```

### Null Logger

Discards all log messages (useful for testing).

```php
'null' => [
    'driver' => 'null',
]
```

### Stack Logger

Combines multiple loggers to write to multiple destinations.

```php
'stack' => [
    'driver' => 'stack',
    'channels' => ['daily', 'console', 'syslog'],
]
```

## Usage Examples

### Basic Logging

```php
use MonkeysLegion\Logger\Factory\LoggerFactory;

$factory = new LoggerFactory($config);
$logger = $factory->make('daily');

// PSR-3 log levels
$logger->emergency('System is down!');
$logger->alert('Database is unavailable');
$logger->critical('Application crash');
$logger->error('User registration failed');
$logger->warning('Disk space low');
$logger->notice('User logged in');
$logger->info('Email sent successfully');
$logger->debug('Processing request', ['data' => $requestData]);
```

### Daily Rotation Example

```php
$config = [
    'channels' => [
        'daily' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'daily' => true,  // Enable rotation
        ],
        'errors_daily' => [
            'driver' => 'file',
            'path' => 'logs/errors.log',
            'daily' => true,
            'date_format' => 'Y-m-d',
            'level' => 'error',
        ],
    ],
];

$factory = new LoggerFactory($config);
$logger = $factory->make('daily');

$logger->info('This logs to logs/app-2024-01-15.log');

$errorLogger = $factory->make('errors_daily');
$errorLogger->error('This logs to logs/errors-2024-01-15.log');
```

### Environment-Aware Smart Logging

The `smartLog()` method automatically adjusts log levels based on environment:

```php
// Production: logs as INFO
// Staging: logs as NOTICE
// Testing: logs as WARNING
// Development: logs as DEBUG
$logger->smartLog('Processing payment', ['amount' => 99.99]);
```

### Contextual Logging

```php
$logger->info('User action', [
    'user_id' => 42,
    'action' => 'purchase',
    'amount' => 99.99,
    'timestamp' => time(),
]);

// Output: [2024-01-15 10:30:45] [production] INFO: User action {"user_id":42,"action":"purchase","amount":99.99,"timestamp":1705315845}
```

### Using Different Channels

```php
$factory = new LoggerFactory($config);

// Production logging with daily rotation
$productionLogger = $factory->make('stack');
$productionLogger->info('Application started');

// Debug logging to daily file
$debugLogger = $factory->make('daily');
$debugLogger->debug('Debugging info', ['vars' => $debug]);

// Emergency logging
$emergencyLogger = $factory->make('syslog');
$emergencyLogger->emergency('Critical system failure');
```

### Custom Formatting

Format tokens:
- `{timestamp}` - Current date/time
- `{env}` - Environment name
- `{level}` - Log level (uppercase)
- `{message}` - Log message
- `{context}` - JSON-encoded context

```php
'custom' => [
    'driver' => 'file',
    'path' => 'logs/custom.log',
    'format' => '{timestamp}|{level}|{message}',
]

// Output: 2024-01-15 10:30:45|INFO|User logged in
```

### Log Level Filtering

Only logs at or above the specified level are written:

```php
'production' => [
    'driver' => 'file',
    'path' => 'logs/production.log',
    'daily' => true,
    'level' => 'warning', // Only warning, error, critical, alert, emergency
]

$logger->debug('Debug info');    // Not logged
$logger->info('Info message');   // Not logged
$logger->warning('Warning!');    // Logged ✓ to logs/production-2024-01-15.log
$logger->error('Error occurred'); // Logged ✓ to logs/production-2024-01-15.log
```

**Log Level Hierarchy:**
```
DEBUG < INFO < NOTICE < WARNING < ERROR < CRITICAL < ALERT < EMERGENCY
```

## Advanced Usage

### Factory with Environment

```php
// Automatically uses APP_ENV from environment
$factory = new LoggerFactory($config);

// Or explicitly set environment
$factory = new LoggerFactory($config, 'production');
$factory = new LoggerFactory($config, 'development');
```

### Multiple Loggers with Daily Rotation

```php
$config = [
    'channels' => [
        'app' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'daily' => true,
        ],
        'errors' => [
            'driver' => 'file',
            'path' => 'logs/errors.log',
            'daily' => true,
            'level' => 'error',
        ],
        'audit' => [
            'driver' => 'file',
            'path' => 'logs/audit.log',
            'daily' => true,
            'date_format' => 'Y-m-d',
        ],
    ],
];

$appLogger = $factory->make('app');
$errorLogger = $factory->make('errors');
$auditLogger = $factory->make('audit');

try {
    // Your code
} catch (\Exception $e) {
    $errorLogger->error('Exception caught', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}

$auditLogger->info('User action', ['user_id' => $userId, 'action' => 'login']);
$appLogger->debug('Request details', ['request' => $_REQUEST]);

// This creates:
// - logs/app-2024-01-15.log
// - logs/errors-2024-01-15.log (only errors and above)
// - logs/audit-2024-01-15.log
```

### Testing with Null Logger

```php
class UserService
{
    public function __construct(
        private MonkeysLoggerInterface $logger
    ) {}
}

// Production
$service = new UserService($factory->make('stack'));

// Testing - no actual logging
$service = new UserService($factory->make('null'));
```

## Environment Configuration

```bash
# .env file
LOG_CHANNEL=stack
LOG_LEVEL=debug
APP_ENV=production
APP_NAME=my-application
```

## Best Practices

### 1. Use Appropriate Log Levels

```php
// ❌ Bad
$logger->info('Database connection failed');

// ✅ Good
$logger->error('Database connection failed', [
    'host' => $dbHost,
    'error' => $e->getMessage(),
]);
```

### 2. Add Context

```php
// ❌ Bad
$logger->error('Payment failed');

// ✅ Good
$logger->error('Payment failed', [
    'user_id' => $userId,
    'amount' => $amount,
    'payment_method' => $method,
    'error_code' => $errorCode,
]);
```

### 3. Use Daily Rotation for Production

```php
// ✅ Good for production
'production' => [
    'driver' => 'file',
    'path' => 'logs/app.log',
    'daily' => true,  // New file each day
    'level' => 'warning',
]

// ❌ Avoid single file in production (grows indefinitely)
'production' => [
    'driver' => 'file',
    'path' => 'logs/app.log',
    'daily' => false,
]
```

### 4. Use smartLog() for General Flow

```php
// Automatically adjusts based on environment
$logger->smartLog('Processing order', ['order_id' => $orderId]);
```

### 5. Configure Per Environment

```php
// config/logging-production.php
return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['syslog', 'daily'],
        ],
        'daily' => [
            'driver' => 'file',
            'path' => 'logs/app.log',
            'daily' => true,
            'level' => 'warning',  // Only warnings and above
        ],
    ],
];

// config/logging-development.php
return [
    'default' => 'console',
    'channels' => [
        'console' => [
            'driver' => 'console',
            'level' => 'debug',  // Everything in development
            'colorize' => true,
        ],
    ],
];
```

## Testing

Run the test suite:

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Run static analysis
./vendor/bin/phpstan analyse
```

## Requirements

- PHP 8.1 or higher
- PSR-3 Logger Interface

## License

MIT License - see LICENSE file for details.

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.

## Support

For issues, questions, or suggestions, please open an issue on GitHub.

## Changelog

### Version 1.2.0

**Formatters**
- `LineFormatter` — PSR-3 `{placeholder}` message interpolation, microsecond-precision ISO-8601 timestamps, `{channel}` and `{extra}` tokens
- `JsonFormatter` — Structured JSON output (one object per line) for log aggregation (ELK, Datadog, CloudWatch, Loki)
- `FormatterInterface` — Pluggable formatter contract for custom formatters

**Processors**
- `IntrospectionProcessor` — Adds call-site `file`, `line`, `class`, `function` to log records
- `MemoryUsageProcessor` — Adds `memory_usage` and `memory_peak` to log records
- `UidProcessor` — Generates per-request unique ID for log correlation, with `reset()` for workers

**Exception Handling**
- Automatic `Throwable` normalization in context: `['exception' => $e]` → structured array with `class`, `message`, `code`, `file:line`, `trace` (15 frames), and nested `previous`
- `FileLogger` graceful write-failure handling with `error_log()` fallback — logging failures never crash the app

**New Drivers**
- `BufferLogger` — Defers writes to a wrapped logger, with `flush()`, auto-flush on threshold, and immediate flush for `emergency()` — ideal for workers and queue consumers

**Console Logger**
- Routes `error`, `critical`, `alert`, `emergency` to `stderr` (production best practice)

**Channel Support**
- Each logger now carries a `channel` name (visible in log output via `{channel}` token)
- `LoggerFactory` auto-wires channel names from config key

**Configuration**
- `'formatter'` key: `'line'` or `'json'` per channel
- `'processors'` key: array of processor class names per channel
- `'channel'` key: override channel name per logger
- `'buffer'` driver with `handler`, `buffer_limit`, `flush_on_overflow` options

### Version 1.0.0
- Initial release
- PSR-3 compliant logging
- Multiple driver support (File, Console, Syslog, ErrorLog, Null, Stack)
- Environment-aware logging with smartLog()
- Daily rotation support for file logger
- Custom formatting with tokens
- Log level filtering
- Full test coverage
- PHPStan level max compliance

---

Made with ❤️ by MonkeysLegion
