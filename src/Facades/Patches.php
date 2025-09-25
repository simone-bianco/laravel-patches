<?php

namespace SimoneBianco\Patches\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * Provides static access to the Patches service.
 *
 * @see \SimoneBianco\Patches\Patches
 *
 * @method static int runPatches(Closure|null $log = null, callable|null $before = null, callable|null $after = null) Runs all pending patches.
 * @method static bool runSinglePatch(string $patchName, Closure|null $log = null) Force runs a single patch by its class name.
 * @method static string createPatch(string $name) Creates a new patch file.
 * @method static int rollback(array $options = [], Closure|null $log = null, callable|null $before = null, callable|null $after = null) Rolls back data patches based on given options.
 */
class Patches extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'patches';
    }
}
