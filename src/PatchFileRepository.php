<?php

namespace SimoneBianco\Patches;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

class PatchFileRepository
{
    public function __construct(private Filesystem $files) {}

    /**
     * @return array<int, array{identifier: string, path: string}>
     *
     * @throws Exception
     */
    public function findAll(): array
    {
        $directoryPath = database_path('patches');
        if (! $this->files->isDirectory($directoryPath)) {
            return [];
        }

        $patches = collect($this->files->allFiles($directoryPath))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): ?array => $this->patchRecordForFile($file, $directoryPath))
            ->filter()
            ->values();

        $duplicates = $patches
            ->groupBy('identifier')
            ->filter(fn (Collection $items): bool => $items->count() > 1);

        if ($duplicates->isNotEmpty()) {
            throw new Exception('Duplicate patch identifiers found: '.$duplicates->keys()->implode(', '));
        }

        return $patches
            ->sortBy(fn (array $patch): string => $patch['identifier'], SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @throws Exception
     */
    public function resolve(string $patchIdentifier): ?string
    {
        $normalizedIdentifier = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($patchIdentifier, '\\/'));
        $basePath = database_path('patches'.DIRECTORY_SEPARATOR.$normalizedIdentifier);
        $classicFile = $basePath.'.php';
        $moduleFile = $basePath.DIRECTORY_SEPARATOR.'patch.php';
        $matches = array_values(array_filter([$classicFile, $moduleFile], fn (string $path): bool => $this->files->exists($path)));

        if (count($matches) > 1) {
            throw new Exception("Ambiguous patch identifier {$patchIdentifier}: both classic file and module patch exist.");
        }

        return $matches[0] ?? null;
    }

    /**
     * @throws Exception
     */
    public function create(string $name, bool $module = false, bool $withData = false): string
    {
        $normalizedName = str_replace('\\', '/', trim($name));
        $segments = array_values(array_filter(explode('/', $normalizedName), fn (string $segment) => $segment !== ''));
        if ($segments === []) {
            throw new Exception('Patch name cannot be empty.');
        }

        $patchName = Str::snake(array_pop($segments));
        $directoryPath = database_path('patches');
        if ($segments !== []) {
            $directoryPath .= DIRECTORY_SEPARATOR.collect($segments)
                ->map(fn (string $segment): string => Str::snake($segment))
                ->implode(DIRECTORY_SEPARATOR);
        }

        $this->files->ensureDirectoryExists($directoryPath);
        $patchBaseName = $this->generatePatchBaseName($directoryPath, $patchName);

        return $module
            ? $this->createModulePatch($directoryPath, $patchBaseName, $withData)
            : $this->createClassicPatch($directoryPath, $patchBaseName);
    }

    /** @return array{identifier: string, path: string}|null */
    private function patchRecordForFile(SplFileInfo $file, string $patchesPath): ?array
    {
        $relativePath = $this->relativePath($file->getPathname(), $patchesPath);

        if ($file->getFilename() === 'patch.php') {
            $identifier = trim(Str::beforeLast($relativePath, '/patch.php'), '/');
            $moduleName = basename(str_replace('\\', '/', $identifier));

            return $identifier !== '' && $this->isTimestampedPatchName($moduleName)
                ? ['identifier' => $identifier, 'path' => $file->getPathname()]
                : null;
        }

        if (! $this->isTimestampedPatchFile($file->getFilename()) || $this->isInsidePatchModule($file->getPath(), $patchesPath)) {
            return null;
        }

        return ['identifier' => Str::beforeLast($relativePath, '.php'), 'path' => $file->getPathname()];
    }

    private function isTimestampedPatchFile(string $filename): bool
    {
        return (bool) preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+\.php$/', $filename);
    }

    private function isTimestampedPatchName(string $name): bool
    {
        return (bool) preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+$/', $name);
    }

    private function isInsidePatchModule(string $directory, string $patchesPath): bool
    {
        $patchesPath = str_replace('\\', '/', realpath($patchesPath) ?: $patchesPath);
        $directory = str_replace('\\', '/', realpath($directory) ?: $directory);

        while ($directory !== '' && $directory !== '.' && str_starts_with($directory, $patchesPath)) {
            if ($directory === $patchesPath) {
                return false;
            }

            $modulePatchFile = str_replace('/', DIRECTORY_SEPARATOR, $directory).DIRECTORY_SEPARATOR.'patch.php';
            if ($this->isTimestampedPatchName(basename($directory)) && $this->files->exists($modulePatchFile)) {
                return true;
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                return false;
            }
            $directory = $parent;
        }

        return false;
    }

    private function relativePath(string $path, string $basePath): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        return ltrim(Str::after($normalizedPath, $normalizedBase), '/');
    }

    private function createClassicPatch(string $directoryPath, string $patchBaseName): string
    {
        $fileName = "{$patchBaseName}.php";
        $fullPath = $directoryPath.DIRECTORY_SEPARATOR.$fileName;
        if ($this->files->exists($fullPath)) {
            throw new Exception("Patch file {$fileName} already exists.");
        }

        $this->files->put($fullPath, $this->createPatchFileContent());

        return $fullPath;
    }

    private function createModulePatch(string $directoryPath, string $patchBaseName, bool $withData): string
    {
        $modulePath = $directoryPath.DIRECTORY_SEPARATOR.$patchBaseName;
        if ($this->files->exists($modulePath)) {
            throw new Exception("Patch module {$patchBaseName} already exists.");
        }

        $this->files->ensureDirectoryExists($modulePath);
        $fullPath = $modulePath.DIRECTORY_SEPARATOR.'patch.php';
        $this->files->put($fullPath, $this->createPatchFileContent());
        if ($withData) {
            $this->files->put($modulePath.DIRECTORY_SEPARATOR.'data.php', $this->createPatchDataFileContent());
        }

        return $fullPath;
    }

    private function generatePatchBaseName(string $directoryPath, string $snakeName): string
    {
        $date = now()->format('Y_m_d');
        $pathsToday = array_merge(
            $this->files->glob($directoryPath.'/'.$date.'_*.php') ?: [],
            $this->files->glob($directoryPath.'/'.$date.'_*') ?: [],
        );
        $lastIncrement = 0;
        foreach ($pathsToday as $path) {
            if (preg_match('/'.$date.'_(\d{6})_/', basename($path), $matches)) {
                $lastIncrement = max($lastIncrement, (int) $matches[1]);
            }
        }

        return "{$date}_".str_pad((string) ($lastIncrement + 1), 6, '0', STR_PAD_LEFT)."_{$snakeName}";
    }

    private function createPatchFileContent(): string
    {
        return <<<'PHP'
<?php

use SimoneBianco\Patches\Patch;

return new class extends Patch
{
    public bool $transactional = false;

    public function up(): void
    {
        // Add your data modification logic here.
    }

    public function down(): void
    {
        // Add logic to reverse the changes made in the up() method.
    }
};
PHP;
    }

    private function createPatchDataFileContent(): string
    {
        return <<<'PHP'
<?php

return [
    // Keep large seed payloads close to patch.php without making them executable patches.
];
PHP;
    }
}
