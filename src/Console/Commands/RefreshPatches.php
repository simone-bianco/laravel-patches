<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RefreshPatches extends Command
{
    /**
     * @var string
     */
    protected $signature = 'patch:fresh {--force : Force rollback in production}';

    /**
     * @var string
     */
    protected $description = 'Refreshes all data patches, rolling back the already installed and reapplying them.';

    public function handle(): int
    {
        $this->info('🚀 Checking for pending data patches...');
        try {
            Artisan::call('patch:rollback', ['--all' => true, '--force' => (bool) $this->option('force')], $this->getOutput());
            Artisan::call('patch:run', [], $this->getOutput());
        } catch (Throwable $e) {
            $this->error('❌ An error occurred while applying patches:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
