<?php

return [
    'delivery_enabled' => env('INVITATION_DELIVERY_ENABLED', env('APP_ENV', 'production') !== 'local'),
];
