<?php

Route::group([
    'namespace' => 'Seat\Akturis\Stats\Http\Controllers',
    'prefix' => 'stats'
], function () {
    Route::group([
        'middleware' => ['web', 'auth', 'locale'],
    ], function () {
        Route::get('/stats/', [
            'as'   => 'stats.stats.view',
            'uses' => 'StatsController@getStatsView',
            'middleware' => 'bouncer:stats.stats.view'
        ]);

        Route::get('/paps/',
        [
            'as'   => 'stats.paps.view',
            'uses' => 'PapsController@getPapsView',
            'middleware' => 'bouncer:stats.paps.view'
        ]);

        Route::get('/paps/operations/', [
            'as'   => 'stats.paps.operations',
            'uses' => 'PapsController@getOperationsView',
            'middleware' => 'bouncer:stats.paps.view'
        ]);

        Route::get('/summary/',
        [
            'as'   => 'stats.paps.summary',
            'uses' => 'PapsController@getPapsSummaryView',
            'middleware' => 'bouncer:stats.paps.summary'
        ]);

        Route::get('/tags/',
        [
            'as'   => 'stats.tags',
            'uses' => 'PapsController@getTagsView',
            'middleware' => 'bouncer:stats.paps.view'
        ]);

        Route::post('/settings', [
            'as'   => 'stats.savesettings',
            'uses' => 'StatsController@saveStatsSettings',
            'middleware' => 'bouncer:stats.settings'
        ]);

        Route::get('/user/{id}', [
            'as'   => 'stast.user',
            'uses' => 'StatsController@getUserStats',
            'middleware' => 'bouncer:stats.stats.view'
        ]);

    });
});
