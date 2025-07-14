<?php

namespace App\Messenger\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupLogsCommand extends Command
{
    protected $signature = 'messenger:cleanup-logs {--days=30 : Days to keep logs} {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Cleanup old messenger log files';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $logPath = storage_path('logs');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up messenger logs older than {$days} days...");
        $this->info("Cutoff date: {$cutoffDate->toDateTimeString()}");

        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will be deleted");
        }

        $logFiles = collect(File::glob("{$logPath}/messenger-*.log"))
            ->filter(function ($file) use ($cutoffDate) {
                return File::lastModified($file) < $cutoffDate->timestamp;
            });

        if ($logFiles->isEmpty()) {
            $this->info("No log files found older than {$days} days");
            return Command::SUCCESS;
        }

        $this->info("Found {$logFiles->count()} log files to cleanup:");

        foreach ($logFiles as $file) {
            $lastModified = date('Y-m-d H:i:s', File::lastModified($file));
            $size = File::size($file);

            $this->line("  - " . basename($file) . " (modified: {$lastModified}, size: " . $this->formatBytes($size) . ")");

            if (!$dryRun) {
                File::delete($file);
            }
        }

        if (!$dryRun) {
            $this->info("Successfully deleted {$logFiles->count()} log files");
        } else {
            $this->info("Would delete {$logFiles->count()} log files (use without --dry-run to actually delete)");
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
