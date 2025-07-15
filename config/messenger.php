<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Messenger Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default messenger provider that will be used
    | when no specific provider is specified.
    |
    */

    'default' => env('MESSENGER_DEFAULT_PROVIDER', 'smsmisr'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the messenger providers for your application.
    | Each provider has its own configuration options.
    |
    */

    'providers' => [
        'smsmisr' => [
            'driver' => 'smsmisr',
            'api_base_url' => env('SMS_MISR_API_BASE_URL', 'https://smsmisr.com/api/SMS/?'),
            'otp_base_url' => env('SMS_MISR_OTP_BASE_URL', 'https://smsmisr.com/api/OTP/?'),
            'balance_url' => env('SMS_MISR_BALANCE_URL', 'https://smsmisr.com/api/Balance/?'),
            'username' => env('SMS_MISR_API_USERNAME'),
            'password' => env('SMS_MISR_API_PASSWORD'),
            'sender_id' => env('SMS_MISR_SENDER_ID'),
            'test_sender_id' => 'b611afb996655a94c8e942a823f1421de42bf8335d24ba1f84c437b2ab11ca27',
            'environment' => env('SMS_MISR_ENVIRONMENT', 2), // 1 for Live, 2 for Test
            'webhook_secret' => env('SMS_MISR_WEBHOOK_SECRET'),
            'timeout' => 30,
            'retries' => 3,
            'retry_delay' => 1000, // milliseconds
            'max_recipients' => 5000,
            'rate_limit' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000,
            ],
            'error_mappings' => [
                // SMS API Response Codes
                '1901' => ['message' => 'Message submitted successfully', 'type' => 'success'],
                '1902' => ['message' => 'Invalid request', 'type' => 'validation'],
                '1903' => ['message' => 'Invalid username or password', 'type' => 'authentication'],
                '1904' => ['message' => 'Invalid sender field', 'type' => 'configuration'],
                '1905' => ['message' => 'Invalid mobile field', 'type' => 'invalid_recipient'],
                '1906' => ['message' => 'Insufficient credit', 'type' => 'insufficient_credit'],
                '1907' => ['message' => 'Server under updating', 'type' => 'temporary'],
                '1908' => ['message' => 'Invalid date & time format in DelayUntil parameter', 'type' => 'validation'],
                '1909' => ['message' => 'Invalid message', 'type' => 'validation'],
                '1910' => ['message' => 'Invalid language', 'type' => 'validation'],
                '1911' => ['message' => 'Text is too long', 'type' => 'validation'],
                '1912' => ['message' => 'Invalid environment', 'type' => 'configuration'],

                // OTP API Response Codes
                '4901' => ['message' => 'OTP message submitted successfully', 'type' => 'success'],
                '4903' => ['message' => 'Invalid username or password for OTP', 'type' => 'authentication'],
                '4904' => ['message' => 'Invalid sender field for OTP', 'type' => 'configuration'],
                '4905' => ['message' => 'Invalid mobile field for OTP', 'type' => 'invalid_recipient'],
                '4906' => ['message' => 'Insufficient credit for OTP', 'type' => 'insufficient_credit'],
                '4907' => ['message' => 'Server under updating for OTP', 'type' => 'temporary'],
                '4908' => ['message' => 'Invalid OTP code', 'type' => 'validation'],
                '4909' => ['message' => 'Invalid template token', 'type' => 'configuration'],
                '4912' => ['message' => 'Invalid environment for OTP', 'type' => 'configuration'],
            ],
        ],

        'twilio' => [
            'driver' => 'twilio',
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
            'webhook_secret' => env('TWILIO_WEBHOOK_SECRET'),
            'timeout' => 30,
            'retries' => 3,
            'retry_delay' => 1000, // milliseconds
            'max_recipients' => 1000,
            'rate_limit' => [
                'requests_per_minute' => 100,
                'requests_per_hour' => 3600,
            ],
            'error_mappings' => [
                // Authentication Errors
                '20003' => ['message' => 'Authentication failed', 'type' => 'authentication'],
                '20404' => ['message' => 'Invalid API version', 'type' => 'configuration'],

                // Account Errors
                '20429' => ['message' => 'Insufficient funds', 'type' => 'insufficient_credit'],
                '21608' => ['message' => 'Account suspended', 'type' => 'authentication'],

                // Phone Number Errors
                '21211' => ['message' => 'Invalid To phone number', 'type' => 'invalid_recipient'],
                '21212' => ['message' => 'Invalid From phone number', 'type' => 'configuration'],
                '21213' => ['message' => 'Invalid SMS body', 'type' => 'validation'],
                '21214' => ['message' => 'To phone number cannot receive SMS', 'type' => 'invalid_recipient'],
                '21215' => ['message' => 'Account not authorized to send to this number', 'type' => 'authentication'],
                '21216' => ['message' => 'Account not authorized to send from this number', 'type' => 'configuration'],
                '21217' => ['message' => 'Phone number is not a valid SMS-enabled number', 'type' => 'invalid_recipient'],

                // Message Delivery Errors
                '30001' => ['message' => 'Queue overflow', 'type' => 'temporary'],
                '30002' => ['message' => 'Account suspended', 'type' => 'authentication'],
                '30003' => ['message' => 'Unreachable destination handset', 'type' => 'temporary'],
                '30004' => ['message' => 'Message blocked', 'type' => 'invalid_recipient'],
                '30005' => ['message' => 'Unknown destination handset', 'type' => 'invalid_recipient'],
                '30006' => ['message' => 'Landline or unreachable carrier', 'type' => 'invalid_recipient'],
                '30007' => ['message' => 'Carrier violation', 'type' => 'temporary'],
                '30008' => ['message' => 'Unknown error', 'type' => 'temporary'],
                '30009' => ['message' => 'Missing segment', 'type' => 'temporary'],
                '30010' => ['message' => 'Message price exceeds max price', 'type' => 'configuration'],

                // Rate Limiting
                '20429' => ['message' => 'Too many requests', 'type' => 'rate_limit'],
            ],
        ],

        // Mock Test Provider (for testing plugin architecture)
        'mocktest' => [
            'driver' => 'mocktest',
            'api_key' => env('MOCKTEST_API_KEY', 'mock_test_key'),
            'debug_mode' => env('MOCKTEST_DEBUG', true),
            'timeout' => 30,
            'max_recipients' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    |
    | Configure which providers should be used for specific channels.
    |
    */

    'channels' => [
        'sms' => [
            'default' => 'smsmisr',
            'providers' => ['smsmisr', 'twilio', 'mocktest'],
        ],
        'whatsapp' => [
            'default' => 'twilio',
            'providers' => ['twilio'],
        ],
        'otp' => [
            'default' => 'smsmisr',
            'providers' => ['smsmisr'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Template System
    |--------------------------------------------------------------------------
    |
    | Configuration for the message template system.
    |
    */

    'templates' => [
        'cache_ttl' => env('MESSENGER_TEMPLATE_CACHE_TTL', 3600), // 1 hour
        'default_language' => 'en',
        'supported_languages' => ['en', 'ar'],
        'auto_detect_arabic' => true,
        'variable_pattern' => '/\{\{\s*(\w+)\s*\}\}/',
        'categories' => [
            'otp',
            'welcome',
            'verification',
            'marketing',
            'transactional',
            'emergency',
        ],
        'predefined' => [
            'otp_sms' => [
                'en' => 'Your OTP is {{otp_code}}',
                'ar' => 'كود التحقق الخاص بك هو {{otp_code}}',
            ],
            'welcome_member' => [
                'en' => 'Welcome {{first_name}}! Your membership is now active.',
                'ar' => 'مرحباً {{first_name}}! عضويتك نشطة الآن.',
            ],
            'entry_confirmation' => [
                'en' => 'Entry confirmed for {{first_name}} at {{entry_time}}',
                'ar' => 'تم تأكيد الدخول لـ {{first_name}} في {{entry_time}}',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for message processing.
    |
    */

    'queue' => [
        'connection' => env('MESSENGER_QUEUE_CONNECTION', 'redis'),
        'queue' => env('MESSENGER_QUEUE_NAME', 'messenger'),
        'retry_after' => 90,
        'max_exceptions' => 3,
        'backoff_delay' => [1, 5, 15], // minutes
        'priority' => [
            'emergency' => 100,
            'otp' => 90,
            'transactional' => 80,
            'verification' => 70,
            'welcome' => 60,
            'marketing' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoints for delivery status updates.
    |
    */

    'webhooks' => [
        'enabled' => env('MESSENGER_WEBHOOKS_ENABLED', true),
        'base_url' => env('APP_URL', 'http://localhost'),
        'endpoints' => [
            'smsmisr' => '/messenger/webhook/smsmisr',
            'twilio' => '/messenger/webhook/twilio',
        ],
        'signature_verification' => [
            'smsmisr' => [
                'method' => 'hmac_sha256',
                'header' => 'X-SMS-Misr-Signature',
            ],
            'twilio' => [
                'method' => 'twilio_signature',
                'header' => 'X-Twilio-Signature',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics & Logging
    |--------------------------------------------------------------------------
    |
    | Configure analytics and logging settings.
    |
    */

    'analytics' => [
        'enabled' => env('MESSENGER_ANALYTICS_ENABLED', true),
        'retention_days' => env('MESSENGER_LOG_RETENTION_DAYS', 90),
        'cache_ttl' => env('MESSENGER_ANALYTICS_CACHE_TTL', 3600),
        'real_time_updates' => env('MESSENGER_REAL_TIME_UPDATES', true),
    ],

    'logging' => [
        'channel' => env('MESSENGER_LOG_CHANNEL', 'stack'),
        'level' => env('MESSENGER_LOG_LEVEL', 'info'),
        'include_payload' => env('MESSENGER_LOG_INCLUDE_PAYLOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Compliance
    |--------------------------------------------------------------------------
    |
    | Configure security and compliance settings.
    |
    */

    'security' => [
        'phone_validation' => true,
        'consent_required' => true,
        'rate_limiting' => true,
        'data_anonymization' => [
            'enabled' => true,
            'after_days' => 90,
        ],
        'audit_logging' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Configure cost tracking for different providers and message types.
    |
    */

    'costs' => [
        'currency' => 'EGP',
        'providers' => [
            'smsmisr' => [
                'sms_cost' => 0.10, // per SMS
                'otp_cost' => 0.12, // per OTP
            ],
            'twilio' => [
                'sms_cost' => 0.15,
                'whatsapp_cost' => 0.05,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features.
    |
    */

    'features' => [
        'bulk_messaging' => true,
        'scheduled_messaging' => true,
        'template_versioning' => true,
        'a_b_testing' => true,
        'delivery_tracking' => true,
        'consent_management' => true,
        'cost_estimation' => true,
        'real_time_analytics' => true,
        'circuit_breaker' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Circuit breaker settings for provider reliability
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => env('MESSENGER_CIRCUIT_BREAKER_THRESHOLD', 5),
        'timeout' => env('MESSENGER_CIRCUIT_BREAKER_TIMEOUT', 300), // 5 minutes
        'half_open_timeout' => env('MESSENGER_CIRCUIT_BREAKER_HALF_OPEN', 60), // 1 minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for background message processing
    |
    */

    'queues' => [
        'urgent' => env('MESSENGER_URGENT_QUEUE', 'urgent'),
        'high' => env('MESSENGER_HIGH_QUEUE', 'high'),
        'default' => env('MESSENGER_DEFAULT_QUEUE', 'default'),
        'low' => env('MESSENGER_LOW_QUEUE', 'low'),
        'bulk' => env('MESSENGER_BULK_QUEUE', 'bulk'),
        'scheduled' => env('MESSENGER_SCHEDULED_QUEUE', 'scheduled'),
        'retries' => env('MESSENGER_RETRY_QUEUE', 'retries'),
        'webhooks' => env('MESSENGER_WEBHOOK_QUEUE', 'webhooks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for failed message retries
    |
    */

    'retries' => [
        'max_retries' => env('MESSENGER_MAX_RETRIES', 3),
        'exponential_backoff' => true,
        'base_delay' => 5, // minutes
        'max_delay' => 60, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Management Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for GDPR-compliant consent management
    |
    */

    'consent' => [
        'enabled' => env('MESSENGER_CONSENT_ENABLED', true),
        'double_opt_in' => env('MESSENGER_DOUBLE_OPT_IN', true),
        'retention_days' => env('MESSENGER_CONSENT_RETENTION_DAYS', 2555), // ~7 years
        'default_type' => env('MESSENGER_DEFAULT_CONSENT_TYPE', 'marketing'),
        'auto_cleanup' => env('MESSENGER_CONSENT_AUTO_CLEANUP', true),
        'reply_keywords' => [
            'opt_in' => ['yes', 'y', 'ok', 'confirm', 'agree', 'accept'],
            'opt_out' => ['stop', 'unsubscribe', 'no', 'n', 'cancel', 'remove'],
        ],
        'preferences' => [
            'marketing' => 'Marketing messages',
            'notifications' => 'Service notifications',
            'reminders' => 'Appointment reminders',
            'alerts' => 'Important alerts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for analytics and reporting
    |
    */

    'analytics' => [
        'enabled' => env('MESSENGER_ANALYTICS_ENABLED', true),
        'real_time' => env('MESSENGER_REAL_TIME_ANALYTICS', true),
        'retention_days' => env('MESSENGER_ANALYTICS_RETENTION_DAYS', 365),
        'refresh_interval' => env('MESSENGER_ANALYTICS_REFRESH_INTERVAL', 30), // seconds
        'chart_data_points' => env('MESSENGER_ANALYTICS_CHART_POINTS', 24),
        'cost_tracking' => env('MESSENGER_COST_TRACKING', true),
        'engagement_tracking' => env('MESSENGER_ENGAGEMENT_TRACKING', true),
        'performance_thresholds' => [
            'delivery_rate' => 0.95,
            'success_rate' => 0.90,
            'response_time' => 5000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Redis caching settings for improved performance
    |
    */

    'caching' => [
        'enabled' => env('MESSENGER_CACHING_ENABLED', true),
        'driver' => env('MESSENGER_CACHE_DRIVER', 'redis'),
        'prefix' => env('MESSENGER_CACHE_PREFIX', 'messenger'),
        'ttl' => [
            'templates' => env('MESSENGER_CACHE_TEMPLATES_TTL', 3600), // 1 hour
            'analytics' => env('MESSENGER_CACHE_ANALYTICS_TTL', 300), // 5 minutes
            'consent' => env('MESSENGER_CACHE_CONSENT_TTL', 1800), // 30 minutes
            'settings' => env('MESSENGER_CACHE_SETTINGS_TTL', 7200), // 2 hours
            'provider_status' => env('MESSENGER_CACHE_PROVIDER_STATUS_TTL', 60), // 1 minute
        ],
        'tags' => [
            'templates' => 'messenger.templates',
            'analytics' => 'messenger.analytics',
            'consent' => 'messenger.consent',
            'settings' => 'messenger.settings',
            'providers' => 'messenger.providers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Sentry integration for error monitoring
    |
    */

    'error_tracking' => [
        'enabled' => env('MESSENGER_ERROR_TRACKING_ENABLED', false),
        'sentry_dsn' => env('MESSENGER_SENTRY_DSN'),
        'sample_rate' => env('MESSENGER_SENTRY_SAMPLE_RATE', 0.1),
        'environments' => ['production', 'staging'],
        'tags' => [
            'component' => 'messenger',
            'package' => 'messenger',
        ],
        'contexts' => [
            'message_id' => true,
            'provider' => true,
            'channel' => true,
            'template_id' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Performance metrics and monitoring settings
    |
    */

    'monitoring' => [
        'enabled' => env('MESSENGER_MONITORING_ENABLED', true),
        'metrics' => [
            'response_times' => true,
            'throughput' => true,
            'error_rates' => true,
            'queue_depths' => true,
            'provider_health' => true,
        ],
        'alerts' => [
            'high_error_rate_threshold' => 0.1, // 10%
            'slow_response_threshold' => 10000, // 10 seconds
            'queue_depth_threshold' => 1000,
            'notification_email' => env('MESSENGER_ALERT_EMAIL'),
        ],
        'sampling' => [
            'rate' => env('MESSENGER_MONITORING_SAMPLE_RATE', 1.0),
            'max_samples_per_minute' => 1000,
        ],
    ],
];
