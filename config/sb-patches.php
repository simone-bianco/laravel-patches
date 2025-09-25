<?php

return [
    /**
     * these callbacks will be executed
     * before and after each patch class execution.
     * They must be fully qualified class names with an __invoke method or will be ignored.
     */
    'callbacks' => [
        'up' => [
            'before' => null,
            'after' => null,
        ],
        'down' => [
            'before' => null,
            'after' => null,
        ],
    ]
];
