@extends('kaveh::layouts.app')
@section('title', 'Projects — Kaveh')
@section('content')
<h1>Projects & API keys</h1>

<div class="panel">
    <h2>Create project</h2>
    <p style="color:var(--muted);margin-top:0">Creating a project issues an API key and shows install commands for your app.</p>
    <form method="post" action="{{ route('kaveh.projects.store') }}" class="row">
        @csrf
        <div class="field"><label>Name</label><input name="name" required></div>
        <div class="field"><label>Retention days</label><input type="number" name="retention_days" value="14" min="1"></div>
        <button type="submit">Create</button>
    </form>
</div>

@foreach($projects as $project)
<div class="panel">
    <h2>{{ $project->name }} <span class="badge">#{{ $project->id }}</span></h2>
    <p style="color:var(--muted)">Retention {{ $project->retention_days }} days · max {{ number_format($project->max_events) }} events</p>

    <h3>API keys</h3>
    <table>
        <thead><tr><th>Name</th><th>Prefix</th><th>Last used</th><th></th></tr></thead>
        <tbody>
        @foreach($project->apiKeys as $key)
            <tr>
                <td>{{ $key->name }}</td>
                <td><code>{{ $key->key_prefix }}…</code></td>
                <td>{{ optional($key->last_used_at)?->diffForHumans() ?: 'never' }}</td>
                <td style="white-space:nowrap">
                    @if(!$key->revoked_at)
                    <form method="post" action="{{ route('kaveh.projects.keys.revoke', [$project, $key]) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:var(--danger);color:#fff">Revoke</button>
                    </form>
                    @else
                        <span class="badge">revoked</span>
                        <form method="post" action="{{ route('kaveh.projects.keys.destroy', [$project, $key]) }}" style="display:inline;margin-left:.35rem">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost" onclick="return confirm('Permanently delete this revoked key?')">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <form method="post" action="{{ route('kaveh.projects.keys.create', $project) }}" class="row" style="margin-top:1rem">
        @csrf
        <input name="name" placeholder="Key name" required>
        <button type="submit">Issue API key</button>
    </form>
</div>
@endforeach
@endsection
