<?php

namespace SimoneBianco\Patches;

/**
 * Base class for data patches.
 *
 * You can extend this class when creating patches. Both methods are optional.
 */
abstract class Patch
{
    /**
     * Indicates if the patch should be run within a transaction.
     */
    public bool $transactional = false;

    /**
     * Apply the data patch.
     */
    public function up(): void
    {
        // Optional.
    }

    /**
     * Revert the data patch.
     */
    public function down(): void
    {
        // Optional.
    }
}
