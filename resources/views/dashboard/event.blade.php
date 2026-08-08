@extends('kaveh::layouts.app')
@section('title', $event->name.' — Kaveh')
@section('content')
@php
    $pretty = static fn ($value) => is_string($value)
        ? $value
        : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
@endphp
<div class="panel">
    <p style="margin:0 0 .75rem"><a href="{{ route('kaveh.events.index', ['project_id' => $project?->id, 'range' => $kavehRange ?? '24h']) }}">&larr; Events</a></p>

    <div class="detail-hero">
        <span class="badge type-{{ $event->type }}">{{ $event->type }}</span>
        @if($event->type === 'request' && $event->httpMethod())
            <span class="badge verb verb-{{ $event->verbTone() }}">{{ $event->httpMethod() }}</span>
        @endif
        @if($event->statusLabel())
            <span class="badge status {{ $event->statusTone() }}">{{ $event->statusLabel() }}</span>
        @endif
        <span class="badge {{ $event->level }}">{{ $event->level }}</span>
        <span class="badge">{{ $event->environment }}</span>
        <span class="muted mono">{{ $event->durationLabel() }}</span>
        @if($event->contextValue('memory_mb'))
            <span class="muted mono">{{ $event->contextValue('memory_mb') }} MB</span>
        @endif
        <span class="muted">{{ optional($event->occurred_at)?->diffForHumans() }}</span>
    </div>

    <h1 class="detail-title">{{ $event->primaryLabel() }}</h1>
    @if($event->secondaryLabel())
        <p class="muted" style="margin:.4rem 0 0">{{ $event->secondaryLabel() }}</p>
    @endif
</div>

<div class="accordion">
    <details class="acc" open>
        <summary>Summary <span class="acc-meta">identity · timing</span></summary>
        <div class="acc-body">
            <table class="kv">
                <tr><th>UUID</th><td><code>{{ $event->uuid }}</code></td></tr>
                <tr><th>Name</th><td><code>{{ $event->name }}</code></td></tr>
                <tr><th>Occurred</th><td>{{ optional($event->occurred_at)?->toIso8601String() }}</td></tr>
                <tr><th>Hostname</th><td>{{ $event->hostname ?: '—' }}</td></tr>
                <tr><th>Trace</th><td>{{ $event->trace_id ?: '—' }}</td></tr>
                <tr><th>Duration</th><td>{{ $event->durationLabel() }}</td></tr>
                <tr><th>Memory</th><td>{{ $event->contextValue('memory_mb') ? $event->contextValue('memory_mb').' MB' : '—' }}</td></tr>
                <tr><th>Tags</th><td>{{ is_array($event->tags) && count($event->tags) ? implode(', ', $event->tags) : '—' }}</td></tr>
            </table>
        </div>
    </details>

    @if($event->type === 'request')
    <details class="acc" open>
        <summary>Request <span class="acc-meta">method · path · controller</span></summary>
        <div class="acc-body">
            <table class="kv">
                <tr><th>Method</th><td><span class="badge verb verb-{{ $event->verbTone() }}">{{ $event->httpMethod() ?: '—' }}</span></td></tr>
                <tr><th>URI</th><td><code>{{ $event->httpPath() ?: '—' }}</code></td></tr>
                <tr><th>Status</th><td>
                    @if($event->httpStatus())
                        <span class="badge status {{ $event->statusTone() }}">{{ $event->httpStatus() }}</span>
                    @else — @endif
                </td></tr>
                <tr><th>Controller</th><td><code>{{ $event->contextValue('controller_action') ?: '—' }}</code></td></tr>
                <tr><th>Middleware</th><td>
                    @php $mw = $event->contextValue('middleware'); @endphp
                    {{ is_array($mw) && count($mw) ? implode(', ', $mw) : '—' }}
                </td></tr>
                <tr><th>IP</th><td>{{ $event->contextValue('ip_address') ?: $event->contextValue('ip') ?: '—' }}</td></tr>
                <tr><th>Duration</th><td class="mono">{{ $event->durationLabel() }}</td></tr>
                <tr><th>Memory</th><td class="mono">{{ $event->contextValue('memory_mb') ? $event->contextValue('memory_mb').' MB' : '—' }}</td></tr>
            </table>
        </div>
    </details>

    <details class="acc" open>
        <summary>Payload <span class="acc-meta">request input</span></summary>
        <div class="acc-body">
            <pre>{{ $pretty($event->contextValue('payload') ?? $event->contextValue('input') ?? []) }}</pre>
        </div>
    </details>

    <details class="acc">
        <summary>Headers <span class="acc-meta">request</span></summary>
        <div class="acc-body">
            <pre>{{ $pretty($event->contextValue('headers') ?? []) }}</pre>
        </div>
    </details>

    <details class="acc" open>
        <summary>Response <span class="acc-meta">status {{ $event->httpStatus() ?: '—' }}</span></summary>
        <div class="acc-body">
            <table class="kv" style="margin-bottom:1rem">
                <tr><th>Status</th><td>
                    @if($event->httpStatus())
                        <span class="badge status {{ $event->statusTone() }}">{{ $event->httpStatus() }}</span>
                    @else — @endif
                </td></tr>
            </table>
            <h3 style="margin:0 0 .5rem;font-size:.9rem;color:var(--muted)">Body</h3>
            <pre>{{ $pretty($event->contextValue('response') ?? '—') }}</pre>
            <h3 style="margin:1rem 0 .5rem;font-size:.9rem;color:var(--muted)">Response headers</h3>
            <pre>{{ $pretty($event->contextValue('response_headers') ?? []) }}</pre>
        </div>
    </details>

    <details class="acc">
        <summary>Session <span class="acc-meta">request session</span></summary>
        <div class="acc-body">
            <pre>{{ $pretty($event->contextValue('session') ?? []) }}</pre>
        </div>
    </details>
    @endif

    @if($event->type === 'exception')
    <details class="acc" open>
        <summary>Exception <span class="acc-meta">message · location</span></summary>
        <div class="acc-body">
            <table class="kv">
                <tr><th>Class</th><td><code>{{ $event->name }}</code></td></tr>
                <tr><th>Message</th><td>{{ $event->contextValue('message') ?: '—' }}</td></tr>
                <tr><th>File</th><td><code>{{ $event->contextValue('file') ?: '—' }}:{{ $event->contextValue('line') }}</code></td></tr>
                <tr><th>Code</th><td>{{ $event->contextValue('code') ?? '—' }}</td></tr>
            </table>
            @if(is_array($event->contextValue('trace')))
                <h3 style="margin:1rem 0 .5rem;font-size:.9rem;color:var(--muted)">Stack (top 20)</h3>
                <pre>@foreach($event->contextValue('trace') as $i => $frame)
{{ $i }}. {{ ($frame['class'] ?? '').($frame['type'] ?? (isset($frame['class']) ? '::' : '')).($frame['function'] ?? '?') }}
   {{ ($frame['file'] ?? 'unknown').':'.($frame['line'] ?? '?') }}
@endforeach</pre>
            @endif
        </div>
    </details>
    @endif

    @if($event->type === 'job')
    <details class="acc" open>
        <summary>Job <span class="acc-meta">queue · result</span></summary>
        <div class="acc-body">
            <table class="kv">
                <tr><th>Job</th><td><code>{{ $event->name }}</code></td></tr>
                <tr><th>Connection</th><td>{{ $event->contextValue('connection') ?: '—' }}</td></tr>
                <tr><th>Queue</th><td>{{ $event->contextValue('queue') ?: '—' }}</td></tr>
                <tr><th>Status</th><td><span class="badge status {{ $event->statusTone() }}">{{ $event->contextValue('status') ?: '—' }}</span></td></tr>
                @if($event->contextValue('exception'))
                <tr><th>Error</th><td style="color:var(--danger)">{{ $event->contextValue('exception') }}</td></tr>
                @endif
            </table>
        </div>
    </details>
    @endif

    @if($event->type === 'query')
    <details class="acc" open>
        <summary>Slow query <span class="acc-meta">{{ $event->durationLabel() }}</span></summary>
        <div class="acc-body">
            <table class="kv">
                <tr><th>Connection</th><td>{{ $event->contextValue('connection') ?: '—' }}</td></tr>
                <tr><th>Bindings</th><td>{{ $event->contextValue('bindings_count') ?? '—' }}</td></tr>
                <tr><th>Duration</th><td class="mono">{{ $event->durationLabel() }}</td></tr>
            </table>
            <h3 style="margin:1rem 0 .5rem;font-size:.9rem;color:var(--muted)">SQL</h3>
            <pre>{{ $event->contextValue('sql') ?: '—' }}</pre>
        </div>
    </details>
    @endif

    <details class="acc" @if(! in_array($event->type, ['request','exception','job','query'], true)) open @endif>
        <summary>Raw context <span class="acc-meta">full payload</span></summary>
        <div class="acc-body">
            <pre>{{ json_encode($event->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </details>

    <details class="acc">
        <summary>User <span class="acc-meta">authenticated actor</span></summary>
        <div class="acc-body">
            <pre>{{ json_encode($event->user, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </details>
</div>
@endsection
