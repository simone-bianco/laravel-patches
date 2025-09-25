<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use SimoneBianco\Patches\Facades\Patches;
use Throwable;

class ApplyDataPatches extends Command
{
    /**
     * @var string
     */
    protected $signature = 'patches:run';

    /**
     * @var string
     */
    protected $description = 'Apply all pending data patches.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $this->info('🚀 Checking for pending data patches...');

        try {
            $logger = fn ($message) => $this->line($message);

            $patchesRun = Patches::runPatches($logger);

            if ($patchesRun > 0) {
                $this->info("✅ Success: {$patchesRun} new patch(es) have been applied.");
            } else {
                $this->info('👍 Your data is already up to date. Nothing to apply.');
            }

        } catch (Throwable $e) {
            $this->error('❌ An error occurred while applying patches:');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
