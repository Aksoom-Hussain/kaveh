@extends('kaveh::layouts.app')
@section('title', 'Overview — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem;align-items:center">
    <h1 style="margin:0">Overview <span style="color:var(--muted);font-size:1rem;font-family:'DM Sans',sans-serif">past {{ $rangeLabel }}</span></h1>
</div>

<div class="grid" style="margin-bottom:1.25rem">
    <div class="panel stat"><h3>Events</h3><p>{{ number_format($stats['total']) }}</p></div>
    <div class="panel stat"><h3>Exceptions</h3><p>{{ number_format($stats['exceptions']) }}</p></div>
    <div class="panel stat"><h3>Failed jobs</h3><p>{{ number_format($stats['failed_jobs']) }}</p></div>
    <div class="panel stat"><h3>Slow queries</h3><p>{{ number_format($stats['slow_queries']) }}</p></div>
    <div class="panel stat"><h3>Avg request ms</h3><p>{{ $stats['avg_request_ms'] ? number_format($stats['avg_request_ms'], 1) : '—' }}</p></div>
</div>

<div class="row" style="justify-content:space-between;align-items:baseline;margin-bottom:.65rem">
    <h2 style="margin:0">Graphs</h2>
    <span id="metrics-status" style="color:var(--muted);font-size:.85rem">loading…</span>
</div>

<div class="grid" style="margin-bottom:1rem">
    <div class="panel stat" style="margin:0"><h3>CPU</h3><p id="metrics-latest-cpu">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Memory</h3><p id="metrics-latest-mem">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Requests (window)</h3><p id="metrics-latest-req">—</p></div>
    <div class="panel stat" style="margin:0"><h3>Exceptions (window)</h3><p id="metrics-latest-ex">—</p></div>
</div>

<div class="accordion">
    <details class="acc" open>
        <summary>
            System
            <span class="acc-meta">CPU · Memory</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-system" height="140"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            Traffic
            <span class="acc-meta">Requests · Exceptions</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-traffic" height="140"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            Queue
            <span class="acc-meta">Queued · Processing · Processed</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-queue" height="140"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            Cache
            <span class="acc-meta">Hits · Misses</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-cache" height="140"></canvas>
        </div>
    </details>

    <details class="acc" open>
        <summary>
            Events
            <span class="acc-meta">via API · hourly</span>
        </summary>
        <div class="acc-body">
            <canvas id="chart-events" height="110"></canvas>
        </div>
    </details>

    <details class="acc">
        <summary>
            By type
            <span class="acc-meta">{{ $byType->count() }} types</span>
        </summary>
        <div class="acc-body">
            @forelse($byType as $type => $count)
                <span class="badge type-{{ $type }}" style="margin:.2rem">{{ $type }}: {{ $count }}</span>
            @empty
                <p style="color:var(--muted);margin:0">No events yet. Point a client at <code>/api/v1/ingest</code>.</p>
            @endforelse
        </div>
    </details>

    <details class="acc" open>
        <summary>
            Recent events
            <span class="acc-meta">{{ $recent->count() }} shown · <a href="{{ route('kaveh.events.index', ['project_id' => $project?->id, 'range' => $range]) }}" onclick="event.stopPropagation()">view all</a></span>
        </summary>
        <div class="acc-body">
            @include('kaveh::partials.events-table', ['events' => $recent])
        </div>
    </details>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const metricsUrl = @json(route('kaveh.metrics.pulse', ['period' => $metricsPeriod, 'range' => $metricsRange]));
  const eventsUrl = @json(route('kaveh.metrics.events', ['hours' => $metricsHours, 'project_id' => $project?->id]));
  const accent = '#3dd6c6';
  const danger = '#ff6b6b';
  const warn = '#f4c15d';
  const blue = '#6ea8fe';
  const muted = '#8b9cb0';
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
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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
      requestAnimationFrame(() => {
        Object.values(charts).forEach((c) => c.resize());
      });
    });
  });

  async function loadMetrics() {
    const status = document.getElementById('metrics-status');
    try {
      const res = await fetch(metricsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.available) {
        status.textContent = data.message || 'Metrics unavailable';
        return;
      }
      const mins = Math.round((data.range || 3600) / 60);
      status.textContent = `last ${mins}m · ${data.period}s buckets`;

      const latest = data.latest || {};
      document.getElementById('metrics-latest-cpu').textContent =
        latest.cpu == null ? '—' : `${Number(latest.cpu).toFixed(1)}%`;
      document.getElementById('metrics-latest-mem').textContent =
        latest.memory_mb == null ? '—' : `${Number(latest.memory_mb).toFixed(1)} MB`;
      document.getElementById('metrics-latest-req').textContent =
        latest.requests == null ? '—' : Number(latest.requests).toLocaleString();
      document.getElementById('metrics-latest-ex').textContent =
        latest.exceptions == null ? '—' : Number(latest.exceptions).toLocaleString();

      const s = data.series || {};
      const sysRaw = labelsFrom([s.cpu, s.memory]);
      upsert('chart-system', {
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

      const trafRaw = labelsFrom([s.user_request, s.exception]);
      upsert('chart-traffic', {
        type: 'line',
        data: {
          labels: trafRaw.map(short),
          datasets: [
            ds('Requests / min', align(trafRaw, s.user_request), accent),
            ds('Exceptions / min', align(trafRaw, s.exception), danger),
          ],
        },
        options: baseOptions,
      });

      const qRaw = labelsFrom([s.queued, s.processing, s.processed]);
      upsert('chart-queue', {
        type: 'line',
        data: {
          labels: qRaw.map(short),
          datasets: [
            ds('Queued', align(qRaw, s.queued), warn),
            ds('Processing', align(qRaw, s.processing), blue),
            ds('Processed', align(qRaw, s.processed), accent),
          ],
        },
        options: baseOptions,
      });

      const cRaw = labelsFrom([s.cache_hit, s.cache_miss]);
      upsert('chart-cache', {
        type: 'line',
        data: {
          labels: cRaw.map(short),
          datasets: [
            ds('Hits', align(cRaw, s.cache_hit), accent),
            ds('Misses', align(cRaw, s.cache_miss), danger),
          ],
        },
        options: baseOptions,
      });
    } catch (e) {
      status.textContent = 'Failed to load metrics';
      console.error(e);
    }
  }

  async function loadEvents() {
    try {
      const res = await fetch(eventsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      const s = data.series || {};
      const types = Object.keys(s);
      const raw = labelsFrom(types.map(t => s[t]));
      const colors = [accent, danger, warn, blue, '#c084fc', '#fb7185', '#34d399'];
      upsert('chart-events', {
        type: 'line',
        data: {
          labels: raw.map(short),
          datasets: types.map((t, i) => ds(t, align(raw, s[t]), colors[i % colors.length])),
        },
        options: baseOptions,
      });
    } catch (e) {
      console.error(e);
    }
  }

  loadMetrics();
  loadEvents();
  setInterval(() => { loadMetrics(); loadEvents(); }, 60000);
})();
</script>
@endsection
