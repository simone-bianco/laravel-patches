<?php

namespace SimoneBianco\Patches\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SimoneBianco\Patches\PatchesServiceProvider;
use Symfony\Component\Finder\SplFileInfo;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Esegue il setup dell'ambiente di test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Carica i Service Provider del package.
     */
    protected function getPackageProviders($app)
    {
        return [
            PatchesServiceProvider::class,
        ];
    }

    /**
     * @param string $path
     * @param string $like
     * @return array<SplFileInfo>
     */
    protected function getFilesByPartialMatch(string $path, string $like = 'my_first_patch'): array
    {
        $files = File::files($path);
        $matchFiles = [];
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), $like)) {
                $matchFiles[] = $file;
            }
        }

        return $matchFiles;
    }

    protected function filePartialMatchesCount(string $path, string $like = 'my_first_patch'): int
    {
        return count($this->getFilesByPartialMatch($path, $like));
    }
}
