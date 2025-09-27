<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use SimoneBianco\Patches\Facades\Patches;
use Throwable;

class RefreshPatches extends Command
{
    /**
     * @var string
     */
    protected $signature = 'patch:fresh';

    /**
     * @var string
     */
    protected $description = 'Refreshes all data patches, rolling back the already installed and reapplying them.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 Checking for pending data patches...');

        try {
            Artisan::call('patch:rollback', [], $this->getOutput());
            Artisan::call('patch:run', [], $this->getOutput());
        } catch (Throwable $e) {
            $this->error('❌ An error occurred while applying patches:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
