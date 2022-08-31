<?php

return [

    // The maximum allowed depth of nested filter groups. An upper bound
    // should be used to prevent ghastly queries from wreaking havoc.
    'max_nested_depth' => env('LI_SEARCH_MAX_NESTED_DEPTH', 15),

    'pagination' => [

        // The maximum page size returnable at once.
        'max_size' => 30,

        // The default page size when otherwise unspecified.
        'default_size' => 30,

        // The name of the query parameter used for pagination.
        'pagination_parameter' => 'page',

        // The key of the page[x] query string parameter for page number.
        'number_parameter' => 'number',

        // The key of the page[x] query string parameter for page size.
        'size_parameter' => 'size',

        // The key of the page[x] query string parameter for cursor.
        'cursor_parameter' => 'cursor',

        // The name of the macro that is added to the Eloquent query builder.
        'method_name' => 'apiPaginate',

        // The pagination method that should be used by default. Can be one
        // of 'combined', 'paginate', 'simplePaginate', 'cursorPaginate'.
        'method' => 'combined',

        // If true, use relative paths in pagination links rather than the
        // server's uri. Useful when behind an API gateway for example.
        'use_relative_urls' => true,

        // If true, endpoints return exact total counts by default which
        // provides a better UX in exchange for a bit of performance.
        'include_total_count_by_default' => false,

    ],
];
