<?php

return [
    'documents' => dirname(__DIR__) . '/storage/documentos',
    'max_upload_mb' => (int) env('MAX_UPLOAD_MB', 10),
];
