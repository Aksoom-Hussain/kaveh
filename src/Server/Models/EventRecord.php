<?php

namespace Kaveh\Server\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRecord extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'project_id',
        'uuid',
        'type',
        'name',
        'level',
        'environment',
        'hostname',
        'trace_id',
        'user',
        'context',
        'tags',
        'duration_ms',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'user' => 'array',
            'context' => 'array',
            'tags' => 'array',
            'occurred_at' => 'datetime',
            'duration_ms' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(EventEmbedding::class, 'event_id');
    }

    public function searchableText(): string
    {
        $parts = [
            $this->type,
            $this->name,
            $this->level,
            $this->environment,
            is_array($this->tags) ? implode(' ', $this->tags) : '',
            json_encode($this->context) ?: '',
        ];

        return mb_substr(implode(' ', array_filter($parts)), 0, 8000);
    }

    public function contextValue(string $key, mixed $default = null): mixed
    {
        $context = is_array($this->context) ? $this->context : [];

        return $context[$key] ?? $default;
    }

    public function httpMethod(): ?string
    {
        $method = $this->contextValue('method');
        if (is_string($method) && $method !== '') {
            return strtoupper($method);
        }

        if ($this->type === 'request' && preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+/i', (string) $this->name, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    public function httpPath(): ?string
    {
        $uri = $this->contextValue('uri');
        if (is_string($uri) && $uri !== '') {
            return $uri;
        }

        if ($this->type === 'request' && preg_match('/^(?:GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+(.+)$/i', (string) $this->name, $m)) {
            return $m[1];
        }

        return null;
    }

    public function httpStatus(): ?int
    {
        $status = $this->contextValue('status');
        if (is_numeric($status)) {
            return (int) $status;
        }

        return null;
    }

    public function primaryLabel(): string
    {
        return match ($this->type) {
            'request' => (string) ($this->httpPath() ?: $this->name),
            'query' => (string) ($this->contextValue('sql') ?: $this->name),
            'job' => (string) $this->name,
            'exception' => (string) ($this->contextValue('message') ?: $this->name),
            default => (string) $this->name,
        };
    }

    public function secondaryLabel(): ?string
    {
        return match ($this->type) {
            'request' => $this->contextValue('ip') ? 'IP '.$this->contextValue('ip') : null,
            'job' => collect([
                $this->contextValue('connection'),
                $this->contextValue('queue') ? 'queue:'.$this->contextValue('queue') : null,
                $this->contextValue('status'),
            ])->filter()->implode(' · ') ?: null,
            'query' => $this->contextValue('connection')
                ? 'connection: '.$this->contextValue('connection')
                : null,
            'exception' => collect([
                class_basename((string) $this->name),
                $this->contextValue('file')
                    ? basename((string) $this->contextValue('file')).':'.$this->contextValue('line')
                    : null,
            ])->filter()->implode(' · ') ?: null,
            'log' => is_string($this->contextValue('message')) ? (string) $this->contextValue('message') : null,
            default => null,
        };
    }

    public function statusLabel(): ?string
    {
        if ($this->type === 'request') {
            $status = $this->httpStatus();

            return $status !== null ? (string) $status : null;
        }

        if ($this->type === 'job') {
            $status = $this->contextValue('status');

            return is_string($status) ? $status : null;
        }

        return $this->level ? (string) $this->level : null;
    }

    public function statusTone(): string
    {
        if ($this->type === 'request') {
            $status = $this->httpStatus();
            if ($status === null) {
                return '';
            }
            if ($status >= 500) {
                return 'error';
            }
            if ($status >= 400) {
                return 'warning';
            }
            if ($status >= 300) {
                return 'redirect';
            }

            return 'ok';
        }

        if ($this->type === 'job') {
            return $this->contextValue('status') === 'failed' ? 'error' : 'ok';
        }

        return match ($this->level) {
            'critical', 'error' => 'error',
            'warning' => 'warning',
            default => '',
        };
    }

    public function durationLabel(): string
    {
        if ($this->duration_ms === null) {
            return '—';
        }

        $ms = (float) $this->duration_ms;
        if ($ms >= 1000) {
            return number_format($ms / 1000, 2).'s';
        }

        return number_format($ms, $ms >= 100 ? 0 : 1).'ms';
    }

    public function verbTone(): string
    {
        return match ($this->httpMethod()) {
            'POST' => 'post',
            'PUT', 'PATCH' => 'put',
            'DELETE' => 'delete',
            'OPTIONS', 'HEAD' => 'meta',
            default => 'get',
        };
    }
}
