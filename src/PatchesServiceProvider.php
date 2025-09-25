<?php

namespace SimoneBianco\Patches;

use SimoneBianco\Patches\Console\Commands\RollbackDataPatch;
use SimoneBianco\Patches\Console\Commands\RunSinglePatch;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use SimoneBianco\Patches\Console\Commands\ApplyDataPatches;
use SimoneBianco\Patches\Console\Commands\MakePatchCommand;


class PatchesServiceProvider extends PackageServiceProvider
{
    /**
     * Configura il pacchetto.
     *
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
                ApplyDataPatches::class,
                RunSinglePatch::class,
                RollbackDataPatch::class
            ])
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToStarRepoOnGitHub('simonebianco/laravel-patches');
            });
    }

    /**
     * Esegue codice dopo che il service provider è stato registrato.
     *
     * @return void
     */
    public function packageRegistered(): void
    {
        // Il binding del singleton va qui, è l'equivalente del vecchio metodo register()
        $this->app->singleton('patches', function ($app) {
            return $app->make(Patches::class);
        });
    }
}
