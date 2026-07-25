<?php

return [
    'documents' => dirname(__DIR__) . '/storage/documentos',
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 10),
    'image_compression_enabled' => filter_var(env('IMAGE_COMPRESSION_ENABLED', true), FILTER_VALIDATE_BOOL),
    'image_target_kb' => max(10, (int) env('IMAGE_TARGET_KB', 50)),
    'image_max_side' => max(320, (int) env('IMAGE_MAX_SIDE', 1400)),
];
