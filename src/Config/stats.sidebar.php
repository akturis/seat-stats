<?php

return [
    'stats' => [
        'name' => 'Statistics',
        'label' => 'stats::stats.label',
        'icon' => 'fa-bar-chart',
        'route_segment' => 'stats',
        'entries' => [
            'stats' => [
                'name' => 'Statistics',
                'label' => 'stats::stats.label',
                'icon' => 'fa-bar-chart',
                'route' => 'stats.stats.view',
                'permission' => 'stats.stats.view',
            ],
            'operations' => [
                'name' => 'Summary paps',
                'label' => 'stats::stats.operations.label',
                'icon' => 'fa-bar-chart',
                'route' => 'stats.paps.summary',
                'permission' => 'stats.paps.summary',
            ],
            'paps' => [
                'name' => 'Paps',
                'label' => 'stats::stats.paps-label',
                'icon' => 'fa-bar-chart',
                'route' => 'stats.paps.view',
                'permission' => 'stats.paps.view',
            ],
        ],
    ],
];
