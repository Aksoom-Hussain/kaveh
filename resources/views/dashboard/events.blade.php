@extends('kaveh::layouts.app')
@section('title', 'Events — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem;align-items:flex-start">
    <div>
        <h1 style="margin:0">Events</h1>
        <p class="muted" style="margin:.35rem 0 0">Past {{ $rangeLabel }} · requests, exceptions, jobs, queries</p>
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
    @include('kaveh::partials.events-table', ['events' => $events])
    <div style="margin-top:1rem">{{ $events->links() }}</div>
</div>
@endsection
