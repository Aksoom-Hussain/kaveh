@extends('kaveh::layouts.app')
@section('title', $event->name.' — Kaveh')
@section('content')
<div class="panel">
    <p><a href="{{ route('kaveh.events.index') }}">&larr; Events</a></p>
    <h1>{{ $event->name }}</h1>
    <p>
        <span class="badge">{{ $event->type }}</span>
        <span class="badge {{ $event->level }}">{{ $event->level }}</span>
        <span class="badge">{{ $event->environment }}</span>
    </p>
    <table>
        <tr><th>UUID</th><td><code>{{ $event->uuid }}</code></td></tr>
        <tr><th>Occurred</th><td>{{ optional($event->occurred_at)?->toIso8601String() }}</td></tr>
        <tr><th>Hostname</th><td>{{ $event->hostname }}</td></tr>
        <tr><th>Trace</th><td>{{ $event->trace_id ?: '—' }}</td></tr>
        <tr><th>Duration</th><td>{{ $event->duration_ms ? $event->duration_ms.' ms' : '—' }}</td></tr>
        <tr><th>Tags</th><td>{{ is_array($event->tags) ? implode(', ', $event->tags) : '—' }}</td></tr>
    </table>
</div>
<div class="panel">
    <h2>Context</h2>
    <pre>{{ json_encode($event->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
</div>
<div class="panel">
    <h2>User</h2>
    <pre>{{ json_encode($event->user, JSON_PRETTY_PRINT) }}</pre>
</div>
@endsection
