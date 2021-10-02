<?php

return [
    'base_url' => env('GIV_BASE_URL', 'http://127.0.0.1:8200'),
    'token' => env('GIV_WEB_TOKEN', null),
    'syncronize_customers' => true,
    'giv_user_form_id' => 2,
    'author_id' => 1,
    'datetime_format' => 'Y-m-d\TH:i:sO',
    'product_default_image_width' => 256,
    'product_default_image_height' => 256,
];
