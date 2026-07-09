<?php

namespace SimoneBianco\Patches;

use Closure;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class Patches
{
    protected Filesystem $files;

    protected ConnectionInterface $db;

    protected PatchFileRepository $patchFiles;

    public function __construct(Filesystem $files, ConnectionInterface $db)
    {
        $this->files = $files;
        $this->db = $db;
        $this->patchFiles = new PatchFileRepository($files);
    }

    protected function getClassName(string $fullPath): string
    {
        $className = Str::of($fullPath)
            ->basename('.php')
            ->replaceMatches('/^\d{4}_\d{2}_\d{2}_\d+_/u', '')
            ->studly()
            ->toString();
        $content = file_get_contents($fullPath);
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            $namespace = $matches[1];

            return "$namespace\\$className";
        }

        return $className;
    }

    /**
     * Runs all pending patches in the database/patches directory.
     *
     * @throws Exception
     */
    public function runPatches(?Closure $log = null, ?callable $before = null, ?callable $after = null, ?int $limit = null): int
    {
        $log = $log ?: fn ($message) => null;
        $this->executeGlobalHook(config('sb-patches.callbacks.up.before'), $log);
        $lastBatch = $this->db->table('sb_patches')->max('batch') ?? 0;
        $currentBatch = $lastBatch + 1;
        if ($before) {
            $before();
        }
        $patchFiles = $this->patchFiles->findAll();
        $executedPatches = $this->db->table('sb_patches')->pluck('patch')->all();
        $patchesToRun = 0;
        foreach ($patchFiles as $patchFile) {
            $fullPath = $patchFile['path'];
            $patchIdentifier = $patchFile['identifier'];
            if (in_array($patchIdentifier, $executedPatches)) {
                continue;
            }
            $log(" - Applying patch: {$patchIdentifier}");
            $transactionStarted = false;
            try {
                $returned = require $fullPath;
                if (is_object($returned) && method_exists($returned, 'up')) {
                    $patchInstance = $returned;
                } else {
                    $className = $this->getClassName($fullPath);
                    $patchInstance = new $className;
                }
                if ($patchInstance->transactional) {
                    $this->db->beginTransaction();
                    $transactionStarted = true;
                }
                if (method_exists($patchInstance, 'up')) {
                    $patchInstance->up();
                }
                $this->db->table('sb_patches')->insert([
                    'patch' => $patchIdentifier,
                    'batch' => $currentBatch,
                ]);
                if ($transactionStarted) {
                    $this->db->commit();
                    $transactionStarted = false;
                }
                $patchesToRun++;
                if ($limit !== null && $patchesToRun >= $limit) {
                    break;
                }
            } catch (Throwable $e) {
                if ($transactionStarted) {
                    $this->db->rollBack();
                }
                throw new Exception("Failed to apply patch {$patchIdentifier}: ".$e->getMessage(), 0, $e);
            }
        }
        if ($after) {
            $after();
        }
        $this->executeGlobalHook(config('sb-patches.callbacks.up.after'), $log);

        return $patchesToRun;
    }

    /**
     * @throws Exception
     */
    public function rollback(array $options = [], ?Closure $log = null, ?callable $before = null, ?callable $after = null): int
    {
        $log = $log ?: fn ($message) => null;
        $this->executeGlobalHook(config('sb-patches.callbacks.down.before'), $log);
        if ($before) {
            $before();
        }
        if (! empty($options['all'])) {
            $rolledBackCount = $this->performFullRollback($log);
        } elseif (! empty($options['step'])) {
            $rolledBackCount = $this->performStepRollback((int) $options['step'], $log);
        } else {
            $rolledBackCount = $this->performBatchRollback($log);
        }
        if ($after) {
            $after();
        }
        $this->executeGlobalHook(config('sb-patches.callbacks.down.after'), $log);

        return $rolledBackCount;
    }

    /**
     * @throws Exception
     */
    protected function performBatchRollback(Closure $log): int
    {
        $lastBatch = $this->db->table('sb_patches')->max('batch');
        if (! $lastBatch) {
            $log('Nothing to rollback.');

            return 0;
        }
        $patchesToRollback = $this->db->table('sb_patches')
            ->where('batch', $lastBatch)
            ->orderBy('id', 'desc')
            ->get();
        if ($patchesToRollback->isEmpty()) {
            $log('No patches found in the last batch to rollback.');

            return 0;
        }
        $this->executeDownMethods($patchesToRollback, $log);
        $this->db->table('sb_patches')->where('batch', $lastBatch)->delete();

        return $patchesToRollback->count();
    }

    protected function performStepRollback(int $steps, Closure $log): int
    {
        if ($steps <= 0) {
            return 0;
        }
        $patchesToRollback = $this->db->table('sb_patches')
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
        $this->db->table('sb_patches')->whereIn('id', $idsToDelete)->delete();

        return $patchesToRollback->count();
    }

    /**
     * @throws Exception
     */
    protected function performFullRollback(Closure $log): int
    {
        $patchesToRollback = $this->db->table('sb_patches')
            ->orderBy('batch', 'desc')
            ->orderBy('id', 'desc')
            ->get();
        if ($patchesToRollback->isEmpty()) {
            $log('No patches to rollback.');

            return 0;
        }
        $this->executeDownMethods($patchesToRollback, $log);
        $this->db->table('sb_patches')->truncate();

        return $patchesToRollback->count();
    }

    /**
     * @throws Exception
     */
    protected function executeDownMethods(Collection $patches, Closure $log): void
    {
        foreach ($patches as $patch) {
            $patchIdentifier = $patch->patch;
            $file = $this->patchFiles->resolve($patchIdentifier);
            $log(" - Rolling back patch: {$patchIdentifier}");
            if ($file === null) {
                throw new Exception("Patch file not found for identifier: {$patchIdentifier}");
            }
            $returned = require $file;
            if (is_object($returned) && method_exists($returned, 'down')) {
                $instance = $returned;
            } else {
                $className = $this->getClassName($file);
                $instance = new $className;
            }
            if (! method_exists($instance, 'down')) {
                throw new Exception("Rollback failed. Method down() does not exist in patch: {$patchIdentifier}");
            }
            $transactionStarted = false;
            try {
                if ($instance->transactional) {
                    $this->db->beginTransaction();
                    $transactionStarted = true;
                }
                $instance->down();
                if ($transactionStarted) {
                    $this->db->commit();
                }
            } catch (Throwable $e) {
                if ($transactionStarted) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }
    }

    public function runSinglePatch(string $patchIdentifier, ?Closure $log = null): bool
    {
        $log = $log ?: fn ($message) => null;
        try {
            $fullPath = $this->patchFiles->resolve($patchIdentifier);
        } catch (Throwable $e) {
            $log('   - ERROR: '.$e->getMessage());

            return false;
        }

        if ($fullPath === null) {
            $log("   - ERROR: Patch file not found for identifier '{$patchIdentifier}'");

            return false;
        }
        try {
            $log(" - Force running patch: {$patchIdentifier}");
            $returned = require $fullPath;
            if (is_object($returned) && method_exists($returned, 'up')) {
                $patchInstance = $returned;
            } else {
                $className = $this->getClassName($fullPath);
                $patchInstance = new $className;
            }
            $transactionStarted = false;
            try {
                if ($patchInstance->transactional) {
                    $this->db->beginTransaction();
                    $transactionStarted = true;
                }
                if (method_exists($patchInstance, 'up')) {
                    $patchInstance->up();
                }
                if ($transactionStarted) {
                    $this->db->commit();
                }
            } catch (Throwable $e) {
                if ($transactionStarted) {
                    $this->db->rollBack();
                }
                throw $e;
            }
            $log('   - Patch executed successfully.');

            return true;
        } catch (Throwable $e) {
            $log("   - ERROR: Failed to run patch {$patchIdentifier}: ".$e->getMessage());

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
     * @throws Exception
     */
    public function createPatch(string $name, bool $module = false, bool $withData = false): string
    {
        return $this->patchFiles->create($name, $module, $withData);
    }
}
