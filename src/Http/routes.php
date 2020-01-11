<?php

Route::group([
    'namespace' => 'Akturis\Seat\Stats\Http\Controllers',
    'prefix' => 'stats'
], function () {
    Route::group([
        'middleware' => ['web', 'auth'],
    ], function () {
        Route::get('/', [
            'as'   => 'stats.view',
            'uses' => 'StatsController@getStastView',
            'middleware' => 'bouncer:stats.view'
        ]);

        Route::get('/alliance/{alliance_id}', [
            'as'   => 'stats.allianceview',
            'uses' => 'StatsController@getStatsView',
            'middleware' => 'bouncer:stats.view'
        ]);

        Route::get('/settings', [
            'as'   => 'stats.settings',
            'uses' => 'StatsController@getStatsSettings',
            'middleware' => 'bouncer:stats.settings'
        ]);

        Route::post('/settings', [
            'as'   => 'stats.savesettings',
            'uses' => 'StatsController@saveStatsSettings',
            'middleware' => 'bouncer:stats.settings'
        ]);

        Route::get('/user/{id}', [
            'as'   => 'stast.user',
            'uses' => 'StatsController@getUserStats',
            'middleware' => 'bouncer:stats.view'
        ]);

    });
});
