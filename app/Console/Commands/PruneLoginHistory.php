<?php

namespace App\Console\Commands;

use App\Models\LoginHistory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('login-history:prune {--days=90 : The number of days to retain login history}')]
#[Description('Prune old login history records from the database')]
class PruneLoginHistory extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $deletedCount = LoginHistory::where('login_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deletedCount} login history records older than {$days} days.");

        return self::SUCCESS;
    }
}