<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use SimoneBianco\Patches\Facades\Patches;

class RunSinglePatch extends Command
{
    /**
     * @var string
     */
    protected $signature = 'patch:run {name : The full name of the patch class to run (e.g., 2025_09_23_000001_add_core_game_systems)}';

    /**
     * @var string
     */
    protected $description = 'Force runs a single data patch, bypassing the tracking table.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $patchName = (string) $this->argument('name');
        $this->info("🚀 Forcing execution of patch: {$patchName}");

        $logger = fn ($message) => $this->line($message);

        $success = Patches::runSinglePatch($patchName, $logger);

        if ($success) {
            $this->info("✅ Success: The patch '{$patchName}' was executed.");
        } else {
            $this->error("❌ Error: Failed to execute the patch '{$patchName}'. Check the logs for details.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
