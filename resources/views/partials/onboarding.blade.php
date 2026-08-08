@php
    $ob = session('kaveh_onboarding');
@endphp
@if($ob)
@php
    $serverUrl = $ob['server_url'];
    $apiKey = $ob['api_key'];
    $package = $ob['package'] ?? 'aksoom-hussain/kaveh';
    $installCmd = "composer require {$package}\nphp artisan kaveh:install --role=client --mode=remote \\\n  --server-url={$serverUrl} \\\n  --api-key={$apiKey} \\\n  --non-interactive\nphp artisan queue:work\nphp artisan kaveh:check";
@endphp
<div class="onboard-overlay" id="kaveh-onboarding" role="dialog" aria-modal="true" aria-labelledby="kaveh-onboard-title">
    <div class="onboard-modal">
        <div class="onboard-head">
            <div>
                <p class="onboard-eyebrow">Connect your app</p>
                <h2 id="kaveh-onboard-title">Set up {{ $ob['project_name'] }}</h2>
                <p class="onboard-lead">Run these in the Laravel project you want to monitor. Copy the API key now — it is shown only once.</p>
            </div>
            <form method="post" action="{{ route('kaveh.onboarding.dismiss') }}">
                @csrf
                <button type="submit" class="onboard-close" aria-label="Dismiss">×</button>
            </form>
        </div>

        <div class="onboard-grid">
            <div class="onboard-field">
                <label>Server URL</label>
                <div class="onboard-copyrow">
                    <code data-copy>{{ $serverUrl }}</code>
                    <button type="button" class="btn-ghost" data-copy-btn>Copy</button>
                </div>
            </div>
            <div class="onboard-field">
                <label>API key <span class="badge">once</span></label>
                <div class="onboard-copyrow">
                    <code data-copy>{{ $apiKey }}</code>
                    <button type="button" class="btn-ghost" data-copy-btn>Copy</button>
                </div>
            </div>
        </div>

        <div class="onboard-field">
            <label>Install commands</label>
            <div class="onboard-copyrow stack">
                <pre data-copy>{{ $installCmd }}</pre>
                <button type="button" class="btn-ghost" data-copy-btn>Copy all</button>
            </div>
        </div>

        <div class="onboard-field">
            <label>Server stats worker (supervisord)</label>
            <div class="onboard-copyrow stack">
                <pre data-copy>[program:kaveh-check]
command=php /path/to/your-app/artisan kaveh:check
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your-app/storage/logs/kaveh-check.log</pre>
                <button type="button" class="btn-ghost" data-copy-btn>Copy</button>
            </div>
        </div>

        <ol class="onboard-steps">
            <li>In your app: require the package and run <code>kaveh:install</code> with the values above.</li>
            <li>Start a queue worker (<code>php artisan queue:work</code>) so request/exception events ship.</li>
            <li>Run <code>php artisan kaveh:check</code> under supervisord for CPU / memory / disk gauges (Pulse-style).</li>
            <li>Hit a few pages in that app — traffic and host stats appear under Overview for <strong>{{ $ob['project_name'] }}</strong>.</li>
        </ol>

        <div class="onboard-actions">
            <form method="post" action="{{ route('kaveh.onboarding.dismiss') }}">
                @csrf
                <button type="submit">Got it — open dashboard</button>
            </form>
            <a class="btn-ghost" href="{{ route('kaveh.projects.index') }}">Manage projects</a>
        </div>
    </div>
</div>
<script>
(() => {
  const root = document.getElementById('kaveh-onboarding');
  if (!root) return;
  root.querySelectorAll('[data-copy-btn]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const row = btn.closest('.onboard-copyrow');
      const target = row?.querySelector('[data-copy]');
      if (!target) return;
      try {
        await navigator.clipboard.writeText(target.textContent.trim());
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = btn.dataset.label || 'Copy'; }, 1400);
      } catch (_) {}
    });
    btn.dataset.label = btn.textContent.trim();
  });
})();
</script>
@endif
