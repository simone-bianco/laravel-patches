<?php

namespace SimoneBianco\Patches;

// Import necessari dal package Spatie e i tuoi comandi
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use SimoneBianco\Patches\Console\Commands\ApplyDataPatches;
use SimoneBianco\Patches\Console\Commands\MakePatchCommand;
// Assumi che questi comandi esistano nel tuo namespace, altrimenti aggiorna il path
use App\Console\Commands\RollbackDataPatch;
use App\Console\Commands\RunSinglePatch;


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
        /*
         * Questo metodo centrale definisce tutto ciò che il tuo pacchetto
         * offre all'applicazione Laravel.
         */
        $package
            ->name('sb-patches') // Nome del pacchetto, usato per config, views, etc.
            ->hasConfigFile()    // Registra e permette la pubblicazione del file di configurazione
            ->hasMigrations(['create_patches_table', 'add_another_column_to_patches_table'])
            ->hasCommands([      // Registra i comandi Artisan
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
