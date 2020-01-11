<?php

return [
    'stats' => [
        'name' => 'SeAT Stats',
        'icon' => 'fa-credit-card',
        'route_segment' => 'stats',
        'permission' => 'stats.view',
        'route' => 'stats.view',
        'entries' => [
            'stats' => [
                'name' => 'Statistics',
                'icon' => 'fa-money',
                'route_segment' => 'stats',
                'route' => 'stats.view',
                'permission' => 'stats.view',
            ],
            'settings' => [
                'name' => 'Settings',
                'icon' => 'fa-gear',
                'route_segment' => 'stats',
                'route' => 'stats.settings',
                'permission' => 'stats.settings',
            ],
        ],
    ],
];
