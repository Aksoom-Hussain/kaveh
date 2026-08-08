<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kaveh')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c1218;
            --panel: #15202b;
            --panel-2: #1a2836;
            --border: #2a3a4a;
            --text: #e8eef6;
            --muted: #8b9cb0;
            --accent: #3dd6c6;
            --accent-dim: rgba(61, 214, 198, 0.14);
            --danger: #ff6b6b;
            --warn: #f4c15d;
            --nav-h: 3.5rem;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "DM Sans", "Segoe UI", sans-serif;
            background:
                radial-gradient(900px 480px at 8% -8%, #1a3d3a 0%, transparent 55%),
                radial-gradient(700px 400px at 100% 0%, #1a2a3d 0%, transparent 45%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            min-height: var(--nav-h);
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--border);
            background: rgba(12, 18, 24, 0.88);
            backdrop-filter: blur(12px);
        }
        .topbar .brand {
            font-family: "Instrument Serif", Georgia, serif;
            font-size: 1.55rem;
            letter-spacing: 0.01em;
            color: var(--text);
            text-decoration: none;
            flex-shrink: 0;
        }
        .topbar .brand:hover { text-decoration: none; color: var(--accent); }
        .topnav {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            flex: 1;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .topnav::-webkit-scrollbar { display: none; }
        .topnav a {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .topnav a:hover { background: var(--panel); color: var(--text); text-decoration: none; }
        .topnav a.active {
            background: var(--accent-dim);
            color: var(--accent);
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-shrink: 0;
            margin-left: auto;
        }
        .topbar-actions button {
            padding: 0.4rem 0.85rem;
            font-size: 0.85rem;
        }
        .topbar-filters {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }
        .topbar-filters select {
            padding: 0.35rem 0.55rem;
            font-size: 0.85rem;
            min-width: 9rem;
            max-width: 14rem;
            cursor: pointer;
        }
        .topbar-filters select:focus {
            outline: 1px solid var(--accent);
            border-color: var(--accent);
        }
        .range-pills {
            display: inline-flex;
            align-items: center;
            gap: 0.15rem;
            padding: 0.15rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: rgba(14, 21, 28, 0.8);
        }
        .range-pills a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.4rem;
            padding: 0.28rem 0.55rem;
            border-radius: 6px;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: 0.02em;
        }
        .range-pills a:hover { color: var(--text); text-decoration: none; background: rgba(26,40,54,.7); }
        .range-pills a.active {
            background: var(--accent-dim);
            color: var(--accent);
        }

        main {
            padding: 1.5rem 2rem 2.5rem;
            max-width: 1280px;
            margin: 0 auto;
        }

        .panel {
            background: rgba(21, 32, 43, 0.92);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
        .stat h3 { margin: 0; font-size: 0.8rem; color: var(--muted); font-weight: 500; letter-spacing: 0.02em; text-transform: uppercase; }
        .stat p { margin: 0.35rem 0 0; font-size: 1.55rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        th, td { text-align: left; padding: 0.55rem 0.4rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-weight: 500; }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.45rem;
            border-radius: 6px;
            font-size: 0.75rem;
            background: #243040;
            color: var(--muted);
        }
        .badge.error, .badge.critical { background: rgba(255,107,107,.15); color: var(--danger); }
        .badge.warning { background: rgba(244,193,93,.15); color: var(--warn); }
        .badge.ok { background: rgba(52,211,153,.15); color: #34d399; }
        .badge.redirect { background: rgba(110,168,254,.15); color: #6ea8fe; }
        .badge.verb { font-weight: 700; letter-spacing: .03em; min-width: 3.4rem; text-align: center; }
        .badge.verb-get { background: #2a3544; color: #c5d0dc; }
        .badge.verb-post { background: rgba(110,168,254,.18); color: #6ea8fe; }
        .badge.verb-put { background: rgba(244,193,93,.18); color: var(--warn); }
        .badge.verb-delete { background: rgba(255,107,107,.18); color: var(--danger); }
        .badge.verb-meta { background: #1e2834; color: #7a8899; }
        .badge.type-request { background: rgba(61,214,198,.12); color: var(--accent); }
        .badge.type-exception { background: rgba(255,107,107,.15); color: var(--danger); }
        .badge.type-job { background: rgba(244,193,93,.12); color: var(--warn); }
        .badge.type-query { background: rgba(110,168,254,.12); color: #6ea8fe; }
        .badge.type-log { background: #243040; color: var(--muted); }
        .muted { color: var(--muted); }
        .mono { font-variant-numeric: tabular-nums; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; }

        .events-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .events-table th {
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--muted);
            font-weight: 500;
            padding: .45rem .4rem .65rem;
            border-bottom: 1px solid var(--border);
        }
        .events-table td {
            padding: .7rem .4rem;
            border-bottom: 1px solid rgba(42,58,74,.7);
            vertical-align: middle;
        }
        .event-row { cursor: pointer; transition: background .12s; }
        .event-row:hover { background: rgba(61,214,198,.05); }
        .event-detail { min-width: 0; }
        .event-primary {
            color: var(--text);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 42rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .84rem;
        }
        .event-secondary {
            color: var(--muted);
            font-size: .78rem;
            margin-top: .2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 42rem;
        }
        .event-tags { margin-top: .3rem; display: flex; flex-wrap: wrap; gap: .25rem; }
        .tag {
            font-size: .68rem;
            padding: .1rem .35rem;
            border-radius: 4px;
            background: #1a2430;
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .event-go a {
            display: inline-flex;
            width: 1.7rem;
            height: 1.7rem;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
        }
        .event-go a:hover { border-color: var(--accent); color: var(--accent); }

        .kv { width: 100%; border-collapse: collapse; }
        .kv th {
            width: 9rem;
            color: var(--muted);
            font-weight: 500;
            text-align: left;
            padding: .45rem .35rem;
            vertical-align: top;
            border-bottom: 1px solid var(--border);
        }
        .kv td {
            padding: .45rem .35rem;
            border-bottom: 1px solid var(--border);
            word-break: break-word;
        }
        .detail-hero {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
            margin: .75rem 0 1rem;
        }
        .detail-title {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 1.05rem;
            font-weight: 500;
            word-break: break-all;
            margin: 0;
        }
        input, select, textarea, button {
            font: inherit;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #0e151c;
            color: var(--text);
            padding: 0.55rem 0.7rem;
        }
        button, .btn {
            background: var(--accent);
            color: #06221f;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            padding: 0.55rem 0.9rem;
            border-radius: 8px;
        }
        button:hover, .btn:hover { filter: brightness(1.05); }
        .row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: end; }
        .flash {
            background: var(--accent-dim);
            border: 1px solid rgba(61,214,198,.35);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .auth {
            max-width: 420px;
            margin: 8vh auto;
            padding: 0 1rem;
        }
        label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem; }
        .field { margin-bottom: 0.9rem; }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0a0f14;
            padding: 1rem;
            border-radius: 8px;
            overflow: auto;
        }
        h1 { font-family: "Instrument Serif", Georgia, serif; font-weight: 400; font-size: 2rem; letter-spacing: -0.01em; }
        h2 { font-size: 1.05rem; font-weight: 600; }

        /* Accordions */
        .accordion { display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1rem; }
        details.acc {
            background: rgba(21, 32, 43, 0.92);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            transition: border-color 0.15s;
        }
        details.acc[open] { border-color: rgba(61, 214, 198, 0.35); }
        details.acc > summary {
            list-style: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.15rem;
            user-select: none;
            font-weight: 600;
            font-size: 0.98rem;
        }
        details.acc > summary::-webkit-details-marker { display: none; }
        details.acc > summary::before {
            content: '';
            width: 0.45rem;
            height: 0.45rem;
            border-right: 2px solid var(--muted);
            border-bottom: 2px solid var(--muted);
            transform: rotate(-45deg);
            transition: transform 0.18s ease, border-color 0.15s;
            flex-shrink: 0;
            margin-top: -2px;
        }
        details.acc[open] > summary::before {
            transform: rotate(45deg);
            border-color: var(--accent);
        }
        details.acc > summary:hover { background: rgba(26, 40, 54, 0.6); }
        details.acc > summary .acc-meta {
            margin-left: auto;
            font-weight: 400;
            font-size: 0.82rem;
            color: var(--muted);
        }
        details.acc .acc-body {
            padding: 0 1.15rem 1.15rem;
            border-top: 1px solid transparent;
        }
        details.acc[open] .acc-body { border-top-color: var(--border); padding-top: 1rem; }

        @media (max-width: 720px) {
            .topbar { padding: 0.65rem 1rem; flex-wrap: wrap; gap: 0.5rem; }
            .topnav { order: 3; width: 100%; padding-bottom: 0.35rem; }
            .topbar-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
            .topbar-filters { flex-wrap: wrap; }
            main { padding: 1.15rem 1rem 2rem; }
            h1 { font-size: 1.65rem; }
        }
    </style>
</head>
<body>
@auth
<header class="topbar">
    <a href="{{ route('kaveh.dashboard', ['project_id' => $project?->id, 'range' => $kavehRange ?? '24h']) }}" class="brand">Kaveh</a>
    <nav class="topnav" aria-label="Main">
        <a href="{{ route('kaveh.dashboard', ['project_id' => $project?->id, 'range' => $kavehRange ?? '24h']) }}" class="{{ request()->routeIs('kaveh.dashboard') ? 'active' : '' }}">Overview</a>
        <a href="{{ route('kaveh.events.index', ['project_id' => $project?->id, 'range' => $kavehRange ?? '24h']) }}" class="{{ request()->routeIs('kaveh.events.*') ? 'active' : '' }}">Events</a>
        <a href="{{ route('kaveh.alerts.index', ['project_id' => $project?->id, 'range' => $kavehRange ?? '24h']) }}" class="{{ request()->routeIs('kaveh.alerts.*') ? 'active' : '' }}">Alerts</a>
        <a href="{{ route('kaveh.projects.index') }}" class="{{ request()->routeIs('kaveh.projects.*') ? 'active' : '' }}">Projects</a>
    </nav>
    <div class="topbar-actions">
        <div class="topbar-filters">
            @if(($projects ?? collect())->isNotEmpty())
            @php
                $projectFilterBase = match (true) {
                    request()->routeIs('kaveh.events.*') => route('kaveh.events.index'),
                    request()->routeIs('kaveh.alerts.*') => route('kaveh.alerts.index'),
                    request()->routeIs('kaveh.projects.*') => route('kaveh.projects.index'),
                    default => route('kaveh.dashboard'),
                };
                $projectFilterQuery = array_filter([
                    'range' => $kavehRange ?? '24h',
                    'type' => request('type'),
                    'level' => request('level'),
                    'q' => request('q'),
                ], static fn ($v) => $v !== null && $v !== '');
            @endphp
            <form method="get" action="{{ $projectFilterBase }}" id="kaveh-project-filter">
                @foreach($projectFilterQuery as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <select
                    name="project_id"
                    aria-label="Project"
                    onchange="if (this.selectedOptions[0]?.dataset?.href) { window.location.assign(this.selectedOptions[0].dataset.href); }"
                    onclick="if (this.options.length === 1 && this.options[0].dataset.href && !window.location.search.includes('project_id=' + this.value)) { window.location.assign(this.options[0].dataset.href); }"
                >
                    @foreach($projects as $p)
                        @php
                            $href = $projectFilterBase.'?'.http_build_query(array_merge($projectFilterQuery, [
                                'project_id' => $p->id,
                            ]));
                        @endphp
                        <option
                            value="{{ $p->id }}"
                            data-href="{{ $href }}"
                            @selected((int) ($project->id ?? 0) === (int) $p->id)
                        >{{ $p->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
            <div class="range-pills" role="group" aria-label="Time range">
                @foreach(\Kaveh\Server\Support\DashboardFilter::RANGES as $key => $meta)
                    @php
                        $rangeUrl = ($kavehFilters ?? null)
                            ? $kavehFilters->url(['range' => $key])
                            : request()->fullUrlWithQuery(['range' => $key, 'project_id' => $project->id ?? null]);
                    @endphp
                    <a href="{{ $rangeUrl }}" class="{{ ($kavehRange ?? '24h') === $key ? 'active' : '' }}">{{ $meta['label'] }}</a>
                @endforeach
            </div>
        </div>
        <form method="post" action="{{ route('kaveh.logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </div>
</header>
<main>
    @if(session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if(session('api_key_plain'))
        <div class="flash">New API key (copy now): <code>{{ session('api_key_plain') }}</code></div>
    @endif
    @yield('content')
</main>
@else
<main class="auth">
    @yield('content')
</main>
@endauth
    @yield('scripts')
</body>
</html>
