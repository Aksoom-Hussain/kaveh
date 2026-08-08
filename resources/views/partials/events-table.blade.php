{{-- Telescope-style event rows — expects $events (iterable of EventRecord) --}}
<table class="events-table">
    <thead>
        <tr>
            <th style="width:7rem">Type</th>
            <th style="width:5.5rem">Verb / kind</th>
            <th>Detail</th>
            <th style="width:5.5rem">Status</th>
            <th style="width:5.5rem">Duration</th>
            <th style="width:7rem">Happened</th>
            <th style="width:2.5rem"></th>
        </tr>
    </thead>
    <tbody>
    @forelse($events as $event)
        <tr class="event-row" onclick="window.location='{{ route('kaveh.events.show', $event) }}'">
            <td>
                <span class="badge type-{{ $event->type }}">{{ $event->type }}</span>
            </td>
            <td>
                @if($event->type === 'request' && $event->httpMethod())
                    <span class="badge verb verb-{{ $event->verbTone() }}">{{ $event->httpMethod() }}</span>
                @elseif($event->type === 'job')
                    <span class="badge">job</span>
                @elseif($event->type === 'query')
                    <span class="badge">sql</span>
                @elseif($event->type === 'exception')
                    <span class="badge error">throw</span>
                @else
                    <span class="badge">{{ $event->type }}</span>
                @endif
            </td>
            <td class="event-detail">
                <div class="event-primary" title="{{ $event->primaryLabel() }}">{{ \Illuminate\Support\Str::limit($event->primaryLabel(), 90) }}</div>
                @if($event->secondaryLabel())
                    <div class="event-secondary" title="{{ $event->secondaryLabel() }}">{{ \Illuminate\Support\Str::limit($event->secondaryLabel(), 100) }}</div>
                @endif
                @if(is_array($event->tags) && count($event->tags))
                    <div class="event-tags">
                        @foreach(array_slice($event->tags, 0, 4) as $tag)
                            <span class="tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </td>
            <td>
                @if($event->statusLabel())
                    <span class="badge status {{ $event->statusTone() }}">{{ $event->statusLabel() }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="mono">{{ $event->durationLabel() }}</td>
            <td class="muted" title="{{ optional($event->occurred_at)?->toDateTimeString() }}">
                {{ optional($event->occurred_at)?->diffForHumans(short: true) ?: '—' }}
            </td>
            <td class="event-go">
                <a href="{{ route('kaveh.events.show', $event) }}" aria-label="Open event" onclick="event.stopPropagation()">&rarr;</a>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7" class="muted" style="padding:1.25rem .4rem">No events yet.</td>
        </tr>
    @endforelse
    </tbody>
</table>
