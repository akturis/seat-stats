@extends('web::layouts.grids.12')

@section('title', trans('stats::stats.summary'))
@section('page_header', trans('stats::stats.summary'))

@push('head')
	<script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-3.3.1.js"></script>
	<script type="text/javascript" language="javascript" src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js"></script>
	<script type="text/javascript" language="javascript" src="https://cdn.datatables.net/rowgroup/1.1.1/js/dataTables.rowGroup.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>
@endpush

@section('full')
  <div class="nav-tabs-custom">
    <ul class="nav nav-tabs pull-right bg-gray">
      <li><a href="#tab2" data-toggle="tab">{{ trans('stats::stats.summary-char-pvp') }}</a></li>
      <li><a href="#tab3" data-toggle="tab">{{ trans('stats::stats.summary-char-bounty') }}</a></li>
      <li><a href="#tab1" data-toggle="tab">{{ trans('stats::stats.summary-char-mission') }}</a></li>
      <li class="active"><a href="#tab4" data-toggle="tab">{{ trans('stats::stats.summary-corp-bounty') }}</a></li>
      <li class="pull-left header">
        <i class="fa fa-line-chart"></i> {{ trans('stats::stats.summary-live') }}
      </li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane" id="tab1">
        <div class="row">
            <div class="col-sm-10">
              <select class="form-control" style="width: 25%" id="corpspinner">
                <option selected disabled>{{ trans('stats::stats.choose-corp') }}</option>
                <option value="0">{{ trans('stats::stats.all-corps') }}</option>
              </select>
            </div>
            <div class="col-sm-2 text-right"><a class="fa fa-refresh" id="reload" href="#" value=1></a></div>
        </div>
        <table class="table table-striped" id='livenumbers'>
          <thead>
          <tr>
            <th>{{ trans('stats::stats.name') }}</th>
            <th>{{ trans('stats::stats.main') }}</th>
            <th>{{ trans('stats::stats.corp') }}</th>
            <th>{{ trans('stats::stats.mission-corp-reward') }}</th>
            <th>{{ trans('stats::stats.mission-corp-reward') }}</th>
            <th>{{ trans('stats::stats.mission-char-reward') }}</th>
            <th title="{{ trans('stats::stats.mission-lp-desc') }}">{{ trans('stats::stats.mission-lp') }}</th>
            <th>{{ trans('stats::stats.mission-count') }}</th>
            <th>{{ trans('stats::stats.kill-count') }}</th>
            <th>{{ trans('stats::stats.paps-count') }}</th>
          </tr>
          </thead>
        </table>
      </div>
      <div class="tab-pane" id="tab2">
        <div class="row">
            <div class="col-sm-10">
              <select class="form-control" style="width: 25%" id="corpspinner">
                <option selected disabled>{{ trans('stats::stats.choose-corp') }}</option>
                <option value="0">{{ trans('stats::stats.all-corps') }}</option>
              </select>
            </div>
            <div class="col-sm-2 text-right"><a class="fa fa-refresh" id="reload" href="#"></a></div>
        </div>
        <table class="table table-striped" id='pvp_numbers'>
          <thead>
          <tr>
            <th>{{ trans('stats::stats.name') }}</th>
            <th>{{ trans('stats::stats.main') }}</th>
            <th>{{ trans('stats::stats.corp') }}</th>
            <th>{{ trans('stats::stats.kill-count') }}</th>
            <th>{{ trans('stats::stats.lose-count') }}</th>
          </tr>
          </thead>
        </table>
      </div>
      <div class="tab-pane" id="tab3">
        <div class="row">
            <div class="col-sm-10">
              <select class="form-control" style="width: 25%" id="corpspinner3">
                <option selected disabled>{{ trans('stats::stats.choose-corp') }}</option>
                <option value="0">{{ trans('stats::stats.all-corps') }}</option>
              </select>
            </div>
            <div class="col-sm-2 text-right"><a class="fa fa-refresh" id="reload" href="#"></a></div>
        </div>
        <table class="table table-striped" id='livebounty'>
          <thead>
          <tr>
            <th>{{ trans('stats::stats.name') }}</th>
            <th>{{ trans('stats::stats.main') }}</th>
            <th>{{ trans('stats::stats.corp') }}</th>
            <th>{{ trans('stats::stats.mission-corp-reward') }}</th>
            <th>{{ trans('stats::stats.mission-corp-reward') }}</th>
            <th>{{ trans('stats::stats.mission-char-reward') }}</th>
            <th>{{ trans('stats::stats.kill-count') }}</th>
            <th>{{ trans('stats::stats.paps-count') }}</th>
          </tr>
          </thead>
        </table>
      </div>
      <div class="tab-pane  active" id="tab4">
        <div class="row">
            <div class="col-sm-10">
              <select class="form-control" style="width: 25%" id="corpspinner4">
                <option selected disabled>{{ trans('stats::stats.choose-corp') }}</option>
                <option value="0">{{ trans('stats::stats.all-corps') }}</option>
              </select>
            </div>
            <div class="col-sm-2 text-right"><a class="fa fa-refresh" id="reload" href="#"></a></div>
        </div>
        <table class="table table-striped" id='corpbounty'>
          <thead>
          <tr>
            <th>{{ trans('stats::stats.corp') }}</th>
            <th>{{ trans('stats::stats.bounty-corp-reward') }}</th>
            <th>{{ trans('stats::stats.bounty-corp-reward') }}</th>
            <th>{{ trans('stats::stats.mission-corp-reward') }}</th>
          </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>



@endsection

@push('javascript')
  @include('web::includes.javascript.id-to-name')
  <script type="application/javascript">
      table = $('#livenumbers').DataTable( {
          "pageLength": 50,
          responsive: true,
          processing: true,
          serverside: true,
          "deferRender": true,
          "ajax": {
            url: '/stats/stats/',
            data: function (d) {
                d.reload = 0;
                id = $('#corpspinner').find(":selected").val();
                if(id>0) {
                    d.corp_id = id;
                }
            }
          },
          "columns": [
            {data: 'name', name: 'name'},
            {data: 'main_character', name: 'main_character'},
            {data: 'corporation_name', name: 'corporation_name'},
            {data: 'corporation', name: 'corporation',visible:false},
            {data: 'bounties_format', name: 'bounties_format',type: 'formatted-num'},
            {data: 'bounties_char_format', name: 'bounties_char_format',type: 'formatted-num'},
            {data: 'lp', name: 'lp',type: 'formatted-num'},
            {data: 'missions', name: 'missions'},
            {data: 'kills', name: 'kills'},
            {data: 'paps', name: 'paps'}
          ],
        "order": [[ 4, "desc" ]],
        "drawCallback": function () {
                table = this.api().data();
                var corp = this.api()
                .column( 3 )
                .data()
                .unique().toArray();
                for (var i = 0; i < corp.length; i++) {
                        var _corp = corp[i].split(',');
                        var o = $("<option/>", {value: _corp[0], text: _corp[1]});
                        if(!$('#corpspinner').find("option[value='" + _corp[0] + "']").length) {
                            $('#corpspinner').append(o);
//                        }
                    }
                }
                $("img").unveil(100);
                ids_to_names();
                $('[data-toggle="tooltip"]').tooltip();
              }
      } );

      table3 = $('#livebounty').DataTable( {
          "pageLength": 50,
          responsive: true,
          processing: true,
          serverside: true,
          "deferRender": true,
          "ajax": {
            url: '/stats/stats/',
            data: function (d) {
                d.reload = 0;
                d.ref_type = 3;
                id = $('#corpspinner3').find(":selected").val();
                if(id>0) {
                    d.corp_id = id;
                }
            }
          },
          "columns": [
            {data: 'name', name: 'name'},
            {data: 'main_character', name: 'main_character'},
            {data: 'corporation_name', name: 'corporation_name'},
            {data: 'corporation', name: 'corporation',visible:false},
            {data: 'bounties_format', name: 'bounties_format',type: 'formatted-num'},
            {data: 'bounties_char_format', name: 'bounties_char_format',type: 'formatted-num'},
            {data: 'kills', name: 'kills'},
            {data: 'paps', name: 'paps'}
          ],
        "order": [[ 4, "desc" ]],
        "drawCallback": function () {
                var corp = this.api()
                .column( 3 )
                .data()
                .unique().toArray();
                for (var i = 0; i < corp.length; i++) {
                        var _corp = corp[i].split(',');
                        var o = $("<option/>", {value: _corp[0], text: _corp[1]});
                        if(!$('#corpspinner3').find("option[value='" + _corp[0] + "']").length) {
                            $('#corpspinner3').append(o);
//                        }
                    }
                }
                $("img").unveil(100);
                ids_to_names();
                $('[data-toggle="tooltip"]').tooltip();
              }
      } );

      table4 = $('#corpbounty').DataTable( {
          "pageLength": 10,
          responsive: true,
          processing: true,
          serverside: true,
          "deferRender": true,
          "ajax": {
            url: '/stats/stats/',
            data: function (d) {
                d.reload = 0;
                d.ref_type = 4;
                id = $('#corpspinner4').find(":selected").val();
                if(id>0) {
                    d.corp_id = id;
                }
            }
          },
          "columns": [
            {data: 'corporation_name', name: 'corporation_name'},
            {data: 'corporation', name: 'corporation',visible:false},
            {data: 'bounties_format', name: 'bounties_format',type: 'formatted-num'},
            {data: 'bounties_mission_format', name: 'bounties_mission_format',type: 'formatted-num'},
          ],
        "order": [[ 2, "desc" ]],
        "drawCallback": function () {
                var corp = this.api()
                .column( 1 )
                .data()
                .unique().toArray();
                for (var i = 0; i < corp.length; i++) {
                        var _corp = corp[i].split(',');
                        var o = $("<option/>", {value: _corp[0], text: _corp[1]});
                        if(!$('#corpspinner4').find("option[value='" + _corp[0] + "']").length) {
                            $('#corpspinner4').append(o);
//                        }
                    }
                }
                $("img").unveil(100);
                ids_to_names();
                $('[data-toggle="tooltip"]').tooltip();
              }
      } );


      $('#reload').click(function () {
          table.ajax.url('/stats/stats/').load();
          table3.ajax.url('/stats/stats/').load();
          table4.ajax.url('/stats/stats/').load();
      });

      $('#corpspinner').change(function () {
          table.ajax.url('/stats/stats/').load();
      });

      $('#corpspinner3').change(function () {
          table3.ajax.url('/stats/stats/').load();
      });

      $('#corpspinner4').change(function () {
          table4.ajax.url('/stats/stats/').load();
      });

      $(document).ready(function () {
          $('#corpspinner').select2();
          $('#corpspinner3').select2();
      });

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
	"formatted-num-pre": function ( a ) {
		a = (a === "-" || a === "") ? 0 : a.replace( /[^\d\-\.]/g, "" );
		return parseFloat( a );
	},

	"formatted-num-asc": function ( a, b ) {
		return a - b;
	},

	"formatted-num-desc": function ( a, b ) {
		return b - a;
	}
} );

  </script>
@endpush
