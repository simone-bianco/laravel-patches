<?php

namespace SimoneBianco\Patches\Console\Commands;

use Illuminate\Console\Command;
use SimoneBianco\Patches\Facades\Patches;
use Throwable;

class MakePatchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:patch {name : The name of the patch (e.g., add_new_admins)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new incremental data patch file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line("🚀 \e[1;34mCreating a new Data Patch...\e[0m");
        $this->line('======================================');

        try {
            // 1. Get the patch name from the user input.
            $name = (string) $this->argument('name');

            // 2. Delegate the file creation to the Patches service.
            $this->line('   - Calling the patch service to generate the file...');
            $fullPath = Patches::createPatch($name);

            // 3. Display the success message.
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $fullPath);
            $this->line('   ----------------------------------------');
            $this->info("🎉 \e[1;32mPatch file created successfully!\e[0m");
            $this->comment("   File created at: \e[0;33m{$relativePath}\e[0m");
            $this->newLine();

        } catch (Throwable $e) {
            $this->error('   ❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
