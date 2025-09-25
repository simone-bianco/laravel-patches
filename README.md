# Laravel Data Patches

[](https://www.google.com/search?q=https://packagist.org/packages/simonebianco/laravel-patches)
[](https://www.google.com/search?q=https://packagist.org/packages/simonebianco/laravel-patches)
[](https://www.google.com/search?q=https://github.com/simonebianco/laravel-patches/actions)

This package provides a robust, migration-like system for managing incremental data changes in your Laravel application. Instead of messy, non-repeatable seeders, you can create timestamped "patch" files that are tracked in the database, can be rolled back, and can be organized into subdirectories.

It's the perfect solution for:

- Seeding initial application data (like user roles, settings, countries, etc.).
- Deploying data changes to a production environment in a controlled and reversible way.
- Organizing complex data modifications into logical, ordered steps.

-----

## Installation

You can install the package via composer:

```bash
composer require simonebianco/laravel-patches
```

Next, you should publish the package's assets (configuration file and migration):

```bash
php artisan vendor:publish --provider="SimoneBianco\Patches\PatchesServiceProvider"
```

This will publish:

- A configuration file to `config/patches.php`.
- A migration file to `database/migrations/`.

Finally, run the migration to create the `data_patches` table, which will track the executed patches.

```bash
php artisan migrate
```

-----

## Configuration ⚙️

The configuration file `config/patches.php` allows you to define global hooks that run before and after the patch processes. This is useful for tasks like disabling model observers during execution or clearing the application cache afterward.

Each hook must be a fully qualified class name that contains an `__invoke` method.

```php
// config/patches.php

return [
    'callbacks' => [
        'up' => [
            // Executed before `patch:apply` starts
            'before' => null, 
            // Executed after `patch:apply` finishes
            'after' => App\Patches\Hooks\ClearCache::class, 
        ],
        'down' => [
            // Executed before `patch:rollback` starts
            'before' => null,
            // Executed after `patch:rollback` finishes
            'after' => null,
        ],
    ],
];
```

#### Example Hook Class

```php
// app/Patches/Hooks/ClearCache.php

namespace App\Patches\Hooks;

use Illuminate\Support\Facades\Artisan;

class ClearCache
{
    public function __invoke(): void
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
    }
}
```

-----

## Usage

### 1\. Creating a Patch

To create a new patch, use the `make:patch` Artisan command. The patch files will be stored in the `database/patches` directory.

```bash
php artisan make:patch seed_initial_roles_and_permissions
```

This will create a file with the naming convention `YYYY_MM_DD_XXXXXX_seed_initial_roles_and_permissions.php`.

You can also create patches in subdirectories for better organization:

```bash
php artisan make:patch settings/site/add_maintenance_mode_setting
```

This will create the file inside `database/patches/settings/site/`.

### 2\. The Patch File Structure

Each patch file is a simple class with two methods: `up()` and `down()`.

- `up()`: Contains the logic to apply the data change.
- `down()`: Contains the logic to reverse the change.

<!-- end list -->

```php
<?php

use Spatie\Permission\Models\Role; // Example using a popular package

class SeedInitialRolesAndPermissions
{
    /**
     * Run the data patch.
     */
    public function up(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'editor']);
    }

    /**
     * Reverse the data patch.
     */
    public function down(): void
    {
        Role::whereIn('name', ['admin', 'editor'])->delete();
    }
}
```

### 3\. Applying Patches ⚡️

To run all pending patches that haven't been executed yet, use the `patch:apply` command. The system will find all `.php` files recursively, sort them alphabetically by path, and execute them in order.

```bash
php artisan patch:apply
```

#### Forcing a Single Patch

For debugging or testing purposes, you can force-run a single patch, even if it has already been applied. This command **does not** record the execution in the tracking table.

The patch name is its path relative to the `patches` directory.

```bash
php artisan patch:run settings/site/2025_09_25_000001_add_maintenance_mode_setting
```

### 4\. Rolling Back Patches ↩️

The `patch:rollback` command is powerful and flexible, mirroring Laravel's `migrate:rollback`.

#### Roll Back the Last Batch

This is the default behavior. It will roll back all patches that were applied in the last `patch:apply` run.

```bash
php artisan patch:rollback
```

#### Roll Back by Steps

To roll back a specific number of the most recently applied patches, regardless of their batch, use the `--step` option.

```bash
php artisan patch:rollback --step=3
```

#### Roll Back All Patches

To roll back every single patch that has been applied, use the `--all` option. **This is a destructive operation.**

```bash
php artisan patch:rollback --all
```

### 5\. Production Safety ⚠️

Running a rollback in a production environment is risky. Therefore, if your `APP_ENV` is set to `production`, the `patch:rollback` command will prompt you for confirmation before executing.

To bypass this confirmation in your deployment scripts, use the `--force` flag.

```bash
php artisan patch:rollback --step=1 --force
```

### 6\. Advanced Usage: Using the Facade

You can also trigger the patch operations directly from your application code using the `Patches` facade.

```php
use SimoneBianco\Patches\Facades\Patches;

// Apply all pending patches
$patchesRun = Patches::runPatches();

// Roll back the last batch
$rolledBackCount = Patches::rollback();

// Roll back 5 steps
$rolledBackCount = Patches::rollback(['step' => 5]);

// Create a new patch file programmatically
$filePath = Patches::createPatch('update_user_country_codes');
```

-----

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://www.google.com/search?q=CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [link sospetto rimosso] on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
