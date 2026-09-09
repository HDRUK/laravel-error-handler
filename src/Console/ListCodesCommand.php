<?php

namespace HDRUK\ErrorHandler\Console;

use HDRUK\ErrorHandler\Support\ProfileRegistry;
use Illuminate\Console\Command;

class ListCodesCommand extends Command
{
    protected $signature = 'error-handler:list-codes';

    protected $description = 'List every registered exception => error code mapping (config and programmatic).';

    public function handle(ProfileRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->resolvedProfiles() as $exceptionClass => $profile) {
            $rows[] = [$exceptionClass, $profile->code(), $profile::class];
        }

        $rows[] = ['(unmapped)', $registry->fallback()->code(), $registry->fallback()::class];

        usort($rows, fn ($a, $b) => $a[1] <=> $b[1]);

        $this->table(['Exception', 'Code', 'Profile'], $rows);

        return self::SUCCESS;
    }
}
