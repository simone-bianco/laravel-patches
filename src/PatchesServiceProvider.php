<?php

namespace SimoneBianco\Patches;

use SimoneBianco\Patches\Console\Commands\RefreshPatches;
use SimoneBianco\Patches\Console\Commands\RollbackPatch;
use SimoneBianco\Patches\Console\Commands\RunSinglePatch;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use SimoneBianco\Patches\Console\Commands\ApplyPatches;
use SimoneBianco\Patches\Console\Commands\MakePatchCommand;


class PatchesServiceProvider extends PackageServiceProvider
{
    /**
     * @param Package $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-patches')
            ->hasConfigFile('sb-patches')
            ->hasMigrations(['0001_01_01_000100_create_data_patches_table'])
            ->hasCommands([
                MakePatchCommand::class,
                ApplyPatches::class,
                RunSinglePatch::class,
                RollbackPatch::class,
                RefreshPatches::class,
            ])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('simonebianco/laravel-patches');
            });
    }

    /**
     * @return void
     */
    public function packageRegistered(): void
    {
        $this->app->singleton('patches', function ($app) {
            return $app->make(Patches::class);
        });
    }
}
