<?php

return [

    /*
    | The decision driver used to answer questions about rows.
    */
    'driver' => env('VIBEFILTER_DRIVER', 'typesafe'),

    'drivers' => [
        'typesafe' => [
            'api_key' => env('TYPESAFE_API_KEY'),
            'base_url' => env('TYPESAFE_BASE_URL', 'https://api.typesafe.ai/v1'),
            'model' => env('TYPESAFE_MODEL', 'jev-latest'),
            'timeout' => 60,
            // Pauses (ms) before retrying a batch that hit a rate limit or a server error.
            'retry_delays' => [500, 2000, 5000],
        ],
    ],

    /*
    | Rows scoring at or above this probability pass the filter.
    */
    'threshold' => 0.8,

    /*
    | Above this many rows without a cached score, the filter doesn't run on its
    | own: it tells the user how many rows it would send and offers to narrow
    | the table down or to run anyway. Cached rows don't count.
    */
    'max_unscored_rows' => (int) env('VIBEFILTER_MAX_UNSCORED_ROWS', 1000),

    /*
    | Rows sent to the driver in one request, and requests sent in parallel.
    */
    'batch_size' => 100,
    'concurrency' => 10,

];
