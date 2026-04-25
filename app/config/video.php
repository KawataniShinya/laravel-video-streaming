<?php

return [
    'root' => env('VIDEO_ROOT', '/videos'),
    'hls_cache_path' => env('VIDEO_HLS_CACHE_PATH', storage_path('hls')),
];
