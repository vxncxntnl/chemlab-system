<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Illuminate\Console\Command;

class ExpireActivities extends Command
{
    protected $signature = 'activities:expire';
    protected $description = 'Mark upcoming/ongoing activities as expired once their date is more than a day past';

    public function handle(): void
    {
        $count = Activity::expired()->count();

        Activity::expired()->update(['status' => 'expired']);

        $this->info("Done. {$count} activity(ies) expired.");
    }
}
