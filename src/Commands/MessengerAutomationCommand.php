<?php

namespace Ihabrouk\Messenger\Commands;

use Ihabrouk\Messenger\Services\AutomationService;
use Illuminate\Console\Command;

class MessengerAutomationCommand extends Command
{
    protected $signature = 'messenger:automation
                           {--task= : Specific task to run (scheduled|bulk|retry|heal|balance|alerts|cleanup)}
                           {--force : Force execution even if conditions are not met}';

    protected $description = 'Run messenger automation tasks';

    protected AutomationService $automationService;

    public function __construct(AutomationService $automationService)
    {
        parent::__construct();
        $this->automationService = $automationService;
    }

    public function handle(): int
    {
        $task = $this->option('task');

        if ($task) {
            return $this->runSpecificTask($task);
        }

        return $this->runAllTasks();
    }

    protected function runSpecificTask(string $task): int
    {
        $this->info("Running automation task: {$task}");

        try {
            switch ($task) {
                case 'scheduled':
                    $count = $this->automationService->processScheduledMessages();
                    $this->info("Processed {$count} scheduled messages");
                    break;

                case 'bulk':
                    $count = $this->automationService->processBulkCampaigns();
                    $this->info("Processed {$count} bulk campaigns");
                    break;

                case 'retry':
                    $count = $this->automationService->autoRetryFailedMessages();
                    $this->info("Queued {$count} messages for retry");
                    break;

                case 'heal':
                    $count = $this->automationService->healCircuitBreakers();
                    $this->info("Healed {$count} circuit breakers");
                    break;

                case 'balance':
                    $recommendations = $this->automationService->balanceProviderLoad();
                    $this->info("Generated " . count($recommendations) . " load balance recommendations");

                    if ($this->option('verbose')) {
                        foreach ($recommendations as $rec) {
                            $this->line("- {$rec['action']} for {$rec['provider']}: {$rec['reason']}");
                        }
                    }
                    break;

                case 'alerts':
                    $alerts = $this->automationService->generateHealthAlerts();
                    $this->info("Generated " . count($alerts) . " health alerts");

                    foreach ($alerts as $alert) {
                        $level = $alert['type'] === 'critical' ? 'error' : 'warn';
                        $this->$level($alert['message']);
                    }
                    break;

                case 'cleanup':
                    $result = $this->automationService->cleanupOldData();
                    $this->info("Cleaned up {$result['deleted_messages']} messages and {$result['deleted_logs']} logs");
                    break;

                default:
                    $this->error("Unknown task: {$task}");
                    return 1;
            }

            $this->info("Task completed successfully");
            return 0;

        } catch (\Exception $e) {
            $this->error("Task failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function runAllTasks(): int
    {
        $this->info("Running all automation tasks...");

        try {
            $results = $this->automationService->runAll();

            $this->info("Automation completed:");
            $this->line("- Scheduled messages: {$results['scheduled_messages']}");
            $this->line("- Bulk campaigns: {$results['bulk_campaigns']}");
            $this->line("- Retried messages: {$results['retried_messages']}");
            $this->line("- Healed circuits: {$results['healed_circuits']}");
            $this->line("- Load recommendations: " . count($results['load_balance']));
            $this->line("- Health alerts: " . count($results['alerts']));

            if (!empty($results['alerts'])) {
                $this->warn("Health alerts generated:");
                foreach ($results['alerts'] as $alert) {
                    $this->line("  - {$alert['message']}");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Automation failed: " . $e->getMessage());
            return 1;
        }
    }
}
