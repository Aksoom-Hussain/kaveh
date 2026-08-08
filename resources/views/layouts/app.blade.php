<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Kaveh')</title>
    <script>
      (() => {
        try {
          const t = localStorage.getItem('kaveh.theme');
          document.documentElement.setAttribute('data-theme', (t === 'light' || t === 'dark') ? t : 'dark');
        } catch (_) {
          document.documentElement.setAttribute('data-theme', 'dark');
        }
      })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c1218;
            --bg-soft: #101820;
            --panel: #15202b;
            --panel-2: #1a2836;
            --border: #2a3a4a;
            --text: #e8eef6;
            --muted: #8b9cb0;
            --accent: #3dd6c6;
            --accent-strong: #2bb8a9;
            --accent-dim: rgba(61, 214, 198, 0.14);
            --danger: #ff6b6b;
            --warn: #f4c15d;
            --chart: #3dd6c6;
            --shadow: 0 1px 2px rgba(0,0,0,.25);
            --code-bg: #0a0f14;
            --code-text: #e2e8f0;
            --topbar-bg: rgba(12, 18, 24, 0.88);
            --badge-bg: #243040;
            --badge-text: #8b9cb0;
            --hover-bg: rgba(26, 40, 54, 0.6);
            --overlay: rgba(4, 8, 12, 0.72);
            --nav-h: 3.5rem;
            color-scheme: dark;
        }
        html[data-theme="light"] {
            --bg: #f1f3f5;
            --bg-soft: #e9ecef;
            --panel: #ffffff;
            --panel-2: #f8fafb;
            --border: #dde3ea;
            --text: #15202b;
            --muted: #6b7c8f;
            --accent: #0f766e;
            --accent-strong: #0d9488;
            --accent-dim: rgba(15, 118, 110, 0.12);
            --danger: #e11d48;
            --warn: #d97706;
            --chart: #0f766e;
            --shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            --code-bg: #0f172a;
            --code-text: #e2e8f0;
            --topbar-bg: rgba(255, 255, 255, 0.92);
            --badge-bg: #e8eef4;
            --badge-text: #4b5c6e;
            --hover-bg: rgba(15, 118, 110, 0.06);
            --overlay: rgba(15, 23, 42, 0.45);
            color-scheme: light;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "DM Sans", "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }
        html:not([data-theme="light"]) body {
            background:
                radial-gradient(900px 480px at 8% -8%, #1a3d3a 0%, transparent 55%),
                radial-gradient(700px 400px at 100% 0%, #1a2a3d 0%, transparent 45%),
                var(--bg);
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
            background: var(--topbar-bg);
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
            background: var(--panel-2);
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
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.1rem;
            height: 2.1rem;
            padding: 0;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--muted);
            cursor: pointer;
        }
        .icon-btn:hover { color: var(--text); border-color: var(--accent); }
        .icon-btn svg { width: 1.05rem; height: 1.05rem; display: block; }
        html[data-theme="light"] .theme-icon-moon { display: none; }
        html:not([data-theme="light"]) .theme-icon-sun { display: none; }
        .live-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.28rem 0.65rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }
        .live-toggle:hover { color: var(--text); border-color: rgba(61,214,198,.35); }
        .live-toggle[aria-pressed="true"] {
            background: var(--accent-dim);
            border-color: rgba(61,214,198,.45);
            color: var(--accent);
        }
        .live-toggle .live-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.55;
        }
        .live-toggle[aria-pressed="true"] .live-dot {
            opacity: 1;
            box-shadow: 0 0 0 0 rgba(61,214,198,.7);
            animation: kaveh-live-pulse 1.4s ease-out infinite;
        }
        @keyframes kaveh-live-pulse {
            0% { box-shadow: 0 0 0 0 rgba(61,214,198,.55); }
            70% { box-shadow: 0 0 0 8px rgba(61,214,198,0); }
            100% { box-shadow: 0 0 0 0 rgba(61,214,198,0); }
        }
        .live-toggle .live-label::before { content: 'Paused'; }
        .live-toggle[aria-pressed="true"] .live-label::before { content: 'Live'; }

        main {
            padding: 1.25rem 1.5rem 2.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }

        /* Pulse-style board */
        .servers-strip {
            display: flex;
            gap: 0.75rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
            margin-bottom: 1rem;
        }
        .server-chip {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex: 0 0 auto;
            min-width: min(100%, 520px);
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            box-shadow: var(--shadow);
        }
        .server-chip .host {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 600;
            min-width: 7rem;
        }
        .server-chip .dot {
            width: 0.55rem; height: 0.55rem; border-radius: 999px;
            background: var(--muted);
        }
        .server-chip .dot.on { background: #34d399; box-shadow: 0 0 0 3px rgba(52,211,153,.18); }
        .server-metric { min-width: 4.5rem; }
        .server-metric .label { font-size: .7rem; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .server-metric .value { font-size: .95rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .spark { width: 72px; height: 28px; display: block; }
        .disk-rings { display: flex; gap: 0.55rem; align-items: center; }
        .disk-ring {
            --p: 0;
            width: 52px; height: 52px;
            border-radius: 50%;
            background: conic-gradient(var(--chart) calc(var(--p) * 1%), var(--bg-soft) 0);
            display: grid; place-items: center;
            position: relative;
        }
        .disk-ring::after {
            content: '';
            width: 34px; height: 34px;
            border-radius: 50%;
            background: var(--panel);
        }
        .disk-ring span {
            position: absolute;
            font-size: .55rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.15;
            color: var(--muted);
            max-width: 46px;
        }
        .pulse-board {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 0.85rem;
            align-items: start;
        }
        .pcard {
            grid-column: span 4;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            min-height: 220px;
            max-height: 360px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: box-shadow .15s, border-color .15s, transform .12s;
        }
        .pcard.span-6 { grid-column: span 6; max-height: 420px; }
        .pcard.span-8 { grid-column: span 8; }
        .pcard.span-12 { grid-column: span 12; max-height: 520px; }
        .pcard.dragging { opacity: .55; border-color: var(--accent); }
        .pcard-drag {
            cursor: grab;
            color: var(--muted);
            padding: 0.15rem;
            border-radius: 6px;
            line-height: 0;
            touch-action: none;
            user-select: none;
        }
        .pcard-drag:active { cursor: grabbing; }
        .pcard-head {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.85rem 1rem 0.55rem;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        .pcard-title {
            display: flex;
            align-items: baseline;
            gap: 0.45rem;
            font-weight: 650;
            font-size: .98rem;
            min-width: 0;
        }
        .pcard-title .sub { color: var(--muted); font-weight: 400; font-size: .8rem; }
        .pcard-head .controls { margin-left: auto; display: flex; align-items: center; gap: .4rem; }
        .pcard-body {
            padding: 0 1rem 1rem;
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            scrollbar-gutter: stable;
        }
        .pcard-body::-webkit-scrollbar { width: 8px; }
        .pcard-body::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--muted) 45%, transparent);
            border-radius: 999px;
        }
        .pcard[data-card="queues"] { max-height: 260px; }
        .pcard[data-card="queues"] .pcard-body {
          max-height: none;
          touch-action: pan-y;
        }
        .pcard-empty {
            display: grid;
            place-items: center;
            gap: 0.35rem;
            min-height: 140px;
            color: var(--muted);
            font-size: .9rem;
        }
        .pcard-empty svg { opacity: .45; }
        .pcard table.compact { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .pcard table.compact th {
            text-align: left;
            color: var(--muted);
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: .35rem .25rem;
            border-bottom: 1px solid var(--border);
        }
        .pcard table.compact td {
            padding: .55rem .25rem;
            border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent);
            vertical-align: top;
        }
        .pcard table.compact tr:last-child td { border-bottom: 0; }
        .pcard .sql {
            font-family: "IBM Plex Mono", ui-monospace, monospace;
            font-size: .72rem;
            background: var(--code-bg);
            color: var(--code-text);
            border-radius: 8px;
            padding: .55rem .65rem;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 4.8rem;
            overflow: hidden;
        }
        .pcard .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .cache-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .5rem;
            margin-bottom: .75rem;
        }
        .cache-stats .stat-box h4 { margin: 0; font-size: .7rem; color: var(--muted); font-weight: 500; text-transform: uppercase; }
        .cache-stats .stat-box p { margin: .2rem 0 0; font-size: 1.35rem; font-weight: 650; font-variant-numeric: tabular-nums; }
        .chart-box {
            position: relative;
            width: 100%;
            overflow: hidden;
        }
        .chart-box canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
            max-height: 100%;
        }
        .chart-box-traffic { height: 72px; }
        .chart-box-queue { height: 36px; }
        .queues-scroll { display: flex; flex-direction: column; gap: 0.15rem; }
        .queue-row { margin-bottom: .55rem; flex-shrink: 0; }
        .queue-row:last-child { margin-bottom: 0; }
        .queue-row .qname { font-size: .78rem; color: var(--muted); margin-bottom: .15rem; font-family: "IBM Plex Mono", monospace; }
        .user-row { display: flex; align-items: center; gap: .65rem; padding: .4rem 0; border-bottom: 1px solid color-mix(in srgb, var(--border) 70%, transparent); }
        .user-row:last-child { border-bottom: 0; }
        .avatar {
            width: 1.7rem; height: 1.7rem; border-radius: 999px;
            background: var(--accent-dim); color: var(--accent);
            display: grid; place-items: center; font-size: .7rem; font-weight: 700;
            flex-shrink: 0;
        }
        .user-meta { min-width: 0; flex: 1; }
        .user-meta .email { font-size: .84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-meta .id { font-size: .72rem; color: var(--muted); }
        .user-count { font-weight: 650; font-variant-numeric: tabular-nums; }
        .board-toolbar {
            display: flex; justify-content: space-between; align-items: center;
            gap: .75rem; margin-bottom: .85rem; flex-wrap: wrap;
        }
        .board-toolbar h1 { margin: 0; font-size: 1.55rem; }
        @media (max-width: 1100px) {
            .pcard, .pcard.span-6, .pcard.span-8 { grid-column: span 6; }
            .pcard.span-12 { grid-column: span 12; }
        }
        @media (max-width: 720px) {
            .pcard, .pcard.span-6, .pcard.span-8, .pcard.span-12 { grid-column: span 12; }
            .server-chip { min-width: 92vw; }
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
            background: var(--badge-bg);
            color: var(--badge-text);
        }
        .badge.error, .badge.critical { background: rgba(255,107,107,.15); color: var(--danger); }
        .badge.warning { background: rgba(244,193,93,.18); color: var(--warn); }
        .badge.ok { background: rgba(52,211,153,.15); color: #059669; }
        .badge.redirect { background: rgba(110,168,254,.18); color: #2563eb; }
        .badge.verb { font-weight: 700; letter-spacing: .03em; min-width: 3.4rem; text-align: center; }
        .badge.verb-get { background: var(--badge-bg); color: var(--badge-text); }
        .badge.verb-post { background: rgba(110,168,254,.18); color: #2563eb; }
        .badge.verb-put { background: rgba(244,193,93,.18); color: var(--warn); }
        .badge.verb-delete { background: rgba(255,107,107,.18); color: var(--danger); }
        .badge.verb-meta { background: var(--badge-bg); color: var(--badge-text); }
        .badge.type-request { background: var(--accent-dim); color: var(--accent); }
        .badge.type-exception { background: rgba(255,107,107,.15); color: var(--danger); }
        .badge.type-job { background: rgba(244,193,93,.18); color: var(--warn); }
        .badge.type-query { background: rgba(110,168,254,.18); color: #2563eb; }
        .badge.type-log { background: var(--badge-bg); color: var(--badge-text); }
        .badge.type-system { background: rgba(52,211,153,.15); color: #059669; }
        .badge.type-custom { background: rgba(192,132,252,.15); color: #7c3aed; }
        html:not([data-theme="light"]) .badge.ok,
        html:not([data-theme="light"]) .badge.type-system { color: #34d399; }
        html:not([data-theme="light"]) .badge.redirect,
        html:not([data-theme="light"]) .badge.verb-post,
        html:not([data-theme="light"]) .badge.type-query { color: #6ea8fe; }
        html:not([data-theme="light"]) .badge.type-custom { color: #c084fc; }
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
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .event-row { cursor: pointer; transition: background .12s; }
        .event-row:hover { background: var(--hover-bg); }
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
            background: var(--badge-bg);
            color: var(--badge-text);
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
        input, select, textarea {
            font: inherit;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--panel-2);
            color: var(--text);
            padding: 0.55rem 0.7rem;
        }
        button, .btn {
            font: inherit;
            background: var(--accent);
            color: #06221f;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            padding: 0.55rem 0.9rem;
            border-radius: 8px;
        }
        html[data-theme="light"] button,
        html[data-theme="light"] .btn { color: #fff; }
        button:hover, .btn:hover { filter: brightness(1.05); }
        button.icon-btn, button.btn-ghost, button.live-toggle {
            filter: none;
        }
        button.icon-btn {
            background: var(--panel-2);
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 0;
        }
        button.btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }
        button.live-toggle {
            background: var(--panel-2);
            color: var(--muted);
            border: 1px solid var(--border);
        }
        button.live-toggle[aria-pressed="true"] {
            background: var(--accent-dim);
            border-color: color-mix(in srgb, var(--accent) 45%, var(--border));
            color: var(--accent);
        }
        .row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: end; }
        .flash {
            background: var(--accent-dim);
            border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--border));
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            font-weight: 600;
            cursor: pointer;
            display: inline-block;
            padding: 0.45rem 0.8rem;
            border-radius: 8px;
            text-decoration: none;
        }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .onboard-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: var(--overlay);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .onboard-modal {
            width: min(640px, 100%);
            max-height: min(90vh, 820px);
            overflow: auto;
            background: var(--panel);
            border: 1px solid color-mix(in srgb, var(--accent) 40%, var(--border));
            border-radius: 16px;
            padding: 1.35rem 1.4rem 1.25rem;
            box-shadow: var(--shadow);
            color: var(--text);
        }
        .onboard-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: start;
            margin-bottom: 1.1rem;
        }
        .onboard-eyebrow {
            color: var(--accent);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 0 0.35rem;
        }
        .onboard-head h2 {
            font-family: "Instrument Serif", Georgia, serif;
            font-size: 1.65rem;
            font-weight: 400;
            margin: 0 0 0.4rem;
        }
        .onboard-lead { color: var(--muted); margin: 0; font-size: 0.95rem; line-height: 1.45; }
        .onboard-close {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            width: 2rem;
            height: 2rem;
            border-radius: 8px;
            font-size: 1.25rem;
            line-height: 1;
            padding: 0;
        }
        .onboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }
        @media (max-width: 640px) {
            .onboard-grid { grid-template-columns: 1fr; }
        }
        .onboard-field { margin-bottom: 0.85rem; }
        .onboard-copyrow {
            display: flex;
            gap: 0.5rem;
            align-items: start;
            background: var(--code-bg);
            color: var(--code-text);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.65rem 0.7rem;
        }
        .onboard-copyrow.stack { flex-direction: column; }
        .onboard-copyrow code, .onboard-copyrow pre {
            flex: 1;
            margin: 0;
            background: transparent;
            padding: 0;
            font-size: 0.82rem;
            line-height: 1.45;
            word-break: break-all;
        }
        .onboard-steps {
            margin: 0.4rem 0 1.1rem;
            padding-left: 1.2rem;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.55;
        }
        .onboard-steps code { color: var(--text); }
        .onboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
        }
        .auth {
            max-width: 420px;
            margin: 8vh auto;
            padding: 0 1rem;
        }
        label { display: block; font-size: 0.85rem; color: var(--muted); margin-bottom: 0.3rem; }
        .field { margin-bottom: 0.9rem; }
        pre, code {
            font-family: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: var(--code-bg);
            color: var(--code-text);
            padding: 1rem;
            border-radius: 8px;
            overflow: auto;
            border: 1px solid var(--border);
        }
        code {
            color: inherit;
        }
        h1 { font-family: "Instrument Serif", Georgia, serif; font-weight: 400; font-size: 2rem; letter-spacing: -0.01em; }
        h2 { font-size: 1.05rem; font-weight: 600; }

        /* Accordions */
        .accordion { display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1rem; }
        details.acc {
            background: var(--panel);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: border-color 0.15s;
        }
        details.acc[open] { border-color: color-mix(in srgb, var(--accent) 45%, var(--border)); }
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
            color: var(--text);
            background: var(--panel);
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
        details.acc > summary:hover { background: var(--hover-bg); }
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
            <button
                type="button"
                id="kaveh-live-toggle"
                class="live-toggle"
                aria-pressed="false"
                title="Auto-refresh page content (like Telescope)"
            >
                <span class="live-dot" aria-hidden="true"></span>
                <span class="live-label"></span>
            </button>
            <button type="button" id="kaveh-theme-toggle" class="icon-btn" title="Toggle light / dark" aria-label="Toggle theme">
                <svg class="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg class="theme-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 14.5A8.5 8.5 0 0 1 9.5 3 7 7 0 1 0 21 14.5z"/></svg>
            </button>
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
    @if(session('api_key_plain') && ! session('kaveh_onboarding'))
        <div class="flash">New API key (copy now): <code>{{ session('api_key_plain') }}</code></div>
    @endif
    @include('kaveh::partials.onboarding')
    @yield('content')
</main>
@else
<main class="auth">
    @yield('content')
</main>
@endauth
<script>
(() => {
  const themeBtn = document.getElementById('kaveh-theme-toggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('kaveh.theme', next);
      window.dispatchEvent(new CustomEvent('kaveh:theme'));
    });
  }

  const KEY = 'kaveh.livePoll';
  const INTERVAL_MS = 5000;
  const btn = document.getElementById('kaveh-live-toggle');
  if (!btn) return;

  window.KavehLive = window.KavehLive || {
    enabled: false,
    handlers: [],
    on(fn) { this.handlers.push(fn); return () => { this.handlers = this.handlers.filter(h => h !== fn); }; },
    async tick() {
      for (const fn of this.handlers) {
        try { await fn(); } catch (e) { console.error(e); }
      }
      window.dispatchEvent(new CustomEvent('kaveh:live-tick'));
    },
  };

  let timer = null;

  const setEnabled = (on) => {
    window.KavehLive.enabled = !!on;
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    localStorage.setItem(KEY, on ? '1' : '0');
    if (timer) { clearInterval(timer); timer = null; }
    if (on) {
      window.KavehLive.tick();
      timer = setInterval(() => window.KavehLive.tick(), INTERVAL_MS);
    }
  };

  btn.addEventListener('click', () => {
    setEnabled(btn.getAttribute('aria-pressed') !== 'true');
  });

  setEnabled(localStorage.getItem(KEY) === '1');
})();
</script>
    @yield('scripts')
</body>
</html>
