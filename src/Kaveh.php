<?php

declare(strict_types=1);

namespace Kaveh;

use Kaveh\Facades\Kaveh as KavehFacade;

/**
 * Convenience root class for snippets: use Kaveh\Kaveh; Kaveh::track(...)
 */
final class Kaveh
{
    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $tags
     */
    public static function track(string $name, array $context = [], array $tags = [], string $level = 'info'): void
    {
        KavehFacade::track($name, $context, $tags, $level);
    }

    public static function flush(): void
    {
        KavehFacade::flush();
    }
}
