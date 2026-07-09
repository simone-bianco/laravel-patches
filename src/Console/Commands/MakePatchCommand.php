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
    protected $signature = 'make:patch
        {name : The name of the patch (e.g., add_new_admins or settings/site/add_new_admins)}
        {--module : Create a patch module directory containing patch.php}
        {--data : Create an empty data.php file next to patch.php; implies --module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new incremental data patch file or patch module';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->line('Creating a new Data Patch...');
        $this->line('======================================');

        try {
            // 1. Get the patch name and desired format from the user input.
            $name = (string) $this->argument('name');
            $withData = (bool) $this->option('data');
            $module = (bool) $this->option('module') || $withData;

            // 2. Delegate the file creation to the Patches service.
            $this->line('   - Calling the patch service to generate the file...');
            $fullPath = Patches::createPatch($name, $module, $withData);

            // 3. Display the success message.
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $fullPath);
            $this->line('   ----------------------------------------');
            $this->info('Patch created successfully!');
            $this->comment("   File created at: \e[0;33m{$relativePath}\e[0m");
            $this->newLine();

        } catch (Throwable $e) {
            $this->error('   Error: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
