<?php

namespace SimoneBianco\Patches;

use Closure;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class Patches
{
    protected Filesystem $files;

    protected ConnectionInterface $db;

    public function __construct(Filesystem $files, ConnectionInterface $db)
    {
        $this->files = $files;
        $this->db = $db;
    }

    /**
     * Recursively finds all patch files and sorts them alphabetically by path.
     *
     * @return array
     */
    protected function findAllPatchFiles(): array
    {
        $directoryPath = database_path('patches');

        if (!$this->files->isDirectory($directoryPath)) {
            return [];
        }

        return collect($this->files->allFiles($directoryPath))
            ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
            ->sortBy(fn (SplFileInfo $file) => $file->getPathname())
            ->map(fn (SplFileInfo $file) => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * Runs all pending patches in the database/patches directory.
     *
     * @throws Exception
     */
    public function runPatches(?Closure $log = null, ?callable $before = null, ?callable $after = null): int
    {
        $log = $log ?: fn ($message) => null;
        $this->executeGlobalHook(config('patches.callbacks.up.before'), $log);

        $lastBatch = $this->db->table('data_patches')->max('batch') ?? 0;
        $currentBatch = $lastBatch + 1;

        if ($before) {
            $before();
        }

        $patchFiles = $this->findAllPatchFiles();

        $executedPatches = $this->db->table('data_patches')->pluck('patch')->all();

        $patchesToRun = 0;
        $patchesBasePath = database_path('patches') . DIRECTORY_SEPARATOR;

        foreach ($patchFiles as $fullPath) {
            $relativePath = Str::after($fullPath, $patchesBasePath);
            $patchIdentifier = Str::before($relativePath, '.php');

            if (in_array($patchIdentifier, $executedPatches)) {
                continue;
            }

            $log(" - Applying patch: {$patchIdentifier}");

            try {
                $this->db->transaction(function () use ($fullPath, $patchIdentifier, $currentBatch) {
                    require_once $fullPath;

                    $className = Str::studly(Str::before(basename($fullPath), '.php'));

                    $patchInstance = new $className;
                    $patchInstance->up();

                    $this->db->table('data_patches')->insert([
                        'patch' => $patchIdentifier,
                        'batch' => $currentBatch
                    ]);
                });

                $patchesToRun++;
            } catch (Throwable $e) {
                throw new Exception("Failed to apply patch {$patchIdentifier}: " . $e->getMessage(), 0, $e);
            }
        }

        if ($after) {
            $after();
        }

        $this->executeGlobalHook(config('patches.callbacks.up.after'), $log);

        return $patchesToRun;
    }


    /**
     * Rolls back data patches based on the provided options.
     * This is the main public method for all rollback operations.
     *
     * @param array $options Options can be ['step' => int] or ['all' => true]. No options means last batch.
     * @param Closure|null $log A logger closure to receive output.
     * @param callable|null $before A callable to execute before rollback starts.
     * @param callable|null $after A callable to execute after rollback ends.
     * @return int The number of patches rolled back.
     * @throws Exception
     */
    public function rollback(
        array $options = [],
        ?Closure $log = null,
        ?callable $before = null,
        ?callable $after = null
    ): int {
        $log = $log ?: fn ($message) => null;

        $this->executeGlobalHook(config('patches.callbacks.down.before'), $log);

        if ($before) {
            $before();
        }

        if (!empty($options['all'])) {
            $rolledBackCount = $this->performFullRollback($log);
        } elseif (!empty($options['step'])) {
            $rolledBackCount = $this->performStepRollback((int) $options['step'], $log);
        } else {
            $rolledBackCount = $this->performBatchRollback($log);
        }

        if ($after) {
            $after();
        }

        $this->executeGlobalHook(config('patches.callbacks.down.after'), $log);

        return $rolledBackCount;
    }

    /**
     * Rolls back the last batch of data patches.
     * @throws Exception
     */
    protected function performBatchRollback(Closure $log): int
    {
        $lastBatch = $this->db->table('data_patches')->max('batch');

        if (!$lastBatch) {
            $log('Nothing to rollback.');
            return 0;
        }

        $patchesToRollback = $this->db->table('data_patches')
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();

        if ($patchesToRollback->isEmpty()) {
            $log('No patches found in the last batch to rollback.');
            return 0;
        }

        $this->executeDownMethods($patchesToRollback, $log);

        $this->db->table('data_patches')->where('batch', $lastBatch)->delete();

        return $patchesToRollback->count();
    }

    /**
     * Reverts a specified number of the last executed patches.
     */
    protected function performStepRollback(int $steps, Closure $log): int
    {
        if ($steps <= 0) {
            return 0;
        }

        $patchesToRollback = $this->db->table('data_patches')
            ->orderBy('batch', 'desc')
            ->orderBy('id', 'desc')
            ->limit($steps)
            ->get();

        if ($patchesToRollback->isEmpty()) {
            $log('No patches to rollback.');
            return 0;
        }

        $this->executeDownMethods($patchesToRollback, $log);

        $idsToDelete = $patchesToRollback->pluck('id');
        $this->db->table('data_patches')->whereIn('id', $idsToDelete)->delete();

        return $patchesToRollback->count();
    }

    /**
     * @param Closure $log
     * @return int
     * @throws Exception
     */
    protected function performFullRollback(Closure $log): int
    {
        $patchesToRollback = $this->db->table('data_patches')
            ->orderBy('batch', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        if ($patchesToRollback->isEmpty()) {
            $log('No patches to rollback.');
            return 0;
        }

        $this->executeDownMethods($patchesToRollback, $log);

        $this->db->table('data_patches')->truncate();

        return $patchesToRollback->count();
    }

    /**
     * Helper method to execute the 'down' method for a collection of patches.
     * @throws Exception
     */
    protected function executeDownMethods(Collection $patches, Closure $log): void
    {
        foreach ($patches as $patch) {
            $patchIdentifier = $patch->patch;
            $file = database_path('patches/' . $patchIdentifier . '.php');

            $log(" - Rolling back patch: {$patchIdentifier}");

            if (!$this->files->exists($file)) {
                throw new Exception("Patch file not found: {$file}");
            }

            require_once $file;

            $className = Str::studly(Str::before(basename($file), '.php'));
            $instance = new $className();

            if (!method_exists($instance, 'down')) {
                throw new Exception("Rollback failed. Method down() does not exist in patch: {$patchIdentifier}");
            }

            $instance->down();
        }
    }

    public function runSinglePatch(string $patchIdentifier, ?Closure $log = null): bool
    {
        $log = $log ?: fn ($message) => null;
        $fullPath = database_path('patches' . DIRECTORY_SEPARATOR . $patchIdentifier . '.php');

        if (!$this->files->exists($fullPath)) {
            $log("   - ERROR: Patch file not found at '{$fullPath}'");
            return false;
        }

        try {
            $log(" - Force running patch: {$patchIdentifier}");
            require_once $fullPath;

            $className = Str::studly(Str::before(basename($fullPath), '.php'));
            $patchInstance = new $className;
            $patchInstance->up();
            $log('   - Patch executed successfully.');

            return true;
        } catch (Throwable $e) {
            $log("   - ERROR: Failed to run patch {$patchIdentifier}: " . $e->getMessage());
            return false;
        }
    }

    protected function executeGlobalHook(?string $className, Closure $log): void
    {
        if ($className && class_exists($className) && method_exists($className, '__invoke')) {
            $log("   - Executing global hook: {$className}");
            $instance = new $className;
            $instance->__invoke();
        }
    }

    /**
     * Creates a new patch file with the correct naming convention.
     */
    public function createPatch(string $name): string
    {
        $directoryPath = database_path('patches');
        $this->files->ensureDirectoryExists($directoryPath);

        $snakeName = Str::snake(trim($name));
        $fileName = $this->generateFileName($directoryPath, $snakeName);
        $className = $this->generateClassName($fileName);
        $fullPath = $directoryPath.DIRECTORY_SEPARATOR.$fileName;

        if ($this->files->exists($fullPath)) {
            throw new Exception("Patch file {$fileName} already exists.");
        }

        $fileContent = $this->createPatchFileContent($className);
        $this->files->put($fullPath, $fileContent);

        return $fullPath;
    }

    /**
     * Generates a unique filename following the date_increment_name convention.
     */
    protected function generateFileName(string $directoryPath, string $snakeName): string
    {
        $date = now()->format('Y_m_d');
        $filesToday = $this->files->glob($directoryPath.'/'.$date.'_*.php');

        $lastIncrement = 0;
        if (count($filesToday) > 0) {
            foreach ($filesToday as $file) {
                if (preg_match('/'.$date.'_(\d{6})_/', basename($file), $matches)) {
                    $increment = (int) $matches[1];
                    if ($increment > $lastIncrement) {
                        $lastIncrement = $increment;
                    }
                }
            }
        }

        $newIncrement = $lastIncrement + 1;
        $paddedIncrement = str_pad((string) $newIncrement, 6, '0', STR_PAD_LEFT);

        return "{$date}_{$paddedIncrement}_{$snakeName}.php";
    }

    /**
     * Generates the class name from the filename.
     */
    protected function generateClassName(string $fileName): string
    {
        $namePart = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $fileName);
        $namePart = Str::before($namePart, '.php');

        return Str::studly($namePart);
    }

    /**
     * Creates the template content for a new patch file.
     */
    protected function createPatchFileContent(string $className): string
    {
        return <<<PHP
<?php

class {$className}
{
    /**
     * Run the data patch.
     *
     * @return void
     */
    public function up(): void
    {
        // Add your data modification logic here.
    }

    /**
     * Reverse the data patch.
     *
     * @return void
     */
    public function down(): void
    {
        // Add logic to reverse the changes made in the up() method.
    }
}

PHP;
    }
}
