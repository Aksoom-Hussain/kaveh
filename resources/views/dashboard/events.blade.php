@extends('kaveh::layouts.app')
@section('title', 'Events — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem">
    <h1 style="margin:0">Events</h1>
    <form method="get" class="row">
        <select name="project_id" onchange="this.form.submit()">
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected($project?->id === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
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
        <input name="q" value="{{ request('q') }}" placeholder="Search name / uuid">
        <button type="submit">Filter</button>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>When</th><th>Type</th><th>Name</th><th>Level</th><th>Env</th><th>Duration</th></tr></thead>
        <tbody>
        @foreach($events as $event)
            <tr>
                <td>{{ optional($event->occurred_at)?->toDateTimeString() }}</td>
                <td><span class="badge">{{ $event->type }}</span></td>
                <td><a href="{{ route('kaveh.events.show', $event) }}">{{ \Illuminate\Support\Str::limit($event->name, 60) }}</a></td>
                <td><span class="badge {{ $event->level }}">{{ $event->level }}</span></td>
                <td>{{ $event->environment }}</td>
                <td>{{ $event->duration_ms ? number_format($event->duration_ms, 1).'ms' : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:1rem">{{ $events->links() }}</div>
</div>
@endsection
