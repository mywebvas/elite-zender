<?php

namespace App\Console\Commands;

use App\Automations\AutomationEngine;
use Illuminate\Console\Command;

/**
 * Advances every automation enrolment whose next step is due.
 *
 * Runs every minute. Enrolments claim themselves with a row lock, so several
 * instances can overlap without executing the same step twice — sending one
 * person the same email twice is the failure that costs a customer.
 */
class RunAutomations extends Command
{
    protected $signature = 'elitesender:run-automations {--limit=500 : Maximum enrolments to advance in one pass}';

    protected $description = 'Advance due automation enrolments';

    public function handle(AutomationEngine $engine): int
    {
        $result = $engine->processDue((int) $this->option('limit'));

        if ($result['processed'] > 0) {
            $this->components->info(sprintf(
                'Advanced %d enrolment(s): %d completed, %d paused after an error.',
                $result['processed'],
                $result['completed'],
                $result['failed'],
            ));
        }

        return self::SUCCESS;
    }
}
