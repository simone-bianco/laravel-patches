<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use SimoneBianco\Patches\Facades\Patches;
use Throwable;

class RollbackDataPatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patch:rollback
                            {--step= : The number of patches to be reverted}
                            {--all : Revert all patches}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Roll back data patches.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if ($this->isProduction() && !$this->option('force')) {
            if (!$this->confirm('You are in a PRODUCTION environment. Do you really want to roll back data patches?')) {
                $this->comment('Operation cancelled.');
                return self::FAILURE;
            }
        }

        $options = $this->getOptionsArray();
        $this->displayStartMessage($options);

        try {
            $logger = fn ($message) => $this->line($message);
            $rolledBackCount = Patches::rollback($options, $logger);

            if ($rolledBackCount > 0) {
                $this->info("✅ Success: {$rolledBackCount} patch(es) have been rolled back.");
            } else {
                $this->info('👍 Nothing to rollback.');
            }
        } catch (Throwable $e) {
            $this->error('❌ An error occurred during rollback:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Checks if the application is in a production environment.
     */
    private function isProduction(): bool
    {
        return in_array(config('app.env'), ['production', 'prod']);
    }

    /**
     * Builds the options array to pass to the service.
     */
    private function getOptionsArray(): array
    {
        $options = [];
        if ($this->option('all')) {
            $options['all'] = true;
        } elseif ($this->option('step')) {
            $options['step'] = (int) $this->option('step');
        }
        return $options;
    }

    /**
     * Displays a contextual start message to the user.
     */
    private function displayStartMessage(array $options): void
    {
        if (isset($options['all'])) {
            $this->info('🚀 Rolling back ALL data patches...');
        } elseif (isset($options['step'])) {
            $this->info("🚀 Rolling back the last {$options['step']} patch(es)...");
        } else {
            $this->info('🚀 Rolling back the last data patch batch...');
        }
    }
}
