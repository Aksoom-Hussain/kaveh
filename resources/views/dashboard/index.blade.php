@extends('kaveh::layouts.app')
@section('title', 'Overview — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem;align-items:center">
    <h1 style="margin:0">
        Overview
        <span style="color:var(--muted);font-size:1rem;font-family:'DM Sans',sans-serif">
            <span id="overview-project-label">{{ $project?->name ?? 'No project' }}</span>
            · past <span id="overview-range-label">{{ $rangeLabel }}</span>
        </span>
    </h1>
    <span id="live-refreshed-at" class="muted" style="font-size:.8rem"></span>
</div>

<div class="grid" style="margin-bottom:1.25rem" id="overview-stats">
    <div class="panel stat"><h3>Events</h3><p id="stat-total">{{ number_format($stats['total']) }}</p></div>
    <div class="panel stat"><h3>Exceptions</h3><p id="stat-exceptions">{{ number_format($stats['exceptions']) }}</p></div>
    <div class="panel stat"><h3>Failed jobs</h3><p id="stat-failed-jobs">{{ number_format($stats['failed_jobs']) }}</p></div>
    <div class="panel stat"><h3>Slow queries</h3><p id="stat-slow-queries">{{ number_format($stats['slow_queries']) }}</p></div>
    <div class="panel stat"><h3>Avg request ms</h3><p id="stat-avg-request">{{ $stats['avg_request_ms'] ? number_format($stats['avg_request_ms'], 1) : '—' }}</p></div>
</div>

<div class="row" style="justify-content:space-between;align-items:baseline;margin-bottom:.65rem">
    <h2 style="margin:0">Project graphs <span style="color:var(--muted);font-weight:400;font-size:.9rem">{{ $project?->name }}</span></h2>
    <span id="project-metrics-status" style="color:var(--muted);font-size:.85rem">loading…</span>
</div>

<div class="grid" style="margin-bottom:1rem">
    <div class="panel stat" style="margin:0"><h3>Requests</h3><p id="project-latest-req">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Exceptions</h3><p id="project-latest-ex">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Jobs</h3><p id="project-latest-jobs">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Slow queries</h3><p id="project-latest-queries">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Avg request ms</h3><p id="project-latest-avg">—</p></div>
</div>

<div class="accordion">
    <details class="acc" open>
        <summary>
            Traffic
            <span class="acc-meta">{{ $project?->name }} · requests · exceptions</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-project-traffic" height="140"></canvas>
        </div>
    </details>

    <details class="acc" open>
        <summary>
            Workload
            <span class="acc-meta">jobs · slow queries · custom</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-project-workload" height="140"></canvas>
        </div>
    </details>

    <details class="acc" open>
        <summary>
            Request latency
            <span class="acc-meta">avg duration ms</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-project-latency" height="110"></canvas>
        </div>
    </details>

    <details class="acc" open>
        <summary>
            All event types
            <span class="acc-meta">{{ $project?->name }} timeseries</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-project-events" height="110"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            By type
            <span class="acc-meta"><span id="by-type-count">{{ $byType->count() }}</span> types</span>
        </summary>
        <div class="acc-body" id="by-type-wrap">
            @include('kaveh::partials.by-type', ['byType' => $byType])
        </div>
    </details>

    <details class="acc" open>
        <summary>
            Recent events
            <span class="acc-meta"><span id="recent-count">{{ $recent->count() }}</span> shown · <a href="{{ route('kaveh.events.index', ['project_id' => $project?->id, 'range' => $range]) }}" onclick="event.stopPropagation()">view all</a></span>
        </summary>
        <div class="acc-body" id="recent-events-wrap">
            @include('kaveh::partials.events-table', ['events' => $recent])
        </div>
    </details>

    <details class="acc" open>
        <summary>
            App hosts
            <span class="acc-meta">{{ $project?->name }} · CPU · memory · disk via kaveh:check</span>
        </summary>
        <div class="acc-body">
            <div class="panel" style="margin:0 0 1rem;border-style:dashed">
                <h3 style="margin-top:0">Remote server stats (Pulse-style worker)</h3>
                <p class="muted" style="margin:0 0 .75rem;font-size:.9rem;line-height:1.45">
                    On each application server (not this monitor), keep a long-running <code>kaveh:check</code> process —
                    same idea as Laravel Pulse’s <code>pulse:check</code>. It samples CPU, memory, and disk and ships them here.
                </p>
                <div class="onboard-copyrow stack" style="margin-bottom:.75rem">
                    <pre id="kaveh-check-cmd" data-copy>php artisan kaveh:check</pre>
                    <button type="button" class="btn-ghost" id="copy-kaveh-check">Copy</button>
                </div>
                <p class="muted" style="margin:0 0 .5rem;font-size:.85rem">Supervisord example:</p>
                <div class="onboard-copyrow stack" style="margin-bottom:.75rem">
                    <pre id="kaveh-check-supervisor" data-copy>[program:kaveh-check]
process_name=%(program_name)s
command=php /path/to/your-app/artisan kaveh:check
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your-app/storage/logs/kaveh-check.log
stopwaitsecs=5</pre>
                    <button type="button" class="btn-ghost" id="copy-kaveh-supervisor">Copy</button>
                </div>
                <p class="muted" style="margin:0;font-size:.85rem">
                    Or schedule once a minute: <code>* * * * * php artisan kaveh:check --once</code>
                    · Optional env: <code>KAVEH_CHECK_INTERVAL=15</code>, <code>KAVEH_CHECK_DISKS=/,/var</code>
                </p>
            </div>

            <div class="row" style="justify-content:space-between;align-items:baseline;margin-bottom:.65rem">
                <span id="system-metrics-status" style="color:var(--muted);font-size:.85rem">loading…</span>
                <span id="system-hostname" style="color:var(--muted);font-size:.85rem"></span>
            </div>
            <div class="grid" style="margin-bottom:1rem">
                <div class="panel stat" style="margin:0"><h3>CPU</h3><p id="system-latest-cpu">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Memory</h3><p id="system-latest-mem">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Disk</h3><p id="system-latest-disk">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Free disk</h3><p id="system-latest-disk-free">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Load 1m</h3><p id="system-latest-load">—</p></div>
            </div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">CPU % · Memory % · Disk %</h3>
            <canvas id="chart-system-usage" height="140"></canvas>
            <h3 style="margin:1rem 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">Disk used GB</h3>
            <canvas id="chart-system-disk" height="100"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            Monitor host
            <span class="acc-meta">this Kaveh server · not {{ $project?->name }}</span>
        </summary>
        <div class="acc-body">
            <p class="muted" style="margin:0 0 1rem;font-size:.85rem">
                CPU / memory / Pulse queue charts below are from <strong>this monitor server</strong>, not from the selected project’s application hosts.
            </p>
            <div class="row" style="justify-content:space-between;align-items:baseline;margin-bottom:.65rem">
                <span id="host-metrics-status" style="color:var(--muted);font-size:.85rem">loading…</span>
            </div>
            <div class="grid" style="margin-bottom:1rem">
                <div class="panel stat" style="margin:0"><h3>Host CPU</h3><p id="host-latest-cpu">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Host memory</h3><p id="host-latest-mem">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Host requests</h3><p id="host-latest-req">—</p></div>
                <div class="panel stat" style="margin:0"><h3>Host exceptions</h3><p id="host-latest-ex">—</p></div>
            </div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">CPU % · Memory MB</h3>
            <canvas id="chart-host-system" height="140"></canvas>
        </div>
    </details>
</div>
@endsection

@php
    $projectMetricsUrl = route('kaveh.metrics.events', [
        'project_id' => $project?->id,
        'range' => $metricsRange,
        'hours' => $metricsHours,
    ]);
    $hostMetricsUrl = route('kaveh.metrics.pulse', [
        'period' => $metricsPeriod,
        'range' => $metricsRange,
    ]);
    $systemMetricsUrl = route('kaveh.metrics.system', [
        'project_id' => $project?->id,
        'range' => $metricsRange,
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
  const projectName = @json($project?->name ?? 'Project');
  const projectUrl = @json($projectMetricsUrl);
  const hostUrl = @json($hostMetricsUrl);
  const systemUrl = @json($systemMetricsUrl);
  const liveOverviewUrl = @json($liveOverviewUrl);
  const accent = '#3dd6c6';
  const danger = '#ff6b6b';
  const warn = '#f4c15d';
  const blue = '#6ea8fe';
  const muted = '#8b9cb0';
  const purple = '#c084fc';
  const charts = {};

  const baseOptions = {
    responsive: true,
    animation: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { color: muted, boxWidth: 12 } } },
    scales: {
      x: { ticks: { color: muted, maxTicksLimit: 8 }, grid: { color: 'rgba(42,53,66,.6)' } },
      y: { ticks: { color: muted }, grid: { color: 'rgba(42,53,66,.6)' }, beginAtZero: true },
    },
  };

  const ds = (label, data, color, extra = {}) => ({
    label, data, borderColor: color, backgroundColor: color + '33',
    tension: .25, fill: false, pointRadius: 0, borderWidth: 2, spanGaps: true, ...extra,
  });

  const labelsFrom = (seriesList) => {
    const set = new Set();
    seriesList.forEach(s => (s || []).forEach(p => set.add(p.t)));
    return [...set].sort();
  };
  const align = (labels, points, fillZero = true) => {
    const map = Object.fromEntries((points || []).map(p => [p.t, p.v]));
    return labels.map(t => (t in map) ? map[t] : (fillZero ? 0 : null));
  };
  const short = (iso) => {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  function upsert(id, config) {
    const el = document.getElementById(id);
    if (!el) return null;
    if (charts[id]) {
      charts[id].data.labels = config.data.labels;
      charts[id].data.datasets = config.data.datasets;
      charts[id].update('none');
      return charts[id];
    }
    charts[id] = new Chart(el, config);
    return charts[id];
  }

  document.querySelectorAll('details.acc').forEach((det) => {
    det.addEventListener('toggle', () => {
      if (!det.open) return;
      requestAnimationFrame(() => Object.values(charts).forEach((c) => c.resize()));
    });
  });

  async function loadProject() {
    const status = document.getElementById('project-metrics-status');
    try {
      const res = await fetch(projectUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      const mins = Math.round((data.range || 3600) / 60);
      status.textContent = `${projectName} · last ${mins}m · ${data.bucket || 'hour'} buckets`;

      const latest = data.latest || {};
      document.getElementById('project-latest-req').textContent =
        Number(latest.requests || 0).toLocaleString();
      document.getElementById('project-latest-ex').textContent =
        Number(latest.exceptions || 0).toLocaleString();
      document.getElementById('project-latest-jobs').textContent =
        Number(latest.jobs || 0).toLocaleString();
      document.getElementById('project-latest-queries').textContent =
        Number(latest.queries || 0).toLocaleString();
      document.getElementById('project-latest-avg').textContent =
        latest.avg_request_ms == null ? '—' : Number(latest.avg_request_ms).toFixed(1);

      const s = data.series || {};
      const avg = data.avg_duration_ms || {};

      const trafRaw = labelsFrom([s.request, s.exception]);
      upsert('chart-project-traffic', {
        type: 'line',
        data: {
          labels: trafRaw.map(short),
          datasets: [
            ds('Requests', align(trafRaw, s.request), accent),
            ds('Exceptions', align(trafRaw, s.exception), danger),
          ],
        },
        options: baseOptions,
      });

      const workRaw = labelsFrom([s.job, s.query, s.custom, s.log]);
      upsert('chart-project-workload', {
        type: 'line',
        data: {
          labels: workRaw.map(short),
          datasets: [
            ds('Jobs', align(workRaw, s.job), warn),
            ds('Slow queries', align(workRaw, s.query), blue),
            ds('Custom', align(workRaw, s.custom), purple),
            ds('Logs', align(workRaw, s.log), muted),
          ],
        },
        options: baseOptions,
      });

      const latRaw = labelsFrom([avg.request]);
      upsert('chart-project-latency', {
        type: 'line',
        data: {
          labels: latRaw.map(short),
          datasets: [
            ds('Avg request ms', align(latRaw, avg.request, false), accent),
          ],
        },
        options: baseOptions,
      });

      const types = Object.keys(s);
      const allRaw = labelsFrom(types.map(t => s[t]));
      const colors = [accent, danger, warn, blue, purple, '#fb7185', '#34d399'];
      upsert('chart-project-events', {
        type: 'line',
        data: {
          labels: allRaw.map(short),
          datasets: types.map((t, i) => ds(t, align(allRaw, s[t]), colors[i % colors.length])),
        },
        options: baseOptions,
      });
    } catch (e) {
      status.textContent = 'Failed to load project metrics';
      console.error(e);
    }
  }

  async function loadSystem() {
    const status = document.getElementById('system-metrics-status');
    if (!status) return;
    try {
      const res = await fetch(systemUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      const hostLabel = document.getElementById('system-hostname');
      if (!data.available) {
        status.textContent = data.message || 'No remote host stats';
        if (hostLabel) hostLabel.textContent = '';
        ['system-latest-cpu','system-latest-mem','system-latest-disk','system-latest-disk-free','system-latest-load']
          .forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '—'; });
        return;
      }
      status.textContent = `app hosts · last ${Math.round((data.range || 3600) / 60)}m · ${(data.hosts || []).length} host(s)`;
      if (hostLabel) hostLabel.textContent = data.hostname ? `showing ${data.hostname}` : '';
      const latest = data.latest || {};
      document.getElementById('system-latest-cpu').textContent =
        latest.cpu_percent == null ? '—' : `${Number(latest.cpu_percent).toFixed(1)}%`;
      document.getElementById('system-latest-mem').textContent =
        latest.memory_percent == null ? '—' : `${Number(latest.memory_percent).toFixed(1)}%` +
          (latest.memory_used_mb != null ? ` · ${Number(latest.memory_used_mb).toFixed(0)} MB` : '');
      document.getElementById('system-latest-disk').textContent =
        latest.disk_percent == null ? '—' : `${Number(latest.disk_percent).toFixed(1)}%`;
      document.getElementById('system-latest-disk-free').textContent =
        latest.disk_free_gb == null ? '—' : `${Number(latest.disk_free_gb).toFixed(1)} GB`;
      document.getElementById('system-latest-load').textContent =
        latest.load_1 == null ? '—' : Number(latest.load_1).toFixed(2);

      const s = data.series || {};
      const usageRaw = labelsFrom([s.cpu, s.memory, s.disk]);
      upsert('chart-system-usage', {
        type: 'line',
        data: {
          labels: usageRaw.map(short),
          datasets: [
            ds('CPU %', align(usageRaw, s.cpu, false), accent),
            ds('Memory %', align(usageRaw, s.memory, false), blue),
            ds('Disk %', align(usageRaw, s.disk, false), warn),
          ],
        },
        options: {
          ...baseOptions,
          scales: {
            x: baseOptions.scales.x,
            y: { ...baseOptions.scales.y, max: 100, title: { display: true, text: '%', color: muted } },
          },
        },
      });

      const diskRaw = labelsFrom([s.disk_used_gb]);
      upsert('chart-system-disk', {
        type: 'line',
        data: {
          labels: diskRaw.map(short),
          datasets: [
            ds('Disk used GB', align(diskRaw, s.disk_used_gb, false), purple),
          ],
        },
        options: baseOptions,
      });
    } catch (e) {
      status.textContent = 'Failed to load app host stats';
      console.error(e);
    }
  }

  document.getElementById('copy-kaveh-check')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const text = document.getElementById('kaveh-check-cmd')?.textContent?.trim() || '';
    try { await navigator.clipboard.writeText(text); btn.textContent = 'Copied'; setTimeout(() => btn.textContent = 'Copy', 1400); } catch (_) {}
  });
  document.getElementById('copy-kaveh-supervisor')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    const text = document.getElementById('kaveh-check-supervisor')?.textContent?.trim() || '';
    try { await navigator.clipboard.writeText(text); btn.textContent = 'Copied'; setTimeout(() => btn.textContent = 'Copy', 1400); } catch (_) {}
  });

  async function loadHost() {
    const status = document.getElementById('host-metrics-status');
    if (!status) return;
    try {
      const res = await fetch(hostUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.available) {
        status.textContent = data.message || 'Host Pulse unavailable';
        return;
      }
      status.textContent = `monitor host · last ${Math.round((data.range || 3600) / 60)}m`;
      const latest = data.latest || {};
      document.getElementById('host-latest-cpu').textContent =
        latest.cpu == null ? '—' : `${Number(latest.cpu).toFixed(1)}%`;
      document.getElementById('host-latest-mem').textContent =
        latest.memory_mb == null ? '—' : `${Number(latest.memory_mb).toFixed(1)} MB`;
      document.getElementById('host-latest-req').textContent =
        latest.requests == null ? '—' : Number(latest.requests).toLocaleString();
      document.getElementById('host-latest-ex').textContent =
        latest.exceptions == null ? '—' : Number(latest.exceptions).toLocaleString();

      const s = data.series || {};
      const sysRaw = labelsFrom([s.cpu, s.memory]);
      upsert('chart-host-system', {
        type: 'line',
        data: {
          labels: sysRaw.map(short),
          datasets: [
            ds('CPU %', align(sysRaw, s.cpu, false), accent, { yAxisID: 'y' }),
            ds('Memory MB', align(sysRaw, s.memory, false), blue, { yAxisID: 'y1' }),
          ],
        },
        options: {
          ...baseOptions,
          scales: {
            x: baseOptions.scales.x,
            y: { ...baseOptions.scales.y, position: 'left', title: { display: true, text: 'CPU %', color: muted }, max: 100 },
            y1: { ...baseOptions.scales.y, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'MB', color: muted } },
          },
        },
      });
    } catch (e) {
      status.textContent = 'Failed to load host metrics';
      console.error(e);
    }
  }

  loadProject();
  loadSystem();
  loadHost();

  async function refreshLivePage() {
    try {
      const res = await fetch(liveOverviewUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      const s = data.stats || {};
      const fmt = (n) => Number(n || 0).toLocaleString();
      document.getElementById('stat-total').textContent = fmt(s.total);
      document.getElementById('stat-exceptions').textContent = fmt(s.exceptions);
      document.getElementById('stat-failed-jobs').textContent = fmt(s.failed_jobs);
      document.getElementById('stat-slow-queries').textContent = fmt(s.slow_queries);
      document.getElementById('stat-avg-request').textContent =
        s.avg_request_ms == null ? '—' : Number(s.avg_request_ms).toFixed(1);
      if (data.range_label) {
        document.getElementById('overview-range-label').textContent = data.range_label;
      }
      if (data.project) {
        document.getElementById('overview-project-label').textContent = data.project;
      }
      const byType = data.by_type || {};
      document.getElementById('by-type-count').textContent = Object.keys(byType).length;
      document.getElementById('by-type-wrap').innerHTML = data.by_type_html || '';
      document.getElementById('recent-count').textContent = data.recent_count ?? 0;
      document.getElementById('recent-events-wrap').innerHTML = data.recent_html || '';
      const stamp = document.getElementById('live-refreshed-at');
      if (stamp && data.refreshed_at) {
        stamp.textContent = 'updated ' + new Date(data.refreshed_at).toLocaleTimeString();
      }
    } catch (e) {
      console.error(e);
    }
    await Promise.all([loadProject(), loadSystem(), loadHost()]);
  }

  // When Live is on, poll every 5s via topbar toggle. When off, keep a slow chart refresh.
  if (window.KavehLive) {
    window.KavehLive.on(refreshLivePage);
  }
  setInterval(() => {
    if (!window.KavehLive?.enabled) {
      loadProject();
      loadSystem();
      loadHost();
    }
  }, 60000);
})();
</script>
@endsection
