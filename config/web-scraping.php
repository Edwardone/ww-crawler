<?php

declare(strict_types=1);

return [
    'web-magic' => [
        'token-url' => env('WEB_MAGIC_GET_TOKEN_URL'),
        'filtered-articles-url' => env('WEB_MAGIC_GET_FILTERED_ARTICLES_URL'),
        'articles-category-id' => env('WEB_MAGIC_ARTICLES_CATEGORY_ID'),
    ],
];
