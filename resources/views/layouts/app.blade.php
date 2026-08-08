<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kaveh')</title>
    <style>
        :root {
            --bg: #0f1419;
            --panel: #1a222c;
            --border: #2a3542;
            --text: #e7eef7;
            --muted: #8ea0b5;
            --accent: #3dd6c6;
            --danger: #ff6b6b;
            --warn: #f4c15d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #1d3a3a 0%, var(--bg) 55%);
            color: var(--text);
            min-height: 100vh;
        }
        a { color: var(--accent); text-decoration: none; }
        .shell { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
        nav {
            border-right: 1px solid var(--border);
            background: rgba(15,20,25,.85);
            padding: 1.5rem 1rem;
        }
        nav .brand { font-family: "IBM Plex Serif", Georgia, serif; font-size: 1.4rem; margin-bottom: 1.5rem; }
        nav a {
            display: block;
            padding: .55rem .75rem;
            border-radius: 8px;
            color: var(--muted);
            margin-bottom: .25rem;
        }
        nav a:hover, nav a.active { background: var(--panel); color: var(--text); }
        main { padding: 1.5rem 2rem; }
        .panel {
            background: rgba(26,34,44,.9);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .stat h3 { margin: 0; font-size: .85rem; color: var(--muted); font-weight: 500; }
        .stat p { margin: .35rem 0 0; font-size: 1.6rem; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        th, td { text-align: left; padding: .55rem .4rem; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-weight: 500; }
        .badge {
            display: inline-block;
            padding: .15rem .45rem;
            border-radius: 999px;
            font-size: .75rem;
            background: #243040;
            color: var(--muted);
        }
        .badge.error, .badge.critical { background: rgba(255,107,107,.15); color: var(--danger); }
        .badge.warning { background: rgba(244,193,93,.15); color: var(--warn); }
        input, select, textarea, button {
            font: inherit;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #121820;
            color: var(--text);
            padding: .55rem .7rem;
        }
        button, .btn {
            background: var(--accent);
            color: #06221f;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            padding: .55rem .9rem;
            border-radius: 8px;
        }
        .row { display: flex; gap: .75rem; flex-wrap: wrap; align-items: end; }
        .flash {
            background: rgba(61,214,198,.12);
            border: 1px solid rgba(61,214,198,.35);
            padding: .75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .auth {
            max-width: 420px;
            margin: 8vh auto;
        }
        label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .3rem; }
        .field { margin-bottom: .9rem; }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #0c1117;
            padding: 1rem;
            border-radius: 8px;
            overflow: auto;
        }
        @media (max-width: 800px) {
            .shell { grid-template-columns: 1fr; }
            nav { border-right: none; border-bottom: 1px solid var(--border); }
        }
    </style>
</head>
<body>
@auth
<div class="shell">
    <nav>
        <div class="brand">Kaveh</div>
        <a href="{{ route('kaveh.dashboard') }}" class="{{ request()->routeIs('kaveh.dashboard') ? 'active' : '' }}">Overview</a>
        <a href="{{ route('kaveh.events.index') }}" class="{{ request()->routeIs('kaveh.events.*') ? 'active' : '' }}">Events</a>
        <a href="{{ route('kaveh.chat.index') }}" class="{{ request()->routeIs('kaveh.chat.*') ? 'active' : '' }}">RAG Chat</a>
        <a href="{{ route('kaveh.alerts.index') }}" class="{{ request()->routeIs('kaveh.alerts.*') ? 'active' : '' }}">Alerts</a>
        <a href="{{ route('kaveh.projects.index') }}" class="{{ request()->routeIs('kaveh.projects.*') ? 'active' : '' }}">Projects</a>
        <form method="post" action="{{ route('kaveh.logout') }}" style="margin-top:1.5rem">
            @csrf
            <button type="submit" style="width:100%">Log out</button>
        </form>
    </nav>
    <main>
        @if(session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if(session('api_key_plain'))
            <div class="flash">New API key (copy now): <code>{{ session('api_key_plain') }}</code></div>
        @endif
        @yield('content')
    </main>
</div>
@else
<main class="auth">
    @yield('content')
</main>
@endauth
    @yield('scripts')
</body>
</html>
