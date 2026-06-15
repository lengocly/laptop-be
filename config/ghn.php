<?php

return [
    'token' => env('GHN_TOKEN'),
    'shop_id' => env('GHN_SHOP_ID'),
    'from_district_id' => (int) env('GHN_FROM_DISTRICT_ID', 0),
    'from_ward_code' => env('GHN_FROM_WARD_CODE', ''),
    'base_url' => rtrim(env('GHN_BASE_URL', 'https://online-gateway.ghn.vn'), '/'),
    'default_weight' => (int) env('GHN_DEFAULT_WEIGHT', 1500),
];
