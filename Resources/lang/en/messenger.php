<?php

return [
    'messages' => [
        'sent' => 'Message sent successfully',
        'failed' => 'Failed to send message',
        'queued' => 'Message queued for delivery',
        'scheduled' => 'Message scheduled successfully',
        'cancelled' => 'Message cancelled',
    ],
    'providers' => [
        'smsmisr' => 'SMS Misr',
        'twilio' => 'Twilio',
        'mocktest' => 'Mock Test Provider',
    ],
    'channels' => [
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
    ],
    'templates' => [
        'created' => 'Template created successfully',
        'updated' => 'Template updated successfully',
        'deleted' => 'Template deleted successfully',
        'not_found' => 'Template not found',
    ],
];
