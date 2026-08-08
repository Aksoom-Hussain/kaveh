@extends('kaveh::layouts.app')
@section('title', 'Events — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem;align-items:flex-start">
    <div>
        <h1 style="margin:0">Events</h1>
        <p class="muted" style="margin:.35rem 0 0">
            Past {{ $rangeLabel }} · requests, exceptions, jobs, queries
            <span id="live-refreshed-at" style="margin-left:.5rem"></span>
        </p>
    </div>
    <form method="get" class="row">
        <input type="hidden" name="project_id" value="{{ $project?->id }}">
        <input type="hidden" name="range" value="{{ $range }}">
        <select name="type">
            <option value="">All types</option>
            @foreach(['exception','request','query','job','log','custom'] as $type)
                <option value="{{ $type }}" @selected(request('type')===$type)>{{ $type }}</option>
            @endforeach
        </select>
        <select name="level">
            <option value="">All levels</option>
            @foreach(['info','warning','error','critical'] as $level)
                <option value="{{ $level }}" @selected(request('level')===$level)>{{ $level }}</option>
            @endforeach
        </select>
        <input name="q" value="{{ request('q') }}" placeholder="Search path / name / uuid" style="min-width:12rem">
        <button type="submit">Filter</button>
    </form>
</div>

<div class="panel" style="overflow-x:auto">
    <div id="events-table-wrap">
        @include('kaveh::partials.events-table', ['events' => $events])
    </div>
    <div style="margin-top:1rem" id="events-pagination">{{ $events->links() }}</div>
</div>
@endsection

@php
    $liveEventsUrl = route('kaveh.live.events', array_filter([
        'project_id' => $project?->id,
        'range' => $range,
        'type' => request('type'),
        'level' => request('level'),
        'q' => request('q'),
    ], static fn ($v) => $v !== null && $v !== ''));
@endphp

@section('scripts')
<script>
(() => {
  const liveUrl = @json($liveEventsUrl);

  async function refreshEvents() {
    try {
      const res = await fetch(liveUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      const wrap = document.getElementById('events-table-wrap');
      if (wrap && data.html) wrap.innerHTML = data.html;
      const stamp = document.getElementById('live-refreshed-at');
      if (stamp && data.refreshed_at) {
        stamp.textContent = '· updated ' + new Date(data.refreshed_at).toLocaleTimeString();
      }
    } catch (e) {
      console.error(e);
    }
  }

  if (window.KavehLive) {
    window.KavehLive.on(refreshEvents);
  }
})();
</script>
@endsection
