<?php

declare(strict_types=1);

namespace Kaveh;

use Kaveh\Contracts\EventEnvelope;
use Kaveh\Contracts\EventType;
use Kaveh\Support\Redactor;
use Kaveh\Support\Silencer;
use Kaveh\Transport\EventBuffer;

final class KavehManager
{
    public function __construct(
        private readonly EventBuffer $buffer,
        private readonly Redactor $redactor,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     */
    public function track(string $name, array $context = [], array $tags = [], string $level = 'info'): void
    {
        Silencer::run(function () use ($name, $context, $tags, $level): void {
            if (! config('kaveh.enabled')) {
                return;
            }

            if (! $this->shouldSample()) {
                return;
            }

            $event = new EventEnvelope(
                id: EventEnvelope::generateId(),
                type: EventType::Custom,
                name: $name,
                timestamp: gmdate('c'),
                environment: (string) config('kaveh.environment'),
                hostname: gethostname() ?: 'unknown',
                traceId: $this->currentTraceId(),
                user: $this->currentUser(),
                context: $this->redactor->redact($context),
                tags: $tags,
                level: $level,
            );

            $this->buffer->push($event);
        });
    }

    public function record(EventEnvelope $event): void
    {
        Silencer::run(function () use ($event): void {
            if (! config('kaveh.enabled')) {
                return;
            }

            if (! $this->shouldSample()) {
                return;
            }

            $payload = $event->toArray();
            $payload['context'] = $this->redactor->redact($payload['context'] ?? []);
            $payload['user'] = $this->redactor->redactUser($payload['user'] ?? null);

            $this->buffer->push(EventEnvelope::fromArray($payload));
        });
    }

    public function flush(): void
    {
        Silencer::run(fn () => $this->buffer->flush());
    }

    private function shouldSample(): bool
    {
        $rate = (float) config('kaveh.sample_rate', 1.0);
        if ($rate >= 1.0) {
            return true;
        }
        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) <= $rate;
    }

    private function currentTraceId(): ?string
    {
        try {
            if (function_exists('request') && request()) {
                return request()->headers->get('X-Request-Id')
                    ?? request()->headers->get('X-Correlation-Id');
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{id?: string|int|null, name?: string|null, email?: string|null}|null
     */
    private function currentUser(): ?array
    {
        try {
            if (! function_exists('auth') || ! auth()->check()) {
                return null;
            }

            $user = auth()->user();

            return [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
