<?php

namespace Seat\Akturis\Stats\Http\Controllers;

use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Character\CharacterStats;
use Seat\Services\Repositories\Eve\EveRepository;
use Seat\Web\Http\Controllers\Controller;


class YearController extends Controller
{
    use EveRepository;

    public function getCharacterYearCharacterChartData(int $character_id)
    {
    }

    public function getCharacterYearCombatChartData(int $character_id)
    {
        $combat = CharacterStats::where('character_id', $character_id)
            ->where('category','isk')
            ->where('year',2017)
            ->first()->only(['stats']);
//        $combat = json_decode($combat['stats'], true);
//        dd($combat);
//        $labels = '';
//        foreach ($combat as $key => $value) {
//            $labels = $labels.$key."\", \"";
//        }
        $labels =  array_keys(json_decode($combat['stats'], true));
        $data = array_values(json_decode($combat['stats'], true));
//        $labels =  implode(', ', array_keys($combat));
//        dd($labels,$combat);
        return response()->json([
            'labels'   => $labels,
            'datasets' => [
                [
                    'data'            => $data,
                ],
            ],
        ]);
    }
    
    public function getCharacterYearView()
    {
        return view('stats::year');
    }
}
