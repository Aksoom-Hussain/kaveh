@forelse($byType as $type => $count)
    <span class="badge type-{{ $type }}" style="margin:.2rem">{{ $type }}: {{ $count }}</span>
@empty
    <p style="color:var(--muted);margin:0">No events for this project yet. Install the Kaveh <strong>client</strong> on the app and ship with this project’s API key.</p>
@endforelse
