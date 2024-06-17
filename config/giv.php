<?php

return [
    'host' => env('GIV_HOST', '127.0.0.1'),
    'base_url' => env('GIV_BASE_URL', 'http://127.0.0.1:8200'),
    'token' => env('GIV_WEB_TOKEN', null),
    'syncronize_customers' => true,
    'giv_user_form_id' => 2,
    'author_id' => 1,
    'datetime_format' => 'Y-m-d H:i:s',
    'datetime_timezone' => 'Asia/Tehran',
    'product_default_image_width' => 256,
    'product_default_image_height' => 256,
    'sms_gate_cart_sync' => 2,
    'giv_brands_parent_category' => null
];
