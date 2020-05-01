@extends('web::layouts.grids.6-6')

@section('title', trans('stats::stats.year'))
@section('page_header', trans('stats::stats.year'))
@section('page_description', trans('stats::stats.dashboard'))

@section('left')

  <div class="row">

    <!-- skills graphs -->
    @if(auth()->user()->name != 'admin')
      <div class="col-md-12 col-sm-6 col-xs-12">

        <div class="box">
          <div class="box-header with-border">
            <h3 class="box-title">
              {!! img('character', auth()->user()->character_id, 64, ['class' => 'img-circle eve-icon small-icon']) !!}
              {{ trans('stats::stats.main_char_skills', ['character_name' => auth()->user()->name]) }}
            </h3>
          </div>
          <div class="box-body">

            <h4 class="box-title">{{ trans('stats::stats.main_char_skills_per_level') }}</h4>
            <div class="chart">
              <canvas id="skills-level" height="230" width="1110"></canvas>
            </div>

            <h4 class="box-title">{{ trans('stats::stats.main_char_skills_coverage') }}</h4>
            <div class="chart">
              <canvas id="skills-coverage" height="600" width="1110"></canvas>
            </div>
          </div>
        </div>

      </div><!-- /.col -->
    @endif

  </div>

@stop

@push('javascript')
  <script type="text/javascript">

    if ($('canvas#skills-level').length)
      $.get("{{ route('stats.year.character', ['character_id' => auth()->user()->character_id]) }}", function (data) {
        new Chart($("canvas#skills-level"), {
          type: 'pie',
          data: data
        });
      });

    if ($('canvas#skills-coverage').length)
      $.get("{{ route('stats.year.combat', ['character_id' => auth()->user()->character_id]) }}", function (data) {
        new Chart($('canvas#skills-coverage'), {
          type   : 'bar',
          data   : data,
          options: {
              responsive: true,
          }
        });
      });
  </script>

@endpush
