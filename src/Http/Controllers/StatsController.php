<?php

namespace Seat\Akturis\Stats\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Seat\Web\Http\Controllers\Controller;
use Seat\Eveapi\Models\Alliances\Alliance;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Corporation\CorporationMemberTracking;
use Seat\Services\Repositories\Character\MiningLedger as CharacterLedger;
use Seat\Services\Repositories\Corporation\Ledger;
use Seat\Services\Repositories\Corporation\MiningLedger;
use Seat\Akturis\Stats\Validation\ValidateSettings;
use Seat\Akturis\Stats\Helpers\StatsHelper;
use Seat\Eveapi\Models\Universe\UniverseName;
use Illuminate\Support\Facades\Cache;
use Seat\Web\Models\User;
use Seat\Kassie\Calendar\Models\Pap;
use Seat\Kassie\Calendar\Models\Operation;
use Seat\Services\Repositories\Corporation\Members;
use Illuminate\Http\Request;
use DataTables;

class StatsController extends Controller
{
    use MiningLedger, Ledger, CharacterLedger, StatsHelper;

    public function getStatsView(Request $request)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();

        $reload = $request->has("reload")?$request->get("reload"):0;
        $corp_id = $request->has("corp_id")?$request->get("corp_id"):null;
        
        if($request->has("ref_type")) {
            switch($request->get("ref_type")) {
                case 3:
                    $ref_type = ['bounty_prizes','bounty_prize'];
                    break;
                case 4:
                    $ref_type = ['bounty_prizes','bounty_prize','agent_mission_reward'];
                    break;
                default:
                    $ref_type = ['agent_mission_reward'];
            }
        } else {
            $ref_type = ['agent_mission_reward'];
        }

        $corporation_ids = array_get(auth()->user()->getAffiliationMap(), 'corp');
        $filter = 'stats.stats.view';
        $corporation_ids = array_keys(array_filter($corporation_ids, function($k) {
            return in_array('stats.stats.view',$k);
        }, ARRAY_FILTER_USE_BOTH));
        
        if(!auth()->user()->hasSuperUser()) {
            if(setting("corporation_ids_".auth()->user()->group_id) != $corporation_ids) {
                Cache::forget('stats_'.auth()->user()->group_id);
                setting(["corporation_ids_".auth()->user()->group_id, $corporation_ids],true);
            }
        }
        
        $tracking[] = null;

        switch($request->get("ref_type")) {
            case 4:
                $tracking = DB::table('corporation_wallet_journals')
                    ->select('corporation_id')
                    ->selectRaw("SUM(CASE WHEN ref_type = 'bounty_prizes' or ref_type = 'bounty_prize' THEN amount END) as bounties")
                    ->selectRaw("SUM(CASE WHEN ref_type = 'agent_mission_reward' THEN amount END) as bounties_mission")
                    ->whereIn('ref_type', $ref_type)
                    ->whereBetween('date', [$start_date, $end_date])
                    ->groupBy('corporation_id')
                    ->orderBy('bounties','desc');
                break;
            default:
                $bounty_stats = DB::table('corporation_wallet_journals')
                    ->select('corporation_wallet_journals.second_party_id as character_id')
                    ->selectRaw('SUM(amount) as bounties')
                    ->selectRaw('count(id) as missions')
                    ->whereIn('ref_type', $ref_type)
                    ->whereBetween('date', [$start_date, $end_date])
                    ->groupBy('character_id');
        
                $bounty_stats_char = DB::table('character_wallet_journals')
                    ->select('character_wallet_journals.character_id')
                    ->selectRaw('SUM(amount) as bounties_char')
                    ->whereIn('ref_type', $ref_type)
                    ->whereBetween('date', [$start_date, $end_date])
                    ->groupBy('character_id');
                
                $kill_stats = DB::table('character_killmails')
                    ->select('character_killmails.character_id')
                    ->selectRaw('count(killmail_attackers.killmail_id) as kills')
                    ->join('killmail_details', 'character_killmails.killmail_id', '=', 'killmail_details.killmail_id')
                    ->join('killmail_attackers', 'character_killmails.killmail_id', '=', 'killmail_attackers.killmail_id')
                    ->whereBetween('killmail_details.killmail_time', [$start_date, $end_date])
                    ->groupBy('character_id');
        
                $paps_stats = DB::table('kassie_calendar_paps')
                    ->select('kassie_calendar_paps.character_id')
                    ->selectRaw('sum(value) as paps')
                    ->whereBetween('join_time', [$start_date, $end_date])
                    ->groupBy('character_id');
                $tracking = CorporationMemberTracking::query()
                    ->with('user')
                    ->select('corporation_member_trackings.corporation_id', 'corporation_member_trackings.character_id','bounties','bounties_char','missions','kills','paps')
                    ->leftJoin(DB::raw('(' . $bounty_stats->toSql() . ') bounty_stats'), function ($join) {
                        $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats.character_id');
                    })
                    ->leftJoin(DB::raw('(' . $bounty_stats_char->toSql() . ') bounty_stats_char'), function ($join) {
                        $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats_char.character_id');
                    })
                    ->leftJoin(DB::raw('(' . $kill_stats->toSql() . ') kill_stats'), function ($join) {
                        $join->on('corporation_member_trackings.character_id', '=', 'kill_stats.character_id');
                    })
                    ->leftJoin(DB::raw('(' . $paps_stats->toSql() . ') paps_stats'), function ($join) {
                        $join->on('corporation_member_trackings.character_id', '=', 'paps_stats.character_id');
                    })
                    ->mergeBindings($bounty_stats)
                    ->mergeBindings($bounty_stats_char)
                    ->mergeBindings($kill_stats)
                    ->mergeBindings($paps_stats)
                    ->where('bounty_stats.bounties','>','10000')
                    ->where(function ($where) use ($corporation_ids){
                        if(!auth()->user()->hasSuperUser()) {
                            $where->whereIn('corporation_member_trackings.corporation_id',$corporation_ids);
                        }
                    })
                    ->where(function ($where) use ($corp_id){
                        if($corp_id) $where->where('corporation_member_trackings.corporation_id',$corp_id);
                    })
                    ->groupBy('character_id', 'corporation_id')
                    ->orderBy('bounties','desc');
        }

        if (! request()->ajax()) {
            return view('stats::summary');
        }

        switch($request->get("ref_type")) {
            case 4:
                $datatable =  DataTables::of($tracking)
                ->addColumn('corporation_name', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return view('web::partials.corporation', compact('corporation','corporation_id'));
                })
                ->addColumn('corporation', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return $corporation_id.','.$corporation->name;
                })
                ->editColumn('bounties', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('bounties_format', function ($row) {
                    return number($row->bounties);
                })
                ->editColumn('bounties_mission', function ($row) {
                    return number($row->bounties_mission);
                })
                ->addColumn('bounties_mission_format', function ($row) {
                    return number($row->bounties_mission);
                })
                ->only(['corporation_name', 'corporation',
                        'bounties','bounties_format','bounties_mission','bounties_mission_format'])
                ->rawColumns(['corporation_name']);
                break;
            default:
                $datatable =  DataTables::of($tracking)
                ->addColumn('name', function ($row) {
                    $character_id = $row->character_id;
                    $character = CharacterInfo::find($row->character_id) ?: $row->character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('corporation_name', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return view('web::partials.corporation', compact('corporation','corporation_id'));
                })
                ->addColumn('corporation', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return $corporation_id.','.$corporation->name;
                })
                ->editColumn('bounties', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('bounties_char_format', function ($row) {
                    return number($row->bounties_char);
                })
                ->addColumn('bounties_format', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('lp', function ($row) {
                    return number($row->bounties_char==0?$row->bounties/200:$row->bounties_char/200);
                })
                ->addColumn('main_character', function ($row) {
                    
                    $user = $row->user;
                    $main_character_id = null;
                    
                    if (optional($user)->group)
                        $main_character_id = optional($user->group->main_character)->character_id ?: null;
                    
                    $character = CharacterInfo::find($main_character_id) ?: null;
                        return view('web::partials.character', compact('character'));
                })
                ->only(['name', 'corporation_name', 'corporation', 'main_character',
                        'bounties','bounties_char_format','bounties_format','lp',
                        'missions','kills','paps'])
                ->rawColumns(['name', 'corporation_name', 'main_character']);
        }

        if($corp_id) {
            $datatable = $datatable->make(true);
        } else {
//            $datatable = $datatable->make(true);
//             Cache::forget('stats_'.($request->get("ref_type")?:0).auth()->user()->group_id);
            $datatable = Cache::remember('stats_'.($request->get("ref_type")?:0).auth()->user()->group_id, 90, function () use ($datatable) {
                return $datatable->make(true);
            });
        }

        return $datatable;
    }

    public function getCorpStatsView(Request $request)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();

        $reload = $request->has("reload")?$request->get("reload"):0;
        $corp_id = $request->has("corp_id")?$request->get("corp_id"):null;
        
        if($request->has("ref_type")) {
            switch($request->get("ref_type")) {
                case 3:
                    $ref_type = ['bounty_prizes','bounty_prize'];
                    break;
                case 4:
                    $ref_type = ['bounty_prizes','bounty_prize'];
                    break;
                default:
                    $ref_type = ['agent_mission_reward'];
            }
        } else {
            $ref_type = ['agent_mission_reward'];
        }

        $corporation_ids = array_get(auth()->user()->getAffiliationMap(), 'corp');
        $filter = 'stats.stats.view';
        $corporation_ids = array_keys(array_filter($corporation_ids, function($k) {
            return in_array('stats.stats.view',$k);
        }, ARRAY_FILTER_USE_BOTH));
        
        if(!auth()->user()->hasSuperUser()) {
            if(setting("corporation_ids_".auth()->user()->group_id) != $corporation_ids) {
                Cache::forget('stats_'.auth()->user()->group_id);
                setting(["corporation_ids_".auth()->user()->group_id, $corporation_ids],true);
            }
        }
        
        $tracking[] = null;

        $bounty_stats = DB::table('corporation_wallet_journals')
            ->select('corporation_wallet_journals.second_party_id as character_id')
            ->selectRaw('SUM(amount) as bounties')
            ->selectRaw('count(id) as missions')
            ->whereIn('ref_type', $ref_type)
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');

        $bounty_stats_char = DB::table('character_wallet_journals')
            ->select('character_wallet_journals.character_id')
            ->selectRaw('SUM(amount) as bounties_char')
            ->whereIn('ref_type', $ref_type)
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');
        
        $kill_stats = DB::table('character_killmails')
            ->select('character_killmails.character_id')
            ->selectRaw('count(killmail_attackers.killmail_id) as kills')
            ->join('killmail_details', 'character_killmails.killmail_id', '=', 'killmail_details.killmail_id')
            ->join('killmail_attackers', 'character_killmails.killmail_id', '=', 'killmail_attackers.killmail_id')
            ->whereBetween('killmail_details.killmail_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $paps_stats = DB::table('kassie_calendar_paps')
            ->select('kassie_calendar_paps.character_id')
            ->selectRaw('sum(value) as paps')
            ->whereBetween('join_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $tracking = CorporationMemberTracking::query()
            ->with('user')
            ->select('corporation_member_trackings.corporation_id', 'corporation_member_trackings.character_id','bounties','bounties_char','missions','kills','paps')
            ->leftJoin(DB::raw('(' . $bounty_stats->toSql() . ') bounty_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $bounty_stats_char->toSql() . ') bounty_stats_char'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats_char.character_id');
            })
            ->leftJoin(DB::raw('(' . $kill_stats->toSql() . ') kill_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'kill_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $paps_stats->toSql() . ') paps_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'paps_stats.character_id');
            })
            ->mergeBindings($bounty_stats)
            ->mergeBindings($bounty_stats_char)
            ->mergeBindings($kill_stats)
            ->mergeBindings($paps_stats)
            ->where('bounty_stats.bounties','>','10000')
            ->where(function ($where) use ($corporation_ids){
                if(!auth()->user()->hasSuperUser()) {
                    $where->whereIn('corporation_member_trackings.corporation_id',$corporation_ids);
                }
            })
            ->where(function ($where) use ($corp_id){
                if($corp_id) $where->where('corporation_member_trackings.corporation_id',$corp_id);
            })
            ->groupBy('character_id', 'corporation_id')
            ->orderBy('bounties','desc');

        if (! request()->ajax()) {
            return view('stats::summary');
        }

        $datatable =  DataTables::of($tracking)
        ->addColumn('name', function ($row) {
            $character_id = $row->character_id;
            $character = CharacterInfo::find($row->character_id) ?: $row->character_id;
            return view('web::partials.character', compact('character', 'character_id'));
        })
        ->addColumn('corporation_name', function ($row) {
            $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
            $corporation_id = $row->corporation_id;
            return view('web::partials.corporation', compact('corporation','corporation_id'));
        })
        ->addColumn('corporation', function ($row) {
            $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
            $corporation_id = $row->corporation_id;
            return $corporation_id.','.$corporation->name;
        })
        ->editColumn('bounties', function ($row) {
            return number($row->bounties);
        })
        ->addColumn('bounties_char_format', function ($row) {
            return number($row->bounties_char);
        })
        ->addColumn('bounties_format', function ($row) {
            return number($row->bounties);
        })
        ->addColumn('lp', function ($row) {
            return number($row->bounties_char==0?$row->bounties/200:$row->bounties_char/200);
        })
        ->addColumn('main_character', function ($row) {
            
            $user = $row->user;
            $main_character_id = null;
            
            if (optional($user)->group)
                $main_character_id = optional($user->group->main_character)->character_id ?: null;
            
            $character = CharacterInfo::find($main_character_id) ?: null;
                return view('web::partials.character', compact('character'));
        })
        ->only(['name', 'corporation_name', 'corporation', 'main_character',
                'bounties','bounties_char_format','bounties_format','lp',
                'missions','kills','paps'])
        ->rawColumns(['name', 'corporation_name', 'main_character']);


        if($corp_id) {
            $datatable = $datatable->make(true);
        } else {
            $datatable = Cache::remember('stats_'.($request->get("ref_type")?:0).auth()->user()->group_id, 90, function () use ($datatable) {
                return $datatable->make(true);
            });
        }
        return $datatable;
    }

    public function getPapsView($period = 2)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();
        $today = carbon();

        $paps = Pap::query()
            ->with('user')
            ->with('operation')
            ->select('character_id','invTypes.groupID','invTypes.typeID','invTypes.typeName','invGroups.groupName')
            ->selectRaw('count(*) as operations')
            ->selectRaw('sum(value) as paps')
            ->leftJoin('invTypes',
            'kassie_calendar_paps.ship_type_id', '=',
            'invTypes.typeID')
            ->leftJoin('invGroups',
            'invGroups.groupID', '=',
            'invTypes.groupID')
            ->whereIn('character_id', auth()->user()->associatedCharacterIds())
            ->where(function($query) use ($today,$period) {
                switch($period) {
                    case 1:
                        $query->where('year',$today->week);
                        break;
                    case 2:
                        $query->where('month',$today->month)
                              ->where('year',$today->year);
                        break;
                    case 3:
                        $query->where('year',$today->year);
                        break;
                }
            })
            ->groupBy('groupID','typeID','character_id');

//            ->first();

//        dd($tracking);

//        if (! request()->ajax()) {
//            return view('stats::summary');
//        }
        if (! request()->ajax()) {
            return view('stats::paps');
        }
        
//        Cache::forget('stats_'.auth()->user()->group_id);
        $datatable =  DataTables::eloquent($paps)
                ->addColumn('name', function ($row) {
//                    dd($row->user->character->character_id);
                    $character_id = $row->user->character->character_id;
                    $character = $row->user->character ?: $row->character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('corporation', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return view('web::partials.corporation', compact('corporation','corporation_id'));
                })
                ->addColumn('ship', function ($row) {
                    $type_id = $row->typeID;
                    //dd(compact('row'));
                    return view('stats::partials.ship', compact('row','type_id'));
                })                
                ->addColumn('group', function ($row) {
                    $group_id = $row->groupID;
                    //dd(compact('row'));
                    return view('stats::partials.group', compact('row','group_id'));
                })
                ->editColumn('operations', function ($row) {
                    //dd(compact('row'));
                    return view('stats::partials.paps', compact('row'));
                })
                ->addColumn('paps', function ($row) {
                    //dd(compact('row'));
                    return number($row->paps);
                })
                ->rawColumns(['group', 'ship', 'name', 'operations'])
                ->make(true)
                ;
//                ->make(true);
//            dd($datatable);
        return $datatable;
    }
    
    public function getOperationsView(int $ship_type_id = 0, $character_id, $period = 3)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();
        $today = carbon();

        $paps = Pap::query()
            ->with('operation')
            ->select('operation_id')
            ->leftJoin('invTypes',
            'kassie_calendar_paps.ship_type_id', '=',
            'invTypes.typeID')
            ->leftJoin('invGroups',
            'invGroups.groupID', '=',
            'invTypes.groupID')
            ->where('character_id', $character_id)
            ->where('invTypes.typeID',$ship_type_id)
            ->where(function($query) use ($today,$period) {
                switch($period) {
                    case 1:
                        $query->where('year',$today->week);
                        break;
                    case 2:
                        $query->where('month',$today->month)
                              ->where('year',$today->year);
                        break;
                    case 3:
                        $query->where('year',$today->year);
                        break;
                }
            })
            ->groupBy('operation_id')->get()->pluck('operation_id');

        $operations = Operation::query()
            ->whereIn('id',$paps);
//        if (! request()->ajax()) {
//            return view('stats::summary');
//        }
 //       if ( request()->ajax()) {
        
    //        Cache::forget('stats_'.auth()->user()->group_id);
            $datatable =  DataTables::eloquent($operations)
                ->addColumn('tag', function ($row) {
                    //dd(compact('row'));
                    return '';
                })                
                ->addColumn('duration', function ($row) {
                    //dd(compact('row'));
                    return '';
                })                
                ->make(true)
                ;
    //                ->make(true);
//                dd($datatable);
            return $datatable;
            
//        }
    }

    public function getStatsAnomView(Request $request)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();

        $reload = $request->has("reload")?$request->get("reload"):0;

        $corporation_ids = array_get(auth()->user()->getAffiliationMap(), 'corp');
        $filter = 'stats.view';
        $corporation_ids = array_keys(array_filter($corporation_ids, function($k) {
            return in_array('stats.view',$k);
        }, ARRAY_FILTER_USE_BOTH));
        
        if(!auth()->user()->hasSuperUser()) {
            if(setting("corporation_ids_".auth()->user()->group_id) != $corporation_ids) {
                Cache::forget('stats_'.auth()->user()->group_id);
                setting(["corporation_ids_".auth()->user()->group_id, $corporation_ids],true);
            }
        }
        
        $tracking[] = null;

        $bounty_stats = DB::table('corporation_wallet_journals')
            ->select('corporation_wallet_journals.second_party_id as character_id')
            ->selectRaw('SUM(amount) as bounties')
            ->selectRaw('count(id) as missions')
            ->whereIn('ref_type', ['bounty_prizes','bounty_prize'])
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');

        $bounty_stats_char = DB::table('character_wallet_journals')
            ->select('character_wallet_journals.character_id')
            ->selectRaw('SUM(amount) as bounties_char')
            ->whereIn('ref_type', ['bounty_prizes','bounty_prize'])
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');
        
        $kill_stats = DB::table('character_killmails')
            ->select('character_killmails.character_id')
            ->selectRaw('count(killmail_attackers.killmail_id) as kills')
            ->join('killmail_details', 'character_killmails.killmail_id', '=', 'killmail_details.killmail_id')
            ->join('killmail_attackers', 'character_killmails.killmail_id', '=', 'killmail_attackers.killmail_id')
            ->whereBetween('killmail_details.killmail_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $paps_stats = DB::table('kassie_calendar_paps')
            ->select('kassie_calendar_paps.character_id')
            ->selectRaw('sum(value) as paps')
            ->whereBetween('join_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $tracking = CorporationMemberTracking::query()
            ->with('user')
            ->select('corporation_member_trackings.corporation_id', 'corporation_member_trackings.character_id','bounties','bounties_char','missions','kills','paps')
            ->leftJoin(DB::raw('(' . $bounty_stats->toSql() . ') bounty_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $bounty_stats_char->toSql() . ') bounty_stats_char'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats_char.character_id');
            })
            ->leftJoin(DB::raw('(' . $kill_stats->toSql() . ') kill_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'kill_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $paps_stats->toSql() . ') paps_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'paps_stats.character_id');
            })
            ->mergeBindings($bounty_stats)
            ->mergeBindings($bounty_stats_char)
            ->mergeBindings($kill_stats)
            ->mergeBindings($paps_stats)
            ->where('bounty_stats.bounties','>','10000')
            ->where(function ($where) use ($corporation_ids){
                if(!auth()->user()->hasSuperUser()) {
                    $where->whereIn('corporation_member_trackings.corporation_id',$corporation_ids);
                }
            })
            ->groupBy('character_id')
            ->orderBy('bounties','desc');

//            ->first();

//        $tracking = $tracking->toSql();
//        dd($tracking);
        if (! request()->ajax()) {
            return view('stats::summary');
        }
        
        if($reload) Cache::forget('stats_anom_'.auth()->user()->group_id);
        $datatable = Cache::remember('stats_anom_'.auth()->user()->group_id, 90, function () use ($tracking) {
            return DataTables::of($tracking)
                ->addColumn('name', function ($row) {
                    $character_id = $row->character_id;
                    $character = CharacterInfo::find($row->character_id) ?: $row->character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('corporation_name', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return view('web::partials.corporation', compact('corporation','corporation_id'));
                })
                ->addColumn('corporation', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
                    $corporation_id = $row->corporation_id;
                    return $corporation_id.','.$corporation->name;
                })
                ->editColumn('bounties', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('bounties_char_format', function ($row) {
                    return number($row->bounties_char);
                })
                ->addColumn('bounties_format', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('lp', function ($row) {
                    return number($row->bounties_char==0?$row->bounties/200:$row->bounties_char/200);
                })
                ->addColumn('main_character', function ($row) {
                    
                    $user = $row->user;
                    $main_character_id = null;
                    
                    if (optional($user)->group)
                        $main_character_id = optional($user->group->main_character)->character_id ?: null;
                    
                    $character = CharacterInfo::find($main_character_id) ?: null;
    
                    return view('web::partials.character', compact('character'));
                })
                ->rawColumns(['name', 'corporation_name', 'main_character'])
                ->make(true);
        });
//            dd($datatable);
        return $datatable;
    }

    public function getStatsViewAjax2(int $corporation_id = 0)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();

        $corporation_ids = array_get(auth()->user()->getAffiliationMap(), 'corp');
        $filter = 'stats.view';
        $corporation_ids = array_keys(array_filter($corporation_ids, function($k) {
            return in_array('stats.view',$k);
        }, ARRAY_FILTER_USE_BOTH));
        
        if(!auth()->user()->hasSuperUser()) {
            if(setting("corporation_ids_".auth()->user()->group_id) != $corporation_ids) {
                Cache::forget('stats_'.auth()->user()->group_id);
                setting(["corporation_ids_".auth()->user()->group_id, $corporation_ids],true);
            }
        }
        
        $tracking[] = null;

        $bounty_stats = DB::table('corporation_wallet_journals')
            ->select('corporation_wallet_journals.second_party_id as character_id')
            ->selectRaw('SUM(amount) as bounties')
            ->selectRaw('count(id) as missions')
            ->whereIn('ref_type', ['agent_mission_reward'])
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');

        $bounty_stats_char = DB::table('character_wallet_journals')
            ->select('character_wallet_journals.character_id')
            ->selectRaw('SUM(amount) as bounties_char')
            ->whereIn('ref_type', ['agent_mission_reward'])
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('character_id');
        
        $kill_stats = DB::table('character_killmails')
            ->select('character_killmails.character_id')
            ->selectRaw('count(killmail_attackers.killmail_id) as kills')
            ->join('killmail_details', 'character_killmails.killmail_id', '=', 'killmail_details.killmail_id')
            ->join('killmail_attackers', 'character_killmails.killmail_id', '=', 'killmail_attackers.killmail_id')
            ->whereBetween('killmail_details.killmail_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $paps_stats = DB::table('kassie_calendar_paps')
            ->select('kassie_calendar_paps.character_id')
            ->selectRaw('COUNT(operation_id) as paps')
            ->whereBetween('join_time', [$start_date, $end_date])
            ->groupBy('character_id');

        $tracking = CorporationMemberTracking::query()
            ->with('user')
            ->select('corporation_member_trackings.corporation_id', 'corporation_member_trackings.character_id','bounties','bounties_char','missions','kills','paps')
            ->leftJoin(DB::raw('(' . $bounty_stats->toSql() . ') bounty_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $bounty_stats_char->toSql() . ') bounty_stats_char'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'bounty_stats_char.character_id');
            })
            ->leftJoin(DB::raw('(' . $kill_stats->toSql() . ') kill_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'kill_stats.character_id');
            })
            ->leftJoin(DB::raw('(' . $paps_stats->toSql() . ') paps_stats'), function ($join) {
                $join->on('corporation_member_trackings.character_id', '=', 'paps_stats.character_id');
            })
            ->mergeBindings($bounty_stats)
            ->mergeBindings($bounty_stats_char)
            ->mergeBindings($kill_stats)
            ->mergeBindings($paps_stats)
            ->where('bounty_stats.bounties','>','10000')
            ->where(function ($where) use ($corporation_ids){
                if(!auth()->user()->hasSuperUser()) {
                    $where->whereIn('corporation_member_trackings.corporation_id',$corporation_ids);
                }
            })
            ->groupBy('character_id', 'corporation_id', 'bounties', 'bounties_char','missions','kills','paps')
            ->orderBy('bounties','desc');

//            ->first();

//        dd($tracking);

        if (! request()->ajax()) {
            return view('stats::ajax');
        }
        
//        Cache::forget('stats_'.auth()->user()->group_id);
        $datatable = Cache::remember('stats_'.auth()->user()->group_id, 90, function () use ($tracking) {
            return DataTables::of($tracking)
                ->addColumn('name', function ($row) {
                    $character_id = $row->character_id;
                    $character = CharacterInfo::find($row->character_id) ?: $row->character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('corporation_name', function ($row) {
                    $corporation = CorporationInfo::find($row->corporation_id) ?: $row->corporation_id;
    
                    return view('web::partials.corporation', compact('corporation'));
                })
                ->editColumn('bounties', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('bounties_char_format', function ($row) {
                    return number($row->bounties_char);
                })
                ->addColumn('bounties_format', function ($row) {
                    return number($row->bounties);
                })
                ->addColumn('lp', function ($row) {
                    return number($row->bounties_char==0?$row->bounties/200:$row->bounties_char/200);
                })
                ->addColumn('main_character', function ($row) {
                    
                    $user = $row->user;
                    $main_character_id = null;
                    
                    if (optional($user)->group)
                        $main_character_id = optional($user->group->main_character)->character_id ?: null;
                    
                    $character = CharacterInfo::find($main_character_id) ?: null;
    
                    return view('web::partials.character', compact('character'));
                })
                ->rawColumns(['name', 'corporation_name', 'main_character'])
                ->only(['name', 'corporation_name', 'main_character',
                        'bounties','bounties_char_format','bounties_format','lp'])
                ->make(true);
        });
//            dd($datatable);
        return $datatable;
    }

    private function getCorporations()
    {
        if (auth()->user()->hasSuperUser()) {
            $corporations = CorporationInfo::orderBy('name')->get();
        } else {
            $corpids = CharacterInfo::whereIn('character_id', auth()->user()->associatedCharacterIds())
                ->select('corporation_id')
                ->get()
                ->toArray();

            $corporations = CorporationInfo::whereIn('corporation_id', $corpids)->orderBy('name')->get();
        }

        return $corporations;
    }

    public function getStatsSettings()
    {
        return view('stats::settings');
    }

    public function saveStatsSettings(ValidateSettings $request)
    {
        setting(["oremodifier", $request->oremodifier], true);
        setting(["oretaxrate", $request->oretaxrate], true);
        setting(["refinerate", $request->refinerate], true);
        setting(["bountytaxrate", $request->bountytaxrate], true);
        setting(["ioremodifier", $request->ioremodifier], true);
        setting(["ioretaxrate", $request->ioretaxrate], true);
        setting(["ibountytaxrate", $request->ibountytaxrate], true);
        setting(["irate", $request->irate], true);
        setting(["pricevalue", $request->pricevalue], true);

        return redirect()->back()->with('success', 'Stats Settings have successfully been updated.');
    }

    public function getUserStats($corporation_id)
    {
        $summary = $this->getMainsStats($corporation_id);

        return $summary;
    }

    public function getPastUserStats($corporation_id, $year, $month)
    {
        $summary = $this->getPastMainsStatsByMonth($corporation_id, $year, $month);

        return $summary;
    }

    public function previousStatsCycle($year, $month)
    {
        $corporations = $this->getCorporations();

        $stats = $this->getCorporationBillByMonth($year, $month)->sortBy('corporation.name');

        $dates = $this->getCorporationStatsMonths($corporations->pluck('corporation_id')->toArray());

        return view('stats::pastbill', compact('stats', 'dates', 'year', 'month'));
    }
}
