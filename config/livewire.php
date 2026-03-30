<?php

return [
    'payload' => [
        // Increase limit for rich-text post content (TinyMCE with tables/images).
        'max_size' => 16 * 1024 * 1024, // 4MB
        'max_components' => 50,
    ],
];

