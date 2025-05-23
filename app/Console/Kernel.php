<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // ...existing commands...
        Commands\CleanMigrationsCommand::class,
        Commands\InspectLeadTableCommand::class, // Add the new command
    ];

    // ...existing code...
}