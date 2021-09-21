<?php

return [
    // sms notifications
    'sms' => [
        // sms notifications default title
        'default-title' => 'SMSService',
        // notifications tasks queue
        'queue' => 'listeners',
        // available gateways
        'gateways' => [
            'nexmo' => \Larapress\Notifications\Services\SMSService\Gateways\Nexmo\NexmoSMSGateway::class,
            'faraz' => \Larapress\Notifications\Services\SMSService\Gateways\FarazSMS\FarazSMSGateway::class,
            'farapayamak' => \Larapress\Notifications\Services\SMSService\Gateways\FaraPayamak\FaraPayamakSMSGateway::class,
            'smsir_fast' => \Larapress\Notifications\Services\SMSService\Gateways\SMSIR\SMSIRSimpleGateway::class,
            'smsir_simple' => \Larapress\Notifications\Services\SMSService\Gateways\SMSIR\SMSIRFastGateway::class,
            'mizbansms' => \Larapress\Notifications\Services\SMSService\Gateways\MizbanSMS\MizbanSMSGateway::class,
            'mockery' => Larapress\Notifications\Services\SMSService\Gateways\MockerySMSGateway::class,
        ],
    ],

    // crud resources in notifications package
    'routes' => [
        'notifications' => [
            'name' => 'notifications',
            'model' => \Larapress\Notifications\Models\Notification::class,
            'provider' => \Larapress\Notifications\CRUD\NotificationCRUDProvider::class,
        ],
        'sms_gateways' => [
            'name' => 'sms-gateways',
            'model' => \Larapress\Notifications\Models\SMSGatewayData::class,
            'provider' => \Larapress\Notifications\CRUD\SMSGatewayDataCRUDProvider::class,
        ],
        'sms_messages' => [
            'name' => 'sms-messages',
            'model' => \Larapress\Notifications\Models\SMSMessage::class,
            'provider' => \Larapress\Notifications\CRUD\SMSMessageCRUDProvider::class,
        ],
        'chat_rooms' => [
            'name' => 'chat-rooms',
            'model' => \Larapress\Notifications\Models\ChatRoom::class,
            'provider' => \Larapress\Notifications\CRUD\ChatRoomCRUDProvider::class,
        ],
        'chat_messages' => [
            'name' => 'chat-messages',
            'model' => \Larapress\Notifications\Models\ChatMessage::class,
            'provider' => \Larapress\Notifications\CRUD\ChatMessageCRUDProvider::class,
        ],
    ],

    'permissions' => [
        \Larapress\Notifications\CRUD\NotificationCRUDProvider::class,
        \Larapress\Notifications\CRUD\SMSGatewayDataCRUDProvider::class,
        \Larapress\Notifications\CRUD\SMSMessageCRUDProvider::class,
        \Larapress\Notifications\CRUD\ChatMessageCRUDProvider::class,
        \Larapress\Notifications\CRUD\ChatRoomCRUDProvider::class,
    ],
];
