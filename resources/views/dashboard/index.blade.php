@extends('kaveh::layouts.app')
@section('title', 'Overview — Kaveh')
@section('content')
<div class="row" style="justify-content:space-between;margin-bottom:1rem">
    <h1 style="margin:0">Overview <span style="color:var(--muted);font-size:1rem">last 24h</span></h1>
    @if($projects->isNotEmpty())
    <form method="get">
        <select name="project_id" onchange="this.form.submit()">
            @foreach($projects as $p)
                <option value="{{ $p->id }}" @selected($project?->id === $p->id)>{{ $p->name }}</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

<div class="grid">
    <div class="panel stat"><h3>Events</h3><p>{{ number_format($stats['total']) }}</p></div>
    <div class="panel stat"><h3>Exceptions</h3><p>{{ number_format($stats['exceptions']) }}</p></div>
    <div class="panel stat"><h3>Failed jobs</h3><p>{{ number_format($stats['failed_jobs']) }}</p></div>
    <div class="panel stat"><h3>Slow queries</h3><p>{{ number_format($stats['slow_queries']) }}</p></div>
    <div class="panel stat"><h3>Avg request ms</h3><p>{{ $stats['avg_request_ms'] ? number_format($stats['avg_request_ms'], 1) : '—' }}</p></div>
</div>

<div class="panel">
    <div class="row" style="justify-content:space-between;margin-bottom:.75rem">
        <h2 style="margin:0">Pulse graphs</h2>
        <span id="pulse-status" style="color:var(--muted);font-size:.85rem">loading…</span>
    </div>
    <div class="grid" style="margin-bottom:1rem">
        <div class="panel stat" style="margin:0"><h3>CPU</h3><p id="pulse-latest-cpu">—</p></div>
        <div class="panel stat" style="margin:0"><h3>Memory</h3><p id="pulse-latest-mem">—</p></div>
        <div class="panel stat" style="margin:0"><h3>Requests (window)</h3><p id="pulse-latest-req">—</p></div>
        <div class="panel stat" style="margin:0"><h3>Exceptions (window)</h3><p id="pulse-latest-ex">—</p></div>
    </div>
    <div class="grid" style="grid-template-columns:1fr 1fr">
        <div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">CPU % <span style="opacity:.7">·</span> Memory MB</h3>
            <canvas id="chart-pulse-system" height="140"></canvas>
        </div>
        <div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">Requests / Exceptions</h3>
            <canvas id="chart-pulse-traffic" height="140"></canvas>
        </div>
        <div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">Queue</h3>
            <canvas id="chart-pulse-queue" height="140"></canvas>
        </div>
        <div>
            <h3 style="margin:0 0 .5rem;color:var(--muted);font-size:.85rem;font-weight:500">Cache hit / miss</h3>
            <canvas id="chart-pulse-cache" height="140"></canvas>
        </div>
    </div>
</div>

<div class="panel">
    <div class="row" style="justify-content:space-between;margin-bottom:.75rem">
        <h2 style="margin:0">Kaveh events</h2>
        <span style="color:var(--muted);font-size:.85rem">via API · hourly</span>
    </div>
    <canvas id="chart-kaveh-events" height="110"></canvas>
</div>

<div class="panel">
    <h2>By type</h2>
    @forelse($byType as $type => $count)
        <span class="badge" style="margin:.2rem">{{ $type }}: {{ $count }}</span>
    @empty
        <p style="color:var(--muted)">No events yet. Point a client at <code>/api/v1/ingest</code>.</p>
    @endforelse
</div>

<div class="panel">
    <h2>Recent events</h2>
    <table>
        <thead><tr><th>When</th><th>Type</th><th>Name</th><th>Level</th></tr></thead>
        <tbody>
        @foreach($recent as $event)
            <tr>
                <td>{{ optional($event->occurred_at)?->diffForHumans() }}</td>
                <td><span class="badge">{{ $event->type }}</span></td>
                <td><a href="{{ route('kaveh.events.show', $event) }}">{{ $event->name }}</a></td>
                <td><span class="badge {{ $event->level }}">{{ $event->level }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const pulseUrl = @json(route('kaveh.metrics.pulse', ['period' => 60, 'range' => 3600]));
  const eventsUrl = @json(route('kaveh.metrics.events', ['hours' => 24, 'project_id' => $project?->id]));
  const accent = '#3dd6c6';
  const danger = '#ff6b6b';
  const warn = '#f4c15d';
  const blue = '#6ea8fe';
  const muted = '#8ea0b5';
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
    if (charts[id]) {
      charts[id].data.labels = config.data.labels;
      charts[id].data.datasets = config.data.datasets;
      charts[id].update('none');
      return charts[id];
    }
    charts[id] = new Chart(document.getElementById(id), config);
    return charts[id];
  }

  async function loadPulse() {
    const status = document.getElementById('pulse-status');
    try {
      const res = await fetch(pulseUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const data = await res.json();
      if (!data.available) {
        status.textContent = data.message || 'Pulse unavailable';
        return;
      }
      const mins = Math.round((data.range || 3600) / 60);
      status.textContent = `last ${mins}m · ${data.period}s buckets`;

      const latest = data.latest || {};
      document.getElementById('pulse-latest-cpu').textContent =
        latest.cpu == null ? '—' : `${Number(latest.cpu).toFixed(1)}%`;
      document.getElementById('pulse-latest-mem').textContent =
        latest.memory_mb == null ? '—' : `${Number(latest.memory_mb).toFixed(1)} MB`;
      document.getElementById('pulse-latest-req').textContent =
        latest.requests == null ? '—' : Number(latest.requests).toLocaleString();
      document.getElementById('pulse-latest-ex').textContent =
        latest.exceptions == null ? '—' : Number(latest.exceptions).toLocaleString();

      const s = data.series || {};
      const sysRaw = labelsFrom([s.cpu, s.memory]);
      upsert('chart-pulse-system', {
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
      upsert('chart-pulse-traffic', {
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
      upsert('chart-pulse-queue', {
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
      upsert('chart-pulse-cache', {
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
      status.textContent = 'Failed to load Pulse API';
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
      upsert('chart-kaveh-events', {
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

  loadPulse();
  loadEvents();
  setInterval(() => { loadPulse(); loadEvents(); }, 60000);
})();
</script>
@endsection
