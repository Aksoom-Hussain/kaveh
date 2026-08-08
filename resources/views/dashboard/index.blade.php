@extends('kaveh::layouts.app')
@section('title', 'Overview — Kaveh')
@section('content')
<div class="board-toolbar">
    <h1>
        Overview
        <span class="muted" style="font-size:1rem;font-family:'DM Sans',sans-serif;font-weight:400">
            <span id="overview-project-label">{{ $project?->name ?? 'No project' }}</span>
            · past <span id="overview-range-label">{{ $rangeLabel }}</span>
        </span>
    </h1>
    <div class="row" style="gap:.5rem;align-items:center">
        <span id="live-refreshed-at" class="muted" style="font-size:.8rem"></span>
        <button type="button" class="btn-ghost" id="reset-card-layout" title="Reset card order">Reset layout</button>
    </div>
</div>

<div class="servers-strip" id="servers-strip" aria-label="Servers">
    <div class="server-chip muted" id="servers-empty">Loading server stats…</div>
</div>

<div class="pulse-board" id="pulse-board">
    <article class="pcard span-4" data-card="usage">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Application Usage <span class="sub" id="usage-sub">past {{ $rangeLabel }}</span></div>
            <div class="controls"><span class="muted" style="font-size:.78rem">Top users</span></div>
        </div>
        <div class="pcard-body" id="card-usage"></div>
    </article>

    <article class="pcard span-4" data-card="queues">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Queues <span class="sub">past {{ $rangeLabel }}</span></div>
        </div>
        <div class="pcard-body" id="card-queues"></div>
    </article>

    <article class="pcard span-4" data-card="cache">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Cache <span class="sub">past {{ $rangeLabel }}</span></div>
        </div>
        <div class="pcard-body" id="card-cache"></div>
    </article>

    <article class="pcard span-6" data-card="exceptions">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Exceptions <span class="sub">past {{ $rangeLabel }}</span></div>
            <div class="controls"><span class="muted" style="font-size:.78rem">by count</span></div>
        </div>
        <div class="pcard-body" id="card-exceptions"></div>
    </article>

    <article class="pcard span-6" data-card="slow-requests">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Slow Requests <span class="sub">1000ms+, past {{ $rangeLabel }}</span></div>
            <div class="controls"><span class="muted" style="font-size:.78rem">slowest</span></div>
        </div>
        <div class="pcard-body" id="card-slow-requests"></div>
    </article>

    <article class="pcard span-6" data-card="slow-jobs">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Slow Jobs <span class="sub">past {{ $rangeLabel }}</span></div>
            <div class="controls"><span class="muted" style="font-size:.78rem">slowest</span></div>
        </div>
        <div class="pcard-body" id="card-slow-jobs"></div>
    </article>

    <article class="pcard span-6" data-card="traffic">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Traffic <span class="sub">requests · exceptions</span></div>
            <div class="controls"><span id="project-metrics-status" class="muted" style="font-size:.78rem">loading…</span></div>
        </div>
        <div class="pcard-body">
            <div class="chart-box chart-box-traffic">
                <canvas id="chart-traffic"></canvas>
            </div>
            <div class="grid" style="margin-top:.75rem;grid-template-columns:repeat(4,1fr);gap:.5rem">
                <div class="panel stat" style="margin:0;padding:.65rem"><h3>Requests</h3><p id="project-latest-req" style="font-size:1.2rem">—</p></div>
                <div class="panel stat" style="margin:0;padding:.65rem"><h3>Exceptions</h3><p id="project-latest-ex" style="font-size:1.2rem">—</p></div>
                <div class="panel stat" style="margin:0;padding:.65rem"><h3>Jobs</h3><p id="project-latest-jobs" style="font-size:1.2rem">—</p></div>
                <div class="panel stat" style="margin:0;padding:.65rem"><h3>Avg ms</h3><p id="project-latest-avg" style="font-size:1.2rem">—</p></div>
            </div>
        </div>
    </article>

    <article class="pcard span-12" data-card="slow-queries">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Slow Queries <span class="sub">past {{ $rangeLabel }}</span></div>
            <div class="controls"><span class="muted" style="font-size:.78rem">slowest</span></div>
        </div>
        <div class="pcard-body" id="card-slow-queries"></div>
    </article>

    <article class="pcard span-12" data-card="setup">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Host stats worker <span class="sub">kaveh:check on app servers</span></div>
        </div>
        <div class="pcard-body">
            <p class="muted" style="margin:0 0 .65rem;font-size:.88rem;line-height:1.45">
                For the server strip above, run a Pulse-style worker on each app host:
                <code>php artisan kaveh:check</code> under supervisord.
            </p>
            <div class="onboard-copyrow stack">
                <pre id="kaveh-check-supervisor" data-copy>[program:kaveh-check]
command=php /path/to/your-app/artisan kaveh:check
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your-app/storage/logs/kaveh-check.log</pre>
                <button type="button" class="btn-ghost" id="copy-kaveh-supervisor">Copy</button>
            </div>
        </div>
    </article>

    <article class="pcard span-12" data-card="recent">
        <div class="pcard-head">
            <span class="pcard-drag" title="Drag to move" aria-hidden="true">⠿</span>
            <div class="pcard-title">Recent events <span class="sub"><span id="recent-count">{{ $recent->count() }}</span> shown</span></div>
            <div class="controls">
                <a href="{{ route('kaveh.events.index', ['project_id' => $project?->id, 'range' => $range]) }}">view all</a>
            </div>
        </div>
        <div class="pcard-body" id="recent-events-wrap">
            @include('kaveh::partials.events-table', ['events' => $recent])
        </div>
    </article>
</div>
@endsection

@php
    $insightsUrl = route('kaveh.metrics.insights', [
        'project_id' => $project?->id,
        'range' => $metricsRange,
        'slow_ms' => 1000,
    ]);
    $projectMetricsUrl = route('kaveh.metrics.events', [
        'project_id' => $project?->id,
        'range' => $metricsRange,
        'hours' => $metricsHours,
    ]);
    $liveOverviewUrl = route('kaveh.live.overview', [
        'project_id' => $project?->id,
        'range' => $range,
    ]);
@endphp

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const insightsUrl = @json($insightsUrl);
  const projectUrl = @json($projectMetricsUrl);
  const liveOverviewUrl = @json($liveOverviewUrl);
  const chartColor = () => getComputedStyle(document.documentElement).getPropertyValue('--chart').trim() || '#3dd6c6';
  const muted = () => getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#8b9cb0';
  const danger = () => getComputedStyle(document.documentElement).getPropertyValue('--danger').trim() || '#ff6b6b';
  const warn = () => getComputedStyle(document.documentElement).getPropertyValue('--warn').trim() || '#f4c15d';
  const charts = {};
  const LAYOUT_KEY = 'kaveh.cardOrder.v1';

  const emptyHtml = (label = 'No results') => `
    <div class="pcard-empty">
      <svg width="40" height="18" viewBox="0 0 40 18" fill="none" aria-hidden="true">
        <path d="M1 12 C6 4, 10 16, 15 9 S25 2, 30 10 S36 15, 39 8" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round"/>
      </svg>
      <span>${label}</span>
    </div>`;

  const fmt = (n) => Number(n || 0).toLocaleString();
  const fmtMs = (n) => n == null ? '—' : (n >= 1000 ? (n/1000).toFixed(2)+'s' : Math.round(n)+' ms');
  const fmtGb = (n) => n == null ? '—' : (Number(n).toFixed(Number(n) >= 100 ? 0 : 1) + 'GB');

  function sparkConfig(values, color) {
    return {
      type: 'line',
      data: {
        labels: values.map((_, i) => i),
        datasets: [{
          data: values,
          borderColor: color,
          backgroundColor: color + '22',
          borderWidth: 1.5,
          pointRadius: 0,
          tension: .35,
          fill: true,
        }],
      },
      options: {
        responsive: false,
        animation: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } },
        elements: { line: { borderJoinStyle: 'round' } },
      },
    };
  }

  function upsertSpark(canvas, values, color) {
    if (!canvas) return;
    const id = canvas.id || (canvas.id = 'spark-' + Math.random().toString(36).slice(2));
    if (charts[id]) {
      charts[id].data.datasets[0].data = values;
      charts[id].data.datasets[0].borderColor = color;
      charts[id].data.datasets[0].backgroundColor = color + '22';
      charts[id].update('none');
      return;
    }
    charts[id] = new Chart(canvas.getContext('2d'), sparkConfig(values, color));
  }

  function renderServers(servers) {
    const strip = document.getElementById('servers-strip');
    if (!servers || !servers.length) {
      strip.innerHTML = `<div class="server-chip muted" id="servers-empty">No host stats yet — run <code>php artisan kaveh:check</code> on app servers</div>`;
      return;
    }
    strip.innerHTML = servers.map((s, idx) => {
      const disks = (s.disks || []).slice(0, 3);
      const memLabel = (s.memory_used_mb != null && s.memory_total_mb != null)
        ? `${fmtGb(s.memory_used_mb/1024)} / ${fmtGb(s.memory_total_mb/1024)}`.replace(/GB/g,'GB')
        : (s.memory_percent != null ? `${Number(s.memory_percent).toFixed(0)}%` : '—');
      // nicer mem display as GB when totals look like MB
      const memText = (s.memory_used_mb != null && s.memory_total_mb != null)
        ? `${(s.memory_used_mb/1024).toFixed(1)}GB / ${(s.memory_total_mb/1024).toFixed(1)}GB`
        : memLabel;
      return `
        <div class="server-chip" data-host="${s.hostname}">
          <div class="host"><span class="dot ${s.online ? 'on' : ''}"></span>${s.hostname || 'host'}</div>
          <div class="server-metric">
            <div class="label">CPU</div>
            <div class="value">${s.cpu_percent == null ? '—' : Number(s.cpu_percent).toFixed(0)+'%'}</div>
          </div>
          <canvas class="spark" id="spark-cpu-${idx}" width="72" height="28"></canvas>
          <div class="server-metric">
            <div class="label">Memory</div>
            <div class="value">${memText}</div>
          </div>
          <canvas class="spark" id="spark-mem-${idx}" width="72" height="28"></canvas>
          <div class="disk-rings">
            ${disks.length ? disks.map(d => `
              <div class="disk-ring" style="--p:${Math.min(100, Number(d.percent||0))}" title="${d.path}">
                <span>${fmtGb(d.used_gb)}<br>${fmtGb(d.total_gb)}</span>
              </div>`).join('') : '<span class="muted" style="font-size:.8rem">No disks</span>'}
          </div>
        </div>`;
    }).join('');

    servers.forEach((s, idx) => {
      const cpuVals = (s.series?.cpu || []).map(p => p.v).slice(-40);
      const memVals = (s.series?.memory || []).map(p => p.v).slice(-40);
      upsertSpark(document.getElementById('spark-cpu-'+idx), cpuVals.length ? cpuVals : [0], chartColor());
      upsertSpark(document.getElementById('spark-mem-'+idx), memVals.length ? memVals : [0], chartColor());
    });
  }

  function renderUsers(users) {
    const el = document.getElementById('card-usage');
    if (!users?.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = users.map(u => {
      const label = u.email || u.name || ('User '+u.id);
      const initial = (label[0] || '?').toUpperCase();
      return `<div class="user-row">
        <div class="avatar">${initial}</div>
        <div class="user-meta">
          <div class="email">${label}</div>
          <div class="id">ID: ${u.id ?? '—'}</div>
        </div>
        <div class="user-count">${fmt(u.count)}</div>
      </div>`;
    }).join('');
  }

  function renderQueues(data) {
    const el = document.getElementById('card-queues');
    const queues = data?.queues || [];
    if (!queues.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = `<div class="queues-scroll">${queues.map((q, i) => `
      <div class="queue-row">
        <div class="qname">${q.key}</div>
        <div class="chart-box chart-box-queue">
          <canvas id="queue-chart-${i}"></canvas>
        </div>
      </div>`).join('')}</div>`;
    queues.forEach((q, i) => {
      const canvas = document.getElementById('queue-chart-'+i);
      if (!canvas) return;
      const statuses = Object.keys(q.series || {});
      const labelsSet = new Set();
      statuses.forEach(s => (q.series[s]||[]).forEach(p => labelsSet.add(p.t)));
      const labels = [...labelsSet].sort();
      const colors = { processed: chartColor(), failed: danger(), processing: warn(), queued: muted(), released: '#6ea8fe' };
      const datasets = statuses.map(s => ({
        label: s,
        data: labels.map(t => (q.series[s].find(p => p.t === t)?.v) ?? 0),
        borderColor: colors[s] || chartColor(),
        backgroundColor: 'transparent',
        borderWidth: 1.5,
        pointRadius: 0,
        tension: .3,
      }));
      const id = canvas.id;
      if (charts[id]) { charts[id].destroy(); delete charts[id]; }
      charts[id] = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          plugins: { legend: { display: false } },
          scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
        },
      });
    });
  }

  function renderCache(cache) {
    const el = document.getElementById('card-cache');
    if (!cache?.available) {
      el.innerHTML = emptyHtml(cache?.message || 'No cache metrics');
      return;
    }
    const keys = cache.keys || [];
    el.innerHTML = `
      <div class="cache-stats">
        <div class="stat-box"><h4>Hits</h4><p>${fmt(cache.hits)}</p></div>
        <div class="stat-box"><h4>Misses</h4><p>${fmt(cache.misses)}</p></div>
        <div class="stat-box"><h4>Hit rate</h4><p>${cache.hit_rate == null ? '—' : cache.hit_rate+'%'}</p></div>
      </div>
      ${keys.length ? `<table class="compact"><thead><tr><th>Key</th><th>Hits</th><th>Misses</th><th>Hit rate</th></tr></thead>
        <tbody>${keys.map(k => `<tr>
          <td class="mono" style="max-width:12rem;overflow:hidden;text-overflow:ellipsis">${k.key}</td>
          <td class="num">${fmt(k.hits)}</td>
          <td class="num">${fmt(k.misses)}</td>
          <td class="num">${k.hit_rate == null ? '—' : k.hit_rate+'%'}</td>
        </tr>`).join('')}</tbody></table>` : emptyHtml()}`;
  }

  function renderExceptions(rows) {
    const el = document.getElementById('card-exceptions');
    if (!rows?.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = `<table class="compact"><thead><tr><th>Exception</th><th>Count</th></tr></thead><tbody>
      ${rows.map(r => `<tr>
        <td><div style="font-weight:600">${r.name}</div>
          <div class="muted" style="font-size:.78rem;margin-top:.15rem">${r.message || ''}${r.file ? ' · '+r.file : ''}</div></td>
        <td class="num">${fmt(r.count)}</td>
      </tr>`).join('')}
    </tbody></table>`;
  }

  function renderSlowRequests(rows) {
    const el = document.getElementById('card-slow-requests');
    if (!rows?.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = `<table class="compact"><thead><tr><th>Method / URI</th><th>Count</th><th>Slowest</th></tr></thead><tbody>
      ${rows.map(r => `<tr>
        <td class="mono">${r.name}</td>
        <td class="num">${fmt(r.count)}</td>
        <td class="num">${fmtMs(r.slowest_ms)}</td>
      </tr>`).join('')}
    </tbody></table>`;
  }

  function renderSlowJobs(rows) {
    const el = document.getElementById('card-slow-jobs');
    if (!rows?.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = `<table class="compact"><thead><tr><th>Job</th><th>Count</th><th>Slowest</th></tr></thead><tbody>
      ${rows.map(r => `<tr>
        <td class="mono">${r.name}</td>
        <td class="num">${fmt(r.count)}${r.failed ? ` <span class="badge error">${r.failed} failed</span>` : ''}</td>
        <td class="num">${fmtMs(r.slowest_ms)}</td>
      </tr>`).join('')}
    </tbody></table>`;
  }

  function renderSlowQueries(rows) {
    const el = document.getElementById('card-slow-queries');
    if (!rows?.length) { el.innerHTML = emptyHtml(); return; }
    el.innerHTML = `<table class="compact"><thead><tr><th>Query</th><th>Count</th><th>Slowest</th></tr></thead><tbody>
      ${rows.map(r => `<tr>
        <td><div class="sql">${r.sql}</div>
          ${r.location ? `<div class="muted" style="font-size:.75rem;margin-top:.3rem">${r.location}</div>` : ''}</td>
        <td class="num">${fmt(r.count)}</td>
        <td class="num">${fmtMs(r.slowest_ms)}</td>
      </tr>`).join('')}
    </tbody></table>`;
  }

  function renderTraffic(series) {
    const canvas = document.getElementById('chart-traffic');
    if (!canvas) return;
    const req = series?.request || [];
    const ex = series?.exception || [];
    const labelsSet = new Set([...req, ...ex].map(p => p.t));
    const labels = [...labelsSet].sort();
    const align = (pts) => labels.map(t => pts.find(p => p.t === t)?.v ?? 0);
    const id = 'chart-traffic';
    if (charts[id]) {
      charts[id].data.labels = labels;
      charts[id].data.datasets[0].data = align(req);
      charts[id].data.datasets[1].data = align(ex);
      charts[id].data.datasets[0].borderColor = chartColor();
      charts[id].data.datasets[1].borderColor = danger();
      charts[id].update('none');
      return;
    }
    charts[id] = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Requests', data: align(req), borderColor: chartColor(), borderWidth: 2, pointRadius: 0, tension: .3, fill: false },
          { label: 'Exceptions', data: align(ex), borderColor: danger(), borderWidth: 2, pointRadius: 0, tension: .3, fill: false },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { display: false },
          y: { display: false, beginAtZero: true },
        },
      },
    });
  }

  async function loadInsights() {
    try {
      const res = await fetch(insightsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.available) return;
      renderServers(data.servers);
      renderUsers(data.users);
      renderQueues(data.queues);
      renderCache(data.cache);
      renderExceptions(data.exceptions);
      renderSlowRequests(data.slow_requests);
      renderSlowJobs(data.slow_jobs);
      renderSlowQueries(data.slow_queries);
      renderTraffic(data.traffic);
    } catch (e) {
      console.error(e);
    }
  }

  async function loadProjectStats() {
    const status = document.getElementById('project-metrics-status');
    try {
      const res = await fetch(projectUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      status.textContent = `last ${Math.round((data.range || 3600) / 60)}m`;
      const latest = data.latest || {};
      document.getElementById('project-latest-req').textContent = fmt(latest.requests);
      document.getElementById('project-latest-ex').textContent = fmt(latest.exceptions);
      document.getElementById('project-latest-jobs').textContent = fmt(latest.jobs);
      document.getElementById('project-latest-avg').textContent =
        latest.avg_request_ms == null ? '—' : Number(latest.avg_request_ms).toFixed(1);
    } catch (e) {
      status.textContent = 'failed';
    }
  }

  // Movable cards
  const board = document.getElementById('pulse-board');
  function applyOrder(order) {
    if (!order?.length) return;
    const map = Object.fromEntries([...board.querySelectorAll('[data-card]')].map(el => [el.dataset.card, el]));
    order.forEach(key => { if (map[key]) board.appendChild(map[key]); });
  }
  function saveOrder() {
    const order = [...board.querySelectorAll('[data-card]')].map(el => el.dataset.card);
    localStorage.setItem(LAYOUT_KEY, JSON.stringify(order));
  }
  try {
    applyOrder(JSON.parse(localStorage.getItem(LAYOUT_KEY) || '[]'));
  } catch (_) {}

  let dragEl = null;
  board.querySelectorAll('[data-card]').forEach(card => {
    const handle = card.querySelector('.pcard-drag');
    if (handle) {
      handle.setAttribute('draggable', 'true');
      handle.addEventListener('dragstart', (e) => {
        dragEl = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.dataset.card);
        try { e.dataTransfer.setDragImage(card, 24, 24); } catch (_) {}
      });
      handle.addEventListener('dragend', () => {
        card.classList.remove('dragging');
        dragEl = null;
        saveOrder();
      });
    }
    card.addEventListener('dragover', (e) => {
      e.preventDefault();
      if (!dragEl || dragEl === card) return;
      const rect = card.getBoundingClientRect();
      const before = (e.clientY - rect.top) < rect.height / 2;
      board.insertBefore(dragEl, before ? card : card.nextSibling);
    });
  });
  document.getElementById('reset-card-layout')?.addEventListener('click', () => {
    localStorage.removeItem(LAYOUT_KEY);
    location.reload();
  });

  document.getElementById('copy-kaveh-supervisor')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const text = document.getElementById('kaveh-check-supervisor')?.textContent?.trim() || '';
    try { await navigator.clipboard.writeText(text); btn.textContent = 'Copied'; setTimeout(() => btn.textContent = 'Copy', 1400); } catch (_) {}
  });

  async function refreshLivePage() {
    try {
      const res = await fetch(liveOverviewUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (data.range_label) document.getElementById('overview-range-label').textContent = data.range_label;
      if (data.project) document.getElementById('overview-project-label').textContent = data.project;
      document.getElementById('recent-count').textContent = data.recent_count ?? 0;
      document.getElementById('recent-events-wrap').innerHTML = data.recent_html || '';
      const stamp = document.getElementById('live-refreshed-at');
      if (stamp && data.refreshed_at) stamp.textContent = 'updated ' + new Date(data.refreshed_at).toLocaleTimeString();
    } catch (e) { console.error(e); }
    await Promise.all([loadInsights(), loadProjectStats()]);
  }

  loadInsights();
  loadProjectStats();
  if (window.KavehLive) window.KavehLive.on(refreshLivePage);
  setInterval(() => { if (!window.KavehLive?.enabled) { loadInsights(); loadProjectStats(); } }, 60000);
  window.addEventListener('kaveh:theme', () => { loadInsights(); loadProjectStats(); });
})();
</script>
@endsection
