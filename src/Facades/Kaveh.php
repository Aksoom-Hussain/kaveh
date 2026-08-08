<?php

declare(strict_types=1);

namespace Kaveh\Facades;

use Illuminate\Support\Facades\Facade;
use Kaveh\KavehManager;

/**
 * @method static void track(string $name, array $context = [], array $tags = [], string $level = 'info')
 * @method static void record(\Kaveh\Contracts\EventEnvelope $event)
 * @method static void flush()
 *
 * @see \Kaveh\KavehManager
 */
final class Kaveh extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KavehManager::class;
    }
}
