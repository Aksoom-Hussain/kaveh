<?php

declare(strict_types=1);

namespace Kaveh\Console;

use Illuminate\Console\Command;
use Kaveh\Storage\LocalEventStore;

final class PruneCommand extends Command
{
    protected $signature = 'kaveh:prune';

    protected $description = 'Prune expired local Kaveh events';

    public function handle(LocalEventStore $store): int
    {
        $deleted = $store->prune();
        $this->info("Pruned {$deleted} local Kaveh events.");

        return self::SUCCESS;
    }
}
