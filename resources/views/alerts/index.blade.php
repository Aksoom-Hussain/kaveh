@extends('kaveh::layouts.app')
@section('title', 'Alerts — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;align-items:center">
    <h1 style="margin:0">Alerts</h1>
</div>

<div class="panel">
    <h2>New rule</h2>
    <form method="post" action="{{ route('kaveh.alerts.store') }}">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project?->id }}">
        <div class="row">
            <div class="field"><label>Name</label><input name="name" required></div>
            <div class="field">
                <label>Metric</label>
                <select name="metric">
                    <option value="exception_rate">Exception rate</option>
                    <option value="failed_jobs">Failed jobs</option>
                    <option value="custom_event">Custom event</option>
                </select>
            </div>
            <div class="field"><label>Event name</label><input name="event_name" placeholder="checkout.failed"></div>
            <div class="field"><label>Threshold</label><input type="number" name="threshold" value="10" min="1"></div>
            <div class="field"><label>Window (min)</label><input type="number" name="window_minutes" value="5" min="1"></div>
            <div class="field"><label>Cooldown (min)</label><input type="number" name="cooldown_minutes" value="30" min="1"></div>
            <div class="field">
                <label>Channel</label>
                <select name="channel"><option value="webhook">Webhook</option><option value="email">Email</option></select>
            </div>
            <div class="field"><label>Target</label><input name="target" placeholder="https://… or email" required style="min-width:220px"></div>
            <button type="submit">Add rule</button>
        </div>
    </form>
</div>

<div class="panel">
    <table>
        <thead><tr><th>Name</th><th>Metric</th><th>Threshold</th><th>Channel</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($rules as $rule)
            <tr>
                <td>{{ $rule->name }}</td>
                <td>{{ $rule->metric }} @if($rule->event_name)<code>{{ $rule->event_name }}</code>@endif</td>
                <td>{{ $rule->threshold }} / {{ $rule->window_minutes }}m</td>
                <td>{{ $rule->channel }} → {{ $rule->target }}</td>
                <td>{{ $rule->enabled ? 'enabled' : 'disabled' }}</td>
                <td>
                    <form method="post" action="{{ route('kaveh.alerts.toggle', $rule) }}">@csrf
                        <button type="submit">Toggle</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
