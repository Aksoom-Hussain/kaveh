@extends('kaveh::layouts.app')
@section('title', 'RAG Chat — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between">
    <h1>Event RAG chat</h1>
    <form method="get">
        <select name="project_id" onchange="this.form.submit()">
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected($project?->id === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="panel">
    <form method="post" action="{{ route('kaveh.chat.ask') }}">
        @csrf
        <input type="hidden" name="project_id" value="{{ $project?->id }}">
        <div class="field">
            <label>Ask about your telemetry</label>
            <textarea name="question" rows="3" style="width:100%" required placeholder="What failed for checkout today?">{{ $question }}</textarea>
        </div>
        <button type="submit">Ask</button>
    </form>
</div>

@if($answer)
<div class="panel">
    <h2>Answer</h2>
    <pre>{{ $answer }}</pre>
</div>
<div class="panel">
    <h2>Citations</h2>
    <table>
        <thead><tr><th>ID</th><th>Type</th><th>Name</th><th>Level</th><th>When</th></tr></thead>
        <tbody>
        @foreach($citations as $c)
            <tr>
                <td><a href="{{ route('kaveh.events.show', $c['id']) }}">#{{ $c['id'] }}</a></td>
                <td>{{ $c['type'] }}</td>
                <td>{{ $c['name'] }}</td>
                <td>{{ $c['level'] }}</td>
                <td>{{ $c['occurred_at'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
