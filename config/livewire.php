<?php

return [
    'payload' => [
        // Increase limit for rich-text post content (TinyMCE with tables/images).
        'max_size' => 30 * 1024 * 1024, // 4MB
        'max_components' => 50,
    ],
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:30720'],
    ],
];

