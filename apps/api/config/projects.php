<?php

return [
    'uploads' => [
        'max_request_bytes' => (int) env('PROJECT_UPLOAD_MAX_REQUEST_BYTES', 50 * 1024 * 1024),
    ],
];
