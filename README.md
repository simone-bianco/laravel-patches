# Laravel Data Patches

This package provides a migration-like system for incremental Laravel data changes. It is designed for data that should be ordered, replayable, reversible, and easier to review than a large monolithic seeder.

Use it for:

- Replacing bulky Laravel seeders with small, ordered data patches.
- Seeding initial application data such as roles, settings, content, sources, reference data, or demo/domain records.
- Deploying controlled production data changes that need a rollback path.
- Keeping large seed payloads close to their patch without making every support file executable.

## Installation

```bash
composer require simonebianco/laravel-patches
php artisan vendor:publish --provider="SimoneBianco\Patches\PatchesServiceProvider"
php artisan migrate
```

Publishing creates:

- `config/sb-patches.php`
- a migration for the `sb_patches` tracking table

## Configuration

`config/sb-patches.php` can define global hooks around patch execution and rollback. Each hook must be a fully qualified class with an `__invoke` method.

```php
return [
    'callbacks' => [
        'up' => [
            'before' => null,
            'after' => App\Patches\Hooks\ClearCache::class,
        ],
        'down' => [
            'before' => null,
            'after' => null,
        ],
    ],
];
```

## Patch formats

The package supports two executable patch formats under `database/patches`.

### 1. Classic file patch

```txt
database/patches/settings/site/2026_07_09_000001_add_maintenance_mode.php
```

This is best for small data changes where the full patch fits cleanly in one file.

### 2. Module patch directory

```txt
database/patches/countdowns/taiwan_invasion/2026_07_09_010020_seed_projections/
  patch.php
  data.php
  data1.php
  data2.php
  notes.md
  source-map.json
```

The runner executes only `patch.php`. Any other file inside the module directory is support material and is ignored by discovery, even if it is PHP.

Module patches are the recommended format for substantial seed data. Keep `patch.php` focused on behavior and put large arrays, JSON payloads, localized copy, source maps, or helper data next to it.

## Identifiers and order

Patch identifiers are normalized paths relative to `database/patches`.

Classic file:

```txt
database/patches/settings/site/2026_07_09_000001_add_maintenance_mode.php
```

identifier:

```txt
settings/site/2026_07_09_000001_add_maintenance_mode
```

Module patch:

```txt
database/patches/settings/site/2026_07_09_000001_add_maintenance_mode/patch.php
```

identifier:

```txt
settings/site/2026_07_09_000001_add_maintenance_mode
```

Execution order is alphabetical/natural by identifier. This keeps ordering based on names and timestamp prefixes, regardless of whether a patch is a direct file or a module directory.

A duplicate classic/module identifier is invalid. For example, these two cannot coexist:

```txt
database/patches/foo/2026_07_09_000001_seed.php
database/patches/foo/2026_07_09_000001_seed/patch.php
```

## Creating patches

Create a classic patch file:

```bash
php artisan make:patch settings/site/add_maintenance_mode
```

Create a module patch directory:

```bash
php artisan make:patch countdowns/taiwan_invasion/seed_projections --module
```

Create a module patch with an empty `data.php` file:

```bash
php artisan make:patch countdowns/taiwan_invasion/seed_projections --module --data
```

`--data` implies `--module`.

## Patch structure

A patch returns an anonymous class extending `Patch`.

```php
<?php

use SimoneBianco\Patches\Patch;

return new class extends Patch
{
    public bool $transactional = true;

    public function up(): void
    {
        // Apply data changes.
    }

    public function down(): void
    {
        // Reverse data changes.
    }
};
```

For module patches, `patch.php` may read local support files.

```php
<?php

use App\Models\Setting;
use SimoneBianco\Patches\Patch;

return new class extends Patch
{
    public bool $transactional = true;

    public function up(): void
    {
        foreach (require __DIR__.'/data.php' as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }

    public function down(): void
    {
        Setting::query()
            ->whereIn('key', array_column(require __DIR__.'/data.php', 'key'))
            ->delete();
    }
};
```

```php
<?php

// data.php
return [
    ['key' => 'site.maintenance', 'value' => false],
];
```

## Applying patches

Run all pending patches:

```bash
php artisan patch:run
```

Limit the number of applied patches:

```bash
php artisan patch:run --step=1
```

The runner discovers executable entries only:

- timestamped direct PHP files: `YYYY_MM_DD_XXXXXX_name.php`
- `patch.php` inside timestamped module directories: `YYYY_MM_DD_XXXXXX_name/patch.php`

Non-timestamped PHP files and module support files are ignored.

## Forcing a single patch

Run one patch by identifier without recording it in `sb_patches`:

```bash
php artisan patch:single settings/site/2026_07_09_000001_add_maintenance_mode
```

This resolves both classic and module formats. It is useful for compatibility seeders and explicit replay/debug checks, not for normal deploy tracking.

## Rolling back patches

Roll back the last batch:

```bash
php artisan patch:rollback
```

Roll back a number of recently applied patches:

```bash
php artisan patch:rollback --step=3
```

Roll back every applied patch:

```bash
php artisan patch:rollback --all
```

Rollback in production asks for confirmation. Use `--force` only in controlled deployment scripts.

```bash
php artisan patch:rollback --step=1 --force
```

Re-apply from scratch:

```bash
php artisan patch:fresh
```

## Using the facade

```php
use SimoneBianco\Patches\Facades\Patches;

$patchesRun = Patches::runPatches();
$rolledBackCount = Patches::rollback();
$rolledBackCount = Patches::rollback(['step' => 5]);
$filePath = Patches::createPatch('settings/site/add_maintenance_mode');
$modulePath = Patches::createPatch('content/seed_homepage', true, true);
```

## Seeder best practices

Large seeders should be split into patch modules rather than kept as one huge seeder class.

Recommended split examples:

```txt
database/patches/content/homepage/2026_07_09_000001_seed_page/patch.php
database/patches/content/homepage/2026_07_09_000001_seed_page/data.php

database/patches/content/homepage/2026_07_09_000002_seed_blocks/patch.php
database/patches/content/homepage/2026_07_09_000002_seed_blocks/data.php

database/patches/content/homepage/2026_07_09_000003_seed_sources/patch.php
database/patches/content/homepage/2026_07_09_000003_seed_sources/sources.json
```

Keep each patch responsible for one reversible concern. Avoid broad deletes, truncates, or unrelated data cleanup inside seed patches.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see the license file for more information.
