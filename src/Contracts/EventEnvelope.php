<?php

declare(strict_types=1);

namespace Kaveh\Contracts;

final class EventEnvelope
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     * @param  array{id?: string|int|null, name?: string|null, email?: string|null}|null  $user
     */
    public function __construct(
        public readonly string $id,
        public readonly EventType $type,
        public readonly string $name,
        public readonly string $timestamp,
        public readonly string $environment,
        public readonly string $hostname,
        public readonly ?string $traceId = null,
        public readonly ?array $user = null,
        public readonly array $context = [],
        public readonly array $tags = [],
        public readonly string $level = 'info',
        public readonly ?float $durationMs = null,
        public readonly ?string $projectId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? EventType::Custom->value;
        if ($type instanceof EventType) {
            $eventType = $type;
        } else {
            $eventType = EventType::tryFrom((string) $type) ?? EventType::Custom;
        }

        return new self(
            id: (string) ($data['id'] ?? self::generateId()),
            type: $eventType,
            name: (string) ($data['name'] ?? 'untitled'),
            timestamp: (string) ($data['timestamp'] ?? gmdate('c')),
            environment: (string) ($data['environment'] ?? 'production'),
            hostname: (string) ($data['hostname'] ?? gethostname() ?: 'unknown'),
            traceId: isset($data['trace_id']) ? (string) $data['trace_id'] : null,
            user: isset($data['user']) && is_array($data['user']) ? $data['user'] : null,
            context: isset($data['context']) && is_array($data['context']) ? $data['context'] : [],
            tags: isset($data['tags']) && is_array($data['tags'])
                ? array_values(array_map('strval', $data['tags']))
                : [],
            level: (string) ($data['level'] ?? 'info'),
            durationMs: isset($data['duration_ms']) ? (float) $data['duration_ms'] : null,
            projectId: isset($data['project_id']) ? (string) $data['project_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'type' => $this->type->value,
            'name' => $this->name,
            'timestamp' => $this->timestamp,
            'environment' => $this->environment,
            'hostname' => $this->hostname,
            'trace_id' => $this->traceId,
            'user' => $this->user,
            'context' => $this->context,
            'tags' => $this->tags,
            'level' => $this->level,
            'duration_ms' => $this->durationMs,
        ];
    }

    public static function generateId(): string
    {
        return sprintf(
            '%s-%s',
            bin2hex(random_bytes(8)),
            str_replace('.', '', uniqid('', true))
        );
    }

    /**
     * Validate a raw ingest payload event.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (! isset($data['type']) || ! is_string($data['type'])) {
            $errors[] = 'type is required and must be a string';
        } elseif (EventType::tryFrom($data['type']) === null) {
            $errors[] = 'type must be one of: exception, request, query, job, log, custom';
        }

        if (! isset($data['name']) || ! is_string($data['name']) || $data['name'] === '') {
            $errors[] = 'name is required';
        }

        if (isset($data['context']) && ! is_array($data['context'])) {
            $errors[] = 'context must be an object/array';
        }

        if (isset($data['tags']) && ! is_array($data['tags'])) {
            $errors[] = 'tags must be an array';
        }

        return $errors;
    }
}
