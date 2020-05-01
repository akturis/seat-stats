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
use Seat\Kassie\Calendar\Models\Tag;
use Seat\Services\Repositories\Corporation\Members;
use Illuminate\Http\Request;
use DataTables;

class PapsController extends Controller
{
    use StatsHelper;

    public function getPapsView(Request $request)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();
        $today = carbon();
        
        $period = $request->has('period')?(int)$request->get('period'):2;
        $tag = $request->has('tag')?$request->get('tag'):null;
        $main = $request->has('main')?(int)$request->get('main'):1;
        $character_id = $request->get('character_id');
        if(empty($character_id))
            $user = auth()->user();
        else
            $user = User::where('id',$character_id)->first();
            
        $character_ids = array();
        
        if($main) $character_ids = $user->associatedCharacterIds();
        else $character_ids = array($character_id);
        
        $paps = Pap::query()
            ->with('user')
            ->has('operation')
            ->select('character_id','invTypes.groupID','invTypes.typeID','invTypes.typeName','invGroups.groupName')
            ->selectRaw('count(*) as operations')
            ->selectRaw('sum(value) as paps')
            ->leftJoin('invTypes',
            'kassie_calendar_paps.ship_type_id', '=',
            'invTypes.typeID')
            ->leftJoin('invGroups',
            'invGroups.groupID', '=',
            'invTypes.groupID')
            ->whereIn('character_id', $character_ids)
            ->whereHas('operation.tags',function($query)use($tag){
//                dd($query,$_tag);
                if($tag) $query->whereIn('id',$tag);
            })
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
                    case 4:
                        $query->where('join_time','>',carbon()->subDays(30));
                        break;
                    case 5:
                        $query->where('join_time','>',carbon()->subDays(90));
                        break;
                }
            })
            ->groupBy('groupID','typeID','character_id');

        if (! request()->ajax()) {
            return view('stats::paps');
        }
        
        $datatable =  DataTables::eloquent($paps)
                ->addColumn('name', function ($row) {
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
                    return view('stats::partials.ship', compact('row','type_id'));
                })                
                ->addColumn('group', function ($row) {
                    $group_id = $row->groupID;
                    return view('stats::partials.group', compact('row','group_id'));
                })
                ->editColumn('operations', function ($row) {
                    return view('stats::partials.paps', compact('row'));
                })
                ->addColumn('paps', function ($row) {
                    $paps = number($row->paps);
                    return view('stats::paps.partials.paps', compact('paps'));
                })
                ->rawColumns(['group', 'ship', 'name', 'paps','operations'])
                ->make(true)
                ;
//            dd($period,$datatable,$paps->get());
        return $datatable;
    }
    
    public function getOperationsView(Request $request)
    {
        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();
        $today = carbon();

        $period = $request->has('period')?(int)$request->get('period'):2;
        $tag = $request->has('tag')?$request->get('tag'):null;
        $character_id = $request->get('character_id');
        $ship_type_id = $request->get('ship_type_id');

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
            ->whereHas('operation.tags',function($query)use($tag){
//                dd($query,$_tag);
                if($tag) $query->whereIn('id',$tag);
            })
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
                    case 4:
                        $query->where('join_time','>',carbon()->subDays(30));
                        break;
                    case 5:
                        $query->where('join_time','>',carbon()->subDays(90));
                        break;
                }
            })
            ->groupBy('operation_id')->get()->pluck('operation_id');

        $operations = Operation::query()
            ->whereIn('id',$paps);
       if ( request()->ajax()) {
            $datatable =  DataTables::eloquent($operations)
                ->addColumn('tag', function ($row) {
                    //dd(compact('row'));
                    return view('calendar::operation.includes.tags', ['op' => $row]);
                })                
		->addColumn('paps', function($row){
		    return $row->tags->max('quantifier');
		})
                ->rawColumns(['title', 'tag'])
                ->make(true)
                ;
//                dd($datatable);
            return $datatable;
        }
    }

    public function getPapsSummaryView(Request $request)
    {

        $tags = Tag::all();

        if (! request()->ajax()) {
            return view('stats::paps.summary',compact('tags'));
        }

        $start_date = carbon()->startOfMonth()->toDateString();
        $end_date = carbon()->endOfMonth()->toDateString();
        $today = carbon();
        
        $period = $request->has('period')?(int)$request->get('period'):2;
        $tag = $request->has('tag')?$request->get('tag'):null;
        $main = $request->get('main')?:0;

        if(!$main) $grouped = 'character_id';
        else $grouped = 'main_character_id';

        $paps = Pap::has('user')
            ->with('operation')
            ->with('operation.tags')
            ->has('operation')
            ->select('character_id','kassie_calendar_paps.operation_id')
            ->selectraw('cast(user_settings.value AS UNSIGNED INTEGER) as main_character_id,sum(kassie_calendar_paps.value) as paps')
            ->join('users','kassie_calendar_paps.character_id','users.id')
            ->join('groups','groups.id','users.group_id')
            ->join('user_settings','user_settings.group_id','groups.id')
            ->where('user_settings.name','main_character_id')
            ->whereHas('operation.tags',function($query)use($tag){
//                dd($query,$_tag);
                if($tag) $query->whereIn('id',$tag);
            })
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
                    case 4:
                        $query->where('join_time','>',carbon()->subDays(30));
                        break;
                    case 5:
                        $query->where('join_time','>',carbon()->subDays(90));
                        break;
                }
            })
            ->groupby($grouped,'kassie_calendar_paps.operation_id')
            ->orderByRaw('sum(kassie_calendar_paps.value) desc')
            ->get()
            ->groupBy([
                $grouped,
                'operation.id'
            ])
            ->map(function($row,$key){
                $row->character_id = $key;
                $row->main_character_id = $key;
                $ops = array();
                $tags = array();
                foreach($row as $key => $op) {
                    $ops[$key] =  $op[0]->operation;
                    foreach($op[0]->operation->tags as $key => $tag) {
                        $tags[$tag->id] = $tag;
                    }
                }
                $row->ops = $ops;
                $row->tags = $tags;
                return $row;
            })

            ;
//       dd($paps);

        $datatable =  DataTables::collection($paps)
                ->addColumn('name', function ($row) {
                    $character_id = $row->character_id;
                    $character = CharacterInfo::find($character_id) ?: $character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('main_character', function ($row) {
                    $character_id = $row->main_character_id;
                    $character = CharacterInfo::find($character_id) ?: $character_id;
                    return view('web::partials.character', compact('character', 'character_id'));
                })
                ->addColumn('paps', function ($row) {
                    $paps = number($row->sum(function($items){
                        return $items->sum('paps');
                    }));
                    $character_id = $row->main_character_id;
                    return view('stats::paps.partials.paps', compact('character_id','paps'));
                })
                ->addColumn('tag', function ($row)  {
                    return view('stats::paps.partials.tags', ['tags' => $row->tags]);
                })                
                
                ->rawColumns(['name','main_character','tag', 'paps'])
                ->only(['name','main_character','tag', 'paps'])
                ->make(true)
                ;
//            dd($datatable);
        return $datatable;
    }
    
    public function getTagsView(Request $request)
    {
        return '';
    }
    
}

